<?php
/**
 * License Manager — Pro Tier Gate
 *
 * Phase 1: Stub implementation. License validation will be implemented
 * via FluentCart in a future release.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_License_Manager {

	/**
	 * Check if Pro license is active.
	 *
	 * Phase 1: checks a simple option. Future: FluentCart API validation.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		// Check cached status first.
		$status = get_transient( 'wp_abilities_suite_pro_status' );
		if ( false !== $status ) {
			return 'active' === $status;
		}

		// Phase 1: simple option check.
		$license_key = get_option( 'wp_abilities_suite_license_key', '' );
		if ( empty( $license_key ) ) {
			return false;
		}

		// TODO: Remote validation via FluentCart API.
		// For now, any non-empty key = active (dev/testing mode).
		$is_active = ! empty( $license_key );
		set_transient( 'wp_abilities_suite_pro_status', $is_active ? 'active' : 'inactive', 12 * HOUR_IN_SECONDS );

		return $is_active;
	}

	/**
	 * Get the Pro-required error response.
	 *
	 * @param string $ability_name The ability slug.
	 * @return WP_Error
	 */
	public static function pro_required_error( $ability_name ) {
		return new WP_Error(
			'pro_required',
			sprintf(
				'The "%s" ability requires an active Pro license. Visit https://wickedevolutions.com/pro to upgrade.',
				$ability_name
			),
			array( 'status' => 403 )
		);
	}

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key The license key to activate.
	 * @return bool
	 */
	public static function activate( $license_key ) {
		update_option( 'wp_abilities_suite_license_key', sanitize_text_field( $license_key ) );
		delete_transient( 'wp_abilities_suite_pro_status' );
		return true;
	}

	/**
	 * Deactivate the current license.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		delete_option( 'wp_abilities_suite_license_key' );
		delete_transient( 'wp_abilities_suite_pro_status' );
		return true;
	}
}
