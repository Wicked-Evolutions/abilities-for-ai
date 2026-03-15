<?php
/**
 * SureCart Suite — Webhook Abilities
 *
 * Webhook endpoint management.
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

	// ===== LIST WEBHOOKS =====
	$reg->read( 'surecart/list-webhooks', array(
		'label'       => 'List SureCart Webhooks',
		'description' => 'Returns configured webhook endpoints.',
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() {
				$result = \SureCart\Models\Webhook::where( array() )->get();

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
			}, 'list-webhooks' );
		},
	));

	// ===== GET WEBHOOK =====
	$reg->read( 'surecart/get-webhook', array(
		'label'       => 'Get SureCart Webhook',
		'description' => 'Returns a single webhook endpoint by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Webhook ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$webhook = \SureCart\Models\Webhook::find( $input['id'] );

				if ( is_wp_error( $webhook ) ) {
					return $webhook;
				}

				return abilities_for_ai_surecart_format_model( $webhook );
			}, 'get-webhook' );
		},
	));

	// ===== CREATE WEBHOOK =====
	$reg->write( 'surecart/create-webhook', array(
		'label'       => 'Create SureCart Webhook',
		'description' => 'Creates a new webhook endpoint.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'url'    => array( 'type' => 'string', 'description' => 'Webhook endpoint URL.' ),
				'events' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Events to subscribe to (e.g. order.paid, subscription.created).',
				),
			),
			'required' => array( 'url', 'events' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array(
					'url'    => $input['url'],
					'events' => $input['events'],
				);

				$webhook = \SureCart\Models\Webhook::create( $attrs );

				if ( is_wp_error( $webhook ) ) {
					return $webhook;
				}

				return abilities_for_ai_surecart_format_model( $webhook );
			}, 'create-webhook' );
		},
	));

	// ===== DELETE WEBHOOK =====
	$reg->delete( 'surecart/delete-webhook', array(
		'label'       => 'Delete SureCart Webhook',
		'description' => 'Deletes a webhook endpoint. This action cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Webhook ID to delete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Webhook::delete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array( 'deleted' => true, 'id' => $input['id'] );
			}, 'delete-webhook' );
		},
	));

});
