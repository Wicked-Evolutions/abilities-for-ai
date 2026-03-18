<?php
/**
 * Knowledge Layer — Tags REST Controller.
 *
 * Thin REST layer over Tag and Taggable models.
 * Namespace: abilities-kl/v1/tags
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

use WickedEvolutions\AbilitiesForAI\Knowledge\Tag;
use WickedEvolutions\AbilitiesForAI\Knowledge\Taggable;

defined( 'ABSPATH' ) || exit;

class TagsController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'tags';

	public function register_routes() {
		// GET /tags  +  POST /tags
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'admin_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
		) );

		// PUT|DELETE /tags/{id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
		) );

		// POST /tags/bulk
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/bulk', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_create' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /tags — list with search and usage counts.
	 */
	public function get_items( $request ) {
		global $wpdb;

		$args = array(
			'per_page' => $request->get_param( 'per_page' ) ?? 100,
			'page'     => $request->get_param( 'page' ) ?? 1,
		);

		if ( $request->get_param( 'search' ) ) {
			$args['search'] = $request->get_param( 'search' );
		}

		$result = Tag::all( $args );

		// Add usage counts via a single query.
		$tag_ids = array_map( function( $t ) { return $t->id; }, $result['items'] );

		$counts = array();
		if ( ! empty( $tag_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $tag_ids ), '%d' ) );
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT tag_id, COUNT(*) as usage_count FROM %i WHERE tag_id IN ({$placeholders}) GROUP BY tag_id",
				array_merge( array( Taggable::table() ), $tag_ids )
			) );
			foreach ( $rows as $row ) {
				$counts[ (int) $row->tag_id ] = (int) $row->usage_count;
			}
		}

		// Attach counts to items.
		foreach ( $result['items'] as &$item ) {
			$item->usage_count = $counts[ $item->id ] ?? 0;
		}

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $result['per_page'] ) ) );

		return $response;
	}

	/**
	 * POST /tags — create tag.
	 */
	public function create_item( $request ) {
		$body   = $request->get_json_params();
		$result = Tag::create( $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = (array) $result;
		$data['usage_count'] = 0;

		return new \WP_REST_Response( $data, 201 );
	}

	/**
	 * PUT /tags/{id} — update tag.
	 */
	public function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();

		$result = Tag::update( $id, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( (array) $result );
	}

	/**
	 * DELETE /tags/{id} — delete tag (cascades to taggables).
	 */
	public function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$result = Tag::delete( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'id' => $id, 'deleted' => true ) );
	}

	/**
	 * POST /tags/bulk — bulk create tags.
	 *
	 * Body: { tags: [ { title, slug?, description?, color? }, ... ] }
	 */
	public function bulk_create( $request ) {
		$body = $request->get_json_params();
		$tags = $body['tags'] ?? array();

		if ( empty( $tags ) ) {
			return new \WP_Error( 'missing_tags', 'No tags provided.', array( 'status' => 400 ) );
		}

		$results = array( 'created' => array(), 'errors' => array() );

		foreach ( $tags as $tag_data ) {
			$r = Tag::create( $tag_data );
			if ( is_wp_error( $r ) ) {
				$results['errors'][] = array(
					'title' => $tag_data['title'] ?? '(no title)',
					'error' => $r->get_error_message(),
				);
			} else {
				$results['created'][] = (array) $r;
			}
		}

		return rest_ensure_response( $results );
	}

	public function get_collection_params() {
		return array(
			'per_page' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500 ),
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'search'   => array( 'type' => 'string' ),
		);
	}
}
