<?php
/**
 * Shared Input/Output Schema Helpers
 *
 * Centralised JSON Schema building blocks used across all ability modules.
 * Abilities for Fluent Plugins copies this pattern — keep the function signatures stable.
 *
 * Naming convention: abilities_for_ai_schema_*()
 * *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// INPUT SCHEMA HELPERS
// ============================================================

/**
 * Standard pagination input schema properties (page + per_page).
 *
 * Usage — spread into 'properties':
 *   'properties' => array_merge( [...], abilities_for_ai_schema_pagination() )
 *
 * @param int $default_per_page Default items per page (default: 20).
 * @return array Schema property definitions.
 */
function abilities_for_ai_schema_pagination( $default_per_page = 20 ) {
	return array(
		'page' => array(
			'type'        => 'integer',
			'description' => 'Page number (default: 1)',
			'default'     => 1,
			'minimum'     => 1,
		),
		'per_page' => array(
			'type'        => 'integer',
			'description' => 'Items per page, max 100 (default: ' . $default_per_page . ')',
			'default'     => $default_per_page,
			'minimum'     => 1,
			'maximum'     => 100,
		),
	);
}

/**
 * Post type input schema property with pattern constraint.
 *
 * @param string $description Optional custom description.
 * @return array Schema property definition.
 */
function abilities_for_ai_schema_post_type( $description = 'Post type slug (e.g. post, page, or custom post type)' ) {
	return array(
		'type'        => 'string',
		'description' => $description,
		'default'     => 'post',
		'pattern'     => '^[a-z0-9_-]+$',
	);
}

/**
 * Search query input schema property with minLength.
 *
 * @param string $description Optional custom description.
 * @return array Schema property definition.
 */
function abilities_for_ai_schema_search( $description = 'Search query string' ) {
	return array(
		'type'        => 'string',
		'description' => $description,
		'minLength'   => 1,
	);
}

/**
 * Orderby input schema property.
 *
 * @param array  $options    Allowed orderby values.
 * @param string $default    Default orderby value.
 * @param string $description Optional custom description.
 * @return array Schema property definition.
 */
function abilities_for_ai_schema_orderby( $options = array( 'date', 'title', 'modified', 'ID', 'menu_order' ), $default = 'date', $description = 'Field to order results by' ) {
	return array(
		'type'        => 'string',
		'description' => $description,
		'default'     => $default,
		'enum'        => $options,
	);
}

// ============================================================
// OUTPUT SCHEMA HELPERS
// ============================================================

/**
 * Standard paginated list response output_schema.
 *
 * Returns a schema for: { total, pages, page, per_page, items: [...] }
 *
 * Usage:
 *   'output_schema' => abilities_for_ai_schema_list_output( 'posts', array(
 *       'id'    => array( 'type' => 'integer' ),
 *       'title' => array( 'type' => 'string' ),
 *   ) )
 *
 * @param string $items_key   Key name for the items array (e.g. 'posts', 'terms').
 * @param array  $item_props  Properties of a single item object.
 * @return array output_schema definition.
 */
function abilities_for_ai_schema_list_output( $items_key = 'items', $item_props = array() ) {
	$item_schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$item_schema['properties'] = $item_props;
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'total'    => array( 'type' => 'integer', 'description' => 'Total matching items across all pages' ),
			'pages'    => array( 'type' => 'integer', 'description' => 'Total number of pages' ),
			'page'     => array( 'type' => 'integer', 'description' => 'Current page number' ),
			'per_page' => array( 'type' => 'integer', 'description' => 'Items per page' ),
			$items_key => array( 'type' => 'array', 'items' => $item_schema ),
		),
	);
}

/**
 * Standard non-paginated collection output_schema.
 *
 * Returns a schema for: { total, items: [...] }
 * Use this for full collections that are not paginated (e.g. cron schedules, theme list).
 *
 * @param string $items_key  Key name for the items array.
 * @param array  $item_props Properties of a single item object.
 * @return array output_schema definition.
 */
function abilities_for_ai_schema_collection_output( $items_key = 'items', $item_props = array() ) {
	$item_schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$item_schema['properties'] = $item_props;
	}

	return array(
		'type'       => 'object',
		'properties' => array(
			'total'    => array( 'type' => 'integer', 'description' => 'Total number of items' ),
			$items_key => array( 'type' => 'array', 'items' => $item_schema ),
		),
	);
}

/**
 * Standard success response output_schema.
 *
 * Returns a schema for: { success: true, ... }
 *
 * @param array $extra_props Additional properties beyond 'success'.
 * @return array output_schema definition.
 */
function abilities_for_ai_schema_success_output( $extra_props = array() ) {
	return array(
		'type'       => 'object',
		'properties' => array_merge(
			array(
				'success' => array( 'type' => 'boolean', 'description' => 'Whether the operation succeeded' ),
			),
			$extra_props
		),
	);
}

/**
 * Standard single-item lookup output_schema.
 *
 * Returns a schema for a single object with known properties.
 *
 * @param array $item_props Properties of the returned object.
 * @return array output_schema definition.
 */
function abilities_for_ai_schema_item_output( $item_props = array() ) {
	$schema = array( 'type' => 'object' );
	if ( ! empty( $item_props ) ) {
		$schema['properties'] = $item_props;
	}
	return $schema;
}
