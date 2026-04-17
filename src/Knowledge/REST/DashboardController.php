<?php
/**
 * Knowledge Layer — Dashboard REST Controller.
 *
 * Aggregate stats endpoint for the admin SPA dashboard.
 * Namespace: abilities-kl/v1/dashboard
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

use WickedEvolutions\AbilitiesForAI\Knowledge\Document;
use WickedEvolutions\AbilitiesForAI\Knowledge\Session;
use WickedEvolutions\AbilitiesForAI\Knowledge\Observation;
use WickedEvolutions\AbilitiesForAI\Knowledge\Tag;

defined( 'ABSPATH' ) || exit;

class DashboardController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'dashboard';

	public function register_routes() {
		// GET /dashboard/stats
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/stats', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );
	}

	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /dashboard/stats — aggregate counts for the admin dashboard.
	 */
	public function get_stats() {
		global $wpdb;

		$docs_table = Document::table();
		$obs_table  = Observation::table();

		// Documents by type.
		$docs_by_type = $wpdb->get_results(
			"SELECT doc_type, COUNT(*) as count FROM {$docs_table} WHERE status != 'archived' GROUP BY doc_type ORDER BY count DESC"
		);

		// Documents by status.
		$docs_by_status = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$docs_table} GROUP BY status ORDER BY count DESC"
		);

		// Recent sessions (last 5).
		$recent_sessions = Session::list_sessions( array( 'per_page' => 5, 'page' => 1 ) );

		// Observations by severity (open only).
		$obs_by_severity = $wpdb->get_results(
			"SELECT severity, COUNT(*) as count FROM {$obs_table} WHERE status = 'open' GROUP BY severity"
		);

		// Total counts.
		$total_docs     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$docs_table} WHERE status != 'archived'" );
		$total_sessions = Session::count();
		$open_obs       = Observation::count_open();
		$total_tags     = Tag::count();

		// Activity stats (kl_activity table).
		// v0.6.0 (issue #123) — extended with operational signal aggregations.
		$activity_table = $wpdb->prefix . 'kl_activity';
		$activity_data  = array(
			'total'             => 0,
			'total_error'       => 0,
			'total_compiled'    => 0,
			'compiled_pct'      => 0,
			'caller_origins'    => array(),
			'replaced_unique'   => 0,
			'avg_response_bytes'=> 0,
			'cache_candidates'  => array(),
			'recent'            => array(),
		);

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $activity_table ) ) === $activity_table ) {
			$total                        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$activity_table}" );
			$activity_data['total']       = $total;
			$activity_data['total_error'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$activity_table} WHERE status = 'error'" );

			// v0.6.0: Compiled vs CRUD summary.
			$total_compiled                  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$activity_table} WHERE is_compiled = 1" );
			$activity_data['total_compiled'] = $total_compiled;
			$activity_data['compiled_pct']   = $total > 0 ? round( $total_compiled / $total * 100, 1 ) : 0;

			// v0.6.0: Caller origin distribution.
			$by_caller      = $wpdb->get_results(
				"SELECT caller_origin, COUNT(*) as count FROM {$activity_table} GROUP BY caller_origin ORDER BY count DESC"
			);
			$caller_origins = array();
			foreach ( $by_caller as $row ) {
				$key                    = $row->caller_origin ?: 'unknown';
				$caller_origins[ $key ] = (int) $row->count;
			}
			$activity_data['caller_origins'] = $caller_origins;

			// v0.6.0: Replaced surface coverage — distinct wp-admin screens replaced.
			$activity_data['replaced_unique'] = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT replaced_surface) FROM {$activity_table} WHERE replaced_surface IS NOT NULL AND replaced_surface != ''"
			);

			// v0.6.0: Average response size across all calls.
			$activity_data['avg_response_bytes'] = (int) round( (float) $wpdb->get_var(
				"SELECT AVG(response_size_bytes) FROM {$activity_table} WHERE status = 'success'"
			) );

			// v0.6.0: Top 5 cache candidates (abilities with high response-hash repeat rate).
			$cache_candidates_raw = $wpdb->get_results(
				"SELECT ability_name, COUNT(*) as total_calls, COUNT(DISTINCT response_hash) as unique_hashes
				FROM {$activity_table}
				WHERE response_hash != '' AND status = 'success'
				GROUP BY ability_name
				HAVING COUNT(*) >= 5
				ORDER BY (COUNT(*) - COUNT(DISTINCT response_hash)) DESC, COUNT(*) DESC
				LIMIT 5"
			);
			$cache_candidates = array();
			foreach ( $cache_candidates_raw as $row ) {
				$tc = (int) $row->total_calls;
				$uh = (int) $row->unique_hashes;
				$cache_candidates[] = array(
					'name'        => $row->ability_name,
					'total_calls' => $tc,
					'repeat_rate' => $tc > 0 ? round( ( $tc - $uh ) / $tc, 3 ) : 0,
				);
			}
			$activity_data['cache_candidates'] = $cache_candidates;

			$recent_activity = $wpdb->get_results(
				"SELECT * FROM {$activity_table} ORDER BY created_at DESC LIMIT 5"
			);
			foreach ( $recent_activity as $item ) {
				$item->id                  = (int) $item->id;
				$item->duration_ms         = (int) $item->duration_ms;
				$item->user_id             = (int) $item->user_id;
				$item->response_size_bytes = (int) ( $item->response_size_bytes ?? 0 );
				$item->input_size_bytes    = (int) ( $item->input_size_bytes ?? 0 );
				$item->memory_delta_bytes  = (int) ( $item->memory_delta_bytes ?? 0 );
				$item->sql_query_count     = (int) ( $item->sql_query_count ?? 0 );
				$item->is_compiled         = (bool) ( $item->is_compiled ?? false );
			}
			$activity_data['recent'] = $recent_activity;
		}

		return rest_ensure_response( array(
			'documents' => array(
				'total'     => $total_docs,
				'by_type'   => $this->key_counts( $docs_by_type, 'doc_type' ),
				'by_status' => $this->key_counts( $docs_by_status, 'status' ),
			),
			'sessions' => array(
				'total'  => $total_sessions,
				'recent' => $recent_sessions['items'],
			),
			'observations' => array(
				'open'        => $open_obs,
				'by_severity' => $this->key_counts( $obs_by_severity, 'severity' ),
			),
			'tags' => array(
				'total' => $total_tags,
			),
			'activity' => $activity_data,
		) );
	}

	/**
	 * Convert array of {key, count} rows to an associative array.
	 */
	private function key_counts( $rows, $key_field ) {
		$out = array();
		foreach ( $rows as $row ) {
			$out[ $row->$key_field ] = (int) $row->count;
		}
		return $out;
	}
}
