<?php
/**
 * Unit Tests — Permissions Sanitizer
 *
 * Covers abilities_for_ai_sanitize_permissions() — the Settings API callback
 * that filters POST input before write. Specifically locks down the v1.9.2
 * fix: per-ability overrides for ALL modules in permission_defaults() must
 * survive sanitize, not just the older hardcoded list (#145).
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class PermissionsSanitizerTest extends TestCase {

	public static function setUpBeforeClass(): void {
		// permissions.php is admin-only; bootstrap only loads helpers + schemas
		// in unit mode. Pull it in once for the test surface.
		require_once dirname( __DIR__, 2 ) . '/includes/permissions.php';
	}

	public function test_sanitize_persists_override_for_each_module() {
		// Build an input that disables one synthetic ability per module, with
		// the module-level read+write turned ON so the override is "active".
		$input = array();
		foreach ( abilities_for_ai_permission_defaults() as $module => $ops ) {
			$input[ $module ] = array();
			foreach ( $ops as $op => $_ ) {
				$input[ $module ][ $op ] = true;
			}
			$input['_overrides'][ "{$module}/test-ability" ] = false;
		}

		$result = abilities_for_ai_sanitize_permissions( $input );

		$this->assertArrayHasKey( '_overrides', $result, 'Sanitized output must carry the _overrides key when overrides exist.' );

		foreach ( array_keys( abilities_for_ai_permission_defaults() ) as $module ) {
			$key = "{$module}/test-ability";
			$this->assertArrayHasKey(
				$key,
				$result['_overrides'],
				"Module '{$module}': per-ability override was silently dropped during sanitize. Pre-#145, this happened for knowledge/diagnostic/editorial/astra/presto-player/spectra/surecart because their prefixes weren't in the hardcoded category_to_module map."
			);
			$this->assertFalse(
				$result['_overrides'][ $key ],
				"Module '{$module}': stored override value should be false (the user's chosen disable)."
			);
		}
	}

	public function test_sanitize_drops_override_for_unknown_prefix() {
		// Defense-in-depth: prefixes not in defaults() must still be silently
		// rejected, even after introspection. Garbage input cannot create a
		// new module out of thin air.
		$input = array(
			'content' => array( 'read' => true, 'write' => true, 'delete' => false ),
			'_overrides' => array(
				'definitely-not-a-real-module/foo' => false,
				'content/legitimate'               => false,
			),
		);

		$result = abilities_for_ai_sanitize_permissions( $input );

		$this->assertArrayNotHasKey(
			'definitely-not-a-real-module/foo',
			$result['_overrides'] ?? array(),
			'Unknown prefix must be dropped from overrides — defense in depth against POST tampering.'
		);
		$this->assertArrayHasKey(
			'content/legitimate',
			$result['_overrides'] ?? array(),
			'Known prefix in same input should still persist — drop is selective, not blanket.'
		);
	}
}
