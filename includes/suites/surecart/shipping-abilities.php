<?php
/**
 * SureCart Suite — Shipping Abilities
 *
 * Shipping zones, methods, rates, and profiles.
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

	// ===== LIST SHIPPING ZONES =====
	$reg->read( 'surecart/list-shipping-zones', array(
		'label'       => 'List SureCart Shipping Zones',
		'description' => 'Returns configured shipping zones.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\ShippingZone::where( array() )->get();

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
			}, 'list-shipping-zones' );
		},
	));

	// ===== LIST SHIPPING METHODS =====
	$reg->read( 'surecart/list-shipping-methods', array(
		'label'       => 'List SureCart Shipping Methods',
		'description' => 'Returns available shipping methods.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\ShippingMethod::where( array() )->get();

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
			}, 'list-shipping-methods' );
		},
	));

	// ===== LIST SHIPPING RATES =====
	$reg->read( 'surecart/list-shipping-rates', array(
		'label'       => 'List SureCart Shipping Rates',
		'description' => 'Returns configured shipping rates.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\ShippingRate::where( array() )->get();

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
			}, 'list-shipping-rates' );
		},
	));

	// ===== LIST SHIPPING PROFILES =====
	$reg->read( 'surecart/list-shipping-profiles', array(
		'label'       => 'List SureCart Shipping Profiles',
		'description' => 'Returns configured shipping profiles.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\ShippingProfile::where( array() )->get();

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
			}, 'list-shipping-profiles' );
		},
	));

});
