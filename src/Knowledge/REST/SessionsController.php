<?php
/**
 * Knowledge Layer — Sessions REST Controller.
 *
 * Thin REST layer over Session and Observation models.
 * Namespace: abilities-kl/v1/sessions
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

use WickedEvolutions\AbilitiesForAI\Knowledge\Session;
use WickedEvolutions\AbilitiesForAI\Knowledge\Observation;

defined( 'ABSPATH' ) || exit;

class SessionsController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'sessions';

	public function register_routes() {
		// GET /sessions
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_items' ),
			'permission_callback' => array( $this, 'admin_check' ),
			'args'                => $this->get_collection_params(),
		) );

		// GET /sessions/{id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
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
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /sessions — paginated list with filters.
	 */
	public function get_items( $request ) {
		$args = array(
			'per_page' => $request->get_param( 'per_page' ) ?? 20,
			'page'     => $request->get_param( 'page' ) ?? 1,
		);

		if ( $request->get_param( 'agent_type' ) ) {
			$args['agent_type'] = $request->get_param( 'agent_type' );
		}
		if ( $request->get_param( 'since' ) ) {
			$args['since'] = $request->get_param( 'since' );
		}

		$result = Session::list_sessions( $args );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $result['per_page'] ) ) );

		return $response;
	}

	/**
	 * GET /sessions/{id} — single session with its observations.
	 *
	 * Note: The route param is the DB `id`, but Session::find() uses `session_id` (string).
	 * We look up by numeric ID first, then attach observations by session_id.
	 */
	public function get_item( $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			Session::table(),
			$id
		) );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Session not found.', array( 'status' => 404 ) );
		}

		// Decode JSON columns.
		$row->id      = (int) $row->id;
		$row->user_id = (int) $row->user_id;
		foreach ( array( 'protocols_run', 'documents_modified', 'findings' ) as $col ) {
			if ( isset( $row->$col ) && is_string( $row->$col ) ) {
				$row->$col = json_decode( $row->$col, true );
			}
		}

		$data = (array) $row;

		// Attach observations for this session.
		$obs = Observation::list_observations( array(
			'session_id' => $row->session_id,
			'status'     => 'all',
			'per_page'   => 100,
		) );
		$data['observations'] = $obs['items'];

		return rest_ensure_response( $data );
	}

	/**
	 * PUT /sessions/{id} — update a session.
	 */
	public function update_item( $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT session_id FROM %i WHERE id = %d",
			Session::table(),
			$id
		) );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Session not found.', array( 'status' => 404 ) );
		}

		$data = $request->get_json_params();
		$result = Session::update( $row->session_id, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( (array) $result );
	}

	/**
	 * DELETE /sessions/{id} — delete a session.
	 */
	public function delete_item( $request ) {
		global $wpdb;

		$id  = (int) $request->get_param( 'id' );
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT session_id FROM %i WHERE id = %d",
			Session::table(),
			$id
		) );

		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Session not found.', array( 'status' => 404 ) );
		}

		$result = Session::delete( $row->session_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true, 'session_id' => $row->session_id ) );
	}

	public function get_collection_params() {
		return array(
			'per_page'   => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'       => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'agent_type' => array( 'type' => 'string' ),
			'since'      => array( 'type' => 'string', 'description' => 'ISO date — sessions started after this.' ),
		);
	}
}
