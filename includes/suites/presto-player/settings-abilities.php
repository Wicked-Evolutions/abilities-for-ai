<?php
/**
 * Presto Player — Settings Abilities
 *
 * Read and update Presto Player global settings stored in wp_options
 * via the Setting model (presto_player_* prefix).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\Setting' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// Known setting groups from Presto Player source.
$known_groups = array(
	'branding',
	'analytics',
	'bunny_stream_public',
	'bunny_stream_private',
	'performance',
	'audio',
	'youtube',
);

// ===== GET SETTINGS (P0, free) =====
$reg->read( 'presto-player/get-settings', array(
	'label'       => 'Get Presto Player Settings',
	'description' => 'Returns all settings for a specific Presto Player settings group (e.g., branding, analytics, bunny_stream_public).',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'group' => array(
				'type'        => 'string',
				'description' => 'Settings group name (e.g., branding, analytics, bunny_stream_public, bunny_stream_private, performance, audio, youtube).',
			),
		),
		'required' => array( 'group' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$group    = sanitize_text_field( $input['group'] );
		$settings = \PrestoPlayer\Models\Setting::getGroup( $group );
		return array(
			'group'    => $group,
			'settings' => $settings ? $settings : new \stdClass(),
		);
	},
));

// ===== LIST SETTING GROUPS (P2, free) =====
$reg->read( 'presto-player/list-setting-groups', array(
	'label'       => 'List Presto Player Setting Groups',
	'description' => 'Lists all known Presto Player setting groups and their current values.',
	'callback'    => function( $input = null ) use ( $known_groups ) {
		$groups = array();
		foreach ( $known_groups as $group ) {
			$value = \PrestoPlayer\Models\Setting::getGroup( $group );
			$groups[] = array(
				'group'    => $group,
				'settings' => $value ? $value : new \stdClass(),
			);
		}
		return array(
			'total'  => count( $groups ),
			'groups' => $groups,
		);
	},
));

// ===== UPDATE SETTING (P2, pro) =====
$reg->write( 'presto-player/update-setting', array(
	'label'       => 'Update Presto Player Setting',
	'description' => 'Updates an individual setting within a Presto Player settings group.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'group' => array(
				'type'        => 'string',
				'description' => 'Settings group name.',
			),
			'name' => array(
				'type'        => 'string',
				'description' => 'Setting name within the group.',
			),
			'value' => array(
				'type'        => array( 'string', 'number', 'boolean', 'object', 'array' ),
				'description' => 'New value for the setting (string, number, boolean, object, or array).',
			),
		),
		'required' => array( 'group', 'name', 'value' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$group = sanitize_text_field( $input['group'] );
		$name  = sanitize_text_field( $input['name'] );
		$value = $input['value'];

		// Read current to verify group exists.
		$current = \PrestoPlayer\Models\Setting::getGroup( $group );

		\PrestoPlayer\Models\Setting::update( $group, $name, $value );

		// Read back updated value.
		$updated = \PrestoPlayer\Models\Setting::get( $group, $name );
		return array(
			'group'   => $group,
			'name'    => $name,
			'value'   => $updated,
			'message' => 'Setting updated successfully.',
		);
	},
));
