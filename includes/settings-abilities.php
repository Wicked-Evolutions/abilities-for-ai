<?php
/**
 * Settings Abilities
 *
 * Core WordPress settings access. V1.0: read-only + allowlisted writes.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	// Settings groups and their option keys.
	$settings_groups = array(
		'general'    => array( 'blogname', 'blogdescription', 'admin_email', 'siteurl', 'home', 'date_format', 'time_format', 'start_of_week', 'timezone_string', 'WPLANG' ),
		'writing'    => array( 'default_category', 'default_post_format', 'use_smilies', 'use_balanceTags', 'default_link_category' ),
		'reading'    => array( 'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page', 'posts_per_rss', 'rss_use_excerpt', 'blog_public' ),
		'discussion' => array( 'default_pingback_flag', 'default_ping_status', 'default_comment_status', 'require_name_email', 'comment_registration', 'close_comments_for_old_posts', 'close_comments_days_old', 'thread_comments', 'thread_comments_depth', 'page_comments', 'comments_per_page', 'default_comments_page', 'comment_order', 'moderation_keys', 'disallowed_keys' ),
		'media'      => array( 'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop', 'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h', 'uploads_use_yearmonth_folders' ),
		'permalink'  => array( 'permalink_structure', 'category_base', 'tag_base' ),
		'privacy'    => array( 'wp_page_for_privacy_policy' ),
	);

	// Safe-to-write settings (V1.0 allowlist).
	$writable_settings = array(
		'blogname', 'blogdescription', 'date_format', 'time_format', 'start_of_week',
		'timezone_string', 'WPLANG', 'default_category', 'default_post_format',
		'posts_per_page', 'posts_per_rss', 'rss_use_excerpt',
		'default_comment_status', 'default_ping_status',
		'thumbnail_size_w', 'thumbnail_size_h', 'medium_size_w', 'medium_size_h',
		'large_size_w', 'large_size_h',
		'category_base', 'tag_base',
	);

	$reg = new WP_Abilities_Suite_Registrar( 'settings', 'manage_options' );

	$reg->read( 'settings/list', array(
		'label'       => 'List Settings',
		'description' => 'List core WordPress settings grouped by settings page with current values.',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'groups' => array( 'type' => 'object', 'description' => 'Settings keyed by group name' ),
		) ),
		'callback' => function() use ( $settings_groups ) {
			$result = array();
			foreach ( $settings_groups as $group => $keys ) {
				$values = array();
				foreach ( $keys as $key ) {
					$values[ $key ] = get_option( $key );
				}
				$result[ $group ] = $values;
			}
			return array( 'groups' => $result );
		},
	));

	$reg->read( 'settings/get', array(
		'label'       => 'Get Setting',
		'description' => 'Get a specific WordPress setting/option value.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'option_name' => array( 'type' => 'string', 'description' => 'Option name to retrieve' ),
			),
			'required' => array( 'option_name' ),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'option_name' => array( 'type' => 'string' ),
			'value'       => array( 'type' => 'string', 'description' => 'Option value (may be string, array, or serialized data)' ),
		) ),
		'callback' => function( $params ) {
			$name  = sanitize_text_field( $params['option_name'] ?? '' );
			$value = get_option( $name );
			if ( $value === false ) {
				return wp_abilities_error( 'not_found', "Option '{$name}' not found." );
			}
			return array( 'option_name' => $name, 'value' => $value );
		},
	));

	$reg->read( 'settings/get-group', array(
		'label'       => 'Get Settings Group',
		'description' => 'Get all settings in a group: general, writing, reading, discussion, media, permalink, or privacy.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'group' => array( 'type' => 'string', 'description' => 'Settings group name', 'enum' => array( 'general', 'writing', 'reading', 'discussion', 'media', 'permalink', 'privacy' ) ),
			),
			'required' => array( 'group' ),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'group'    => array( 'type' => 'string' ),
			'settings' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $params ) use ( $settings_groups ) {
			$group = sanitize_text_field( $params['group'] ?? '' );
			if ( ! isset( $settings_groups[ $group ] ) ) {
				return wp_abilities_error( 'ability_invalid_input', "Invalid group '{$group}'. Valid: " . implode( ', ', array_keys( $settings_groups ) ) );
			}
			$values = array();
			foreach ( $settings_groups[ $group ] as $key ) {
				$values[ $key ] = get_option( $key );
			}
			return array( 'group' => $group, 'settings' => $values );
		},
	));

	$reg->read( 'settings/get-permalink-structure', array(
		'label'       => 'Get Permalink Structure',
		'description' => 'Get the current permalink configuration.',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'structure'              => array( 'type' => 'string' ),
			'category_base'          => array( 'type' => 'string' ),
			'tag_base'               => array( 'type' => 'string' ),
			'using_permalinks'       => array( 'type' => 'boolean' ),
			'using_index_permalinks' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function() {
			global $wp_rewrite;
			return array(
				'structure'              => get_option( 'permalink_structure' ),
				'category_base'          => get_option( 'category_base' ),
				'tag_base'               => get_option( 'tag_base' ),
				'using_permalinks'       => $wp_rewrite->using_permalinks(),
				'using_index_permalinks' => $wp_rewrite->using_index_permalinks(),
			);
		},
	));

	$reg->write( 'settings/update', array(
		'label'       => 'Update Setting',
		'description' => 'Update a WordPress setting. V1.0: limited to safe allowlisted settings (no siteurl, home, admin_email).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'option_name'  => array( 'type' => 'string', 'description' => 'Option name to update' ),
				'option_value' => array( 'type' => 'string', 'description' => 'New value' ),
			),
			'required' => array( 'option_name', 'option_value' ),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'option_name' => array( 'type' => 'string' ),
			'updated'     => array( 'type' => 'boolean' ),
			'new_value'   => array( 'type' => 'string', 'description' => 'Updated option value' ),
		) ),
		'callback' => function( $params ) use ( $writable_settings ) {
			$name = sanitize_text_field( $params['option_name'] ?? '' );
			if ( ! in_array( $name, $writable_settings, true ) ) {
				return wp_abilities_error( 'rest_forbidden', "Setting '{$name}' is not in the V1.0 writable allowlist." );
			}
			$value  = sanitize_text_field( $params['option_value'] );
			$result = update_option( $name, $value );
			return array( 'option_name' => $name, 'updated' => (bool) $result, 'new_value' => get_option( $name ) );
		},
	));
});
