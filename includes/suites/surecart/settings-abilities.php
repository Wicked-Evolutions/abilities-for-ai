<?php
/**
 * SureCart Suite — Store Settings & Account Abilities
 *
 * Account, brand, protocols, and payment processors.
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

	// ===== GET ACCOUNT =====
	$reg->read( 'surecart/get-account', array(
		'label'       => 'Get SureCart Store Account',
		'description' => 'Returns the SureCart store account details (name, currency, plan, etc.).',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$account = \SureCart\Models\Account::find();

				if ( is_wp_error( $account ) ) {
					return $account;
				}

				return abilities_for_ai_surecart_format_model( $account );
			}, 'get-account' );
		},
	));

	// ===== GET BRAND =====
	$reg->read( 'surecart/get-brand', array(
		'label'       => 'Get SureCart Store Brand',
		'description' => 'Returns the store brand settings (logo, colors, etc.).',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$brand = \SureCart\Models\Brand::find();

				if ( is_wp_error( $brand ) ) {
					return $brand;
				}

				return abilities_for_ai_surecart_format_model( $brand );
			}, 'get-brand' );
		},
	));

	// ===== GET TAX PROTOCOL =====
	$reg->read( 'surecart/get-tax-protocol', array(
		'label'       => 'Get SureCart Tax Protocol',
		'description' => 'Returns the store tax configuration.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$protocol = \SureCart\Models\TaxProtocol::find();

				if ( is_wp_error( $protocol ) ) {
					return $protocol;
				}

				return abilities_for_ai_surecart_format_model( $protocol );
			}, 'get-tax-protocol' );
		},
	));

	// ===== GET SHIPPING PROTOCOL =====
	$reg->read( 'surecart/get-shipping-protocol', array(
		'label'       => 'Get SureCart Shipping Protocol',
		'description' => 'Returns the store shipping configuration.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$protocol = \SureCart\Models\ShippingProtocol::find();

				if ( is_wp_error( $protocol ) ) {
					return $protocol;
				}

				return abilities_for_ai_surecart_format_model( $protocol );
			}, 'get-shipping-protocol' );
		},
	));

	// ===== GET SUBSCRIPTION PROTOCOL =====
	$reg->read( 'surecart/get-subscription-protocol', array(
		'label'       => 'Get SureCart Subscription Protocol',
		'description' => 'Returns the store subscription management configuration.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$protocol = \SureCart\Models\SubscriptionProtocol::find();

				if ( is_wp_error( $protocol ) ) {
					return $protocol;
				}

				return abilities_for_ai_surecart_format_model( $protocol );
			}, 'get-subscription-protocol' );
		},
	));

	// ===== GET ORDER PROTOCOL =====
	$reg->read( 'surecart/get-order-protocol', array(
		'label'       => 'Get SureCart Order Protocol',
		'description' => 'Returns the store order management configuration.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$protocol = \SureCart\Models\OrderProtocol::find();

				if ( is_wp_error( $protocol ) ) {
					return $protocol;
				}

				return abilities_for_ai_surecart_format_model( $protocol );
			}, 'get-order-protocol' );
		},
	));

	// ===== GET CUSTOMER PORTAL PROTOCOL =====
	$reg->read( 'surecart/get-customer-portal-protocol', array(
		'label'       => 'Get SureCart Customer Portal Protocol',
		'description' => 'Returns the customer portal configuration.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$protocol = \SureCart\Models\CustomerPortalProtocol::find();

				if ( is_wp_error( $protocol ) ) {
					return $protocol;
				}

				return abilities_for_ai_surecart_format_model( $protocol );
			}, 'get-customer-portal-protocol' );
		},
	));

	// ===== LIST PROCESSORS =====
	$reg->read( 'surecart/list-processors', array(
		'label'       => 'List SureCart Payment Processors',
		'description' => 'Returns configured payment processors (Stripe, PayPal, etc.).',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\Processor::where( array() )->get();

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
			}, 'list-processors' );
		},
	));

});
