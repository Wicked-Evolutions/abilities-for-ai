<?php
/**
 * SureCart Suite — Customer Abilities (P0)
 *
 * Customers and purchases.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'surecart', 'manage_options' );

	// ===== LIST CUSTOMERS =====
	$reg->read( 'surecart/list-customers', array(
		'label'       => 'List SureCart Customers',
		'description' => 'Returns a paginated list of customers.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'query' => array( 'type' => 'string', 'description' => 'Search by name or email.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['query'] ) ) $query['query'] = $input['query'];

				$result = \SureCart\Models\Customer::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-customers' );
		},
	));

	// ===== GET CUSTOMER =====
	$reg->read( 'surecart/get-customer', array(
		'label'       => 'Get SureCart Customer',
		'description' => 'Returns a single customer by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Customer ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$customer = \SureCart\Models\Customer::with( array( 'subscriptions', 'orders' ) )
					->find( $input['id'] );

				if ( is_wp_error( $customer ) ) {
					return $customer;
				}

				return abilities_for_ai_surecart_format_model( $customer );
			}, 'get-customer' );
		},
	));

	// ===== UPDATE CUSTOMER =====
	$reg->write( 'surecart/update-customer', array(
		'label'       => 'Update SureCart Customer',
		'description' => 'Updates customer fields (name, email, phone, metadata).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'string', 'description' => 'Customer ID.' ),
				'name'     => array( 'type' => 'string', 'description' => 'Customer name.' ),
				'email'    => array( 'type' => 'string', 'description' => 'Customer email.' ),
				'phone'    => array( 'type' => 'string', 'description' => 'Customer phone.' ),
				'metadata' => array( 'type' => 'object', 'description' => 'Custom metadata key-value pairs.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'id' => $input['id'] );
				if ( isset( $input['name'] ) )     $attrs['name']     = $input['name'];
				if ( isset( $input['email'] ) )    $attrs['email']    = $input['email'];
				if ( isset( $input['phone'] ) )    $attrs['phone']    = $input['phone'];
				if ( isset( $input['metadata'] ) ) $attrs['metadata'] = $input['metadata'];

				$customer = \SureCart\Models\Customer::update( $attrs );

				if ( is_wp_error( $customer ) ) {
					return $customer;
				}

				return abilities_for_ai_surecart_format_model( $customer );
			}, 'update-customer' );
		},
	));

	// ===== LIST PURCHASES =====
	$reg->read( 'surecart/list-purchases', array(
		'label'       => 'List SureCart Purchases',
		'description' => 'Returns a paginated list of purchases (entitlements).',
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

				$result = \SureCart\Models\Purchase::where( $query )
					->with( array( 'product', 'price' ) )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-purchases' );
		},
	));

});
