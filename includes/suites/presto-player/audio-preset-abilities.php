<?php
/**
 * Presto Player — Audio Preset Abilities
 *
 * CRUD for Presto Player audio presets. Audio presets control player
 * appearance for audio content (podcasts, meditations, etc.).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\AudioPreset' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== LIST AUDIO PRESETS (P0, free) =====
$reg->read( 'presto-player/list-audio-presets', array(
	'label'       => 'List Presto Player Audio Presets',
	'description' => 'Returns all audio presets with their control settings and styling options.',
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
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\AudioPreset();
		$result = $preset->fetch( array(
			'per_page' => min( (int) ( $input['per_page'] ?? 20 ), 100 ),
			'page'     => max( (int) ( $input['page'] ?? 1 ), 1 ),
		));
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'total'         => (int) $result->total,
			'per_page'      => (int) $result->per_page,
			'page'          => (int) $result->page,
			'audio_presets' => array_map( function( $p ) {
				return $p->toArray();
			}, $result->data ),
		);
	},
));

// ===== GET AUDIO PRESET (P1, free) =====
$reg->read( 'presto-player/get-audio-preset', array(
	'label'       => 'Get Presto Player Audio Preset',
	'description' => 'Returns a single audio preset by ID with all control and styling settings.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Audio preset ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\AudioPreset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Audio preset not found.', array( 'status' => 404 ) );
		}
		return $data;
	},
));

// ===== CREATE AUDIO PRESET (P1, pro) =====
$reg->write( 'presto-player/create-audio-preset', array(
	'label'       => 'Create Presto Player Audio Preset',
	'description' => 'Creates a new audio preset with player controls and styling. The name field is required.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'name' => array(
				'type'        => 'string',
				'description' => 'Preset name.',
			),
			'skin' => array(
				'type'        => 'string',
				'description' => 'Player skin identifier.',
			),
			'play' => array(
				'type'        => 'boolean',
				'description' => 'Show play button.',
			),
			'rewind' => array(
				'type'        => 'boolean',
				'description' => 'Show rewind button.',
			),
			'fast-forward' => array(
				'type'        => 'boolean',
				'description' => 'Show fast-forward button.',
			),
			'progress' => array(
				'type'        => 'boolean',
				'description' => 'Show progress bar.',
			),
			'current-time' => array(
				'type'        => 'boolean',
				'description' => 'Show current time.',
			),
			'mute' => array(
				'type'        => 'boolean',
				'description' => 'Show mute button.',
			),
			'volume' => array(
				'type'        => 'boolean',
				'description' => 'Show volume slider.',
			),
			'speed' => array(
				'type'        => 'boolean',
				'description' => 'Show speed control.',
			),
			'background_color' => array(
				'type'        => 'string',
				'description' => 'Background color (hex or CSS color).',
			),
			'control_color' => array(
				'type'        => 'string',
				'description' => 'Control color (hex or CSS color).',
			),
			'border_radius' => array(
				'type'        => 'integer',
				'description' => 'Player border radius in pixels.',
			),
			'cta' => array(
				'type'        => 'object',
				'description' => 'Call-to-action overlay configuration.',
			),
			'email_collection' => array(
				'type'        => 'object',
				'description' => 'Email collection gate configuration.',
			),
			'action_bar' => array(
				'type'        => 'object',
				'description' => 'Action bar configuration.',
			),
		),
		'required' => array( 'name' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$args = array();
		foreach ( $input as $key => $value ) {
			if ( is_string( $value ) ) {
				$args[ $key ] = sanitize_text_field( $value );
			} else {
				$args[ $key ] = $value;
			}
		}

		$preset = new \PrestoPlayer\Models\AudioPreset();
		$result = $preset->createAndGet( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));

// ===== UPDATE AUDIO PRESET (P1, pro) =====
$reg->write( 'presto-player/update-audio-preset', array(
	'label'       => 'Update Presto Player Audio Preset',
	'description' => 'Updates an existing audio preset. All fields are optional except id.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Audio preset ID to update.',
			),
			'name' => array(
				'type'        => 'string',
				'description' => 'New preset name.',
			),
			'skin' => array(
				'type'        => 'string',
				'description' => 'Player skin identifier.',
			),
			'play' => array(
				'type'        => 'boolean',
				'description' => 'Show play button.',
			),
			'rewind' => array(
				'type'        => 'boolean',
				'description' => 'Show rewind button.',
			),
			'fast-forward' => array(
				'type'        => 'boolean',
				'description' => 'Show fast-forward button.',
			),
			'progress' => array(
				'type'        => 'boolean',
				'description' => 'Show progress bar.',
			),
			'current-time' => array(
				'type'        => 'boolean',
				'description' => 'Show current time.',
			),
			'mute' => array(
				'type'        => 'boolean',
				'description' => 'Show mute button.',
			),
			'volume' => array(
				'type'        => 'boolean',
				'description' => 'Show volume slider.',
			),
			'speed' => array(
				'type'        => 'boolean',
				'description' => 'Show speed control.',
			),
			'background_color' => array(
				'type'        => 'string',
				'description' => 'Background color.',
			),
			'control_color' => array(
				'type'        => 'string',
				'description' => 'Control color.',
			),
			'border_radius' => array(
				'type'        => 'integer',
				'description' => 'Player border radius in pixels.',
			),
			'cta' => array(
				'type'        => 'object',
				'description' => 'Call-to-action overlay configuration.',
			),
			'email_collection' => array(
				'type'        => 'object',
				'description' => 'Email collection gate configuration.',
			),
			'action_bar' => array(
				'type'        => 'object',
				'description' => 'Action bar configuration.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\AudioPreset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Audio preset not found.', array( 'status' => 404 ) );
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

		$result = $preset->update( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));

// ===== DELETE AUDIO PRESET (P2, pro) =====
$reg->delete( 'presto-player/delete-audio-preset', array(
	'label'       => 'Delete Presto Player Audio Preset',
	'description' => 'Soft-deletes an audio preset.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Audio preset ID to delete.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\AudioPreset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Audio preset not found.', array( 'status' => 404 ) );
		}
		$preset->trash();
		return array(
			'deleted' => true,
			'id'      => (int) $input['id'],
			'message' => 'Audio preset soft-deleted successfully.',
		);
	},
));
