<?php
/**
 * Knowledge Abilities — Auto-loader for .md knowledge docs.
 *
 * Scans the plugin's knowledge/ directory for .md files and registers each
 * as a read-only, free-tier ability under the "knowledge" category.
 *
 * Adding a new knowledge doc is as simple as dropping a .md file in knowledge/.
 * File name becomes the ability slug: getting-started.md → knowledge/getting-started
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the knowledge category.
 */
add_action( 'wp_abilities_api_categories_init', function() {
	wp_register_ability_category( 'knowledge', array(
		'label'       => __( 'Knowledge', 'abilities-for-ai' ),
		'description' => __( 'Domain knowledge docs that help AI understand WordPress concepts, modules, and best practices. Read-only, free tier.', 'abilities-for-ai' ),
	) );
} );

/**
 * Register knowledge abilities from .md files.
 */
add_action( 'wp_abilities_api_init', function() {

	$knowledge_dir = ABILITIES_FOR_AI_PATH . 'knowledge/';

	if ( ! is_dir( $knowledge_dir ) ) {
		return;
	}

	$files = glob( $knowledge_dir . '*.md' );

	if ( empty( $files ) ) {
		return;
	}

	foreach ( $files as $file ) {
		$slug  = basename( $file, '.md' );
		$name  = 'knowledge/' . $slug;

		// Extract the first H1 heading as the label.
		$contents = file_get_contents( $file );
		$label    = $slug; // fallback
		if ( preg_match( '/^#\s+(.+)$/m', $contents, $matches ) ) {
			$label = trim( $matches[1] );
		}

		// Extract the first blockquote line as description.
		$description = "Knowledge doc: {$slug}";
		if ( preg_match( '/^>\s+(.+)$/m', $contents, $matches ) ) {
			$description = trim( $matches[1] );
		}

		// Capture $file in closure scope.
		$file_path = $file;

		wp_register_ability( $name, array(
			'label'            => $label,
			'description'      => $description,
			'category'         => 'knowledge',
			'execute_callback' => static function() use ( $file_path, $slug ) {
				$content = file_get_contents( $file_path );
				if ( false === $content ) {
					return new \WP_Error(
						'knowledge_read_error',
						sprintf( 'Failed to read knowledge doc: %s', $slug ),
						array( 'status' => 500 )
					);
				}
				return array(
					'slug'    => $slug,
					'content' => $content,
				);
			},
			'permission_callback' => function() {
				return current_user_can( 'read' );
			},
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'slug'    => array( 'type' => 'string', 'description' => 'Knowledge doc identifier' ),
					'content' => array( 'type' => 'string', 'description' => 'Full markdown content of the knowledge doc' ),
				),
			),
			'meta' => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true, 'type' => 'tool' ),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
					'permission'  => 'read',
				),
				'tier' => 'free',
			),
		) );
	}

	// Log registration count.
	$count = count( $files );
	if ( $count > 0 ) {
		error_log( sprintf( 'Abilities for AI: Registered %d knowledge abilities', $count ) );
	}
} );
