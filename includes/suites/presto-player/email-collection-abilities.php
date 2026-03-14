<?php
/**
 * Presto Player — Email Collection Abilities
 *
 * CRUD for Presto Player email collection gates. These are configured
 * per-preset and control email capture behaviour during video playback.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\EmailCollection' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== LIST EMAIL COLLECTIONS (P1, free) =====
$reg->read( 'presto-player/list-email-collections', array(
	'label'       => 'List Presto Player Email Collections',
	'description' => 'Returns all email collection gate configurations with their linked preset IDs.',
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
		$model  = new \PrestoPlayer\Models\EmailCollection();
		$result = $model->fetch( array(
			'per_page' => min( (int) ( $input['per_page'] ?? 20 ), 100 ),
			'page'     => max( (int) ( $input['page'] ?? 1 ), 1 ),
		));
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'total'             => (int) $result->total,
			'per_page'          => (int) $result->per_page,
			'page'              => (int) $result->page,
			'email_collections' => array_map( function( $ec ) {
				return $ec->toArray();
			}, $result->data ),
		);
	},
));

// ===== GET EMAIL COLLECTION (P2, free) =====
$reg->read( 'presto-player/get-email-collection', array(
	'label'       => 'Get Presto Player Email Collection',
	'description' => 'Returns a single email collection gate by ID.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Email collection ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$model = new \PrestoPlayer\Models\EmailCollection( $input['id'] );
		$data  = $model->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Email collection not found.', array( 'status' => 404 ) );
		}
		return $data;
	},
));

// ===== CREATE EMAIL COLLECTION (P2, pro) =====
$reg->write( 'presto-player/create-email-collection', array(
	'label'       => 'Create Presto Player Email Collection',
	'description' => 'Creates a new email collection gate linked to a video preset.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'enabled' => array(
				'type'        => 'boolean',
				'description' => 'Whether the email gate is active.',
				'default'     => true,
			),
			'behavior' => array(
				'type'        => 'string',
				'description' => 'Gate behaviour (e.g., gate, bottom).',
			),
			'percentage' => array(
				'type'        => 'integer',
				'description' => 'Percentage of video playback before gate appears (0-100).',
			),
			'allow_skip' => array(
				'type'        => 'boolean',
				'description' => 'Whether viewers can skip the email gate.',
			),
			'headline' => array(
				'type'        => 'string',
				'description' => 'Headline text shown on the email gate.',
			),
			'email_provider' => array(
				'type'        => 'string',
				'description' => 'Email provider integration (e.g., mailchimp, convertkit).',
			),
			'preset_id' => array(
				'type'        => 'integer',
				'description' => 'Associated video preset ID.',
			),
		),
	),
	'callback' => function( $input ) {
		$args = array();
		foreach ( $input as $key => $value ) {
			if ( is_string( $value ) ) {
				$args[ $key ] = sanitize_text_field( $value );
			} else {
				$args[ $key ] = $value;
			}
		}

		$model  = new \PrestoPlayer\Models\EmailCollection();
		$result = $model->createAndGet( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));

// ===== UPDATE EMAIL COLLECTION (P2, pro) =====
$reg->write( 'presto-player/update-email-collection', array(
	'label'       => 'Update Presto Player Email Collection',
	'description' => 'Updates an existing email collection gate configuration.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Email collection ID to update.',
			),
			'enabled' => array(
				'type'        => 'boolean',
				'description' => 'Whether the email gate is active.',
			),
			'behavior' => array(
				'type'        => 'string',
				'description' => 'Gate behaviour.',
			),
			'percentage' => array(
				'type'        => 'integer',
				'description' => 'Playback percentage before gate (0-100).',
			),
			'allow_skip' => array(
				'type'        => 'boolean',
				'description' => 'Whether viewers can skip.',
			),
			'headline' => array(
				'type'        => 'string',
				'description' => 'Headline text.',
			),
			'email_provider' => array(
				'type'        => 'string',
				'description' => 'Email provider integration.',
			),
			'preset_id' => array(
				'type'        => 'integer',
				'description' => 'Associated preset ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$model = new \PrestoPlayer\Models\EmailCollection( $input['id'] );
		$data  = $model->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Email collection not found.', array( 'status' => 404 ) );
		}

		$args = array();
		foreach ( $input as $key => $value ) {
			if ( $key === 'id' ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$args[ $key ] = sanitize_text_field( $value );
			} else {
				$args[ $key ] = $value;
			}
		}

		if ( empty( $args ) ) {
			return new \WP_Error( 'no_changes', 'No fields provided to update.', array( 'status' => 400 ) );
		}

		$result = $model->update( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));
