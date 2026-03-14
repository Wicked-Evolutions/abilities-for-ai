<?php
/**
 * Astra Global Styles Abilities
 *
 * WordPress Global Styles API access (wp_global_styles post type).
 *
 * Abilities:
 *   - astra/get-global-styles    (read)
 *   - astra/update-global-styles (write)
 *
 * @package Abilities_For_AI
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	$reg = new Abilities_For_AI_Registrar( 'astra', 'edit_posts' );

	// ===== GET GLOBAL STYLES =====

	$reg->read( 'astra/get-global-styles', array(
		'label'       => 'Get Global Styles',
		'description' => 'Read the WordPress Global Styles object (theme.json-format settings and styles). Returns the wp_global_styles post for the active theme. Use source "custom" for user customizations, "theme" for base theme defaults.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'source' => array(
					'type'        => 'string',
					'description' => 'Source to read from: "custom" (user overrides) or "theme" (base theme.json defaults). Default: "custom".',
					'default'     => 'custom',
					'enum'        => array( 'custom', 'theme' ),
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer' ),
				'version'  => array( 'type' => 'integer' ),
				'settings' => array( 'type' => 'object' ),
				'styles'   => array( 'type' => 'object' ),
				'theme'    => array( 'type' => 'string' ),
			),
		),
		'callback' => function( $input ) {
			$source = $input['source'] ?? 'custom';

			if ( 'theme' === $source ) {
				return array(
					'id'       => 0,
					'version'  => 3,
					'settings' => wp_get_global_settings(),
					'styles'   => wp_get_global_styles(),
					'theme'    => get_stylesheet(),
				);
			}

			$stylesheet = get_stylesheet();
			$posts = get_posts( array(
				'post_type'   => 'wp_global_styles',
				'post_status' => array( 'publish', 'draft' ),
				'name'        => 'wp-global-styles-' . $stylesheet,
				'numberposts' => 1,
			));

			if ( empty( $posts ) ) {
				return array(
					'id'       => 0,
					'version'  => 3,
					'settings' => new \stdClass(),
					'styles'   => new \stdClass(),
					'theme'    => $stylesheet,
					'message'  => 'No custom global styles found. The site uses theme defaults only.',
				);
			}

			$post    = $posts[0];
			$content = json_decode( $post->post_content, true ) ?: array();

			return array(
				'id'       => $post->ID,
				'version'  => $content['version'] ?? 3,
				'settings' => ! empty( $content['settings'] ) ? $content['settings'] : new \stdClass(),
				'styles'   => ! empty( $content['styles'] ) ? $content['styles'] : new \stdClass(),
				'theme'    => $stylesheet,
			);
		},
	));

	// ===== UPDATE GLOBAL STYLES =====

	$reg->write( 'astra/update-global-styles', array(
		'label'       => 'Update Global Styles',
		'description' => 'Write to the WordPress Global Styles post (theme.json format). Deep-merges provided settings/styles with existing values. Only provided keys are changed.',
		'capability'  => 'edit_theme_options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'settings' => array(
					'type'        => 'object',
					'description' => 'Global settings to merge (theme.json format).',
				),
				'styles' => array(
					'type'        => 'object',
					'description' => 'Global styles to merge (theme.json format).',
				),
			),
		),
		'output_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'success'  => array( 'type' => 'boolean' ),
				'id'       => array( 'type' => 'integer' ),
				'settings' => array( 'type' => 'object' ),
				'styles'   => array( 'type' => 'object' ),
			),
		),
		'callback' => function( $input ) {
			if ( empty( $input['settings'] ) && empty( $input['styles'] ) ) {
				return new \WP_Error( 'no_data', 'Provide at least one of "settings" or "styles" to update.' );
			}

			$stylesheet = get_stylesheet();
			$posts = get_posts( array(
				'post_type'   => 'wp_global_styles',
				'post_status' => array( 'publish', 'draft' ),
				'name'        => 'wp-global-styles-' . $stylesheet,
				'numberposts' => 1,
			));

			if ( empty( $posts ) ) {
				$content = array(
					'version'                    => 3,
					'isGlobalStylesUserThemeJSON' => true,
				);
				if ( ! empty( $input['settings'] ) ) {
					$content['settings'] = $input['settings'];
				}
				if ( ! empty( $input['styles'] ) ) {
					$content['styles'] = $input['styles'];
				}
				$post_id = wp_insert_post( array(
					'post_type'    => 'wp_global_styles',
					'post_status'  => 'publish',
					'post_name'    => 'wp-global-styles-' . $stylesheet,
					'post_title'   => 'Custom Styles',
					'post_content' => wp_json_encode( $content ),
				), true );
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
				return array(
					'success'  => true,
					'id'       => $post_id,
					'settings' => $content['settings'] ?? new \stdClass(),
					'styles'   => $content['styles'] ?? new \stdClass(),
				);
			}

			$post    = $posts[0];
			$content = json_decode( $post->post_content, true ) ?: array(
				'version'                    => 3,
				'isGlobalStylesUserThemeJSON' => true,
			);

			if ( ! empty( $input['settings'] ) ) {
				$existing = $content['settings'] ?? array();
				$content['settings'] = astra_abilities_deep_merge( $existing, $input['settings'] );
			}
			if ( ! empty( $input['styles'] ) ) {
				$existing = $content['styles'] ?? array();
				$content['styles'] = astra_abilities_deep_merge( $existing, $input['styles'] );
			}

			$result = wp_update_post( array(
				'ID'           => $post->ID,
				'post_content' => wp_json_encode( $content ),
			), true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success'  => true,
				'id'       => $post->ID,
				'settings' => $content['settings'] ?? new \stdClass(),
				'styles'   => $content['styles'] ?? new \stdClass(),
			);
		},
	));

}, 100 );
