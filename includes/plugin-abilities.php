<?php
/**
 * Plugin Abilities
 *
 * WordPress plugin management — listing, activation, deactivation, installation.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'plugins', 'activate_plugins' );

	// ===== PLUGINS — READ =====

	$reg->read( 'plugins/list', array(
		'label'       => 'List Plugins',
		'description' => 'List all installed plugins with their details and status',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status' => array(
					'type'        => 'string',
					'description' => 'Filter by plugin status: all, active, inactive, mustuse, dropins',
					'enum'        => array( 'all', 'active', 'inactive', 'mustuse', 'dropins' ),
					'default'     => 'all',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_collection_output( 'plugins', array(
			'file'      => array( 'type' => 'string' ),
			'name'      => array( 'type' => 'string' ),
			'version'   => array( 'type' => 'string' ),
			'is_active' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$all_plugins    = get_plugins();
			$active_plugins = get_option( 'active_plugins', array() );
			$network_active = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
			$status_filter  = $input['status'] ?? 'all';
			$plugins        = array();

			if ( ! in_array( $status_filter, array( 'mustuse', 'dropins' ), true ) ) {
				foreach ( $all_plugins as $plugin_file => $plugin_data ) {
					$is_active         = in_array( $plugin_file, $active_plugins );
					$is_network_active = isset( $network_active[ $plugin_file ] );

					if ( $status_filter === 'active' && ! $is_active && ! $is_network_active ) {
						continue;
					}
					if ( $status_filter === 'inactive' && ( $is_active || $is_network_active ) ) {
						continue;
					}

					$plugins[] = array(
						'file'              => $plugin_file,
						'name'              => $plugin_data['Name'],
						'version'           => $plugin_data['Version'],
						'description'       => $plugin_data['Description'],
						'author'            => $plugin_data['Author'],
						'author_uri'        => $plugin_data['AuthorURI'],
						'plugin_uri'        => $plugin_data['PluginURI'],
						'network'           => $plugin_data['Network'],
						'requires_wp'       => $plugin_data['RequiresWP'],
						'requires_php'      => $plugin_data['RequiresPHP'],
						'is_active'         => $is_active,
						'is_network_active' => $is_network_active,
					);
				}
			}

			if ( $status_filter === 'all' || $status_filter === 'mustuse' ) {
				foreach ( get_mu_plugins() as $plugin_file => $plugin_data ) {
					$plugins[] = array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'],
						'version'     => $plugin_data['Version'],
						'description' => $plugin_data['Description'],
						'author'      => $plugin_data['Author'],
						'author_uri'  => $plugin_data['AuthorURI'],
						'plugin_uri'  => $plugin_data['PluginURI'],
						'is_mustuse'  => true,
						'is_active'   => true,
					);
				}
			}

			if ( $status_filter === 'all' || $status_filter === 'dropins' ) {
				foreach ( get_dropins() as $plugin_file => $plugin_data ) {
					$plugins[] = array(
						'file'        => $plugin_file,
						'name'        => $plugin_data['Name'],
						'description' => $plugin_data['Description'],
						'is_dropin'   => true,
						'is_active'   => true,
					);
				}
			}

			return array( 'plugins' => $plugins, 'total' => count( $plugins ) );
		},
	) );

	$reg->read( 'plugins/get', array(
		'label'       => 'Get Plugin',
		'description' => 'Get detailed information about a specific plugin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'plugin' ),
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'file'      => array( 'type' => 'string' ),
			'name'      => array( 'type' => 'string' ),
			'version'   => array( 'type' => 'string' ),
			'author'    => array( 'type' => 'string' ),
			'is_active' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_file = $input['plugin'];
			$all_plugins = get_plugins();

			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				return new WP_Error( 'not_found', 'Plugin not found' );
			}

			$plugin_data       = $all_plugins[ $plugin_file ];
			$active_plugins    = get_option( 'active_plugins', array() );
			$network_active    = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
			$is_active         = in_array( $plugin_file, $active_plugins );
			$is_network_active = isset( $network_active[ $plugin_file ] );

			return array(
				'file'              => $plugin_file,
				'name'              => $plugin_data['Name'],
				'version'           => $plugin_data['Version'],
				'description'       => $plugin_data['Description'],
				'author'            => $plugin_data['Author'],
				'author_uri'        => $plugin_data['AuthorURI'],
				'plugin_uri'        => $plugin_data['PluginURI'],
				'text_domain'       => $plugin_data['TextDomain'],
				'domain_path'       => $plugin_data['DomainPath'],
				'network'           => $plugin_data['Network'],
				'requires_wp'       => $plugin_data['RequiresWP'],
				'requires_php'      => $plugin_data['RequiresPHP'],
				'update_uri'        => $plugin_data['UpdateURI'],
				'title'             => $plugin_data['Title'],
				'author_name'       => $plugin_data['AuthorName'],
				'is_active'         => $is_active,
				'is_network_active' => $is_network_active,
			);
		},
	) );

	$reg->read( 'plugins/search-repository', array(
		'capability'  => 'install_plugins',
		'label'       => 'Search Plugin Repository',
		'description' => 'Search the WordPress.org plugin repository',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'search' ),
			'properties' => array(
				'search' => array(
					'type'        => 'string',
					'description' => 'Search query',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Number of results per page',
					'default'     => 10,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number',
					'default'     => 1,
					'minimum'     => 1,
				),
				'browse' => array(
					'type'        => 'string',
					'description' => 'Browse type: popular, featured, recommended, favorites',
					'enum'        => array( 'popular', 'featured', 'recommended', 'favorites' ),
				),
				'author' => array(
					'type'        => 'string',
					'description' => 'Filter by author username',
				),
				'tag' => array(
					'type'        => 'string',
					'description' => 'Filter by tag',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'plugins' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
			'info'    => array( 'type' => 'object' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}

			$args = array(
				'per_page' => $input['per_page'] ?? 10,
				'page'     => $input['page'] ?? 1,
				'fields'   => array(
					'short_description' => true,
					'description'       => false,
					'sections'          => false,
					'tested'            => true,
					'requires'          => true,
					'requires_php'      => true,
					'rating'            => true,
					'ratings'           => false,
					'downloaded'        => true,
					'downloadlink'      => false,
					'last_updated'      => true,
					'added'             => true,
					'tags'              => true,
					'compatibility'     => false,
					'homepage'          => true,
					'versions'          => false,
					'donate_link'       => false,
					'reviews'           => false,
					'banners'           => false,
					'icons'             => true,
					'active_installs'   => true,
				),
			);

			if ( ! empty( $input['search'] ) ) {
				$args['search'] = $input['search'];
			}
			if ( ! empty( $input['browse'] ) ) {
				$args['browse'] = $input['browse'];
			}
			if ( ! empty( $input['author'] ) ) {
				$args['author'] = $input['author'];
			}
			if ( ! empty( $input['tag'] ) ) {
				$args['tag'] = $input['tag'];
			}

			$api = plugins_api( 'query_plugins', $args );
			if ( is_wp_error( $api ) ) {
				return $api;
			}

			$plugins = array();
			if ( isset( $api->plugins ) && is_array( $api->plugins ) ) {
				foreach ( $api->plugins as $plugin ) {
					// plugins_api returns each plugin as an array, not object.
					$p          = is_array( $plugin ) ? $plugin : (array) $plugin;
					$plugins[] = array(
						'name'              => $p['name'] ?? null,
						'slug'              => $p['slug'] ?? null,
						'version'           => $p['version'] ?? null,
						'author'            => $p['author'] ?? null,
						'author_profile'    => $p['author_profile'] ?? null,
						'requires'          => $p['requires'] ?? null,
						'tested'            => $p['tested'] ?? null,
						'requires_php'      => $p['requires_php'] ?? null,
						'rating'            => $p['rating'] ?? 0,
						'num_ratings'       => $p['num_ratings'] ?? 0,
						'active_installs'   => $p['active_installs'] ?? 0,
						'downloaded'        => $p['downloaded'] ?? 0,
						'last_updated'      => $p['last_updated'] ?? null,
						'added'             => $p['added'] ?? null,
						'homepage'          => $p['homepage'] ?? null,
						'short_description' => $p['short_description'] ?? null,
						'icons'             => ! empty( $p['icons'] ) ? array_values( (array) $p['icons'] ) : array(),
						'tags'              => ! empty( $p['tags'] ) ? array_keys( (array) $p['tags'] ) : array(),
					);
				}
			}

			return array(
				'plugins' => $plugins,
				'info'    => array(
					'page'    => $api->info['page'] ?? 1,
					'pages'   => $api->info['pages'] ?? 1,
					'results' => $api->info['results'] ?? 0,
				),
			);
		},
	) );

	// ===== PLUGINS — WRITE =====

	$reg->write( 'plugins/activate', array(
		'label'       => 'Activate Plugin',
		'description' => 'Activate a plugin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'plugin' ),
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")',
				),
				'network_wide' => array(
					'type'        => 'boolean',
					'description' => 'Activate network-wide (for multisite)',
					'default'     => false,
				),
				'silent' => array(
					'type'        => 'boolean',
					'description' => 'Prevent activation hooks from running',
					'default'     => false,
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'activate_plugin' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_file    = $input['plugin'];
			$network_wide   = $input['network_wide'] ?? false;
			$silent         = $input['silent'] ?? false;
			$all_plugins    = get_plugins();

			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				return new WP_Error( 'not_found', 'Plugin not found' );
			}

			$active_plugins    = get_option( 'active_plugins', array() );
			$network_active    = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
			$is_active         = in_array( $plugin_file, $active_plugins );
			$is_network_active = isset( $network_active[ $plugin_file ] );

			if ( $is_active || $is_network_active ) {
				return array( 'success' => true, 'message' => 'Plugin is already active' );
			}

			$result = activate_plugin( $plugin_file, '', $network_wide, $silent );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array( 'success' => true, 'message' => 'Plugin activated successfully' );
		},
	) );

	$reg->write( 'plugins/deactivate', array(
		'label'       => 'Deactivate Plugin',
		'description' => 'Deactivate a plugin',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'plugin' ),
			'properties' => array(
				'plugin' => array(
					'type'        => 'string',
					'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")',
				),
				'network_wide' => array(
					'type'        => 'boolean',
					'description' => 'Deactivate network-wide (for multisite)',
					'default'     => false,
				),
				'silent' => array(
					'type'        => 'boolean',
					'description' => 'Prevent deactivation hooks from running',
					'default'     => false,
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$plugin_file = $input['plugin'];
			$network_wide = $input['network_wide'] ?? false;
			$silent       = $input['silent'] ?? false;
			$all_plugins  = get_plugins();

			if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
				return new WP_Error( 'not_found', 'Plugin not found' );
			}

			deactivate_plugins( $plugin_file, $silent, $network_wide );
			return array( 'success' => true, 'message' => 'Plugin deactivated successfully' );
		},
	) );

	$reg->write( 'plugins/install', array(
		'capability'  => 'install_plugins',
		'label'       => 'Install Plugin',
		'description' => 'Install a plugin from the WordPress.org repository',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'slug' ),
			'properties' => array(
				'slug' => array(
					'type'        => 'string',
					'description' => 'Plugin slug from WordPress.org repository',
				),
				'activate' => array(
					'type'        => 'boolean',
					'description' => 'Activate plugin after installation',
					'default'     => false,
				),
				'network_wide' => array(
					'type'        => 'boolean',
					'description' => 'Activate network-wide (for multisite)',
					'default'     => false,
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message'     => array( 'type' => 'string' ),
			'plugin_file' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'plugins_api' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			if ( ! class_exists( 'Plugin_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}

			$slug         = $input['slug'];
			$activate     = $input['activate'] ?? false;
			$network_wide = $input['network_wide'] ?? false;

			$api = plugins_api( 'plugin_information', array(
				'slug'   => $slug,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'downloadlink'      => true,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			) );

			if ( is_wp_error( $api ) ) {
				return $api;
			}

			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result   = $upgrader->install( $api->download_link );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', 'Plugin installation failed' );
			}

			$plugin_file = $upgrader->plugin_info();
			$response    = array(
				'success'     => true,
				'message'     => 'Plugin installed successfully',
				'plugin_file' => $plugin_file,
			);

			if ( $activate && $plugin_file ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$activation_result = activate_plugin( $plugin_file, '', $network_wide );
				if ( is_wp_error( $activation_result ) ) {
					$response['message']          = 'Plugin installed but activation failed: ' . $activation_result->get_error_message();
					$response['activation_error'] = true;
				} else {
					$response['message']   = 'Plugin installed and activated successfully';
					$response['activated'] = true;
				}
			}

			return $response;
		},
	) );
} );
