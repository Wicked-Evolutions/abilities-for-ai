<?php
/**
 * Knowledge Layer — Observations REST Controller.
 *
 * Thin REST layer over Observation model.
 * Namespace: abilities-kl/v1/observations
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

use WickedEvolutions\AbilitiesForAI\Knowledge\Observation;

defined( 'ABSPATH' ) || exit;

class ObservationsController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'observations';

	public function register_routes() {
		// GET /observations
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => $this->get_collection_params(),
		) );

		// PUT /observations/{id}/resolve
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/resolve', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'resolve_item' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// POST /observations/bulk-action
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/bulk-action', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_action' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /observations — paginated list with filters.
	 */
	public function get_items( $request ) {
		$args = array(
			'per_page' => $request->get_param( 'per_page' ) ?? 20,
			'page'     => $request->get_param( 'page' ) ?? 1,
		);

		if ( $request->get_param( 'status' ) ) {
			$args['status'] = $request->get_param( 'status' );
		}
		if ( $request->get_param( 'category' ) ) {
			$args['category'] = $request->get_param( 'category' );
		}
		if ( $request->get_param( 'severity' ) ) {
			$args['severity'] = $request->get_param( 'severity' );
		}
		if ( $request->get_param( 'session_id' ) ) {
			$args['session_id'] = $request->get_param( 'session_id' );
		}

		$result = Observation::list_observations( $args );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $result['per_page'] ) ) );

		return $response;
	}

	/**
	 * PUT /observations/{id}/resolve — resolve with note.
	 */
	public function resolve_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();

		$status = $body['status'] ?? 'resolved';
		$note   = $body['resolution_note'] ?? '';

		$result = Observation::resolve( $id, $status, $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( (array) $result );
	}

	/**
	 * POST /observations/bulk-action — bulk resolve or defer.
	 *
	 * Body: { action: "resolve"|"defer"|"wont_fix", ids: [1,2,3], resolution_note?: "..." }
	 */
	public function bulk_action( $request ) {
		$body   = $request->get_json_params();
		$action = $body['action'] ?? '';
		$ids    = array_map( 'intval', $body['ids'] ?? array() );
		$note   = $body['resolution_note'] ?? '';

		if ( empty( $ids ) ) {
			return new \WP_Error( 'missing_ids', 'No observation IDs provided.', array( 'status' => 400 ) );
		}

		$status_map = array(
			'resolve'  => 'resolved',
			'defer'    => 'deferred',
			'wont_fix' => 'wont_fix',
		);

		if ( ! isset( $status_map[ $action ] ) ) {
			return new \WP_Error( 'invalid_action', "Unknown action: {$action}. Use resolve, defer, or wont_fix.", array( 'status' => 400 ) );
		}

		$target_status = $status_map[ $action ];
		$results       = array( 'processed' => 0, 'errors' => array() );

		foreach ( $ids as $id ) {
			$r = Observation::resolve( $id, $target_status, $note );
			if ( is_wp_error( $r ) ) {
				$results['errors'][] = array( 'id' => $id, 'error' => $r->get_error_message() );
			} else {
				$results['processed']++;
			}
		}

		return rest_ensure_response( $results );
	}

	public function get_collection_params() {
		return array(
			'per_page'   => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'       => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'status'     => array( 'type' => 'string', 'default' => 'open' ),
			'category'   => array( 'type' => 'string' ),
			'severity'   => array( 'type' => 'string' ),
			'session_id' => array( 'type' => 'string' ),
		);
	}
}
