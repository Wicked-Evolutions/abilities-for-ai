<?php
/**
 * SureCart Suite — License & Download Abilities
 *
 * Licenses, activations, and downloads.
 * SureCart is a trademark of SureCart Inc. This module is not affiliated with or endorsed by SureCart Inc.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'surecart', 'manage_options' );

	// ===== LIST LICENSES =====
	$reg->read( 'surecart/list-licenses', array(
		'label'       => 'List SureCart Licenses',
		'description' => 'Returns a paginated list of software licenses.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'customer_id' => array( 'type' => 'string', 'description' => 'Filter by customer ID.' ),
					'product_id'  => array( 'type' => 'string', 'description' => 'Filter by product ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['customer_id'] ) ) $query['customer_ids'] = array( $input['customer_id'] );
				if ( ! empty( $input['product_id'] ) )  $query['product_ids']  = array( $input['product_id'] );

				$result = \SureCart\Models\License::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-licenses' );
		},
	));

	// ===== GET LICENSE =====
	$reg->read( 'surecart/get-license', array(
		'label'       => 'Get SureCart License',
		'description' => 'Returns a single license by ID with activation details.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'License ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$license = \SureCart\Models\License::find( $input['id'] );

				if ( is_wp_error( $license ) ) {
					return $license;
				}

				return abilities_for_ai_surecart_format_model( $license );
			}, 'get-license' );
		},
	));

	// ===== LIST ACTIVATIONS =====
	$reg->read( 'surecart/list-activations', array(
		'label'       => 'List SureCart License Activations',
		'description' => 'Returns a paginated list of license activations.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'license_id' => array( 'type' => 'string', 'description' => 'Filter by license ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['license_id'] ) ) $query['license_ids'] = array( $input['license_id'] );

				$result = \SureCart\Models\Activation::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-activations' );
		},
	));

	// ===== LIST DOWNLOADS =====
	$reg->read( 'surecart/list-downloads', array(
		'label'       => 'List SureCart Downloads',
		'description' => 'Returns a paginated list of downloadable files.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'product_id' => array( 'type' => 'string', 'description' => 'Filter by product ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['product_id'] ) ) $query['product_ids'] = array( $input['product_id'] );

				$result = \SureCart\Models\Download::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-downloads' );
		},
	));

});
