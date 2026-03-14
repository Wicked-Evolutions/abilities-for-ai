<?php
/**
 * Presto Player — Analytics Abilities (Pro)
 *
 * Video analytics via the Pro Visit model. All abilities in this file
 * require Presto Player Pro to be active (loaded conditionally in loader.php).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Pro\Models\Visit' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// Date range schema fragment reused across analytics abilities.
$date_range_props = array(
	'start' => array(
		'type'        => 'string',
		'description' => 'Start date (YYYY-MM-DD).',
	),
	'end' => array(
		'type'        => 'string',
		'description' => 'End date (YYYY-MM-DD).',
	),
);

$pagination_props = array(
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
);

// Helper to build sanitized date args.
$build_date_args = function( $input ) {
	$args = array();
	if ( ! empty( $input['start'] ) ) {
		$args['start'] = sanitize_text_field( $input['start'] );
	}
	if ( ! empty( $input['end'] ) ) {
		$args['end'] = sanitize_text_field( $input['end'] );
	}
	return $args;
};

// ===== TOP VIDEOS (P1, pro) =====
$reg->read( 'presto-player/top-videos', array(
	'tier'        => 'pro',
	'label'       => 'Get Top Presto Player Videos',
	'description' => 'Returns the most-viewed videos ranked by view count with average watch duration. Supports date range and pagination.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array_merge( $date_range_props, $pagination_props, array(
			'user_id' => array(
				'type'        => 'integer',
				'description' => 'Optional: filter by WordPress user ID.',
			),
		)),
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit = new \PrestoPlayer\Pro\Models\Visit();
		$args  = $build_date_args( $input );
		$args['per_page'] = min( (int) ( $input['per_page'] ?? 20 ), 100 );
		$args['page']     = max( (int) ( $input['page'] ?? 1 ), 1 );
		if ( ! empty( $input['user_id'] ) ) {
			$args['user_id'] = (int) $input['user_id'];
		}
		$result = $visit->topVideos( $args );
		return $result;
	},
));

// ===== TOP USERS (P1, pro) =====
$reg->read( 'presto-player/top-users', array(
	'tier'        => 'pro',
	'label'       => 'Get Top Presto Player Users',
	'description' => 'Returns the most engaged users ranked by view count with average watch duration.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array_merge( $date_range_props, $pagination_props ),
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit = new \PrestoPlayer\Pro\Models\Visit();
		$args  = $build_date_args( $input );
		$args['per_page'] = min( (int) ( $input['per_page'] ?? 20 ), 100 );
		$args['page']     = max( (int) ( $input['page'] ?? 1 ), 1 );
		$result = $visit->topUsers( $args );
		return $result;
	},
));

// ===== WATCH TIME BY DAY (P1, pro) =====
$reg->read( 'presto-player/watch-time-by-day', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player Watch Time by Day',
	'description' => 'Returns daily total watch time with an overall average. Useful for engagement trending.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => $date_range_props,
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit  = new \PrestoPlayer\Pro\Models\Visit();
		$args   = $build_date_args( $input );
		$result = $visit->totalWatchTimeByDay( $args );
		return $result;
	},
));

// ===== VIEWS BY DAY (P2, pro) =====
$reg->read( 'presto-player/views-by-day', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player Views by Day',
	'description' => 'Returns daily view counts for all videos.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => $date_range_props,
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit  = new \PrestoPlayer\Pro\Models\Visit();
		$args   = $build_date_args( $input );
		$result = $visit->totalViewsByDay( $args );
		return array( 'days' => $result );
	},
));

// ===== VIDEO TIMELINE (P2, pro) =====
$reg->read( 'presto-player/video-timeline', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player Video Timeline',
	'description' => 'Returns audience retention curve (dropoff by duration) for a specific video. Useful for identifying where viewers lose interest.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array_merge( $date_range_props, array(
			'video_id' => array(
				'type'        => 'integer',
				'description' => 'Video ID to get retention data for.',
			),
		)),
		'required' => array( 'video_id' ),
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit = new \PrestoPlayer\Pro\Models\Visit();
		$args  = $build_date_args( $input );
		$args['video_id'] = (int) $input['video_id'];
		$result = $visit->timeline( $args );
		return array(
			'video_id' => (int) $input['video_id'],
			'timeline' => $result,
		);
	},
));

// ===== USER VIDEO STATS (P2, pro) =====
$reg->read( 'presto-player/user-video-stats', array(
	'tier'        => 'pro',
	'label'       => 'Get Presto Player User Video Stats',
	'description' => 'Returns combined video stats for a specific user: total views, average watch time, and total watch time.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array_merge( $date_range_props, array(
			'user_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress user ID.',
			),
		)),
		'required' => array( 'user_id' ),
	),
	'callback' => function( $input ) use ( $build_date_args ) {
		$visit = new \PrestoPlayer\Pro\Models\Visit();
		$args  = $build_date_args( $input );
		$args['user_id'] = (int) $input['user_id'];
		return array(
			'user_id'              => (int) $input['user_id'],
			'total_views'          => (float) $visit->totalVideoViewsByUser( $args ),
			'average_watch_time'   => (float) $visit->videoAverageWatchTimeByUser( $args ),
			'total_watch_time'     => (float) $visit->videoTotalWatchTimeByUser( $args ),
		);
	},
));
