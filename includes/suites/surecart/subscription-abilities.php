<?php
/**
 * SureCart Suite — Subscription Abilities (P0)
 *
 * Subscriptions — list, get, cancel, restore, complete.
 * Note: SureCart does not have pause/resume methods. Use cancel + restore instead.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'surecart', 'manage_options' );

	// ===== LIST SUBSCRIPTIONS =====
	$reg->read( 'surecart/list-subscriptions', array(
		'label'       => 'List SureCart Subscriptions',
		'description' => 'Returns a paginated list of subscriptions with optional filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'status'      => array( 'type' => 'string', 'description' => 'Filter by status: active, canceled, past_due, trialing, completed.' ),
					'customer_id' => array( 'type' => 'string', 'description' => 'Filter by customer ID.' ),
					'product_id'  => array( 'type' => 'string', 'description' => 'Filter by product ID.' ),
					'price_id'    => array( 'type' => 'string', 'description' => 'Filter by price ID.' ),
					'query'       => array( 'type' => 'string', 'description' => 'Search query.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['status'] ) )      $query['status']       = array( $input['status'] );
				if ( ! empty( $input['customer_id'] ) )  $query['customer_ids'] = array( $input['customer_id'] );
				if ( ! empty( $input['product_id'] ) )   $query['product_ids']  = array( $input['product_id'] );
				if ( ! empty( $input['price_id'] ) )     $query['price_ids']    = array( $input['price_id'] );
				if ( ! empty( $input['query'] ) )        $query['query']        = $input['query'];

				$result = \SureCart\Models\Subscription::where( $query )
					->with( array( 'price', 'price.product', 'purchase', 'customer' ) )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-subscriptions' );
		},
	));

	// ===== GET SUBSCRIPTION =====
	$reg->read( 'surecart/get-subscription', array(
		'label'       => 'Get SureCart Subscription',
		'description' => 'Returns a single subscription by ID with price, product, and customer details.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$subscription = \SureCart\Models\Subscription::with( array( 'price', 'price.product', 'purchase', 'customer', 'discount' ) )
					->find( $input['id'] );

				if ( is_wp_error( $subscription ) ) {
					return $subscription;
				}

				return abilities_for_ai_surecart_format_model( $subscription );
			}, 'get-subscription' );
		},
	));

	// ===== CANCEL SUBSCRIPTION =====
	$reg->write( 'surecart/cancel-subscription', array(
		'label'       => 'Cancel SureCart Subscription',
		'description' => 'Cancels an active subscription. Can be restored later with restore-subscription.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID to cancel.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Subscription::cancel( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'cancel-subscription' );
		},
	));

	// ===== RESTORE SUBSCRIPTION =====
	$reg->write( 'surecart/restore-subscription', array(
		'label'       => 'Restore SureCart Subscription',
		'description' => 'Restores a canceled subscription back to active.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID to restore.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Subscription::restore( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'restore-subscription' );
		},
	));

	// ===== COMPLETE SUBSCRIPTION =====
	$reg->write( 'surecart/complete-subscription', array(
		'label'       => 'Complete SureCart Subscription',
		'description' => 'Marks a subscription as completed (e.g. fixed-term subscription that has been fully paid).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID to complete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Subscription::complete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'complete-subscription' );
		},
	));

	// ===== RENEW SUBSCRIPTION =====
	$reg->write( 'surecart/renew-subscription', array(
		'label'       => 'Renew SureCart Subscription',
		'description' => 'Renews a subscription, triggering a new billing cycle.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID to renew.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Subscription::renew( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'renew-subscription' );
		},
	));

	// ===== GET SUBSCRIPTION STATS =====
	$reg->read( 'surecart/get-subscription-stats', array(
		'label'       => 'Get SureCart Subscription Statistics',
		'description' => 'Returns aggregate subscription statistics (active count, MRR, churn, etc.).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'period' => array( 'type' => 'string', 'description' => 'Time period: today, week, month, year, all_time. Default: month.' ),
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$args = array();
				if ( ! empty( $input['period'] ) ) $args['period'] = $input['period'];

				$result = \SureCart\Models\Subscription::stats( $args );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'get-subscription-stats' );
		},
	));

	// ===== PREVIEW UPCOMING PERIOD =====
	$reg->read( 'surecart/preview-upcoming-period', array(
		'label'       => 'Preview SureCart Upcoming Period',
		'description' => 'Previews the next billing period for a subscription (amount, date, etc.).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Subscription ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Subscription::upcomingPeriod( array( 'id' => $input['id'] ) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'preview-upcoming-period' );
		},
	));

	// ===== GET PURCHASE =====
	$reg->read( 'surecart/get-purchase', array(
		'label'       => 'Get SureCart Purchase',
		'description' => 'Returns a single purchase (entitlement) by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Purchase ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$purchase = \SureCart\Models\Purchase::with( array( 'product', 'price', 'customer' ) )
					->find( $input['id'] );

				if ( is_wp_error( $purchase ) ) {
					return $purchase;
				}

				return abilities_for_ai_surecart_format_model( $purchase );
			}, 'get-purchase' );
		},
	));

	// ===== REVOKE PURCHASE =====
	$reg->write( 'surecart/revoke-purchase', array(
		'label'       => 'Revoke SureCart Purchase',
		'description' => 'Revokes a purchase, removing the customer\'s access to the product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Purchase ID to revoke.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Purchase::revoke( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'revoke-purchase' );
		},
	));

	// ===== INVOKE PURCHASE =====
	$reg->write( 'surecart/invoke-purchase', array(
		'label'       => 'Invoke SureCart Purchase',
		'description' => 'Invokes (re-activates) a previously revoked purchase.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Purchase ID to invoke.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Purchase::invoke( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'invoke-purchase' );
		},
	));

});
