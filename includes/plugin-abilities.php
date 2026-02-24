<?php

defined( 'ABSPATH' ) || exit;

// Register all WordPress plugin management abilities
add_action( 'wp_abilities_api_init', function() {

    $perms = wp_abilities_suite_get_permissions( 'plugins' );

    // ===== PLUGINS — READ =====
    if ( $perms['read'] ) {

    // List plugins
    wp_register_ability( 'plugins/list', array(
        'label' => 'List Plugins',
        'description' => 'List all installed plugins with their details and status',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'status' => array(
                    'type' => 'string',
                    'description' => 'Filter by plugin status: all, active, inactive, mustuse, dropins',
                    'enum' => array('all', 'active', 'inactive', 'mustuse', 'dropins'),
                    'default' => 'all'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'array',
            'items' => array('type' => 'object')
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins = get_plugins();
            $active_plugins = get_option( 'active_plugins', array() );

            // For multisite
            if ( is_multisite() ) {
                $network_active = get_site_option( 'active_sitewide_plugins', array() );
            } else {
                $network_active = array();
            }

            $status_filter = $input['status'] ?? 'all';

            $plugins = array();

            foreach ( $all_plugins as $plugin_file => $plugin_data ) {
                $is_active = in_array( $plugin_file, $active_plugins );
                $is_network_active = isset( $network_active[$plugin_file] );

                // Apply status filter
                if ( $status_filter === 'active' && !$is_active && !$is_network_active ) {
                    continue;
                }
                if ( $status_filter === 'inactive' && ($is_active || $is_network_active) ) {
                    continue;
                }

                $plugins[] = array(
                    'file' => $plugin_file,
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'description' => $plugin_data['Description'],
                    'author' => $plugin_data['Author'],
                    'author_uri' => $plugin_data['AuthorURI'],
                    'plugin_uri' => $plugin_data['PluginURI'],
                    'network' => $plugin_data['Network'],
                    'requires_wp' => $plugin_data['RequiresWP'],
                    'requires_php' => $plugin_data['RequiresPHP'],
                    'is_active' => $is_active,
                    'is_network_active' => $is_network_active
                );
            }

            // Add must-use plugins if requested
            if ( $status_filter === 'all' || $status_filter === 'mustuse' ) {
                $mu_plugins = get_mu_plugins();
                foreach ( $mu_plugins as $plugin_file => $plugin_data ) {
                    if ( $status_filter === 'mustuse' || $status_filter === 'all' ) {
                        $plugins[] = array(
                            'file' => $plugin_file,
                            'name' => $plugin_data['Name'],
                            'version' => $plugin_data['Version'],
                            'description' => $plugin_data['Description'],
                            'author' => $plugin_data['Author'],
                            'author_uri' => $plugin_data['AuthorURI'],
                            'plugin_uri' => $plugin_data['PluginURI'],
                            'is_mustuse' => true,
                            'is_active' => true
                        );
                    }
                }
            }

            // Add dropins if requested
            if ( $status_filter === 'all' || $status_filter === 'dropins' ) {
                $dropins = get_dropins();
                foreach ( $dropins as $plugin_file => $plugin_data ) {
                    if ( $status_filter === 'dropins' || $status_filter === 'all' ) {
                        $plugins[] = array(
                            'file' => $plugin_file,
                            'name' => $plugin_data['Name'],
                            'description' => $plugin_data['Description'],
                            'is_dropin' => true,
                            'is_active' => true
                        );
                    }
                }
            }

            return $plugins;
        },
        'permission_callback' => function() {
            return current_user_can( 'activate_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Get plugin details
    wp_register_ability( 'plugins/get', array(
        'label' => 'Get Plugin',
        'description' => 'Get detailed information about a specific plugin',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('plugin'),
            'properties' => array(
                'plugin' => array(
                    'type' => 'string',
                    'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object'
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = $input['plugin'];
            $all_plugins = get_plugins();

            if ( ! isset( $all_plugins[$plugin_file] ) ) {
                return new WP_Error( 'not_found', 'Plugin not found' );
            }

            $plugin_data = $all_plugins[$plugin_file];
            $active_plugins = get_option( 'active_plugins', array() );

            if ( is_multisite() ) {
                $network_active = get_site_option( 'active_sitewide_plugins', array() );
            } else {
                $network_active = array();
            }

            $is_active = in_array( $plugin_file, $active_plugins );
            $is_network_active = isset( $network_active[$plugin_file] );

            return array(
                'file' => $plugin_file,
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'author_uri' => $plugin_data['AuthorURI'],
                'plugin_uri' => $plugin_data['PluginURI'],
                'text_domain' => $plugin_data['TextDomain'],
                'domain_path' => $plugin_data['DomainPath'],
                'network' => $plugin_data['Network'],
                'requires_wp' => $plugin_data['RequiresWP'],
                'requires_php' => $plugin_data['RequiresPHP'],
                'update_uri' => $plugin_data['UpdateURI'],
                'title' => $plugin_data['Title'],
                'author_name' => $plugin_data['AuthorName'],
                'is_active' => $is_active,
                'is_network_active' => $is_network_active
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'activate_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end read

    // ===== PLUGINS — WRITE =====
    if ( ! empty( $perms['write'] ) ) {

    // Activate plugin
    wp_register_ability( 'plugins/activate', array(
        'label' => 'Activate Plugin',
        'description' => 'Activate a plugin',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('plugin'),
            'properties' => array(
                'plugin' => array(
                    'type' => 'string',
                    'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")'
                ),
                'network_wide' => array(
                    'type' => 'boolean',
                    'description' => 'Activate network-wide (for multisite)',
                    'default' => false
                ),
                'silent' => array(
                    'type' => 'boolean',
                    'description' => 'Prevent activation hooks from running',
                    'default' => false
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'activate_plugin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = $input['plugin'];
            $network_wide = $input['network_wide'] ?? false;
            $silent = $input['silent'] ?? false;

            // Check if plugin exists
            $all_plugins = get_plugins();
            if ( ! isset( $all_plugins[$plugin_file] ) ) {
                return new WP_Error( 'not_found', 'Plugin not found' );
            }

            // Check if already active
            $active_plugins = get_option( 'active_plugins', array() );
            if ( is_multisite() ) {
                $network_active = get_site_option( 'active_sitewide_plugins', array() );
            } else {
                $network_active = array();
            }

            $is_active = in_array( $plugin_file, $active_plugins );
            $is_network_active = isset( $network_active[$plugin_file] );

            if ( $is_active || $is_network_active ) {
                return array(
                    'success' => true,
                    'message' => 'Plugin is already active'
                );
            }

            $result = activate_plugin( $plugin_file, '', $network_wide, $silent );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            return array(
                'success' => true,
                'message' => 'Plugin activated successfully'
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'activate_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Deactivate plugin
    wp_register_ability( 'plugins/deactivate', array(
        'label' => 'Deactivate Plugin',
        'description' => 'Deactivate a plugin',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('plugin'),
            'properties' => array(
                'plugin' => array(
                    'type' => 'string',
                    'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php")'
                ),
                'network_wide' => array(
                    'type' => 'boolean',
                    'description' => 'Deactivate network-wide (for multisite)',
                    'default' => false
                ),
                'silent' => array(
                    'type' => 'boolean',
                    'description' => 'Prevent deactivation hooks from running',
                    'default' => false
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'deactivate_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = $input['plugin'];
            $network_wide = $input['network_wide'] ?? false;
            $silent = $input['silent'] ?? false;

            // Check if plugin exists
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins = get_plugins();
            if ( ! isset( $all_plugins[$plugin_file] ) ) {
                return new WP_Error( 'not_found', 'Plugin not found' );
            }

            deactivate_plugins( $plugin_file, $silent, $network_wide );

            return array(
                'success' => true,
                'message' => 'Plugin deactivated successfully'
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'activate_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Install plugin
    wp_register_ability( 'plugins/install', array(
        'label' => 'Install Plugin',
        'description' => 'Install a plugin from the WordPress.org repository',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('slug'),
            'properties' => array(
                'slug' => array(
                    'type' => 'string',
                    'description' => 'Plugin slug from WordPress.org repository'
                ),
                'activate' => array(
                    'type' => 'boolean',
                    'description' => 'Activate plugin after installation',
                    'default' => false
                ),
                'network_wide' => array(
                    'type' => 'boolean',
                    'description' => 'Activate network-wide (for multisite)',
                    'default' => false
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string'),
                'plugin_file' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }
            if ( ! class_exists( 'Plugin_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }

            $slug = $input['slug'];
            $activate = $input['activate'] ?? false;
            $network_wide = $input['network_wide'] ?? false;

            // Get plugin info from WordPress.org
            $api = plugins_api( 'plugin_information', array(
                'slug' => $slug,
                'fields' => array(
                    'short_description' => false,
                    'sections' => false,
                    'requires' => false,
                    'rating' => false,
                    'ratings' => false,
                    'downloaded' => false,
                    'downloadlink' => true,
                    'last_updated' => false,
                    'added' => false,
                    'tags' => false,
                    'compatibility' => false,
                    'homepage' => false,
                    'donate_link' => false
                )
            ) );

            if ( is_wp_error( $api ) ) {
                return $api;
            }

            // Create upgrader
            $upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
            $result = $upgrader->install( $api->download_link );

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            if ( ! $result ) {
                return new WP_Error( 'install_failed', 'Plugin installation failed' );
            }

            $plugin_file = $upgrader->plugin_info();

            $response = array(
                'success' => true,
                'message' => 'Plugin installed successfully',
                'plugin_file' => $plugin_file
            );

            // Activate if requested
            if ( $activate && $plugin_file ) {
                if ( ! function_exists( 'activate_plugin' ) ) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $activation_result = activate_plugin( $plugin_file, '', $network_wide );

                if ( is_wp_error( $activation_result ) ) {
                    $response['message'] = 'Plugin installed but activation failed: ' . $activation_result->get_error_message();
                    $response['activation_error'] = true;
                } else {
                    $response['message'] = 'Plugin installed and activated successfully';
                    $response['activated'] = true;
                }
            }

            return $response;
        },
        'permission_callback' => function() {
            return current_user_can( 'install_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end write

    // ===== PLUGINS — READ (continued) =====
    if ( $perms['read'] ) {

    // Search repository
    wp_register_ability( 'plugins/search-repository', array(
        'label' => 'Search Plugin Repository',
        'description' => 'Search the WordPress.org plugin repository',
        'category' => 'plugins',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('search'),
            'properties' => array(
                'search' => array(
                    'type' => 'string',
                    'description' => 'Search query'
                ),
                'per_page' => array(
                    'type' => 'integer',
                    'description' => 'Number of results per page',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'page' => array(
                    'type' => 'integer',
                    'description' => 'Page number',
                    'default' => 1,
                    'minimum' => 1
                ),
                'browse' => array(
                    'type' => 'string',
                    'description' => 'Browse type: popular, featured, recommended, favorites',
                    'enum' => array('popular', 'featured', 'recommended', 'favorites')
                ),
                'author' => array(
                    'type' => 'string',
                    'description' => 'Filter by author username'
                ),
                'tag' => array(
                    'type' => 'string',
                    'description' => 'Filter by tag'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'plugins' => array('type' => 'array', 'items' => array('type' => 'object')),
                'info' => array('type' => 'object')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            $args = array(
                'per_page' => $input['per_page'] ?? 10,
                'page' => $input['page'] ?? 1,
                'fields' => array(
                    'short_description' => true,
                    'description' => false,
                    'sections' => false,
                    'tested' => true,
                    'requires' => true,
                    'requires_php' => true,
                    'rating' => true,
                    'ratings' => false,
                    'downloaded' => true,
                    'downloadlink' => false,
                    'last_updated' => true,
                    'added' => true,
                    'tags' => true,
                    'compatibility' => false,
                    'homepage' => true,
                    'versions' => false,
                    'donate_link' => false,
                    'reviews' => false,
                    'banners' => false,
                    'icons' => true,
                    'active_installs' => true
                )
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
                    $plugins[] = array(
                        'name' => $plugin->name,
                        'slug' => $plugin->slug,
                        'version' => $plugin->version,
                        'author' => $plugin->author,
                        'author_profile' => $plugin->author_profile ?? null,
                        'requires' => $plugin->requires ?? null,
                        'tested' => $plugin->tested ?? null,
                        'requires_php' => $plugin->requires_php ?? null,
                        'rating' => $plugin->rating ?? 0,
                        'num_ratings' => $plugin->num_ratings ?? 0,
                        'active_installs' => $plugin->active_installs ?? 0,
                        'downloaded' => $plugin->downloaded ?? 0,
                        'last_updated' => $plugin->last_updated ?? null,
                        'added' => $plugin->added ?? null,
                        'homepage' => $plugin->homepage ?? null,
                        'short_description' => $plugin->short_description ?? null,
                        'icons' => $plugin->icons ?? array(),
                        'tags' => ! empty( $plugin->tags ) ? array_keys( (array) $plugin->tags ) : array()
                    );
                }
            }

            return array(
                'plugins' => $plugins,
                'info' => array(
                    'page' => $api->info['page'] ?? 1,
                    'pages' => $api->info['pages'] ?? 1,
                    'results' => $api->info['results'] ?? 0
                )
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'install_plugins' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end read

    error_log( 'WordPress Plugin Abilities: Registered 6 plugin management abilities' );

}, 100 );
