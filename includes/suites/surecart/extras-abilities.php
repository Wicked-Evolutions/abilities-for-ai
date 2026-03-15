<?php
/**
 * SureCart Suite — Extras Abilities
 *
 * Upsell funnels, order bumps, affiliations, referrals, and statistics.
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

	// ===== LIST UPSELL FUNNELS =====
	$reg->read( 'surecart/list-upsell-funnels', array(
		'label'       => 'List SureCart Upsell Funnels',
		'description' => 'Returns configured upsell funnels.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\UpsellFunnel::where( array() )->get();

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
			}, 'list-upsell-funnels' );
		},
	));

	// ===== LIST BUMPS =====
	$reg->read( 'surecart/list-bumps', array(
		'label'       => 'List SureCart Order Bumps',
		'description' => 'Returns configured order bumps.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\Bump::where( array() )->get();

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
			}, 'list-bumps' );
		},
	));

	// ===== GET STATISTICS =====
	$reg->read( 'surecart/get-statistics', array(
		'label'       => 'Get SureCart Statistics',
		'description' => 'Returns aggregate store statistics (revenue, orders, customers, etc.).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'type'   => array( 'type' => 'string', 'description' => 'Statistics type: revenue, orders, customers, subscriptions.' ),
				'period' => array( 'type' => 'string', 'description' => 'Time period: today, week, month, year, all_time. Default: month.' ),
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$args = array();
				if ( ! empty( $input['period'] ) ) $args['period'] = $input['period'];

				$type   = ! empty( $input['type'] ) ? $input['type'] : 'revenue';
				$result = \SureCart\Models\Statistic::where( $args )->find( $type );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_model( $result );
			}, 'get-statistics' );
		},
	));

	// ===== LIST AFFILIATIONS =====
	$reg->read( 'surecart/list-affiliations', array(
		'label'       => 'List SureCart Affiliations',
		'description' => 'Returns a paginated list of affiliate accounts.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => abilities_for_ai_surecart_pagination_schema(),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Affiliation::where( array() )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-affiliations' );
		},
	));

	// ===== LIST REFERRALS =====
	$reg->read( 'surecart/list-referrals', array(
		'label'       => 'List SureCart Referrals',
		'description' => 'Returns a paginated list of affiliate referrals.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'affiliation_id' => array( 'type' => 'string', 'description' => 'Filter by affiliation ID.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['affiliation_id'] ) ) $query['affiliation_ids'] = array( $input['affiliation_id'] );

				$result = \SureCart\Models\Referral::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-referrals' );
		},
	));

});
