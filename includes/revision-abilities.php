<?php
/**
 * Revision Abilities
 *
 * WordPress post revision management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'revisions', 'edit_posts' );

	// ===== REVISIONS — READ =====

	$reg->read( 'revisions/list', array(
		'label'       => 'List Revisions',
		'description' => 'List all revisions for a post, ordered by date (newest first)',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to list revisions for',
				),
			),
		),
		'callback' => function( $input ) {
			$post = get_post( (int) $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found' );
			}

			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				return new WP_Error( 'forbidden', 'You cannot edit this post' );
			}

			$revisions = wp_get_post_revisions( $post->ID, array( 'order' => 'DESC' ) );

			$items = array();
			foreach ( $revisions as $rev ) {
				$items[] = array(
					'id'           => (int) $rev->ID,
					'author'       => (int) $rev->post_author,
					'author_name'  => (string) get_the_author_meta( 'display_name', $rev->post_author ),
					'date'         => (string) $rev->post_date,
					'date_gmt'     => (string) $rev->post_date_gmt,
					'title'        => (string) $rev->post_title,
					'excerpt'      => (string) wp_trim_words( wp_strip_all_tags( $rev->post_content ), 30, '...' ),
				);
			}

			return array(
				'post_id'   => (int) $post->ID,
				'revisions' => $items,
				'total'     => count( $items ),
			);
		},
	) );

	$reg->read( 'revisions/get', array(
		'label'       => 'Get Revision',
		'description' => 'Get a specific revision with full content, useful for comparing or restoring',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'revision_id' ),
			'properties' => array(
				'revision_id' => array(
					'type'        => 'integer',
					'description' => 'Revision ID',
				),
			),
		),
		'callback' => function( $input ) {
			$rev_id   = (int) $input['revision_id'];
			$revision = wp_get_post_revision( $rev_id );
			if ( ! $revision ) {
				return new WP_Error( 'not_found', 'Revision not found' );
			}

			$parent = get_post( $revision->post_parent );
			if ( ! $parent || ! current_user_can( 'edit_post', $parent->ID ) ) {
				return new WP_Error( 'forbidden', 'You cannot access this revision' );
			}

			return array(
				'id'           => (int) $revision->ID,
				'parent_id'    => (int) $revision->post_parent,
				'author'       => (int) $revision->post_author,
				'author_name'  => (string) get_the_author_meta( 'display_name', $revision->post_author ),
				'date'         => (string) $revision->post_date,
				'date_gmt'     => (string) $revision->post_date_gmt,
				'title'        => (string) $revision->post_title,
				'content'      => (string) $revision->post_content,
				'excerpt'      => (string) $revision->post_excerpt,
			);
		},
	) );

	// ===== REVISIONS — WRITE =====

	$reg->write( 'revisions/restore', array(
		'capability'  => 'edit_posts',
		'label'       => 'Restore Revision',
		'description' => 'Restore a post to a specific revision. The current state is saved as a new revision before restoring.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'revision_id' ),
			'properties' => array(
				'revision_id' => array(
					'type'        => 'integer',
					'description' => 'Revision ID to restore',
				),
			),
		),
		'callback' => function( $input ) {
			$rev_id   = (int) $input['revision_id'];
			$revision = wp_get_post_revision( $rev_id );
			if ( ! $revision ) {
				return new WP_Error( 'not_found', 'Revision not found' );
			}

			$parent = get_post( $revision->post_parent );
			if ( ! $parent || ! current_user_can( 'edit_post', $parent->ID ) ) {
				return new WP_Error( 'forbidden', 'You cannot edit this post' );
			}

			$restore_id = $revision->ID;
			$restored   = wp_restore_post_revision( $restore_id );
			if ( ! $restored || is_wp_error( $restored ) ) {
				return new WP_Error( 'restore_failed', 'Failed to restore revision' );
			}

			return array(
				'success'      => true,
				'post_id'      => (int) $parent->ID,
				'restored_from' => (int) $revision->ID,
				'restored_date' => (string) $revision->post_date,
			);
		},
	) );

	// ===== REVISIONS — DELETE =====

	$reg->delete( 'revisions/delete', array(
		'capability'  => 'delete_posts',
		'label'       => 'Delete Revision',
		'description' => 'Permanently delete a specific revision',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'revision_id' ),
			'properties' => array(
				'revision_id' => array(
					'type'        => 'integer',
					'description' => 'Revision ID to delete',
				),
			),
		),
		'callback' => function( $input ) {
			$rev_id   = (int) $input['revision_id'];
			$revision = wp_get_post_revision( $rev_id );
			if ( ! $revision ) {
				return new WP_Error( 'not_found', 'Revision not found' );
			}

			$parent = get_post( $revision->post_parent );
			if ( ! $parent || ! current_user_can( 'delete_post', $parent->ID ) ) {
				return new WP_Error( 'forbidden', 'You cannot delete revisions for this post' );
			}

			$del_id  = $revision->ID;
			$deleted = wp_delete_post_revision( $del_id );
			if ( ! $deleted || is_wp_error( $deleted ) ) {
				return new WP_Error( 'delete_failed', 'Failed to delete revision' );
			}

			return array(
				'success'     => true,
				'deleted_id'  => (int) $del_id,
				'parent_id'   => (int) $parent->ID,
			);
		},
	) );

	$reg->delete( 'revisions/purge', array(
		'capability'  => 'delete_posts',
		'label'       => 'Purge All Revisions',
		'description' => 'Delete all revisions for a specific post. Keeps the current version intact.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'post_id' ),
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Post ID to purge revisions for',
				),
			),
		),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$post = get_post( (int) $input['post_id'] );
			if ( ! $post ) {
				return new WP_Error( 'not_found', 'Post not found' );
			}

			if ( ! current_user_can( 'delete_post', $post->ID ) ) {
				return new WP_Error( 'forbidden', 'You cannot delete revisions for this post' );
			}

			$revisions = wp_get_post_revisions( $post->ID );
			$deleted   = 0;

			foreach ( $revisions as $rev ) {
				$rev_del_id = $rev->ID;
				$result = wp_delete_post_revision( $rev_del_id );
				if ( $result && ! is_wp_error( $result ) ) {
					$deleted++;
				}
			}

			return array(
				'success'  => true,
				'post_id'  => (int) $post->ID,
				'deleted'  => $deleted,
			);
		},
	) );
} );
