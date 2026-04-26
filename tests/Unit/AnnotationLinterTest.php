<?php
/**
 * Annotation Linter — ensures every ability registration carries
 * the `compiled` and `replaces` meta keys.
 *
 * These two keys feed the activity logger (kl_activity table) so the
 * knowledge layer can distinguish compiled abilities from CRUD abilities
 * and report which admin screens each ability replaces.
 *
 * Drift here is silent at runtime: a missing annotation just means the
 * relevant kl_activity columns end up empty. This linter fails the build
 * so the omission surfaces during CI instead of after launch.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class AnnotationLinterTest extends TestCase {

	/**
	 * Any ability file whose name is listed here is skipped by the linter.
	 * Add ONLY when a file genuinely contains no $reg->read/write/delete() calls.
	 */
	private const EXEMPT_FILES = array();

	public function test_every_ability_has_compiled_and_replaces_annotations() {
		$files = glob( dirname( __DIR__, 2 ) . '/includes/*-abilities.php' );
		$this->assertNotEmpty( $files, 'No ability files discovered under includes/*-abilities.php' );

		$missing = array();

		foreach ( $files as $file ) {
			$basename = basename( $file );
			if ( in_array( $basename, self::EXEMPT_FILES, true ) ) {
				continue;
			}

			$source = file_get_contents( $file );
			$calls  = $this->extract_registrar_calls( $source );

			foreach ( $calls as $call ) {
				$ability_name = $this->extract_ability_name( $call['args'] );
				$has_compiled = $this->config_has_key( $call['args'], 'compiled' );
				$has_replaces = $this->config_has_key( $call['args'], 'replaces' );

				if ( ! $has_compiled || ! $has_replaces ) {
					$missing[] = sprintf(
						'%s → %s (%s%s%s)',
						$basename,
						$ability_name ?: '[unknown]',
						$has_compiled ? '' : 'missing compiled',
						( ! $has_compiled && ! $has_replaces ) ? ', ' : '',
						$has_replaces ? '' : 'missing replaces'
					);
				}
			}
		}

		$this->assertSame(
			array(),
			$missing,
			"Abilities missing meta annotations (compiled/replaces):\n  - " . implode( "\n  - ", $missing )
		);
	}

	/**
	 * Walks the source and returns every $reg->read|write|delete( 'name', array(...) ) block,
	 * balanced-paren aware so nested arrays don't confuse the matcher.
	 *
	 * @return array<int,array{method:string,args:string}>
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

			$calls[] = array(
				'method' => $method,
				'args'   => substr( $source, $open_paren + 1, $end_paren - $open_paren - 1 ),
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

	private function extract_ability_name( string $args ): ?string {
		if ( preg_match( "/^\s*['\"]([^'\"]+)['\"]\s*,/", $args, $m ) ) {
			return $m[1];
		}
		return null;
	}

	/**
	 * Detects `'key' => ...` or `"key" => ...` inside the config array, at any depth.
	 * A simple substring-safe check: only top-level config keys use the
	 * 'compiled'/'replaces' names in this codebase, so depth-confusion is
	 * not a realistic concern.
	 */
	private function config_has_key( string $args, string $key ): bool {
		$pattern = "/['\"]" . preg_quote( $key, '/' ) . "['\"]\s*=>/";
		return (bool) preg_match( $pattern, $args );
	}
}
