<?php
/**
 * Multisite Abilities
 *
 * WordPress multisite network management.
 * Only loads on multisite installations.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_multisite() ) {
	return;
}

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'multisite', 'manage_network' );

	// ===== MULTISITE — READ =====

	$reg->read( 'multisite/list-sites', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'List Network Sites',
		'description' => 'List all sites in the multisite network with status and details',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Sites per page',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number',
					'default'     => 1,
					'minimum'     => 1,
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search by domain or path',
				),
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'public', 'archived', 'mature', 'spam', 'deleted' ),
					'default'     => 'all',
					'description' => 'Filter by site status',
				),
			),
		),
		'callback' => function( $input ) {
			$args = array(
				'number' => (int) ( $input['per_page'] ?? 20 ),
				'offset' => ( ( (int) ( $input['page'] ?? 1 ) ) - 1 ) * (int) ( $input['per_page'] ?? 20 ),
			);

			if ( ! empty( $input['search'] ) ) {
				$args['search'] = $input['search'];
			}

			$status = $input['status'] ?? 'all';
			if ( 'all' !== $status ) {
				$status_map = array(
					'public'   => array( 'public' => 1 ),
					'archived' => array( 'archived' => 1 ),
					'mature'   => array( 'mature' => 1 ),
					'spam'     => array( 'spam' => 1 ),
					'deleted'  => array( 'deleted' => 1 ),
				);
				if ( isset( $status_map[ $status ] ) ) {
					$args = array_merge( $args, $status_map[ $status ] );
				}
			}

			$sites = get_sites( $args );
			$total = (int) get_sites( array_merge( $args, array( 'count' => true, 'number' => 0, 'offset' => 0 ) ) );

			$items = array();
			foreach ( $sites as $site ) {
				$items[] = array(
					'blog_id'      => (int) $site->blog_id,
					'domain'       => (string) $site->domain,
					'path'         => (string) $site->path,
					'url'          => (string) get_site_url( (int) $site->blog_id ),
					'registered'   => (string) $site->registered,
					'last_updated' => (string) $site->last_updated,
					'public'       => (bool) $site->public,
					'archived'     => (bool) $site->archived,
					'mature'       => (bool) $site->mature,
					'spam'         => (bool) $site->spam,
					'deleted'      => (bool) $site->deleted,
				);
			}

			return array(
				'sites' => $items,
				'total' => $total,
				'page'  => (int) ( $input['page'] ?? 1 ),
			);
		},
	) );

	$reg->read( 'multisite/get-site', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'Get Site Details',
		'description' => 'Get detailed information about a specific site including its settings',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'blog_id' ),
			'properties' => array(
				'blog_id' => array(
					'type'        => 'integer',
					'description' => 'Blog/site ID',
				),
			),
		),
		'callback' => function( $input ) {
			$blog_id = (int) $input['blog_id'];
			$site    = get_site( $blog_id );

			if ( ! $site ) {
				return new WP_Error( 'not_found', 'Site not found' );
			}

			// Context switch handled by WE_Multisite_Ability::do_execute().
			$details = array(
				'blog_id'        => (int) $site->blog_id,
				'domain'         => (string) $site->domain,
				'path'           => (string) $site->path,
				'url'            => (string) home_url(),
				'site_url'       => (string) site_url(),
				'blogname'       => (string) get_option( 'blogname' ),
				'blogdescription' => (string) get_option( 'blogdescription' ),
				'admin_email'    => (string) get_option( 'admin_email' ),
				'registered'     => (string) $site->registered,
				'last_updated'   => (string) $site->last_updated,
				'public'         => (bool) $site->public,
				'archived'       => (bool) $site->archived,
				'mature'         => (bool) $site->mature,
				'spam'           => (bool) $site->spam,
				'deleted'        => (bool) $site->deleted,
				'language'       => (string) get_option( 'WPLANG', 'en_US' ),
				'active_theme'   => (string) get_option( 'stylesheet' ),
				'post_count'     => (int) wp_count_posts()->publish,
				'user_count'     => (int) count_users()['total_users'],
			);

			return $details;
		},
	) );

	$reg->read( 'multisite/get-network-settings', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'Get Network Settings',
		'description' => 'Get network-level settings like registration policy, upload limits, and default options',
		'callback' => function() {
			return array(
				'site_name'              => (string) get_network_option( null, 'site_name' ),
				'admin_email'            => (string) get_network_option( null, 'admin_email' ),
				'registration'           => (string) get_network_option( null, 'registration', 'none' ),
				'registrationnotification' => (string) get_network_option( null, 'registrationnotification', 'yes' ),
				'upload_space_check_disabled' => (bool) get_network_option( null, 'upload_space_check_disabled', false ),
				'blog_upload_space'      => (int) get_network_option( null, 'blog_upload_space', 100 ),
				'upload_filetypes'       => (string) get_network_option( null, 'upload_filetypes', '' ),
				'fileupload_maxk'        => (int) get_network_option( null, 'fileupload_maxk', 1500 ),
				'active_sitewide_plugins' => array_keys( (array) get_network_option( null, 'active_sitewide_plugins', array() ) ),
				'site_count'             => (int) get_blog_count(),
				'user_count'             => (int) get_user_count(),
			);
		},
	) );

	// ===== MULTISITE — WRITE =====

	$reg->write( 'multisite/create-site', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'Create Network Site',
		'description' => 'Create a new site in the multisite network. Returns the new blog_id, URL, and admin URL.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'domain', 'title' ),
			'properties' => array(
				'domain' => array(
					'type'        => 'string',
					'description' => 'Subdomain or full domain for the new site (e.g., "sub.network.com")',
				),
				'path' => array(
					'type'        => 'string',
					'description' => 'Site path (default "/"). Use "/" for subdomain installs.',
					'default'     => '/',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Site title',
				),
				'admin_email' => array(
					'type'        => 'string',
					'description' => 'Admin email for the new site. Defaults to network admin email.',
				),
				'public' => array(
					'type'        => 'boolean',
					'description' => 'Whether the site is public',
					'default'     => true,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'blog_id'   => array( 'type' => 'integer' ),
			'url'       => array( 'type' => 'string' ),
			'admin_url' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! is_multisite() ) {
				return new WP_Error( 'not_multisite', 'This operation requires a WordPress multisite installation.' );
			}

			$domain      = sanitize_text_field( $input['domain'] );
			$path        = isset( $input['path'] ) ? sanitize_text_field( $input['path'] ) : '/';
			$title       = sanitize_text_field( $input['title'] );
			$admin_email = ! empty( $input['admin_email'] ) ? sanitize_email( $input['admin_email'] ) : get_network_option( null, 'admin_email' );
			$public      = $input['public'] ?? true;

			// Resolve or create the admin user.
			$admin_user = get_user_by( 'email', $admin_email );
			$user_id    = $admin_user ? $admin_user->ID : get_current_user_id();

			$blog_id = wp_insert_site( array(
				'domain'  => $domain,
				'path'    => $path,
				'title'   => $title,
				'user_id' => $user_id,
				'options' => array(
					'public' => $public ? 1 : 0,
				),
			) );

			if ( is_wp_error( $blog_id ) ) {
				return $blog_id;
			}

			// Set admin email on the new site.
			switch_to_blog( $blog_id );
			update_option( 'admin_email', $admin_email );
			$url       = home_url();
			$admin_url = admin_url();
			restore_current_blog();

			return array(
				'success'   => true,
				'blog_id'   => (int) $blog_id,
				'url'       => (string) $url,
				'admin_url' => (string) $admin_url,
			);
		},
	) );

	$reg->write( 'multisite/update-site', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'Update Site Settings',
		'description' => 'Update settings for a specific site (title, description, admin email, status flags)',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'blog_id' ),
			'properties' => array(
				'blog_id' => array(
					'type'        => 'integer',
					'description' => 'Blog/site ID to update',
				),
				'blogname' => array(
					'type'        => 'string',
					'description' => 'Site title',
				),
				'blogdescription' => array(
					'type'        => 'string',
					'description' => 'Site tagline',
				),
				'admin_email' => array(
					'type'        => 'string',
					'description' => 'Site admin email',
				),
				'public' => array(
					'type'        => 'boolean',
					'description' => 'Whether the site is public',
				),
				'archived' => array(
					'type'        => 'boolean',
					'description' => 'Whether the site is archived',
				),
				'mature' => array(
					'type'        => 'boolean',
					'description' => 'Whether the site is marked as mature',
				),
			),
		),
		'callback' => function( $input ) {
			$blog_id = (int) $input['blog_id'];
			$site    = get_site( $blog_id );

			if ( ! $site ) {
				return new WP_Error( 'not_found', 'Site not found' );
			}

			$updated = array();

			// Update site-level flags.
			$site_args = array();
			foreach ( array( 'public', 'archived', 'mature' ) as $flag ) {
				if ( isset( $input[ $flag ] ) ) {
					$site_args[ $flag ] = $input[ $flag ] ? 1 : 0;
					$updated[ $flag ]   = (bool) $input[ $flag ];
				}
			}

			if ( ! empty( $site_args ) ) {
				$result = update_blog_details( $blog_id, $site_args );
				if ( ! $result ) {
					return new WP_Error( 'update_failed', 'Failed to update site flags' );
				}
			}

			// Update blog options (context switch handled by WE_Multisite_Ability::do_execute()).
			$option_fields = array( 'blogname', 'blogdescription', 'admin_email' );
			foreach ( $option_fields as $field ) {
				if ( isset( $input[ $field ] ) ) {
					update_option( $field, sanitize_text_field( $input[ $field ] ) );
					$updated[ $field ] = $input[ $field ];
				}
			}

			return array(
				'success' => true,
				'blog_id' => $blog_id,
				'updated' => $updated,
			);
		},
	) );

	// ===== MULTISITE — DELETE =====

	$reg->delete( 'multisite/delete-site', array(
		'ability_class' => 'WE_Multisite_Ability',
		'label'       => 'Delete Network Site',
		'description' => 'Permanently delete a site from the multisite network. This is destructive and irreversible — requires confirmation matching the site domain or site_id.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'site_id', 'confirm' ),
			'properties' => array(
				'site_id' => array(
					'type'        => 'integer',
					'description' => 'ID of the site to delete',
				),
				'confirm' => array(
					'type'        => 'string',
					'description' => 'Safety confirmation — must match the site domain or the site_id as a string',
				),
				'drop_tables' => array(
					'type'        => 'boolean',
					'description' => 'Whether to drop the site database tables (default true)',
					'default'     => true,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'site_id' => array( 'type' => 'integer' ),
			'domain'  => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'destructive' => true, 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! is_multisite() ) {
				return new WP_Error( 'not_multisite', 'This operation requires a WordPress multisite installation.' );
			}

			$site_id = (int) $input['site_id'];
			$confirm = sanitize_text_field( $input['confirm'] );

			if ( 1 === $site_id ) {
				return new WP_Error( 'ability_invalid_input', 'Cannot delete the main site (ID 1).' );
			}

			$site = get_blog_details( $site_id );
			if ( ! $site ) {
				return new WP_Error( 'not_found', 'Site not found.' );
			}

			$domain = (string) $site->domain;
			if ( $confirm !== $domain && $confirm !== (string) $site_id ) {
				return new WP_Error( 'ability_invalid_input', "Confirmation failed. Provide the site domain \"{$domain}\" or site_id \"{$site_id}\" as the confirm value." );
			}

			$drop_tables = $input['drop_tables'] ?? true;

			$result = wp_delete_site( $site_id );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success' => true,
				'site_id' => $site_id,
				'domain'  => $domain,
			);
		},
	) );
} );
