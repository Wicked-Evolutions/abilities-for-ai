<?php
/**
 * Unit Tests — Permissions Patch Function
 *
 * Covers `abilities_for_ai_patch_permissions()` — the pure function that
 * applies an intentional patch to the stored permissions option in place
 * of a full Settings API replacement. See issue #153.
 *
 * Acceptance gate (Doc A Phase B.3):
 *   "Operator can save permissions on a multisite admin without OOM AND
 *    without unrelated-module permissions being disabled or wiped on save."
 *
 * Verification matrix (4 phases):
 *   1. Positive       — submitted module's permissions reflect the input.
 *   2. Negative ctrl  — unrelated modules are byte-identical post-patch.
 *   3. Idempotent     — re-applying the same input yields the same option.
 *   4. Behavior rev.  — saving module B does not wipe a prior module-A save.
 *
 * Plus the #145 regression intent (per-ability overrides survive for every
 * known module prefix) carried forward into the patch model.
 *
 * @package Abilities_For_AI\Tests\Unit
 */

use PHPUnit\Framework\TestCase;

class PermissionsPatchTest extends TestCase {

	public static function setUpBeforeClass(): void {
		// permissions.php is admin-only; bootstrap only loads helpers + schemas
		// in unit mode. Pull it in once for the test surface.
		require_once dirname( __DIR__, 2 ) . '/includes/permissions.php';
	}

	// ─── Live fixture ────────────────────────────────────────────
	// Captured 2026-05-07 from `mcp__wordpress__settings-get` against the
	// wickedevolutions multisite (post-#146 reconciliation state). Using a
	// real-shape fixture catches drift that hand-crafted inputs miss.

	private function live_fixture_wickedevolutions(): array {
		return array(
			'content'       => array( 'read' => true, 'write' => true, 'delete' => true ),
			'taxonomies'    => array( 'read' => true, 'write' => true, 'delete' => true ),
			'plugins'       => array( 'read' => true, 'write' => true, 'delete' => true ),
			'media'         => array( 'read' => true, 'write' => true, 'delete' => true ),
			'users'         => array( 'read' => true, 'write' => true, 'delete' => true ),
			'comments'      => array( 'read' => true, 'write' => true, 'delete' => true ),
			'menus'         => array( 'read' => true, 'write' => true, 'delete' => true ),
			'blocks'        => array( 'read' => true, 'write' => true, 'delete' => true ),
			'patterns'      => array( 'read' => true, 'write' => true, 'delete' => true ),
			'meta'          => array( 'read' => true, 'write' => true, 'delete' => true ),
			'settings'      => array( 'read' => true, 'write' => true, 'delete' => true ),
			'site-health'   => array( 'read' => true ),
			'cache'         => array( 'read' => true, 'write' => true, 'delete' => true ),
			'cron'          => array( 'read' => true, 'write' => true, 'delete' => true ),
			'themes'        => array( 'read' => true, 'write' => true, 'delete' => true ),
			'rest'          => array( 'read' => true ),
			'rewrite'       => array( 'read' => true, 'write' => true, 'delete' => true ),
			'filesystem'    => array( 'read' => true, 'write' => true, 'delete' => true ),
			'revisions'     => array( 'read' => true, 'write' => true, 'delete' => true ),
			'multisite'     => array( 'read' => true, 'write' => true, 'delete' => true ),
			'knowledge'     => array( 'read' => true, 'write' => true, 'delete' => true ),
			'diagnostic'    => array( 'read' => true ),
			'editorial'     => array( 'read' => true ),
			'astra'         => array( 'read' => false, 'write' => false, 'delete' => false ),
			'presto-player' => array( 'read' => true, 'write' => true, 'delete' => true ),
			'spectra'       => array( 'read' => false, 'write' => false, 'delete' => false ),
			'surecart'      => array( 'read' => false, 'write' => false, 'delete' => false ),
		);
	}

	// ─── Phase 1: Positive ──────────────────────────────────────

	public function test_phase_1_submitted_module_permissions_reflect_input(): void {
		$existing = $this->live_fixture_wickedevolutions();

		// Operator saves a filtered view containing only "cron", enabling
		// cron.delete (was already true in fixture; flip read off to make
		// the assertion meaningful).
		$input = array(
			'cron' => array( 'read' => '0', 'write' => '1', 'delete' => '1' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$this->assertSame(
			array( 'read' => false, 'write' => true, 'delete' => true ),
			$patched['cron'],
			'Submitted module ops must reflect input verbatim.'
		);
	}

	// ─── Phase 2: Negative control (the bug) ────────────────────

	public function test_phase_2_unrelated_modules_are_byte_identical_after_filtered_save(): void {
		// This is the exact scenario from #153: filtered view shows only
		// "cron", operator clicks Save Permissions — every other module
		// must remain byte-identical to its prior state.
		$existing = $this->live_fixture_wickedevolutions();

		$input = array(
			'cron' => array( 'read' => '1', 'write' => '1', 'delete' => '1' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		foreach ( $existing as $module => $ops ) {
			if ( 'cron' === $module ) {
				continue;
			}
			$this->assertSame(
				$ops,
				$patched[ $module ] ?? null,
				"Module '{$module}' was modified by a filtered save that did not include it. This is the #153 regression — unrelated modules must remain byte-identical when not submitted."
			);
		}
	}

	public function test_phase_2_specific_modules_from_issue_evidence_remain_enabled(): void {
		// #153 reported these modules went OFF after saving cron-delete.
		// Pin each one explicitly so a future regression points the reader
		// directly at the live evidence in the issue.
		$existing = $this->live_fixture_wickedevolutions();

		$input = array(
			'cron' => array( 'read' => '1', 'write' => '1', 'delete' => '1' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$victims = array( 'settings', 'diagnostic', 'filesystem', 'plugins', 'users', 'content' );
		foreach ( $victims as $module ) {
			$this->assertTrue(
				! empty( $patched[ $module ]['read'] ),
				"Module '{$module}' read permission was disabled by an unrelated cron save. Issue #153 reported this exact symptom."
			);
		}
	}

	// ─── Phase 3: Idempotency ───────────────────────────────────

	public function test_phase_3_resaving_same_input_is_byte_identical(): void {
		$existing = $this->live_fixture_wickedevolutions();

		$input = array(
			'meta' => array( 'read' => '1', 'write' => '1', 'delete' => '0' ),
		);

		$first  = abilities_for_ai_patch_permissions( $existing, $input );
		$second = abilities_for_ai_patch_permissions( $first, $input );

		$this->assertSame(
			$first,
			$second,
			'Re-applying the same patch input must produce a byte-identical option (idempotency).'
		);
	}

	public function test_phase_3_empty_input_does_not_modify_existing(): void {
		$existing = $this->live_fixture_wickedevolutions();
		$patched  = abilities_for_ai_patch_permissions( $existing, array() );

		$this->assertSame(
			$existing,
			$patched,
			'Empty input must produce a byte-identical option — no module was submitted, no module is modified.'
		);
	}

	// ─── Phase 4: Behavior reversal ─────────────────────────────

	public function test_phase_4_saving_module_b_does_not_wipe_prior_module_a_save(): void {
		// Operator first saves module "meta" with delete OFF, then later
		// saves module "cron". The meta change must persist across the
		// second save.
		$existing = $this->live_fixture_wickedevolutions();

		$step_1 = abilities_for_ai_patch_permissions(
			$existing,
			array(
				'meta' => array( 'read' => '1', 'write' => '1', 'delete' => '0' ),
			)
		);

		$this->assertSame(
			array( 'read' => true, 'write' => true, 'delete' => false ),
			$step_1['meta'],
			'Step 1 must apply the meta-delete-off change.'
		);

		$step_2 = abilities_for_ai_patch_permissions(
			$step_1,
			array(
				'cron' => array( 'read' => '1', 'write' => '0', 'delete' => '0' ),
			)
		);

		$this->assertSame(
			array( 'read' => true, 'write' => true, 'delete' => false ),
			$step_2['meta'],
			"Step 2 (saving 'cron') must not wipe the prior 'meta' change. This is the cross-save regression equivalent of the #153 bug."
		);
		$this->assertSame(
			array( 'read' => true, 'write' => false, 'delete' => false ),
			$step_2['cron'],
			'Step 2 must apply the cron change verbatim.'
		);
	}

	// ─── Per-ability overrides — #145 regression intent ─────────

	public function test_overrides_for_unsubmitted_modules_are_preserved(): void {
		// Pre-populate overrides spanning every known module. Then save
		// only one module's permissions. Overrides for every OTHER module
		// must remain in the option.
		$existing = $this->live_fixture_wickedevolutions();

		$existing['_overrides'] = array();
		foreach ( abilities_for_ai_permission_defaults() as $module => $_ops ) {
			$existing['_overrides'][ "{$module}/something" ] = false;
		}

		$input = array(
			'cron' => array( 'read' => '1', 'write' => '1', 'delete' => '1' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$this->assertArrayHasKey( '_overrides', $patched, 'Overrides bucket must survive a filtered save.' );

		foreach ( abilities_for_ai_permission_defaults() as $module => $_ops ) {
			if ( 'cron' === $module ) {
				$this->assertArrayNotHasKey(
					"{$module}/something",
					$patched['_overrides'],
					"Submitted module '{$module}' overrides should be replaced by the form's authoritative state — they were not in the form input, so they should be dropped."
				);
				continue;
			}
			$this->assertArrayHasKey(
				"{$module}/something",
				$patched['_overrides'],
				"Module '{$module}': override for an UNSUBMITTED module was silently dropped during patch. Pre-#145, this happened for every module whose prefix was missing from a hardcoded map; the patch path must not reintroduce that bug class."
			);
			$this->assertFalse( $patched['_overrides'][ "{$module}/something" ], "Module '{$module}': preserved override value must be unchanged." );
		}
	}

	public function test_new_overrides_persist_only_for_submitted_modules(): void {
		$existing = $this->live_fixture_wickedevolutions();

		$input = array(
			'content'    => array( 'read' => '1', 'write' => '1', 'delete' => '0' ),
			'_overrides' => array(
				'content/legitimate'                  => '0',
				'definitely-not-a-real-module/foo'    => '0',
				'cron/sneaking-in-from-unsubmitted'   => '0', // cron not in $input — must be rejected.
			),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$this->assertArrayHasKey( 'content/legitimate', $patched['_overrides'] ?? array() );
		$this->assertFalse( $patched['_overrides']['content/legitimate'] );

		$this->assertArrayNotHasKey(
			'definitely-not-a-real-module/foo',
			$patched['_overrides'] ?? array(),
			'Unknown prefix must be dropped — defense in depth against POST tampering.'
		);
		$this->assertArrayNotHasKey(
			'cron/sneaking-in-from-unsubmitted',
			$patched['_overrides'] ?? array(),
			'New override for a module that was NOT submitted in this save must be rejected.'
		);
	}

	public function test_override_dropped_when_module_disabled(): void {
		// If module is fully disabled, an ability-level override is dead
		// weight — store nothing.
		$existing = $this->live_fixture_wickedevolutions();
		$input    = array(
			'content'    => array( 'read' => '0', 'write' => '0', 'delete' => '0' ),
			'_overrides' => array( 'content/foo' => '0' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$this->assertArrayNotHasKey(
			'content/foo',
			$patched['_overrides'] ?? array(),
			'Override for a fully-disabled module is dead weight — should not persist.'
		);
	}

	// ─── Option shape preservation ──────────────────────────────

	public function test_option_shape_keys_are_preserved_for_untouched_modules(): void {
		// Untouched modules must keep their exact shape — including
		// modules with only a "read" key (site-health, rest, diagnostic,
		// editorial). A naive merge could expand them to read/write/delete.
		$existing = $this->live_fixture_wickedevolutions();
		$input    = array(
			'content' => array( 'read' => '1', 'write' => '1', 'delete' => '1' ),
		);

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		$this->assertSame( array( 'read' ), array_keys( $patched['site-health'] ) );
		$this->assertSame( array( 'read' ), array_keys( $patched['rest'] ) );
		$this->assertSame( array( 'read' ), array_keys( $patched['diagnostic'] ) );
		$this->assertSame( array( 'read' ), array_keys( $patched['editorial'] ) );
	}

	public function test_full_unfiltered_save_preserves_all_module_keys(): void {
		// When the operator is on the unfiltered view, the form submits
		// every known module. Acceptance: option keys after save match
		// the input's module set exactly (plus optional _overrides).
		$existing = $this->live_fixture_wickedevolutions();

		$input = array();
		foreach ( $existing as $module => $ops ) {
			$input[ $module ] = array();
			foreach ( $ops as $op => $_ ) {
				$input[ $module ][ $op ] = $ops[ $op ] ? '1' : '0';
			}
		}

		$patched = abilities_for_ai_patch_permissions( $existing, $input );

		// Every module from the fixture must be present, and the values
		// must round-trip identically (idempotent over the live state).
		foreach ( $existing as $module => $ops ) {
			$this->assertArrayHasKey( $module, $patched );
			$this->assertSame(
				$ops,
				$patched[ $module ],
				"Round-trip of full live-fixture state must be byte-identical for module '{$module}'."
			);
		}
	}
}
