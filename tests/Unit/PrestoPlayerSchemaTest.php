<?php
/**
 * Unit Tests — Presto Player Settings Schema (issue #134)
 *
 * Asserts that the registered `presto-player/update-setting` input_schema's
 * `value` property does NOT use the array-form `type` shorthand. JSON Schema
 * draft 2020-12 (enforced by the Anthropic API) does not accept a list as
 * the value of `type`; multi-shape values must use `oneOf`.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class PrestoPlayerSchemaTest extends TestCase {

	/**
	 * Read the registered input_schema for presto-player/update-setting straight
	 * out of source — we can't load the suite file in unit mode (requires the
	 * PrestoPlayer\Models\Setting class), so we extract the literal.
	 *
	 * @return array
	 */
	private function load_update_setting_schema(): array {
		$file = dirname( __DIR__, 2 ) . '/includes/suites/presto-player/settings-abilities.php';
		$this->assertFileExists( $file );

		$source = file_get_contents( $file );

		// Locate the update-setting registrar call.
		$pos = strpos( $source, "'presto-player/update-setting'" );
		$this->assertNotFalse( $pos, 'presto-player/update-setting registrar call not found' );

		// From there, find the input_schema => array( ... ) block.
		$schema_pos = strpos( $source, "'input_schema'", $pos );
		$this->assertNotFalse( $schema_pos );

		$open_paren = strpos( $source, '(', $schema_pos );
		$end_paren  = $this->find_matching_paren( $source, $open_paren );

		$literal = substr( $source, $open_paren, $end_paren - $open_paren + 1 );
		// Convert PHP `array(...)` syntax into an evaluable expression.
		$expression = 'return array' . $literal . ';';

		// Sandbox: no helper calls used inside this specific schema, so eval is safe.
		$schema = eval( $expression ); // phpcs:ignore Squiz.PHP.Eval

		$this->assertIsArray( $schema );
		return $schema;
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

	// ── #134: value.type must not be an array ──────────────────────────────

	public function test_update_setting_value_property_exists() {
		$schema = $this->load_update_setting_schema();
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'value', $schema['properties'] );
	}

	public function test_update_setting_value_type_is_not_array_form() {
		$schema = $this->load_update_setting_schema();
		$value  = $schema['properties']['value'];

		if ( array_key_exists( 'type', $value ) ) {
			// JSON Schema 2020-12 requires `type` to be a single string scalar.
			$this->assertIsString(
				$value['type'],
				'value.type must be a string scalar (e.g. "string"), not an array. ' .
				'Use oneOf for multi-shape values. See issue #134.'
			);
		} else {
			// `type` absent → must use oneOf (or another keyword that expresses the union).
			$this->assertArrayHasKey(
				'oneOf',
				$value,
				'value must declare either a string `type` or a `oneOf` union. See issue #134.'
			);
			$this->assertIsArray( $value['oneOf'] );
			$this->assertNotEmpty( $value['oneOf'] );
			foreach ( $value['oneOf'] as $branch ) {
				$this->assertIsArray( $branch );
				$this->assertArrayHasKey( 'type', $branch );
				$this->assertIsString(
					$branch['type'],
					'Each oneOf branch must use a string `type` (no nested array-form types).'
				);
			}
		}
	}
}
