<?php
/**
 * SureCart Suite — Coupon & Promotion Abilities
 *
 * Coupons and promotions.
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

	// ===== LIST COUPONS =====
	$reg->read( 'surecart/list-coupons', array(
		'label'       => 'List SureCart Coupons',
		'description' => 'Returns a paginated list of coupons.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'query' => array( 'type' => 'string', 'description' => 'Search query.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['query'] ) ) $query['query'] = $input['query'];

				$result = \SureCart\Models\Coupon::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-coupons' );
		},
	));

	// ===== GET COUPON =====
	$reg->read( 'surecart/get-coupon', array(
		'label'       => 'Get SureCart Coupon',
		'description' => 'Returns a single coupon by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Coupon ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$coupon = \SureCart\Models\Coupon::find( $input['id'] );

				if ( is_wp_error( $coupon ) ) {
					return $coupon;
				}

				return abilities_for_ai_surecart_format_model( $coupon );
			}, 'get-coupon' );
		},
	));

	// ===== CREATE COUPON =====
	$reg->write( 'surecart/create-coupon', array(
		'label'       => 'Create SureCart Coupon',
		'description' => 'Creates a new coupon.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'               => array( 'type' => 'string', 'description' => 'Coupon name/code.' ),
				'percent_off'        => array( 'type' => 'number', 'description' => 'Percentage discount (0-100). Use this OR amount_off, not both.' ),
				'amount_off'         => array( 'type' => 'integer', 'description' => 'Fixed amount discount in cents. Use this OR percent_off, not both.' ),
				'duration'           => array( 'type' => 'string', 'description' => 'Duration: once, repeating, forever.' ),
				'duration_in_months' => array( 'type' => 'integer', 'description' => 'Number of months (when duration is repeating).' ),
				'max_redemptions'    => array( 'type' => 'integer', 'description' => 'Maximum number of times this coupon can be redeemed.' ),
				'redeem_by'          => array( 'type' => 'integer', 'description' => 'Unix timestamp after which the coupon can no longer be redeemed.' ),
			),
			'required' => array( 'name' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'name' => $input['name'] );
				if ( isset( $input['percent_off'] ) )        $attrs['percent_off']        = $input['percent_off'];
				if ( isset( $input['amount_off'] ) )         $attrs['amount_off']         = $input['amount_off'];
				if ( isset( $input['duration'] ) )           $attrs['duration']           = $input['duration'];
				if ( isset( $input['duration_in_months'] ) ) $attrs['duration_in_months'] = $input['duration_in_months'];
				if ( isset( $input['max_redemptions'] ) )    $attrs['max_redemptions']    = $input['max_redemptions'];
				if ( isset( $input['redeem_by'] ) )          $attrs['redeem_by']          = $input['redeem_by'];

				$coupon = \SureCart\Models\Coupon::create( $attrs );

				if ( is_wp_error( $coupon ) ) {
					return $coupon;
				}

				return abilities_for_ai_surecart_format_model( $coupon );
			}, 'create-coupon' );
		},
	));

	// ===== UPDATE COUPON =====
	$reg->write( 'surecart/update-coupon', array(
		'label'       => 'Update SureCart Coupon',
		'description' => 'Updates an existing coupon.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'              => array( 'type' => 'string', 'description' => 'Coupon ID.' ),
				'name'            => array( 'type' => 'string', 'description' => 'Coupon name/code.' ),
				'max_redemptions' => array( 'type' => 'integer', 'description' => 'Maximum number of redemptions.' ),
				'redeem_by'       => array( 'type' => 'integer', 'description' => 'Unix timestamp expiration.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'id' => $input['id'] );
				if ( isset( $input['name'] ) )            $attrs['name']            = $input['name'];
				if ( isset( $input['max_redemptions'] ) ) $attrs['max_redemptions'] = $input['max_redemptions'];
				if ( isset( $input['redeem_by'] ) )       $attrs['redeem_by']       = $input['redeem_by'];

				$coupon = \SureCart\Models\Coupon::update( $attrs );

				if ( is_wp_error( $coupon ) ) {
					return $coupon;
				}

				return abilities_for_ai_surecart_format_model( $coupon );
			}, 'update-coupon' );
		},
	));

	// ===== DELETE COUPON =====
	$reg->delete( 'surecart/delete-coupon', array(
		'label'       => 'Delete SureCart Coupon',
		'description' => 'Deletes a coupon. This action cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Coupon ID to delete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Coupon::delete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array( 'deleted' => true, 'id' => $input['id'] );
			}, 'delete-coupon' );
		},
	));

	// ===== LIST PROMOTIONS =====
	$reg->read( 'surecart/list-promotions', array(
		'label'       => 'List SureCart Promotions',
		'description' => 'Returns a paginated list of promotions.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'coupon_id' => array( 'type' => 'string', 'description' => 'Filter by coupon ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['coupon_id'] ) ) $query['coupon_ids'] = array( $input['coupon_id'] );

				$result = \SureCart\Models\Promotion::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-promotions' );
		},
	));

	// ===== GET PROMOTION =====
	$reg->read( 'surecart/get-promotion', array(
		'label'       => 'Get SureCart Promotion',
		'description' => 'Returns a single promotion by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Promotion ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$promotion = \SureCart\Models\Promotion::find( $input['id'] );

				if ( is_wp_error( $promotion ) ) {
					return $promotion;
				}

				return abilities_for_ai_surecart_format_model( $promotion );
			}, 'get-promotion' );
		},
	));

	// ===== CREATE PROMOTION =====
	$reg->write( 'surecart/create-promotion', array(
		'label'       => 'Create SureCart Promotion',
		'description' => 'Creates a new promotion linked to a coupon.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'coupon'     => array( 'type' => 'string', 'description' => 'Coupon ID to create the promotion for.' ),
				'code'       => array( 'type' => 'string', 'description' => 'Promotion code customers will enter.' ),
				'name'       => array( 'type' => 'string', 'description' => 'Promotion name.' ),
			),
			'required' => array( 'coupon', 'code' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array(
					'coupon' => $input['coupon'],
					'code'   => $input['code'],
				);
				if ( isset( $input['name'] ) ) $attrs['name'] = $input['name'];

				$promotion = \SureCart\Models\Promotion::create( $attrs );

				if ( is_wp_error( $promotion ) ) {
					return $promotion;
				}

				return abilities_for_ai_surecart_format_model( $promotion );
			}, 'create-promotion' );
		},
	));

});
