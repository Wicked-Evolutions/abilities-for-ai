<?php
/**
 * License Manager — FluentCart API Integration
 *
 * Validates Abilities for WordPress Pro licenses via the FluentCart license API
 * on wickedevolutions.com. Uses a 24-hour transient cache for the validation result
 * and a 7-day grace period for API unreachability.
 *
 * Flow:
 *   1. activate()  — Called when admin saves the license key. POSTs to the
 *                    FluentCart activate_license endpoint. Stores the
 *                    activation_hash returned for future check_license calls.
 *   2. is_pro_active() — Returns cached result when fresh. Otherwise POSTs
 *                    to check_license. Falls back to grace period on failure.
 *   3. deactivate() — POSTs deactivate, clears local state.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_License_Manager {

	/**
	 * FluentCart store URL where the license API lives.
	 * The licensing module is enabled on the community subsite.
	 *
	 * @var string
	 */
	const STORE_URL = 'https://community.wickedevolutions.com';

	/**
	 * FluentCart product ID for "Abilities for WordPress".
	 * Used as the item_id parameter in all API calls.
	 *
	 * @var int
	 */
	const PRODUCT_ID = 66;

	/**
	 * FluentCart product ID for "Abilities for WordPress — Multisite".
	 *
	 * @var int
	 */
	const PRODUCT_ID_MULTISITE = 78;

	/**
	 * Cache lifetime for a successful validation result (24 hours).
	 *
	 * @var int
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Grace period when the license API is unreachable (7 days).
	 * Within this window, a previously-valid license continues to grant Pro access.
	 *
	 * @var int
	 */
	const GRACE_PERIOD = 7 * DAY_IN_SECONDS;

	// WordPress option / transient keys.
	const OPT_LICENSE_KEY  = 'wp_abilities_suite_license_key';
	const OPT_ACTIV_HASH   = 'wp_abilities_suite_activation_hash';
	const OPT_LAST_VALID   = 'wp_abilities_suite_last_valid_ts';
	const TRANSIENT_STATUS = 'wp_abilities_suite_pro_status';

	// ----------------------------------------------------------------------------
	// Public API
	// ----------------------------------------------------------------------------

	/**
	 * Check if a Pro license is currently active.
	 *
	 * Returns cached result when available. Re-validates via API when the cache
	 * expires. Grants access within the grace period if the API is unreachable.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		$cached = get_transient( self::TRANSIENT_STATUS );
		if ( false !== $cached ) {
			return 'active' === $cached;
		}

		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		if ( empty( $license_key ) ) {
			return false;
		}

		$result = self::remote_check( $license_key );

		if ( is_wp_error( $result ) ) {
			// API unreachable — apply grace period.
			return self::is_within_grace_period();
		}

		$is_active = isset( $result['status'] ) && 'valid' === $result['status'];

		if ( $is_active ) {
			self::update_opt( self::OPT_LAST_VALID, time() );
			set_transient( self::TRANSIENT_STATUS, 'active', self::CACHE_TTL );
		} else {
			set_transient( self::TRANSIENT_STATUS, 'inactive', self::CACHE_TTL );
		}

		return $is_active;
	}

	/**
	 * Activate a license key.
	 *
	 * Calls the FluentCart activate_license endpoint, which registers this site
	 * against the key and returns an activation_hash for future checks.
	 *
	 * @param string $license_key The license key to activate.
	 * @return true|WP_Error
	 */
	public static function activate( $license_key ) {
		$license_key = sanitize_text_field( $license_key );
		if ( empty( $license_key ) ) {
			return new WP_Error( 'invalid_key', __( 'License key cannot be empty.', 'wp-abilities-suite' ) );
		}

		$response = self::remote_request( 'activate_license', array(
			'license_key' => $license_key,
			'item_id'     => self::PRODUCT_ID,
			'site_url'    => home_url(),
		) );

		// If key_mismatch on single-site product, try the multisite product ID.
		if (
			! is_wp_error( $response )
			&& isset( $response['error_type'] )
			&& 'key_mismatch' === $response['error_type']
			&& is_multisite()
		) {
			$response = self::remote_request( 'activate_license', array(
				'license_key' => $license_key,
				'item_id'     => self::PRODUCT_ID_MULTISITE,
				'site_url'    => home_url(),
			) );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['status'] ) || 'valid' !== $response['status'] ) {
			$message = $response['message'] ?? __( 'License activation failed.', 'wp-abilities-suite' );
			return new WP_Error( $response['error_type'] ?? 'activation_failed', $message );
		}

		// Detect license scope from the product ID in the API response.
		$response_product = (int) ( $response['product_id'] ?? $response['item_id'] ?? self::PRODUCT_ID );
		$is_network       = is_multisite() && self::PRODUCT_ID_MULTISITE === $response_product;

		// Set scope before storing options so get_opt/update_opt route correctly.
		if ( $is_network ) {
			update_site_option( 'wp_abilities_suite_license_scope', 'network' );
		} else {
			delete_site_option( 'wp_abilities_suite_license_scope' );
		}

		// Store the key and activation hash for future check_license calls.
		self::update_opt( self::OPT_LICENSE_KEY, $license_key );
		self::update_opt( self::OPT_ACTIV_HASH, $response['activation_hash'] ?? '' );
		self::update_opt( self::OPT_LAST_VALID, time() );

		delete_transient( self::TRANSIENT_STATUS );

		return true;
	}

	/**
	 * Deactivate the current license.
	 *
	 * Calls the FluentCart deactivate_license endpoint and clears local state.
	 * Always clears local state even if the remote call fails.
	 *
	 * @return bool
	 */
	public static function deactivate() {
		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		$activ_hash  = self::get_opt( self::OPT_ACTIV_HASH, '' );

		if ( ! empty( $license_key ) ) {
			self::remote_request( 'deactivate_license', array(
				'license_key'     => $license_key,
				'activation_hash' => $activ_hash,
				'item_id'         => self::current_product_id(),
				'site_url'        => home_url(),
			) );
		}

		self::delete_opt( self::OPT_LICENSE_KEY );
		self::delete_opt( self::OPT_ACTIV_HASH );
		self::delete_opt( self::OPT_LAST_VALID );
		delete_transient( self::TRANSIENT_STATUS );
		delete_site_option( 'wp_abilities_suite_license_scope' );

		return true;
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
				/* translators: %s: Ability name */
				__( 'The "%s" ability requires an active Pro license. Visit https://wickedevolutions.com/pro to upgrade.', 'wp-abilities-suite' ),
				$ability_name
			),
			array( 'status' => 403 )
		);
	}

	/**
	 * Get the current license status details for display in admin UI.
	 *
	 * @return array Keys: key (masked), status, expiration, product, activated.
	 */
	public static function get_status() {
		$license_key = self::get_opt( self::OPT_LICENSE_KEY, '' );
		$last_valid  = self::get_opt( self::OPT_LAST_VALID, 0 );

		if ( empty( $license_key ) ) {
			return array(
				'key'        => '',
				'status'     => 'unlicensed',
				'expiration' => '',
				'activated'  => false,
			);
		}

		// Mask the key for display: WKDEVO****abc.
		$masked_key = substr( $license_key, 0, 6 ) . str_repeat( '*', max( 0, strlen( $license_key ) - 9 ) ) . substr( $license_key, -3 );

		$is_active = self::is_pro_active();

		return array(
			'key'        => $masked_key,
			'status'     => $is_active ? 'active' : 'inactive',
			'expiration' => '',  // Populated if needed from API response.
			'activated'  => $is_active,
			'last_valid' => $last_valid ? gmdate( 'Y-m-d H:i:s', $last_valid ) : '',
		);
	}

	// ----------------------------------------------------------------------------
	// Internal Helpers
	// ----------------------------------------------------------------------------

	/**
	 * Whether the current license has network (multisite) scope.
	 *
	 * @return bool
	 */
	private static function is_network_license() {
		if ( ! is_multisite() ) {
			return false;
		}
		return 'network' === get_site_option( 'wp_abilities_suite_license_scope', '' );
	}

	/**
	 * Return the correct FluentCart product ID for API calls.
	 *
	 * @return int
	 */
	private static function current_product_id() {
		return self::is_network_license() ? self::PRODUCT_ID_MULTISITE : self::PRODUCT_ID;
	}

	/**
	 * Read a license option, respecting network scope.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private static function get_opt( $key, $default = '' ) {
		return self::is_network_license() ? get_site_option( $key, $default ) : get_option( $key, $default );
	}

	/**
	 * Write a license option, respecting network scope.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Value.
	 */
	private static function update_opt( $key, $value ) {
		if ( self::is_network_license() ) {
			update_site_option( $key, $value );
		} else {
			update_option( $key, $value );
		}
	}

	/**
	 * Delete a license option, respecting network scope.
	 *
	 * @param string $key Option key.
	 */
	private static function delete_opt( $key ) {
		if ( self::is_network_license() ) {
			delete_site_option( $key );
		} else {
			delete_option( $key );
		}
	}

	/**
	 * POST to the FluentCart check_license endpoint using the activation hash.
	 *
	 * Uses activation_hash (not the raw key) for periodic checks — avoids
	 * re-consuming an activation slot.
	 *
	 * @param string $license_key License key.
	 * @return array|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	private static function remote_check( $license_key ) {
		$activ_hash = self::get_opt( self::OPT_ACTIV_HASH, '' );

		$payload = array(
			'item_id'  => self::current_product_id(),
			'site_url' => home_url(),
		);

		// Use activation_hash if available; fall back to license_key.
		if ( ! empty( $activ_hash ) ) {
			$payload['activation_hash'] = $activ_hash;
		} else {
			$payload['license_key'] = $license_key;
		}

		return self::remote_request( 'check_license', $payload );
	}

	/**
	 * POST to the FluentCart license API.
	 *
	 * The API is a standard WordPress action endpoint:
	 * POST https://wickedevolutions.com/?fluent-cart={action}
	 *
	 * @param string $action  One of: activate_license, check_license, deactivate_license.
	 * @param array  $payload POST body fields.
	 * @return array|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	private static function remote_request( $action, array $payload ) {
		$url = add_query_arg( 'fluent-cart', $action, self::STORE_URL . '/' );

		$response = wp_remote_post( $url, array(
			'timeout'   => 15,
			'sslverify' => true,
			'body'      => $payload,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'invalid_response',
				sprintf( 'License API returned unexpected response (HTTP %d).', $code )
			);
		}

		return $decoded;
	}

	/**
	 * Check whether the last known-valid timestamp is within the grace period.
	 *
	 * @return bool
	 */
	private static function is_within_grace_period() {
		$last_valid = (int) self::get_opt( self::OPT_LAST_VALID, 0 );
		if ( $last_valid <= 0 ) {
			return false;
		}
		return ( time() - $last_valid ) < self::GRACE_PERIOD;
	}
}
