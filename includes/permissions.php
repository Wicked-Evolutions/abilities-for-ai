<?php
/**
 * Permission Toggles — Admin-Post Patch Handler
 *
 * The permissions UI no longer round-trips through the Settings API. The
 * legacy `options.php` flow rebuilt the entire permissions option from form
 * input, which silently disabled any module that wasn't visible in the
 * current filter (and bombed memory rendering per-ability schemas as part of
 * the save surface). See issue #153.
 *
 * The new flow:
 *   - Form posts to admin-post.php with a plugin-owned action.
 *   - Handler patches the existing option intentionally — only modules
 *     submitted in this save are touched. Untouched modules remain
 *     byte-identical to whatever was previously stored.
 *   - Per-ability overrides are scoped to the modules being saved, so a
 *     filtered save cannot drop overrides for unrelated modules.
 *
 * Nonce + capability checks preserve the prior authorization semantics:
 * `manage_options` for site admin, `manage_network_options` for network
 * admin. The action is registered on both `admin_post_*` and
 * `network_admin_post_*` so the same handler serves both surfaces.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

const ABILITIES_FOR_AI_PERMISSIONS_OPTION = 'abilities_for_ai_permissions';
const ABILITIES_FOR_AI_PERMISSIONS_ACTION = 'abilities_for_ai_save_permissions';

add_action( 'admin_post_' . ABILITIES_FOR_AI_PERMISSIONS_ACTION, 'abilities_for_ai_handle_permissions_save' );
add_action( 'network_admin_post_' . ABILITIES_FOR_AI_PERMISSIONS_ACTION, 'abilities_for_ai_handle_permissions_save' );

/**
 * Handle the permissions form submission.
 *
 * Validates nonce + capability, then applies an intentional patch to the
 * stored option so unrelated module permissions are never wiped by a
 * filtered save.
 */
function abilities_for_ai_handle_permissions_save() {
	$is_network = function_exists( 'is_network_admin' ) && is_network_admin();
	$capability = $is_network ? 'manage_network_options' : 'manage_options';

	if ( ! current_user_can( $capability ) ) {
		wp_die(
			esc_html__( 'You do not have permission to save these settings.', 'abilities-for-ai' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( ABILITIES_FOR_AI_PERMISSIONS_ACTION );

	$raw_input = array();
	if ( isset( $_POST['abilities_for_ai_permissions'] ) && is_array( $_POST['abilities_for_ai_permissions'] ) ) {
		$raw_input = wp_unslash( $_POST['abilities_for_ai_permissions'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in patch function.
	}

	$defaults = abilities_for_ai_permission_defaults();
	$existing = get_option( ABILITIES_FOR_AI_PERMISSIONS_OPTION, $defaults );
	if ( ! is_array( $existing ) ) {
		$existing = $defaults;
	}

	$patched = abilities_for_ai_patch_permissions( $existing, $raw_input );

	update_option( ABILITIES_FOR_AI_PERMISSIONS_OPTION, $patched, true );

	$redirect_base = $is_network ? network_admin_url( 'admin.php' ) : admin_url( 'admin.php' );
	$redirect_url  = add_query_arg(
		array(
			'page'             => 'abilities-for-ai',
			'tab'              => 'explorer',
			'settings-updated' => 'true',
		),
		$redirect_base
	);

	wp_safe_redirect( $redirect_url );
	exit;
}

/**
 * Apply an intentional patch to the permissions option.
 *
 * Only modules that appear in `$input` are rewritten. Modules absent from
 * `$input` are preserved verbatim from `$existing`. Per-ability overrides
 * are scoped the same way: an override is only dropped/replaced if its
 * owning module is in the submitted set.
 *
 * Pure function — no globals, no option reads. Suitable for unit testing
 * against captured live fixtures.
 *
 * @param array $existing Previously stored option (or defaults on first save).
 * @param array $input    Raw `$_POST['abilities_for_ai_permissions']` payload.
 * @return array Patched option ready for `update_option()`.
 */
function abilities_for_ai_patch_permissions( array $existing, array $input ) {
	$defaults = abilities_for_ai_permission_defaults();
	$patched  = $existing;

	// Identify which modules the operator submitted (visible in this save).
	$submitted_modules = array();
	foreach ( $defaults as $module => $ops ) {
		if ( isset( $input[ $module ] ) && is_array( $input[ $module ] ) ) {
			$submitted_modules[ $module ] = true;
		}
	}

	// Patch each submitted module: rebuild ops from defaults shape, treat
	// missing op keys as unchecked. Modules NOT in $submitted_modules are
	// untouched.
	foreach ( $submitted_modules as $module => $_ ) {
		$patched[ $module ] = array();
		foreach ( $defaults[ $module ] as $op => $_default_val ) {
			$patched[ $module ][ $op ] = ! empty( $input[ $module ][ $op ] );
		}
	}

	// Per-ability overrides are scoped to the modules being saved.
	$category_to_module = abilities_for_ai_module_prefix_map();
	$existing_overrides = array();
	if ( isset( $existing['_overrides'] ) && is_array( $existing['_overrides'] ) ) {
		$existing_overrides = $existing['_overrides'];
	}

	// Drop existing overrides whose module is in the submitted set; the
	// operator just made an authoritative decision for those modules.
	$kept_overrides = array();
	foreach ( $existing_overrides as $ability_name => $enabled ) {
		$parts  = explode( '/', (string) $ability_name );
		$module = $category_to_module[ $parts[0] ] ?? null;
		if ( $module && isset( $submitted_modules[ $module ] ) ) {
			continue;
		}
		$kept_overrides[ (string) $ability_name ] = (bool) $enabled;
	}

	// Apply newly submitted overrides — but only for submitted modules,
	// and only when the override actually represents a deviation from the
	// resulting module-level permission (otherwise it is dead weight).
	$new_overrides = array();
	if ( isset( $input['_overrides'] ) && is_array( $input['_overrides'] ) ) {
		foreach ( $input['_overrides'] as $ability_name => $enabled ) {
			$ability_name = preg_replace( '/[^a-z0-9\-\/]/', '', (string) $ability_name );
			if ( '' === $ability_name ) {
				continue;
			}

			$parts  = explode( '/', $ability_name );
			$module = $category_to_module[ $parts[0] ] ?? null;
			if ( ! $module || ! isset( $submitted_modules[ $module ] ) ) {
				continue;
			}

			if ( ! empty( $enabled ) ) {
				continue;
			}

			$module_perms       = $patched[ $module ] ?? array();
			$module_would_allow = ! empty( $module_perms['read'] )
				|| ! empty( $module_perms['write'] )
				|| ! empty( $module_perms['delete'] );

			if ( $module_would_allow ) {
				$new_overrides[ $ability_name ] = false;
			}
		}
	}

	$merged_overrides = array_merge( $kept_overrides, $new_overrides );
	if ( ! empty( $merged_overrides ) ) {
		$patched['_overrides'] = $merged_overrides;
	} else {
		unset( $patched['_overrides'] );
	}

	return $patched;
}

/**
 * Count abilities per module and operation type.
 *
 * Scans ability files to determine how many abilities exist per read/write/delete
 * for each module. Used by the admin UI to show "X of Y abilities enabled".
 *
 * @return array Module counts: [ 'content' => ['read' => 5, 'write' => 2, 'delete' => 1, 'total' => 8], ... ]
 */
function abilities_for_ai_get_ability_counts() {
	$category_to_module = abilities_for_ai_module_prefix_map();

	$counts = array();
	foreach ( array_keys( abilities_for_ai_permission_defaults() ) as $module ) {
		$counts[ $module ] = array( 'read' => 0, 'write' => 0, 'delete' => 0, 'total' => 0 );
	}

	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return $counts;
	}

	$abilities = wp_get_abilities();
	foreach ( $abilities as $name => $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_category' ) ) {
			continue;
		}

		$category = $ability->get_category();
		$module   = $category_to_module[ $category ] ?? null;
		if ( ! $module || ! isset( $counts[ $module ] ) ) {
			continue;
		}

		$meta = $ability->get_meta();
		$readonly    = ! empty( $meta['annotations']['readonly'] );
		$destructive = ! empty( $meta['annotations']['destructive'] );

		if ( $readonly ) {
			$counts[ $module ]['read']++;
		} elseif ( $destructive ) {
			$counts[ $module ]['delete']++;
		} else {
			$counts[ $module ]['write']++;
		}
		$counts[ $module ]['total']++;
	}

	return $counts;
}

/**
 * Calculate total enabled abilities based on current permissions.
 *
 * @return array [ 'enabled' => int, 'total' => int ]
 */
function abilities_for_ai_enabled_count() {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array( 'enabled' => 0, 'total' => 0 );
	}

	$category_to_module = abilities_for_ai_module_prefix_map();

	$abilities = wp_get_abilities();
	$enabled   = 0;
	$total     = 0;

	foreach ( $abilities as $name => $ability ) {
		if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_category' ) ) {
			continue;
		}

		$category = $ability->get_category();
		$module   = $category_to_module[ $category ] ?? null;
		if ( ! $module ) {
			continue;
		}

		$meta        = $ability->get_meta();
		$readonly    = ! empty( $meta['annotations']['readonly'] );
		$destructive = ! empty( $meta['annotations']['destructive'] );
		$op          = $destructive ? 'delete' : ( $readonly ? 'read' : 'write' );

		$total++;
		if ( abilities_for_ai_ability_enabled( $name, $module, $op ) ) {
			$enabled++;
		}
	}

	return array( 'enabled' => $enabled, 'total' => $total );
}
