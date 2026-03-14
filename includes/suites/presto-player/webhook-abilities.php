<?php
/**
 * Presto Player — Webhook Abilities
 *
 * Read-only access to Presto Player webhook configurations.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\Webhook' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== LIST WEBHOOKS (P1, free) =====
$reg->read( 'presto-player/list-webhooks', array(
	'label'       => 'List Presto Player Webhooks',
	'description' => 'Returns all configured Presto Player webhooks with their URLs, methods, and headers.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'per_page' => array(
				'type'        => 'integer',
				'description' => 'Items per page (default 20, max 100).',
				'default'     => 20,
			),
			'page' => array(
				'type'        => 'integer',
				'description' => 'Page number.',
				'default'     => 1,
			),
		),
	),
	'callback' => function( $input ) {
		$model  = new \PrestoPlayer\Models\Webhook();
		$result = $model->fetch( array(
			'per_page' => min( (int) ( $input['per_page'] ?? 20 ), 100 ),
			'page'     => max( (int) ( $input['page'] ?? 1 ), 1 ),
		));
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'total'    => (int) $result->total,
			'per_page' => (int) $result->per_page,
			'page'     => (int) $result->page,
			'webhooks' => array_map( function( $w ) {
				return $w->toArray();
			}, $result->data ),
		);
	},
));

// ===== GET WEBHOOK (P2, free) =====
$reg->read( 'presto-player/get-webhook', array(
	'label'       => 'Get Presto Player Webhook',
	'description' => 'Returns a single webhook configuration by ID.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Webhook ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$model = new \PrestoPlayer\Models\Webhook( $input['id'] );
		$data  = $model->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Webhook not found.', array( 'status' => 404 ) );
		}
		return $data;
	},
));
