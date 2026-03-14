<?php
/**
 * SureCart Suite — Order Abilities (P0)
 *
 * Orders and checkouts.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'surecart', 'manage_options' );

	// ===== LIST ORDERS =====
	$reg->read( 'surecart/list-orders', array(
		'label'       => 'List SureCart Orders',
		'description' => 'Returns a paginated list of orders with optional filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'status'      => array( 'type' => 'string', 'description' => 'Filter by status: paid, unpaid, partially_refunded, refunded, void.' ),
					'customer_id' => array( 'type' => 'string', 'description' => 'Filter by customer ID.' ),
					'query'       => array( 'type' => 'string', 'description' => 'Search query.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['status'] ) )      $query['status']       = array( $input['status'] );
				if ( ! empty( $input['customer_id'] ) )  $query['customer_ids'] = array( $input['customer_id'] );
				if ( ! empty( $input['query'] ) )        $query['query']        = $input['query'];

				$result = \SureCart\Models\Order::where( $query )
					->with( array( 'checkout', 'checkout.line_items', 'line_item.price', 'price.product' ) )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-orders' );
		},
	));

	// ===== GET ORDER =====
	$reg->read( 'surecart/get-order', array(
		'label'       => 'Get SureCart Order',
		'description' => 'Returns a single order by ID with line items and customer details.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Order ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$order = \SureCart\Models\Order::with( array( 'checkout', 'checkout.line_items', 'line_item.price', 'price.product', 'checkout.customer' ) )
					->find( $input['id'] );

				if ( is_wp_error( $order ) ) {
					return $order;
				}

				return abilities_for_ai_surecart_format_model( $order );
			}, 'get-order' );
		},
	));

	// ===== UPDATE ORDER =====
	$reg->write( 'surecart/update-order', array(
		'label'       => 'Update SureCart Order',
		'description' => 'Updates order metadata or notes.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'string', 'description' => 'Order ID.' ),
				'metadata' => array( 'type' => 'object', 'description' => 'Custom metadata key-value pairs.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'id' => $input['id'] );
				if ( isset( $input['metadata'] ) ) $attrs['metadata'] = $input['metadata'];

				$order = \SureCart\Models\Order::update( $attrs );

				if ( is_wp_error( $order ) ) {
					return $order;
				}

				return abilities_for_ai_surecart_format_model( $order );
			}, 'update-order' );
		},
	));

	// ===== LIST CHECKOUTS =====
	$reg->read( 'surecart/list-checkouts', array(
		'label'       => 'List SureCart Checkouts',
		'description' => 'Returns a paginated list of checkout sessions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'customer_id' => array( 'type' => 'string', 'description' => 'Filter by customer ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['customer_id'] ) ) $query['customer_ids'] = array( $input['customer_id'] );

				$result = \SureCart\Models\Checkout::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-checkouts' );
		},
	));

	// ===== GET CHECKOUT =====
	$reg->read( 'surecart/get-checkout', array(
		'label'       => 'Get SureCart Checkout',
		'description' => 'Returns a single checkout session by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Checkout ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$checkout = \SureCart\Models\Checkout::with( array( 'line_items', 'line_item.price', 'price.product', 'customer' ) )
					->find( $input['id'] );

				if ( is_wp_error( $checkout ) ) {
					return $checkout;
				}

				return abilities_for_ai_surecart_format_model( $checkout );
			}, 'get-checkout' );
		},
	));

});
