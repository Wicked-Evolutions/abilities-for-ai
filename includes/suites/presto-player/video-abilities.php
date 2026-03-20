<?php
/**
 * Presto Player — Video Abilities
 *
 * CRUD for Presto Player videos. Uses the Video model (custom table).
 * get-video-stats requires Pro plugin (Visit model).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'PrestoPlayer\Models\Video' ) ) {
	return;
}

$reg = new Abilities_For_AI_Registrar( 'presto-player', 'manage_options' );

// ===== LIST VIDEOS (P0, free) =====
$reg->read( 'presto-player/list-videos', array(
	'label'       => 'List Presto Player Videos',
	'description' => 'Returns a paginated list of all Presto Player videos with optional type filtering.',
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
			'type' => array(
				'type'        => 'string',
				'description' => 'Filter by video type.',
				'enum'        => array( 'self-hosted', 'youtube', 'vimeo', 'bunny' ),
			),
		),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$video = new \PrestoPlayer\Models\Video();
		$args  = array(
			'per_page' => min( (int) ( $input['per_page'] ?? 20 ), 100 ),
			'page'     => max( (int) ( $input['page'] ?? 1 ), 1 ),
		);
		if ( ! empty( $input['type'] ) ) {
			$args['type'] = sanitize_text_field( $input['type'] );
		}
		$result = $video->fetch( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'total'    => (int) $result->total,
			'per_page' => (int) $result->per_page,
			'page'     => (int) $result->page,
			'videos'   => array_map( function( $v ) {
				return $v->toArray();
			}, $result->data ),
		);
	},
));

// ===== GET VIDEO (P0, free) =====
$reg->read( 'presto-player/get-video', array(
	'label'       => 'Get Presto Player Video',
	'description' => 'Returns a single Presto Player video by ID with full metadata.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Video ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$video = new \PrestoPlayer\Models\Video( $input['id'] );
		$data  = $video->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Video not found.', array( 'status' => 404 ) );
		}
		return $data;
	},
));

// ===== CREATE VIDEO (P1, pro) =====
$reg->write( 'presto-player/create-video', array(
	'label'       => 'Create Presto Player Video',
	'description' => 'Creates a new video entry in Presto Player. Provide a source URL, attachment ID, or external ID depending on type.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'title' => array(
				'type'        => 'string',
				'description' => 'Video title.',
			),
			'type' => array(
				'type'        => 'string',
				'description' => 'Video type.',
				'enum'        => array( 'self-hosted', 'youtube', 'vimeo', 'bunny' ),
			),
			'src' => array(
				'type'        => 'string',
				'description' => 'Video source URL (for YouTube, Vimeo, or self-hosted).',
			),
			'attachment_id' => array(
				'type'        => 'integer',
				'description' => 'WordPress media attachment ID (for self-hosted videos).',
			),
			'external_id' => array(
				'type'        => 'string',
				'description' => 'External video ID/GUID (for Bunny CDN).',
			),
			'post_id' => array(
				'type'        => 'integer',
				'description' => 'Associated WordPress post ID.',
			),
		),
		'required' => array( 'title', 'type' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$args = array(
			'title' => sanitize_text_field( $input['title'] ),
			'type'  => sanitize_text_field( $input['type'] ),
		);
		if ( isset( $input['src'] ) ) {
			$args['src'] = esc_url_raw( $input['src'] );
		}
		if ( isset( $input['attachment_id'] ) ) {
			$args['attachment_id'] = (int) $input['attachment_id'];
		}
		if ( isset( $input['external_id'] ) ) {
			$args['external_id'] = sanitize_text_field( $input['external_id'] );
		}
		if ( isset( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}

		$video  = new \PrestoPlayer\Models\Video();
		$result = $video->create( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// create() returns the insert ID — fetch the full record.
		$created = new \PrestoPlayer\Models\Video( $result );
		$data    = $created->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'create_failed', 'Video was inserted but could not be retrieved.', array( 'status' => 500 ) );
		}
		return $data;
	},
));

// ===== UPDATE VIDEO (P1, pro) =====
$reg->write( 'presto-player/update-video', array(
	'label'       => 'Update Presto Player Video',
	'description' => 'Updates an existing Presto Player video entry.',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Video ID to update.',
			),
			'title' => array(
				'type'        => 'string',
				'description' => 'New video title.',
			),
			'type' => array(
				'type'        => 'string',
				'description' => 'New video type.',
				'enum'        => array( 'self-hosted', 'youtube', 'vimeo', 'bunny' ),
			),
			'src' => array(
				'type'        => 'string',
				'description' => 'New video source URL.',
			),
			'attachment_id' => array(
				'type'        => 'integer',
				'description' => 'New WordPress media attachment ID.',
			),
			'external_id' => array(
				'type'        => 'string',
				'description' => 'New external video ID/GUID.',
			),
			'post_id' => array(
				'type'        => 'integer',
				'description' => 'New associated WordPress post ID.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$video = new \PrestoPlayer\Models\Video( $input['id'] );
		$data  = $video->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Video not found.', array( 'status' => 404 ) );
		}

		$args = array();
		if ( isset( $input['title'] ) ) {
			$args['title'] = sanitize_text_field( $input['title'] );
		}
		if ( isset( $input['type'] ) ) {
			$args['type'] = sanitize_text_field( $input['type'] );
		}
		if ( isset( $input['src'] ) ) {
			$args['src'] = esc_url_raw( $input['src'] );
		}
		if ( isset( $input['attachment_id'] ) ) {
			$args['attachment_id'] = (int) $input['attachment_id'];
		}
		if ( isset( $input['external_id'] ) ) {
			$args['external_id'] = sanitize_text_field( $input['external_id'] );
		}
		if ( isset( $input['post_id'] ) ) {
			$args['post_id'] = (int) $input['post_id'];
		}

		if ( empty( $args ) ) {
			return new \WP_Error( 'no_changes', 'No fields provided to update.', array( 'status' => 400 ) );
		}

		$result = $video->update( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result->toArray();
	},
));

// ===== DELETE VIDEO (P2, pro) =====
$reg->delete( 'presto-player/delete-video', array(
	'label'       => 'Delete Presto Player Video',
	'description' => 'Soft-deletes a Presto Player video entry (sets deleted_at timestamp).',
	'input_schema' => array(
		'type'       => 'object',
		'properties' => array(
			'id' => array(
				'type'        => 'integer',
				'description' => 'Video ID to delete.',
			),
		),
		'required' => array( 'id' ),
	),
	'callback' => function( $input ) {
		$input = (array) $input;
		$video = new \PrestoPlayer\Models\Video( $input['id'] );
		$data  = $video->toArray();
		if ( empty( $data ) || empty( $data['id'] ) ) {
			return new \WP_Error( 'not_found', 'Video not found.', array( 'status' => 404 ) );
		}
		$video->trash();
		return array(
			'deleted' => true,
			'id'      => (int) $input['id'],
			'message' => 'Video soft-deleted successfully.',
		);
	},
));

// ===== GET VIDEO STATS (P1, pro — requires Pro Visit model) =====
if ( class_exists( 'PrestoPlayer\Pro\Models\Visit' ) ) {
	$reg->read( 'presto-player/get-video-stats', array(
		'tier'        => 'pro',
		'label'       => 'Get Presto Player Video Stats',
		'description' => 'Returns view count and average watch time for a specific video. Requires Presto Player Pro.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'video_id' => array(
					'type'        => 'integer',
					'description' => 'Video ID to get stats for.',
				),
				'start' => array(
					'type'        => 'string',
					'description' => 'Start date for date range filter (YYYY-MM-DD).',
				),
				'end' => array(
					'type'        => 'string',
					'description' => 'End date for date range filter (YYYY-MM-DD).',
				),
			),
			'required' => array( 'video_id' ),
		),
		'callback' => function( $input ) {
			$input = (array) $input;
			$visit = new \PrestoPlayer\Pro\Models\Visit();
			$args  = array( 'video_id' => (int) $input['video_id'] );
			if ( ! empty( $input['start'] ) ) {
				$args['start'] = sanitize_text_field( $input['start'] );
			}
			if ( ! empty( $input['end'] ) ) {
				$args['end'] = sanitize_text_field( $input['end'] );
			}
			return array(
				'video_id'           => (int) $input['video_id'],
				'views'              => (int) $visit->views( $args ),
				'average_watch_time' => (float) $visit->averageWatchTime( $args ),
			);
		},
	));
}
