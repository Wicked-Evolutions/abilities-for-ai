<?php
/**
 * Comment Abilities
 *
 * WordPress comment management.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'comments', 'moderate_comments' );

	// ===== COMMENTS — READ =====

	$reg->read( 'comments/list', array(
		'label'       => 'List Comments',
		'description' => 'List comments with filtering, pagination, and search options',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Comments per page',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number',
					'default'     => 1,
					'minimum'     => 1,
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Filter by post ID',
				),
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'approve', 'hold', 'spam', 'trash', 'all' ),
					'default'     => 'approve',
					'description' => 'Comment status filter',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search in comment content and author name',
				),
				'orderby' => array(
					'type'        => 'string',
					'enum'        => array( 'comment_date', 'comment_date_gmt', 'comment_ID' ),
					'default'     => 'comment_date',
					'description' => 'Field to order by',
				),
				'order' => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'DESC',
					'description' => 'Sort order',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_list_output( 'comments', array(
			'id'      => array( 'type' => 'integer' ),
			'post_id' => array( 'type' => 'integer' ),
			'author'  => array( 'type' => 'string' ),
			'content' => array( 'type' => 'string' ),
			'date'    => array( 'type' => 'string' ),
			'status'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$per_page = min( $input['per_page'] ?? 20, 100 );
			$page     = $input['page'] ?? 1;

			$args = array(
				'number'  => $per_page,
				'offset'  => ( $page - 1 ) * $per_page,
				'orderby' => $input['orderby'] ?? 'comment_date',
				'order'   => $input['order'] ?? 'DESC',
			);

			$status = $input['status'] ?? 'approve';
			if ( $status !== 'all' ) {
				$args['status'] = $status;
			}

			if ( ! empty( $input['post_id'] ) ) {
				$args['post_id'] = (int) $input['post_id'];
			}

			if ( ! empty( $input['search'] ) ) {
				$args['search'] = $input['search'];
			}

			$comments_query = new WP_Comment_Query( $args );
			$comments       = $comments_query->comments;

			$count_args          = $args;
			$count_args['count'] = true;
			unset( $count_args['number'], $count_args['offset'] );
			$total = ( new WP_Comment_Query( $count_args ) )->get_comments();

			$result = array();
			foreach ( $comments as $comment ) {
				$result[] = array(
					'id'           => $comment->comment_ID,
					'post_id'      => $comment->comment_post_ID,
					'post_title'   => get_the_title( $comment->comment_post_ID ),
					'author'       => $comment->comment_author,
					'author_email' => $comment->comment_author_email,
					'author_url'   => $comment->comment_author_url,
					'content'      => $comment->comment_content,
					'date'         => $comment->comment_date,
					'status'       => wp_get_comment_status( $comment ),
					'parent'       => $comment->comment_parent,
					'user_id'      => $comment->user_id,
				);
			}

			return array(
				'comments' => $result,
				'total'    => (int) $total,
			);
		},
	) );

	$reg->read( 'comments/get', array(
		'label'       => 'Get Comment',
		'description' => 'Get detailed information about a specific comment by ID',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Comment ID' ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'      => array( 'type' => 'integer' ),
			'post_id' => array( 'type' => 'integer' ),
			'author'  => array( 'type' => 'string' ),
			'content' => array( 'type' => 'string' ),
			'date'    => array( 'type' => 'string' ),
			'status'  => array( 'type' => 'string' ),
			'parent'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$comment = get_comment( $input['id'] );
			if ( ! $comment ) {
				return new WP_Error( 'not_found', 'Comment not found' );
			}
			if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
				return new WP_Error( 'forbidden', 'You do not have permission to view this comment.' );
			}
			return array(
				'id'           => $comment->comment_ID,
				'post_id'      => $comment->comment_post_ID,
				'post_title'   => get_the_title( $comment->comment_post_ID ),
				'author'       => $comment->comment_author,
				'author_email' => $comment->comment_author_email,
				'author_url'   => $comment->comment_author_url,
				'content'      => $comment->comment_content,
				'date'         => $comment->comment_date,
				'date_gmt'     => $comment->comment_date_gmt,
				'status'       => wp_get_comment_status( $comment ),
				'parent'       => $comment->comment_parent,
				'user_id'      => $comment->user_id,
				'type'         => $comment->comment_type ?: 'comment',
			);
		},
	) );

	// ===== COMMENTS — WRITE =====

	// comments/create is free despite being a write operation.
	$reg->write( 'comments/create', array(
		'tier'        => 'free',
		'label'       => 'Create Comment',
		'description' => 'Create a new comment on a post',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id', 'content' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to comment on',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'Comment content',
				),
				'author' => array(
					'type'        => 'string',
					'description' => 'Author name (uses current user if not provided)',
				),
				'author_email' => array(
					'type'        => 'string',
					'description' => 'Author email',
				),
				'author_url' => array(
					'type'        => 'string',
					'description' => 'Author website URL',
				),
				'parent' => array(
					'type'        => 'integer',
					'default'     => 0,
					'description' => 'Parent comment ID for replies',
				),
				'approved' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Whether comment is approved',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id'     => array( 'type' => 'integer' ),
			'status' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			$post = get_post( $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'invalid_post', 'Post not found' );
			}
			$current_user = wp_get_current_user();
			$commentdata  = array(
				'comment_post_ID'      => $input['post_id'],
				'comment_content'      => wp_kses_post( $input['content'] ),
				'comment_author'       => $input['author'] ?? $current_user->display_name,
				'comment_author_email' => $input['author_email'] ?? $current_user->user_email,
				'comment_author_url'   => $input['author_url'] ?? '',
				'comment_parent'       => $input['parent'] ?? 0,
				'comment_approved'     => ( $input['approved'] ?? true ) ? 1 : 0,
				'user_id'              => $current_user->ID,
			);
			$comment_id = wp_insert_comment( $commentdata );
			if ( ! $comment_id ) {
				return new WP_Error( 'create_failed', 'Failed to create comment' );
			}
			return array(
				'id'     => $comment_id,
				'status' => wp_get_comment_status( $comment_id ),
			);
		},
	) );

	$reg->write( 'comments/update', array(
		'label'       => 'Update Comment',
		'description' => "Update an existing comment's content, author info, or status",
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Comment ID to update',
				),
				'content' => array(
					'type'        => 'string',
					'description' => 'New comment content',
				),
				'author' => array(
					'type'        => 'string',
					'description' => 'Author name',
				),
				'author_email' => array(
					'type'        => 'string',
					'description' => 'Author email',
				),
				'author_url' => array(
					'type'        => 'string',
					'description' => 'Author URL',
				),
				'status' => array(
					'type'        => 'string',
					'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
					'description' => 'Comment status',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$comment = get_comment( $input['id'] );
			if ( ! $comment ) {
				return new WP_Error( 'not_found', 'Comment not found' );
			}
			if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
				return new WP_Error( 'forbidden', 'You do not have permission to edit this comment.' );
			}
			$commentarr = array( 'comment_ID' => $input['id'] );
			if ( isset( $input['content'] ) ) {
				$commentarr['comment_content'] = wp_kses_post( $input['content'] );
			}
			if ( isset( $input['author'] ) ) {
				$commentarr['comment_author'] = sanitize_text_field( $input['author'] );
			}
			if ( isset( $input['author_email'] ) ) {
				$commentarr['comment_author_email'] = sanitize_email( $input['author_email'] );
			}
			if ( isset( $input['author_url'] ) ) {
				$commentarr['comment_author_url'] = esc_url_raw( $input['author_url'] );
			}
			if ( isset( $input['status'] ) ) {
				$status_map = array(
					'approve' => 1,
					'hold'    => 0,
					'spam'    => 'spam',
					'trash'   => 'trash',
				);
				$commentarr['comment_approved'] = $status_map[ $input['status'] ] ?? 0;
			}
			$result = wp_update_comment( $commentarr );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! $result ) {
				return new WP_Error( 'update_failed', 'Failed to update comment' );
			}
			return array( 'success' => true, 'id' => (int) $input['id'] );
		},
	) );

	// ===== COMMENTS — DELETE =====

	$reg->delete( 'comments/delete', array(
		'label'       => 'Delete Comment',
		'description' => 'Delete a comment permanently or move to trash',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Comment ID to delete',
				),
				'force' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Force permanent deletion (skip trash)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$comment = get_comment( $input['id'] );
			if ( ! $comment ) {
				return new WP_Error( 'not_found', 'Comment not found' );
			}
			if ( ! current_user_can( 'delete_comment', $comment->comment_ID ) ) {
				return new WP_Error( 'forbidden', 'You do not have permission to delete this comment.' );
			}
			$force  = $input['force'] ?? false;
			$result = wp_delete_comment( $input['id'], $force );
			if ( ! $result ) {
				return new WP_Error( 'delete_failed', 'Failed to delete comment' );
			}
			return array(
				'success' => true,
				'message' => $force ? 'Comment permanently deleted' : 'Comment moved to trash',
			);
		},
	) );
} );
