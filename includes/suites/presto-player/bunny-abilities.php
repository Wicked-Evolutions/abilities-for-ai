<?php
/**
 * Presto Player — Bunny CDN Abilities (Pro)
 *
 * Read-only inspection of Bunny CDN streaming configuration.
 * Loaded only when Presto Player Pro is active (see loader.php).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\Setting' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== GET BUNNY SETTINGS (P1, pro) =====
$reg->read( 'presto-player/get-bunny-settings', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player Bunny CDN Settings',
	'description' => 'Returns the Bunny CDN streaming configuration including library IDs, pull zone, and hostname. Requires Presto Player Pro.',
	'callback'    => function( $input = null ) {
		$public  = \PrestoPlayer\Models\Setting::getGroup( 'bunny_stream_public' );
		$private = \PrestoPlayer\Models\Setting::getGroup( 'bunny_stream_private' );
		return array(
			'public'  => $public ? $public : new \stdClass(),
			'private' => $private ? $private : new \stdClass(),
		);
	},
));

// ===== GET BUNNY STATUS (P2, pro) =====
$reg->read( 'presto-player/get-bunny-status', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player Bunny CDN Status',
	'description' => 'Checks whether Bunny CDN streaming is configured by verifying required settings exist.',
	'callback'    => function( $input = null ) {
		$public = \PrestoPlayer\Models\Setting::getGroup( 'bunny_stream_public' );

		$has_library_id  = ! empty( $public['video_library_id'] ?? '' );
		$has_api_key     = ! empty( $public['video_library_api_key'] ?? '' );
		$has_pull_zone   = ! empty( $public['pull_zone_id'] ?? '' );
		$has_pull_url    = ! empty( $public['pull_zone_url'] ?? '' );

		$configured = $has_library_id && $has_api_key && $has_pull_zone;

		return array(
			'configured'       => $configured,
			'has_library_id'   => $has_library_id,
			'has_api_key'      => $has_api_key,
			'has_pull_zone'    => $has_pull_zone,
			'has_pull_zone_url' => $has_pull_url,
			'message'          => $configured
				? 'Bunny CDN streaming is configured and ready.'
				: 'Bunny CDN streaming is not fully configured. Check video_library_id, video_library_api_key, and pull_zone_id.',
		);
	},
));
