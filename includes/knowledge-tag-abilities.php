<?php
/**
 * Knowledge Tag Abilities — CRUD + assign/unassign for KL tags.
 *
 * 7 abilities, all free tier.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

use WickedEvolutions\AbilitiesForAI\Core\Registrar;
use WickedEvolutions\AbilitiesForAI\Knowledge\Tag;
use WickedEvolutions\AbilitiesForAI\Knowledge\Taggable;
use WickedEvolutions\AbilitiesForAI\Knowledge\Schema;

add_action( 'wp_abilities_api_init', function() {

	if ( ! Schema::tables_exist() ) {
		return;
	}

	$reg = new Registrar( 'knowledge', 'manage_options' );

	// ─── List Tags ───────────────────────────────────────────

	$reg->read( 'knowledge/list-tags', array(
		'label'        => 'List Knowledge Tags',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'List tags with optional search and pagination.',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search'   => array( 'type' => 'string', 'description' => 'Search tags by title.' ),
				'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (1-100). Default: 20.' ),
				'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input = null ) {
			return Tag::all( $input ?? array() );
		},
	) );

	// ─── Get Tag ─────────────────────────────────────────────

	$reg->read( 'knowledge/get-tag', array(
		'label'        => 'Get Knowledge Tag',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Get a single tag by ID or slug.',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'   => array( 'type' => 'integer', 'description' => 'Tag ID.' ),
				'slug' => array( 'type' => 'string', 'description' => 'Tag slug (alternative to ID).' ),
			),
		),
		'callback' => function( $input = null ) {
			$input = $input ?? array();

			if ( ! empty( $input['id'] ) ) {
				$tag = Tag::find( (int) $input['id'] );
			} elseif ( ! empty( $input['slug'] ) ) {
				$tag = Tag::findBySlug( $input['slug'] );
			} else {
				return new \WP_Error( 'missing_params', 'Provide either id or slug.' );
			}

			if ( ! $tag ) {
				return new \WP_Error( 'not_found', 'Tag not found.' );
			}

			return (array) $tag;
		},
	) );

	// ─── Create Tag ──────────────────────────────────────────

	$reg->write( 'knowledge/create-tag', array(
		'label'        => 'Create Knowledge Tag',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Create a new tag. Slug is auto-generated from title if omitted.',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'title' ),
			'properties' => array(
				'title'       => array( 'type' => 'string', 'description' => 'Tag title.' ),
				'slug'        => array( 'type' => 'string', 'description' => 'URL-safe slug (auto-generated from title if omitted).' ),
				'description' => array( 'type' => 'string', 'description' => 'Tag description.' ),
				'color'       => array( 'type' => 'string', 'description' => 'Hex color code (e.g. #ff6b35).' ),
			),
		),
		'callback' => function( $input ) {
			$result = Tag::create( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return (array) $result;
		},
	) );

	// ─── Update Tag ──────────────────────────────────────────

	$reg->write( 'knowledge/update-tag', array(
		'label'        => 'Update Knowledge Tag',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Update tag properties.',
		'tier'         => 'free',
		'annotations'  => array( 'idempotent' => true ),
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'          => array( 'type' => 'integer', 'description' => 'Tag ID to update.' ),
				'title'       => array( 'type' => 'string', 'description' => 'New title.' ),
				'slug'        => array( 'type' => 'string', 'description' => 'New slug.' ),
				'description' => array( 'type' => 'string', 'description' => 'New description.' ),
				'color'       => array( 'type' => 'string', 'description' => 'New hex color code.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Tag::update( (int) $input['id'], $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return (array) $result;
		},
	) );

	// ─── Delete Tag ──────────────────────────────────────────

	$reg->delete( 'knowledge/delete-tag', array(
		'label'        => 'Delete Knowledge Tag',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Delete a tag and cascade-remove all assignments.',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Tag ID to delete.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Tag::delete( (int) $input['id'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'id' => (int) $input['id'], 'deleted' => true );
		},
	) );

	// ─── Assign Tags ─────────────────────────────────────────

	$reg->write( 'knowledge/assign-tags', array(
		'label'        => 'Assign Tags to Entity',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Assign one or more tags to a knowledge layer entity (document, session, or observation).',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'tag_ids', 'taggable_id', 'taggable_type' ),
			'properties' => array(
				'tag_ids'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Tag IDs to assign.' ),
				'taggable_id'   => array( 'type' => 'integer', 'description' => 'Entity ID.' ),
				'taggable_type' => array( 'type' => 'string', 'enum' => array( 'document', 'session', 'observation' ), 'description' => 'Entity type.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Taggable::assign(
				array_map( 'intval', $input['tag_ids'] ),
				(int) $input['taggable_id'],
				$input['taggable_type']
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'assigned'      => $result,
				'taggable_id'   => (int) $input['taggable_id'],
				'taggable_type' => $input['taggable_type'],
			);
		},
	) );

	// ─── Unassign Tags ───────────────────────────────────────

	$reg->write( 'knowledge/unassign-tags', array(
		'label'        => 'Unassign Tags from Entity',
		'compiled'    => false,
		'replaces'    => 'admin.php?page=abilities-for-ai-knowledge',
		'description'  => 'Remove one or more tags from a knowledge layer entity.',
		'tier'         => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'tag_ids', 'taggable_id', 'taggable_type' ),
			'properties' => array(
				'tag_ids'       => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Tag IDs to remove.' ),
				'taggable_id'   => array( 'type' => 'integer', 'description' => 'Entity ID.' ),
				'taggable_type' => array( 'type' => 'string', 'enum' => array( 'document', 'session', 'observation' ), 'description' => 'Entity type.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Taggable::unassign(
				array_map( 'intval', $input['tag_ids'] ),
				(int) $input['taggable_id'],
				$input['taggable_type']
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'removed'       => $result,
				'taggable_id'   => (int) $input['taggable_id'],
				'taggable_type' => $input['taggable_type'],
			);
		},
	) );

} );
