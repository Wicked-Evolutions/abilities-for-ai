<?php
/**
 * Validates that every registered ability's `input_schema` is acceptable to
 * the Anthropic API's tool-registration endpoint.
 *
 * The Anthropic-strict profile
 * ----------------------------
 * The Anthropic API enforces JSON Schema draft 2020-12 PLUS a stricter
 * profile we call "Anthropic-strict". Two rules in that profile go beyond
 * raw draft 2020-12 — both are accepted by the meta-schema but rejected
 * by Anthropic's tool-catalog endpoint with a 400 invalid_request_error,
 * which drops the entire catalog and breaks every WordPress ability for
 * any client backed by the Anthropic API:
 *
 *   1. No array-form `type` — e.g. `type: ["string", "null"]`. Raw
 *      2020-12 permits this; Anthropic rejects. Use `oneOf` for unions.
 *      (See #134.)
 *   2. No empty `properties: {}` on `type: object`. Raw 2020-12 permits
 *      this; Anthropic rejects. For no-arg abilities, omit the
 *      `properties` key entirely (per CLAUDE.md PHP standards).
 *      (See #135.)
 *
 * What this test combines
 * -----------------------
 *   - opis/json-schema parse against the draft 2020-12 meta-schema —
 *     catches structural malformations the spec itself disallows.
 *   - Explicit Anthropic-strict lint, layered on top of the opis parse —
 *     catches the two rules above that the meta-schema alone would miss.
 *
 * Discovery
 * ---------
 * Ability registrations are discovered via PHP source-text extraction,
 * not WP runtime. We pull the `'input_schema' => array(...)` literal
 * out of each `$reg->read|write|delete()` call and evaluate it in a
 * controlled sandbox. This lets the gate run in the existing Unit suite
 * without WP_TESTS_DIR.
 *
 * XFAIL list
 * ----------
 * Some schemas may be known-malformed and tracked by their own issues.
 * The `XFAIL` constant below carries those exclusions; each entry must
 * point to a tracking issue and is removed as that issue lands. The
 * constant exists even when empty so future regressions can be parked
 * against an issue without re-adding scaffolding.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use PHPUnit\Framework\TestCase;

class InputSchemaDraft202012Test extends TestCase {

	/**
	 * Ability names that are expected to fail validation. Each entry must point
	 * to the GitHub issue tracking the fix. Entries are removed once their
	 * tracking issue lands.
	 *
	 * Currently empty — kept as scaffolding so future regressions can be
	 * parked against a tracking issue without re-introducing the constant.
	 *
	 * @var array<string,string>  ability_name => "see issue #X"
	 */
	private const XFAIL = array();

	/**
	 * Locate every `*-abilities.php` file shipped in the plugin.
	 *
	 * @return string[]
	 */
	private function discover_ability_files(): array {
		$root  = dirname( __DIR__, 2 ) . '/includes';
		$paths = array_merge(
			glob( $root . '/*-abilities.php' ) ?: array(),
			glob( $root . '/suites/*/*-abilities.php' ) ?: array()
		);
		sort( $paths );
		return $paths;
	}

	/**
	 * Walks the source and returns every `$reg->read|write|delete( 'name', array(...) )`
	 * block, balanced-paren aware so nested arrays don't confuse the matcher.
	 * Mirrors the helper in AnnotationLinterTest — keep them aligned.
	 *
	 * @return array<int,array{method:string,name:string,args:string}>
	 */
	private function extract_registrar_calls( string $source ): array {
		$calls   = array();
		$length  = strlen( $source );
		$offset  = 0;
		$pattern = '/\$reg->(read|write|delete)\s*\(/';

		while ( preg_match( $pattern, $source, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
			$method     = $m[1][0];
			$open_paren = $m[0][1] + strlen( $m[0][0] ) - 1;
			$end_paren  = $this->find_matching_paren( $source, $open_paren );

			if ( $end_paren === -1 ) {
				break;
			}

			$args = substr( $source, $open_paren + 1, $end_paren - $open_paren - 1 );
			$name = '';
			if ( preg_match( "/^\s*['\"]([^'\"]+)['\"]\s*,/", $args, $nm ) ) {
				$name = $nm[1];
			}

			$calls[] = array(
				'method' => $method,
				'name'   => $name,
				'args'   => $args,
			);

			$offset = $end_paren + 1;
			if ( $offset >= $length ) {
				break;
			}
		}

		return $calls;
	}

	private function find_matching_paren( string $source, int $open_pos ): int {
		$depth       = 0;
		$length      = strlen( $source );
		$in_string   = false;
		$string_char = '';

		for ( $i = $open_pos; $i < $length; $i++ ) {
			$char = $source[ $i ];
			$prev = $i > 0 ? $source[ $i - 1 ] : '';

			if ( $in_string ) {
				if ( $char === $string_char && $prev !== '\\' ) {
					$in_string = false;
				}
				continue;
			}

			if ( $char === "'" || $char === '"' ) {
				$in_string   = true;
				$string_char = $char;
				continue;
			}

			if ( $char === '(' ) {
				$depth++;
			} elseif ( $char === ')' ) {
				$depth--;
				if ( $depth === 0 ) {
					return $i;
				}
			}
		}

		return -1;
	}

	/**
	 * Pull the `'input_schema' => array(...)` literal out of a registrar args
	 * block. Returns null if the call has no input_schema at all (legitimate —
	 * many no-arg abilities omit the key).
	 */
	private function extract_input_schema_literal( string $args ): ?string {
		if ( ! preg_match( "/['\"]input_schema['\"]\s*=>\s*array\s*\(/", $args, $m, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$arrow_pos = $m[0][1] + strlen( $m[0][0] ) - 1;
		$end       = $this->find_matching_paren( $args, $arrow_pos );
		if ( $end === -1 ) {
			return null;
		}
		return 'array' . substr( $args, $arrow_pos, $end - $arrow_pos + 1 );
	}

	/**
	 * Evaluate a literal `array(...)` expression in a controlled sandbox,
	 * with `$file_scope` providing per-file local variables ($pagination_props,
	 * $date_range_props, etc.) that the input_schema literal may reference.
	 *
	 * Helper functions used inside input_schemas are pre-loaded by the
	 * unit-mode bootstrap (schemas.php). Suite-specific helpers — e.g.
	 * abilities_for_ai_surecart_pagination_schema — are stub-defined here
	 * to delegate to the canonical pagination helper.
	 *
	 * @param array<string,mixed> $file_scope Local-variable scope to expose
	 *                                        to the eval'd expression.
	 */
	private function evaluate_literal( string $literal, array $file_scope = array() ): array {
		if ( ! function_exists( 'abilities_for_ai_surecart_pagination_schema' ) ) {
			eval( 'function abilities_for_ai_surecart_pagination_schema() { return abilities_for_ai_schema_pagination(); }' ); // phpcs:ignore Squiz.PHP.Eval
		}

		// extract() injects $file_scope keys as local variables for the eval.
		extract( $file_scope, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		$result = eval( 'return ' . $literal . ';' ); // phpcs:ignore Squiz.PHP.Eval
		if ( ! is_array( $result ) ) {
			throw new \RuntimeException( 'input_schema literal did not evaluate to an array' );
		}
		return $result;
	}

	/**
	 * Pre-extract the local-variable scope of a file so input_schema literals
	 * can reference vars like $pagination_props or $date_range_props that are
	 * defined at file top-level. We scan for `$name = array(...);` patterns
	 * and evaluate each one in isolation, building up a scope dictionary.
	 *
	 * Stub classes are pre-defined for known references that appear in
	 * descriptions (Document::TYPES, etc.). When a definition still fails to
	 * eval, it is silently skipped — the input_schema using it will then fail
	 * eval too, and the test will surface that as a violation (or skip via XFAIL).
	 *
	 * @return array<string,mixed>
	 */
	private function extract_file_scope( string $source ): array {
		$scope = array();

		// Pre-stub any classes referenced via `Class::CONST` that are not yet
		// defined. We capture every `(?:\\)?ClassName::CONST_NAME` token and
		// define a stub class with each referenced constant as an empty array.
		// This satisfies expressions like `Document::TYPES` inside string
		// concatenation in property descriptions.
		if ( preg_match_all( '/(?:\\\\)?\b([A-Z][A-Za-z0-9_]*)::([A-Z][A-Z0-9_]*)\b/', $source, $tm, PREG_SET_ORDER ) ) {
			$class_consts = array();
			foreach ( $tm as $hit ) {
				$class_consts[ $hit[1] ][ $hit[2] ] = true;
			}
			foreach ( $class_consts as $cls => $consts ) {
				if ( class_exists( $cls ) ) {
					continue;
				}
				$const_decls = array();
				foreach ( array_keys( $consts ) as $cname ) {
					$const_decls[] = "const {$cname} = array();";
				}
				eval( "class {$cls} { " . implode( ' ', $const_decls ) . ' }' ); // phpcs:ignore Squiz.PHP.Eval
			}
		}

		// Match top-level `$name = array(...);` declarations (not inside a function/closure).
		// We use a coarse pass that looks for assignments at indentation level 0 only.
		$lines = explode( "\n", $source );
		$buffer = '';
		$inside_brace_depth = 0;
		foreach ( $lines as $line ) {
			// Track brace depth to skip declarations inside functions/closures.
			$open_braces  = substr_count( $line, '{' );
			$close_braces = substr_count( $line, '}' );

			if ( $inside_brace_depth === 0
				&& preg_match( '/^\s*\$([A-Za-z_][A-Za-z0-9_]*)\s*=\s*array\s*\(/', $line, $m )
			) {
				$var_name = $m[1];
				// Capture the multi-line array literal until balanced parens.
				$start_pos_in_source = strpos( $source, $line );
				if ( $start_pos_in_source === false ) {
					$inside_brace_depth += $open_braces - $close_braces;
					continue;
				}
				$arr_start = strpos( $source, 'array', $start_pos_in_source );
				$open_paren = strpos( $source, '(', $arr_start );
				$end_paren  = $this->find_matching_paren( $source, $open_paren );
				if ( $end_paren === -1 ) {
					$inside_brace_depth += $open_braces - $close_braces;
					continue;
				}
				$lit = 'array' . substr( $source, $open_paren, $end_paren - $open_paren + 1 );
				try {
					$scope[ $var_name ] = $this->evaluate_literal( $lit, $scope );
				} catch ( \Throwable $e ) {
					// Silent skip — failing scope vars become absent in the eval.
				}
			}

			$inside_brace_depth += $open_braces - $close_braces;
			if ( $inside_brace_depth < 0 ) {
				$inside_brace_depth = 0;
			}
		}

		return $scope;
	}

	/**
	 * Recursively check a schema array for Anthropic-incompatible patterns.
	 *
	 * Returns an array of issue strings; empty array means OK.
	 *
	 * @param mixed $node Current node.
	 * @param string $path JSON-pointer-ish path for error messages.
	 * @return string[]
	 */
	private function lint_anthropic_profile( $node, string $path = '$' ): array {
		$issues = array();

		if ( ! is_array( $node ) ) {
			return $issues;
		}

		// Rule 1 (#134): `type` must be a string scalar, never an array. We only
		// flag the array-form-type pattern (numeric-keyed list of strings); a
		// nested associative array under a key literally named "type" is just
		// a property called "type" — not a JSON Schema `type` keyword.
		if ( array_key_exists( 'type', $node )
			&& is_array( $node['type'] )
			&& ! $this->is_assoc_or_empty( $node['type'] )
			&& $this->all_strings( $node['type'] )
		) {
			$issues[] = sprintf(
				'%s.type is an array (%s) — Anthropic API rejects array-form `type`. Use `oneOf` instead. (See #134.)',
				$path,
				implode( ', ', array_map( 'strval', $node['type'] ) )
			);
		}

		// Rule 2 (#135): when `type` is "object" and `properties` is present, it
		// must not be empty. For no-arg shapes, omit `properties` entirely.
		if ( ( $node['type'] ?? null ) === 'object'
			&& array_key_exists( 'properties', $node )
			&& is_array( $node['properties'] )
			&& empty( $node['properties'] )
		) {
			$issues[] = sprintf(
				'%s declares `type: "object"` with empty `properties: {}` — Anthropic API rejects this shape. Omit `properties` entirely for no-arg tools. (See #135.)',
				$path
			);
		}

		// Recurse.
		foreach ( $node as $key => $child ) {
			if ( is_array( $child ) ) {
				$issues = array_merge( $issues, $this->lint_anthropic_profile( $child, $path . '.' . $key ) );
			}
		}

		return $issues;
	}

	/**
	 * Convert a PHP-array schema to a JSON-decoded structure (using stdClass
	 * for associative shapes) so opis/json-schema can parse it.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function to_json_value( $value ) {
		if ( is_array( $value ) ) {
			if ( $this->is_assoc_or_empty( $value ) ) {
				$obj = new \stdClass();
				foreach ( $value as $k => $v ) {
					$obj->{$k} = $this->to_json_value( $v );
				}
				return $obj;
			}
			return array_map( array( $this, 'to_json_value' ), $value );
		}
		return $value;
	}

	private function is_assoc_or_empty( array $arr ): bool {
		if ( empty( $arr ) ) {
			return true;
		}
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}

	private function all_strings( array $arr ): bool {
		foreach ( $arr as $v ) {
			if ( ! is_string( $v ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Try to parse a schema-as-stdClass under draft 2020-12 via opis. Returns
	 * an error string if parsing fails, null on success.
	 */
	private function parse_under_2020_12( $schema_object ): ?string {
		try {
			$loader = new SchemaLoader( new SchemaParser(), new SchemaResolver(), true );
			// loadObjectSchema parses under default draft (set to 2020-12).
			$loader->loadObjectSchema( $schema_object );
			return null;
		} catch ( \Throwable $e ) {
			return $e->getMessage();
		}
	}

	// ── The actual test ───────────────────────────────────────────────────

	public function test_every_ability_input_schema_is_valid_draft_2020_12() {
		$files = $this->discover_ability_files();
		$this->assertNotEmpty( $files, 'No ability files discovered' );

		$violations = array();
		$xfail_hits = array();
		$checked    = 0;

		foreach ( $files as $file ) {
			$source     = file_get_contents( $file );
			$file_scope = $this->extract_file_scope( $source );
			$calls      = $this->extract_registrar_calls( $source );

			foreach ( $calls as $call ) {
				$literal = $this->extract_input_schema_literal( $call['args'] );
				if ( $literal === null ) {
					continue;
				}

				$ability_name = $call['name'] ?: '[unknown]';
				$is_xfail     = array_key_exists( $ability_name, self::XFAIL );

				try {
					$schema_array = $this->evaluate_literal( $literal, $file_scope );
				} catch ( \Throwable $e ) {
					$msg = sprintf(
						'%s (%s): could not evaluate input_schema literal: %s',
						$ability_name,
						basename( $file ),
						$e->getMessage()
					);
					if ( $is_xfail ) {
						$xfail_hits[] = $msg . ' [XFAIL: ' . self::XFAIL[ $ability_name ] . ']';
					} else {
						$violations[] = $msg;
					}
					continue;
				}

				$json_value = $this->to_json_value( $schema_array );

				$file_issues = array();

				// 1. opis parse check (catches any draft 2020-12 parse failures).
				$parse_error = $this->parse_under_2020_12( $json_value );
				if ( $parse_error !== null ) {
					$file_issues[] = sprintf( 'opis parse failed: %s', $parse_error );
				}

				// 2. Anthropic-profile lint (stricter than the spec).
				$lint_issues = $this->lint_anthropic_profile( $schema_array );
				$file_issues = array_merge( $file_issues, $lint_issues );

				$checked++;

				if ( empty( $file_issues ) ) {
					if ( $is_xfail ) {
						$violations[] = sprintf(
							'%s (%s): listed in XFAIL but PASSED validation. Remove the XFAIL entry.',
							$ability_name,
							basename( $file )
						);
					}
					continue;
				}

				$line = sprintf( '%s (%s): %s', $ability_name, basename( $file ), implode( '; ', $file_issues ) );
				if ( $is_xfail ) {
					$xfail_hits[] = $line . ' [XFAIL: ' . self::XFAIL[ $ability_name ] . ']';
				} else {
					$violations[] = $line;
				}
			}
		}

		$this->assertGreaterThan( 0, $checked, 'Expected to validate at least one input_schema' );

		$this->assertSame(
			array(),
			$violations,
			"Abilities with input_schema invalid under JSON Schema draft 2020-12 (Anthropic profile):\n  - " .
			implode( "\n  - ", $violations ) .
			( $xfail_hits ? "\n\nXFAIL hits (skipped, tracked by issue):\n  - " . implode( "\n  - ", $xfail_hits ) : '' )
		);
	}

	// ── Self-checks on the lint mechanism ─────────────────────────────────

	/**
	 * The lint must reject schemas whose `type` is an array (the #134 bug class).
	 */
	public function test_lint_rejects_array_form_type() {
		$bad = array(
			'type'       => 'object',
			'properties' => array(
				'value' => array(
					'type' => array( 'string', 'null' ),
				),
			),
		);
		$issues = $this->lint_anthropic_profile( $bad );
		$this->assertNotEmpty( $issues, 'Lint must flag array-form `type` (issue #134).' );
	}

	/**
	 * The lint must reject schemas with empty `properties: {}` (the #135 bug class).
	 */
	public function test_lint_rejects_empty_properties() {
		$bad = array(
			'type'       => 'object',
			'properties' => array(),
		);
		$issues = $this->lint_anthropic_profile( $bad );
		$this->assertNotEmpty( $issues, 'Lint must flag empty `properties: {}` (issue #135).' );
	}

	/**
	 * The lint must accept the fix shape — oneOf with string types.
	 */
	public function test_lint_accepts_one_of_with_string_types() {
		$good = array(
			'type'       => 'object',
			'properties' => array(
				'value' => array(
					'oneOf' => array(
						array( 'type' => 'string' ),
						array( 'type' => 'integer' ),
						array( 'type' => 'boolean' ),
					),
				),
			),
		);
		$issues = $this->lint_anthropic_profile( $good );
		$this->assertSame( array(), $issues, 'Lint must accept oneOf-with-string-types as valid.' );
	}

	/**
	 * The lint must accept a no-arg ability that omits `properties`.
	 */
	public function test_lint_accepts_object_without_properties_key() {
		$good   = array( 'type' => 'object' );
		$issues = $this->lint_anthropic_profile( $good );
		$this->assertSame( array(), $issues, 'Lint must accept type: "object" without properties (no-arg shape).' );
	}
}
