<?php
/**
 * SureCart Suite — Shared Helpers
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wrap a SureCart model call with error handling.
 *
 * @param callable $callback The model operation.
 * @param string   $context  Error context for logging.
 * @return mixed|WP_Error
 */
function abilities_for_ai_surecart_call( $callback, $context = '' ) {
	try {
		$result = $callback();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result;
	} catch ( \Exception $e ) {
		return new \WP_Error(
			'surecart_api_error',
			sprintf( 'SureCart API error (%s): %s', $context, $e->getMessage() ),
			array( 'status' => 502 )
		);
	}
}

/**
 * Standard pagination input schema properties for SureCart list abilities.
 *
 * @return array
 */
function abilities_for_ai_surecart_pagination_schema() {
	return array(
		'page'     => array( 'type' => 'integer', 'description' => 'Page number (default: 1).', 'default' => 1 ),
		'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (default: 20, max: 100).', 'default' => 20 ),
	);
}

/**
 * Format a SureCart Collection into a standard paginated response.
 *
 * @param \SureCart\Models\Collection $collection The SureCart collection.
 * @return array
 */
function abilities_for_ai_surecart_format_paginated( $collection ) {
	$items = array();
	// Note: do NOT use empty() here — SureCart's Collection uses __get magic,
	// and empty() returns true even when data contains items.
	$data = $collection->data;
	if ( is_array( $data ) || ( is_object( $data ) && $data instanceof \Traversable ) ) {
		foreach ( $data as $model ) {
			$items[] = is_object( $model ) && method_exists( $model, 'toArray' ) ? $model->toArray() : (array) $model;
		}
	}

	$pagination = $collection->pagination ?? (object) array();

	return array(
		'data'       => abilities_for_ai_safe_value( $items ),
		'pagination' => array(
			'total'       => (int) ( $pagination->count ?? 0 ),
			'page'        => (int) ( $pagination->page ?? 1 ),
			'per_page'    => (int) ( $pagination->limit ?? 20 ),
			'total_pages' => (int) $collection->totalPages(),
			'has_next'    => $collection->hasNextPage(),
		),
	);
}

/**
 * Format a single SureCart model into an array.
 *
 * @param object $model The SureCart model instance.
 * @return array
 */
function abilities_for_ai_surecart_format_model( $model ) {
	if ( is_object( $model ) && method_exists( $model, 'toArray' ) ) {
		return abilities_for_ai_safe_value( $model->toArray() );
	}
	return abilities_for_ai_safe_value( (array) $model );
}
