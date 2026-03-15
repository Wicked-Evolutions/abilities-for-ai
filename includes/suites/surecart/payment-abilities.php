<?php
/**
 * SureCart Suite — Payment Abilities
 *
 * Charges, refunds, payment intents, and invoices.
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

	// ===== LIST CHARGES =====
	$reg->read( 'surecart/list-charges', array(
		'label'       => 'List SureCart Charges',
		'description' => 'Returns a paginated list of charges.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'customer_id' => array( 'type' => 'string', 'description' => 'Filter by customer ID.' ),
					'status'      => array( 'type' => 'string', 'description' => 'Filter by status: succeeded, pending, failed.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['customer_id'] ) ) $query['customer_ids'] = array( $input['customer_id'] );
				if ( ! empty( $input['status'] ) )      $query['status']       = array( $input['status'] );

				$result = \SureCart\Models\Charge::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-charges' );
		},
	));

	// ===== GET CHARGE =====
	$reg->read( 'surecart/get-charge', array(
		'label'       => 'Get SureCart Charge',
		'description' => 'Returns a single charge by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Charge ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$charge = \SureCart\Models\Charge::find( $input['id'] );

				if ( is_wp_error( $charge ) ) {
					return $charge;
				}

				return abilities_for_ai_surecart_format_model( $charge );
			}, 'get-charge' );
		},
	));

	// ===== LIST REFUNDS =====
	$reg->read( 'surecart/list-refunds', array(
		'label'       => 'List SureCart Refunds',
		'description' => 'Returns a paginated list of refunds.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'charge_id' => array( 'type' => 'string', 'description' => 'Filter by charge ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['charge_id'] ) ) $query['charge_ids'] = array( $input['charge_id'] );

				$result = \SureCart\Models\Refund::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-refunds' );
		},
	));

	// ===== GET REFUND =====
	$reg->read( 'surecart/get-refund', array(
		'label'       => 'Get SureCart Refund',
		'description' => 'Returns a single refund by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Refund ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$refund = \SureCart\Models\Refund::find( $input['id'] );

				if ( is_wp_error( $refund ) ) {
					return $refund;
				}

				return abilities_for_ai_surecart_format_model( $refund );
			}, 'get-refund' );
		},
	));

	// ===== CREATE REFUND =====
	$reg->write( 'surecart/create-refund', array(
		'label'       => 'Create SureCart Refund',
		'description' => 'Creates a refund for a charge. This processes a payment refund.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'charge' => array( 'type' => 'string', 'description' => 'Charge ID to refund.' ),
				'amount' => array( 'type' => 'integer', 'description' => 'Amount to refund in cents. Omit for full refund.' ),
				'reason' => array( 'type' => 'string', 'description' => 'Reason for the refund.' ),
			),
			'required' => array( 'charge' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'charge' => $input['charge'] );
				if ( isset( $input['amount'] ) ) $attrs['amount'] = $input['amount'];
				if ( isset( $input['reason'] ) ) $attrs['reason'] = $input['reason'];

				$refund = \SureCart\Models\Refund::create( $attrs );

				if ( is_wp_error( $refund ) ) {
					return $refund;
				}

				return abilities_for_ai_surecart_format_model( $refund );
			}, 'create-refund' );
		},
	));

	// ===== LIST PAYMENT INTENTS =====
	$reg->read( 'surecart/list-payment-intents', array(
		'label'       => 'List SureCart Payment Intents',
		'description' => 'Returns a paginated list of payment intents.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => abilities_for_ai_surecart_pagination_schema(),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\PaymentIntent::where( array() )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-payment-intents' );
		},
	));

	// ===== LIST INVOICES =====
	$reg->read( 'surecart/list-invoices', array(
		'label'       => 'List SureCart Invoices',
		'description' => 'Returns a paginated list of invoices.',
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

				$result = \SureCart\Models\Invoice::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-invoices' );
		},
	));

	// ===== GET INVOICE =====
	$reg->read( 'surecart/get-invoice', array(
		'label'       => 'Get SureCart Invoice',
		'description' => 'Returns a single invoice by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Invoice ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$invoice = \SureCart\Models\Invoice::find( $input['id'] );

				if ( is_wp_error( $invoice ) ) {
					return $invoice;
				}

				return abilities_for_ai_surecart_format_model( $invoice );
			}, 'get-invoice' );
		},
	));

});
