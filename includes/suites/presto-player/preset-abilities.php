<?php
/**
 * Presto Player — Video Preset Abilities
 *
 * CRUD for Presto Player video presets. Presets control player appearance,
 * controls, CTA overlays, watermarks, and email collection gates.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\Preset' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== LIST PRESETS (P0, free) =====
$reg->read( 'presto-player/list-presets', array(
	'label'       => 'List Presto Player Video Presets',
	'description' => 'Returns all video presets with their control settings, CTA config, and branding options.',
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
		$preset = new \PrestoPlayer\Models\Preset();
		$result = $preset->fetch( array(
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
			'presets'  => array_map( function( $p ) {
				return $p->toArray();
			}, $result->data ),
		);
	},
));

// ===== GET PRESET (P1, free) =====
$reg->read( 'presto-player/get-preset', array(
	'label'       => 'Get Presto Player Video Preset',
	'description' => 'Returns a single video preset by ID with all control settings and serialized config fields (CTA, watermark, email collection, action bar).',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Preset ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\Preset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Preset not found.', array( 'status' => 404 ) );
		}
		return $data;
	},
));

// ===== CREATE PRESET (P1, pro) =====
$reg->write( 'presto-player/create-preset', array(
	'label'       => 'Create Presto Player Video Preset',
	'description' => 'Creates a new video preset with player controls and overlay configuration. The name field is required; slug is auto-generated if omitted.',
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
			'pip' => array(
				'type'        => 'boolean',
				'description' => 'Show picture-in-picture button.',
			),
			'fullscreen' => array(
				'type'        => 'boolean',
				'description' => 'Show fullscreen button.',
			),
			'captions' => array(
				'type'        => 'boolean',
				'description' => 'Show captions button.',
			),
			'hide_youtube' => array(
				'type'        => 'boolean',
				'description' => 'Hide YouTube branding.',
			),
			'lazy_load_youtube' => array(
				'type'        => 'boolean',
				'description' => 'Lazy load YouTube embeds.',
			),
			'border_radius' => array(
				'type'        => 'integer',
				'description' => 'Player border radius in pixels.',
			),
			'cta' => array(
				'type'        => 'object',
				'description' => 'Call-to-action overlay configuration (Presto Player preset format).',
			),
			'watermark' => array(
				'type'        => 'object',
				'description' => 'Watermark overlay configuration.',
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

		$preset = new \PrestoPlayer\Models\Preset();
		$result = $preset->createAndGet( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));

// ===== UPDATE PRESET (P1, pro) =====
$reg->write( 'presto-player/update-preset', array(
	'label'       => 'Update Presto Player Video Preset',
	'description' => 'Updates an existing video preset. All fields are optional except id.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Preset ID to update.',
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
			'fullscreen' => array(
				'type'        => 'boolean',
				'description' => 'Show fullscreen button.',
			),
			'captions' => array(
				'type'        => 'boolean',
				'description' => 'Show captions button.',
			),
			'border_radius' => array(
				'type'        => 'integer',
				'description' => 'Player border radius in pixels.',
			),
			'cta' => array(
				'type'        => 'object',
				'description' => 'Call-to-action overlay configuration.',
			),
			'watermark' => array(
				'type'        => 'object',
				'description' => 'Watermark overlay configuration.',
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
		$preset = new \PrestoPlayer\Models\Preset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Preset not found.', array( 'status' => 404 ) );
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

// ===== DELETE PRESET (P2, pro) =====
$reg->delete( 'presto-player/delete-preset', array(
	'label'       => 'Delete Presto Player Video Preset',
	'description' => 'Soft-deletes a video preset.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Preset ID to delete.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$preset = new \PrestoPlayer\Models\Preset( $input['id'] );
		$data   = $preset->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Preset not found.', array( 'status' => 404 ) );
		}
		$preset->trash();
		return array(
			'deleted' => true,
			'id'      => (int) $input['id'],
			'message' => 'Preset soft-deleted successfully.',
		);
	},
));
