<?php
/**
 * SureCart Suite — Fulfillment & Returns Abilities
 *
 * Fulfillments, return requests, and return reasons.
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

	// ===== LIST FULFILLMENTS =====
	$reg->read( 'surecart/list-fulfillments', array(
		'label'       => 'List SureCart Fulfillments',
		'description' => 'Returns a paginated list of fulfillments.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'order_id' => array( 'type' => 'string', 'description' => 'Filter by order ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['order_id'] ) ) $query['order_ids'] = array( $input['order_id'] );

				$result = \SureCart\Models\Fulfillment::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-fulfillments' );
		},
	));

	// ===== GET FULFILLMENT =====
	$reg->read( 'surecart/get-fulfillment', array(
		'label'       => 'Get SureCart Fulfillment',
		'description' => 'Returns a single fulfillment by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Fulfillment ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$fulfillment = \SureCart\Models\Fulfillment::find( $input['id'] );

				if ( is_wp_error( $fulfillment ) ) {
					return $fulfillment;
				}

				return abilities_for_ai_surecart_format_model( $fulfillment );
			}, 'get-fulfillment' );
		},
	));

	// ===== CREATE FULFILLMENT =====
	$reg->write( 'surecart/create-fulfillment', array(
		'label'       => 'Create SureCart Fulfillment',
		'description' => 'Creates a fulfillment record for an order (marks items as shipped).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'order'            => array( 'type' => 'string', 'description' => 'Order ID to fulfill.' ),
				'tracking_number'  => array( 'type' => 'string', 'description' => 'Shipping tracking number.' ),
				'tracking_url'     => array( 'type' => 'string', 'description' => 'Shipping tracking URL.' ),
				'shipping_carrier' => array( 'type' => 'string', 'description' => 'Shipping carrier name.' ),
			),
			'required' => array( 'order' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'order' => $input['order'] );
				if ( isset( $input['tracking_number'] ) )  $attrs['tracking_number']  = $input['tracking_number'];
				if ( isset( $input['tracking_url'] ) )     $attrs['tracking_url']     = $input['tracking_url'];
				if ( isset( $input['shipping_carrier'] ) ) $attrs['shipping_carrier'] = $input['shipping_carrier'];

				$fulfillment = \SureCart\Models\Fulfillment::create( $attrs );

				if ( is_wp_error( $fulfillment ) ) {
					return $fulfillment;
				}

				return abilities_for_ai_surecart_format_model( $fulfillment );
			}, 'create-fulfillment' );
		},
	));

	// ===== LIST RETURN REQUESTS =====
	$reg->read( 'surecart/list-return-requests', array(
		'label'       => 'List SureCart Return Requests',
		'description' => 'Returns a paginated list of return requests.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'order_id' => array( 'type' => 'string', 'description' => 'Filter by order ID.' ),
					'status'   => array( 'type' => 'string', 'description' => 'Filter by status: open, approved, rejected, completed.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['order_id'] ) ) $query['order_ids'] = array( $input['order_id'] );
				if ( ! empty( $input['status'] ) )   $query['status']    = array( $input['status'] );

				$result = \SureCart\Models\ReturnRequest::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-return-requests' );
		},
	));

	// ===== GET RETURN REQUEST =====
	$reg->read( 'surecart/get-return-request', array(
		'label'       => 'Get SureCart Return Request',
		'description' => 'Returns a single return request by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Return request ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$return_request = \SureCart\Models\ReturnRequest::find( $input['id'] );

				if ( is_wp_error( $return_request ) ) {
					return $return_request;
				}

				return abilities_for_ai_surecart_format_model( $return_request );
			}, 'get-return-request' );
		},
	));

	// ===== LIST RETURN REASONS =====
	$reg->read( 'surecart/list-return-reasons', array(
		'label'       => 'List SureCart Return Reasons',
		'description' => 'Returns available return reasons.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\ReturnReason::where( array() )->get();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$items = array();
				if ( is_array( $result ) ) {
					foreach ( $result as $model ) {
						$items[] = abilities_for_ai_surecart_format_model( $model );
					}
				}

				return array( 'data' => $items );
			}, 'list-return-reasons' );
		},
	));

});
