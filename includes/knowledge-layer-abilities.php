<?php
/**
 * Knowledge Layer Abilities — CRUD interface for knowledge documents,
 * sessions, observations, and revisions.
 *
 * 14 abilities across free and pro tiers.
 * All data access goes through the Knowledge models (src/Knowledge/).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

use WickedEvolutions\AbilitiesForAI\Core\Registrar;
use WickedEvolutions\AbilitiesForAI\Knowledge\Document;
use WickedEvolutions\AbilitiesForAI\Knowledge\Session;
use WickedEvolutions\AbilitiesForAI\Knowledge\Observation;
use WickedEvolutions\AbilitiesForAI\Knowledge\Revision;
use WickedEvolutions\AbilitiesForAI\Knowledge\Schema;
use WickedEvolutions\AbilitiesForAI\Knowledge\Search\Fulltext_Search_Provider;
use WickedEvolutions\AbilitiesForAI\Knowledge\Search\KL_Search_Provider;

/**
 * Register abilities only if Knowledge Layer tables exist.
 */
add_action( 'wp_abilities_api_init', function() {

	if ( ! Schema::tables_exist() ) {
		return;
	}

	$reg = new Registrar( 'knowledge', 'manage_options' );

	// ─── Documents ────────────────────────────────────────────

	$reg->read( 'knowledge/list', array(
		'label'        => 'List Knowledge Documents',
		'description'  => 'List knowledge layer documents filtered by type, status, or search term.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'doc_type' => array( 'type' => 'string', 'description' => 'Filter by document type: ' . implode( ', ', Document::TYPES ) ),
				'status'   => array( 'type' => 'string', 'description' => 'Filter by status (active, draft, seed, all). Default: active.', 'default' => 'active' ),
				'search'   => array( 'type' => 'string', 'description' => 'Search title and excerpt.' ),
				'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (1-100). Default: 20.' ),
				'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input = null ) {
			$input = $input ?? array();
			return Document::list_documents( $input );
		},
	) );

	$reg->read( 'knowledge/get', array(
		'label'        => 'Get Knowledge Document',
		'description'  => 'Get a single knowledge layer document by ID or by doc_type + slug.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'integer', 'description' => 'Document ID.' ),
				'slug'     => array( 'type' => 'string', 'description' => 'Document slug (requires doc_type).' ),
				'doc_type' => array( 'type' => 'string', 'description' => 'Document type (required when using slug).' ),
			),
		),
		'callback' => function( $input = null ) {
			$input = $input ?? array();

			if ( ! empty( $input['id'] ) ) {
				$doc = Document::find( (int) $input['id'] );
			} elseif ( ! empty( $input['slug'] ) && ! empty( $input['doc_type'] ) ) {
				$doc = Document::find_by_slug( $input['doc_type'], $input['slug'] );
			} else {
				return new \WP_Error( 'missing_params', 'Provide either id, or both slug and doc_type.' );
			}

			if ( ! $doc ) {
				return new \WP_Error( 'not_found', 'Document not found.' );
			}

			return abilities_for_ai_safe_value( (array) $doc );
		},
	) );

	$reg->write( 'knowledge/create', array(
		'label'        => 'Create Knowledge Document',
		'description'  => 'Create a new knowledge layer document.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'doc_type', 'title', 'content' ),
			'properties' => array(
				'doc_type' => array( 'type' => 'string', 'description' => 'Document type: ' . implode( ', ', Document::TYPES ) ),
				'title'    => array( 'type' => 'string', 'description' => 'Document title.' ),
				'content'  => array( 'type' => 'string', 'description' => 'Markdown content.' ),
				'slug'     => array( 'type' => 'string', 'description' => 'URL-safe slug. Auto-generated from title if omitted.' ),
				'excerpt'  => array( 'type' => 'string', 'description' => 'Short description.' ),
				'status'   => array( 'type' => 'string', 'description' => 'Status (active, draft). Default: active.' ),
				'metadata' => array( 'type' => 'object', 'description' => 'Type-specific structured data.' ),
			),
		),
		'callback' => function( $input ) {
			$input['source'] = 'ai';
			$result = Document::create( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	$reg->write( 'knowledge/update', array(
		'label'        => 'Update Knowledge Document',
		'description'  => 'Update an existing knowledge layer document. Creates a revision before updating. Rejects locked documents — use knowledge/fork instead.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'             => array( 'type' => 'integer', 'description' => 'Document ID.' ),
				'title'          => array( 'type' => 'string', 'description' => 'New title.' ),
				'content'        => array( 'type' => 'string', 'description' => 'New markdown content.' ),
				'excerpt'        => array( 'type' => 'string', 'description' => 'New excerpt.' ),
				'status'         => array( 'type' => 'string', 'description' => 'New status.' ),
				'metadata'       => array( 'type' => 'object', 'description' => 'Metadata fields to merge with existing.' ),
				'change_summary' => array( 'type' => 'string', 'description' => 'Brief description of the change (stored in revision).' ),
			),
		),
		'callback' => function( $input ) {
			$result = Document::update( (int) $input['id'], $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	$reg->delete( 'knowledge/delete', array(
		'label'        => 'Archive Knowledge Document',
		'description'  => 'Soft-delete a document (status → archived). Locked documents cannot be archived.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Document ID to archive.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Document::archive( (int) $input['id'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'id' => (int) $input['id'], 'status' => 'archived' );
		},
	) );

	$reg->write( 'knowledge/fork', array(
		'label'        => 'Fork Knowledge Document',
		'description'  => 'Create an editable copy of a locked (plugin-seeded) document.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id'    => array( 'type' => 'integer', 'description' => 'Document ID to fork.' ),
				'title' => array( 'type' => 'string', 'description' => 'Title for the forked copy. Default: original title + " (Custom)".' ),
				'slug'  => array( 'type' => 'string', 'description' => 'Slug for the forked copy. Auto-generated if omitted.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Document::fork( (int) $input['id'], $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	// ─── Search ───────────────────────────────────────────────

	$reg->read( 'knowledge/search', array(
		'label'        => 'Search Knowledge Documents',
		'description'  => 'FULLTEXT search across knowledge layer documents with optional filters. Returns relevance-ranked results. Falls back to filtered listing when no query is provided.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'query'    => array( 'type' => 'string', 'description' => 'Search query. If empty, returns filtered document list.' ),
				'doc_type' => array( 'type' => 'string', 'description' => 'Filter by document type: ' . implode( ', ', Document::TYPES ) ),
				'status'   => array( 'type' => 'string', 'description' => 'Filter by status (active, draft, seed, all). Default: active.' ),
				'tags'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Filter by tag slugs (all must match).' ),
				'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (1-100). Default: 20.' ),
				'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input = null ) {
			$input = $input ?? array();
			$query = $input['query'] ?? '';

			/** @var KL_Search_Provider $provider */
			$provider = apply_filters( 'kl_search_provider', new Fulltext_Search_Provider() );

			return $provider->search( $query, $input );
		},
	) );

	// ─── Sessions ─────────────────────────────────────────────

	$reg->read( 'knowledge/list-sessions', array(
		'label'        => 'List Knowledge Sessions',
		'description'  => 'List AI session history with optional filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'agent_type' => array( 'type' => 'string', 'description' => 'Filter by agent type.' ),
				'since'      => array( 'type' => 'string', 'description' => 'ISO date — only sessions started after this.' ),
				'per_page'   => array( 'type' => 'integer', 'description' => 'Items per page (1-100). Default: 20.' ),
				'page'       => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input = null ) {
			return Session::list_sessions( $input ?? array() );
		},
	) );

	$reg->read( 'knowledge/get-session', array(
		'label'        => 'Get Knowledge Session',
		'description'  => 'Get a single session detail by session ID.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'session_id' ),
			'properties' => array(
				'id'         => array( 'type' => 'string', 'description' => 'Session identifier (preferred; alias for session_id).' ),
				'session_id' => array( 'type' => 'string', 'description' => 'Session identifier (deprecated alias for id).' ),
			),
		),
		'callback' => function( $input ) {
			$session = Session::find( $input['id'] ?? $input['session_id'] );
			if ( ! $session ) {
				return new \WP_Error( 'not_found', 'Session not found.' );
			}
			return abilities_for_ai_safe_value( (array) $session );
		},
	) );

	$reg->write( 'knowledge/log-session', array(
		'label'        => 'Log Knowledge Session',
		'description'  => 'Write an append-only session log entry. Called at end of each AI session.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'session_id', 'agent_type', 'model', 'summary' ),
			'properties' => array(
				'id'                 => array( 'type' => 'string', 'description' => 'Session identifier (preferred; alias for session_id).' ),
				'session_id'         => array( 'type' => 'string', 'description' => 'Session identifier (deprecated alias for id).' ),
				'agent_type'         => array( 'type' => 'string', 'description' => 'Which agent mode was active.' ),
				'model'              => array( 'type' => 'string', 'description' => 'AI model used.' ),
				'started_at'         => array( 'type' => 'string', 'description' => 'ISO datetime — session start.' ),
				'ended_at'           => array( 'type' => 'string', 'description' => 'ISO datetime — session end.' ),
				'summary'            => array( 'type' => 'string', 'description' => 'What happened in this session.' ),
				'protocols_run'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Protocol slugs executed.' ),
				'documents_modified' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Document IDs created or updated.' ),
				'findings'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Key findings from this session.' ),
				'whats_next'         => array( 'type' => 'string', 'description' => 'Items pending user decision for next session. Write as choices the next AI will present to the user — not as tasks to execute.' ),
			),
		),
		'callback' => function( $input ) {
			$input['session_id'] = $input['id'] ?? $input['session_id'] ?? null;
			$result = Session::log( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	// ─── Observations ─────────────────────────────────────────

	$reg->read( 'knowledge/list-observations', array(
		'label'        => 'List Knowledge Observations',
		'description'  => 'List observations (findings from diagnostics) filtered by status, category, or severity.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'status'   => array( 'type' => 'string', 'description' => 'Filter by status (open, resolved, wont_fix, deferred, all). Default: open.' ),
				'category' => array( 'type' => 'string', 'description' => 'Filter by category: ' . implode( ', ', Observation::CATEGORIES ) ),
				'severity' => array( 'type' => 'string', 'description' => 'Filter by severity: ' . implode( ', ', Observation::SEVERITIES ) ),
				'per_page' => array( 'type' => 'integer', 'description' => 'Items per page (1-100). Default: 20.' ),
				'page'     => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input = null ) {
			return Observation::list_observations( $input ?? array() );
		},
	) );

	$reg->write( 'knowledge/add-observation', array(
		'label'        => 'Add Knowledge Observation',
		'description'  => 'Record a finding from a diagnostic session.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'session_id', 'category', 'severity', 'description' ),
			'properties' => array(
				'session_id'        => array( 'type' => 'string', 'description' => 'Session that discovered this.' ),
				'category'          => array( 'type' => 'string', 'description' => 'Category: ' . implode( ', ', Observation::CATEGORIES ) ),
				'severity'          => array( 'type' => 'string', 'description' => 'Severity: ' . implode( ', ', Observation::SEVERITIES ) ),
				'description'       => array( 'type' => 'string', 'description' => 'What was observed.' ),
				'source_diagnostic' => array( 'type' => 'string', 'description' => 'Which diagnostic/protocol found this.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Observation::add( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	$reg->write( 'knowledge/resolve-observation', array(
		'label'        => 'Resolve Knowledge Observation',
		'description'  => 'Mark an observation as resolved, deferred, or won\'t fix.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id', 'status' ),
			'properties' => array(
				'id'              => array( 'type' => 'integer', 'description' => 'Observation ID.' ),
				'status'          => array( 'type' => 'string', 'description' => 'New status: resolved, wont_fix, or deferred.' ),
				'resolution_note' => array( 'type' => 'string', 'description' => 'How it was resolved.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Observation::resolve( (int) $input['id'], $input['status'], $input['resolution_note'] ?? '' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	// ─── Revisions ────────────────────────────────────────────

	$reg->read( 'knowledge/get-revisions', array(
		'label'        => 'Get Document Revisions',
		'description'  => 'Get revision history for a knowledge layer document.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'document_id' ),
			'properties' => array(
				'document_id' => array( 'type' => 'integer', 'description' => 'Document ID.' ),
				'per_page'    => array( 'type' => 'integer', 'description' => 'Items per page. Default: 20.' ),
				'page'        => array( 'type' => 'integer', 'description' => 'Page number. Default: 1.' ),
			),
		),
		'callback' => function( $input ) {
			return Revision::list_for_document(
				(int) $input['document_id'],
				(int) ( $input['per_page'] ?? 20 ),
				(int) ( $input['page'] ?? 1 )
			);
		},
	) );

	$reg->write( 'knowledge/restore-revision', array(
		'label'        => 'Restore Document Revision',
		'description'  => 'Restore a document to a previous version. Creates a new revision of the current state before restoring.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'document_id', 'version' ),
			'properties' => array(
				'document_id' => array( 'type' => 'integer', 'description' => 'Document ID.' ),
				'version'     => array( 'type' => 'integer', 'description' => 'Version number to restore.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Revision::restore( (int) $input['document_id'], (int) $input['version'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return abilities_for_ai_safe_value( (array) $result );
		},
	) );

	// ─── MCP Resources ───────────────────────────────────────

	$reg->read( 'knowledge/resource-site-identity', array(
		'label'       => 'Site Identity',
		'description' => 'Core identity document for this WordPress site — who it is, what it does, infrastructure facts.',
		'meta'        => array(
			'mcp'      => array( 'public' => true, 'type' => 'resource' ),
			'uri'      => 'wordpress://knowledge/site-identity',
			'mimeType' => 'text/markdown',
		),
		'callback'    => function() {
			$doc = Document::find_by_slug( 'site-identity', 'current' );
			if ( ! $doc ) {
				$doc = Document::find_by_slug( 'site-identity', 'site-identity' );
			}
			if ( ! $doc ) {
				return new \WP_Error( 'not_found', 'No site identity document found in Knowledge Layer.' );
			}
			return array(
				'title'   => $doc->title,
				'content' => $doc->content,
				'version' => $doc->version,
			);
		},
	) );

	$reg->read( 'knowledge/resource-site-state', array(
		'label'       => 'Site State',
		'description' => 'Current operational state of this WordPress site — last session, open items, what shipped.',
		'meta'        => array(
			'mcp'      => array( 'public' => true, 'type' => 'resource' ),
			'uri'      => 'wordpress://knowledge/site-state',
			'mimeType' => 'text/markdown',
		),
		'callback'    => function() {
			$doc = Document::find_by_slug( 'site-state', 'current' );
			if ( ! $doc ) {
				return new \WP_Error( 'not_found', 'No site state document found in Knowledge Layer.' );
			}
			return array(
				'title'   => $doc->title,
				'content' => $doc->content,
				'version' => $doc->version,
			);
		},
	) );

	// ─── Boot ─────────────────────────────────────────────────

	$reg->read( 'knowledge/boot', array(
		'label'       => 'Knowledge Layer Boot',
		'description' => 'AI entry point — call this first when connecting to a site. Returns boot sequence, session history, site identity, ESSENCE, active observations, available agents, and courses. Determines if this is a first visit (bootstrap) or returning visit.',
		'callback'    => function() {
			// Mark this session as booted (used for session tracking).
			set_transient( 'kl_session_booted_' . get_current_user_id(), true, HOUR_IN_SECONDS );

			$session_count = Session::count();
			$is_first      = $session_count === 0;

			$response = array(
				'behavioral_directive' => array(
					'mode'              => 'inquiry',
					'allowed'           => array( 'read' ),
					'restricted'        => array( 'write', 'delete', 'update' ),
					'restriction_lifts' => 'when the user gives a directive',
					'first_output'      => 'Present findings to the user and wait for their choice.',
				),
				'is_first_visit'       => $is_first,
				'session_count'        => $session_count,
				'boot_sequence'        => null,
				'last_session'         => null,
				'site_identity'        => null,
				'essence'              => null,
				'active_observations'  => Observation::count_open(),
				'available_agents'     => array(),
				'available_courses'    => array(),
			);

			// Boot document.
			$boot = Document::find_by_slug( 'boot', 'startup' );
			if ( $boot ) {
				$response['boot_sequence'] = array(
					'title'    => $boot->title,
					'content'  => $boot->content,
					'metadata' => $boot->metadata,
				);
			}

			// Last session (if returning).
			if ( ! $is_first ) {
				$latest = Session::latest();
				if ( $latest ) {
					$response['last_session'] = array(
						'session_id' => $latest->session_id,
						'agent_type' => $latest->agent_type,
						'started_at' => $latest->started_at,
						'ended_at'   => $latest->ended_at,
						'summary'    => $latest->summary,
						'pending_user_decisions' => $latest->whats_next,
					);
				}
			}

			// Site identity (if built). Try 'current' slug first, fall back to 'site-identity'.
			$identity = Document::find_by_slug( 'site-identity', 'current' );
			if ( ! $identity ) {
				$identity = Document::find_by_slug( 'site-identity', 'site-identity' );
			}
			if ( $identity ) {
				$response['site_identity'] = array(
					'title'    => $identity->title,
					'excerpt'  => $identity->excerpt,
					'metadata' => $identity->metadata,
				);
			}

			// ESSENCE (if built).
			$essence = Document::find_by_slug( 'essence', 'current' );
			if ( $essence ) {
				$response['essence'] = array(
					'title'      => $essence->title,
					'excerpt'    => $essence->excerpt,
					'metadata'   => $essence->metadata,
					'version'    => $essence->version,
					'updated_at' => $essence->updated_at,
				);
			}

			// Active agents.
			$agents_result = Document::list_documents( array( 'doc_type' => 'agent', 'status' => 'all', 'per_page' => 50 ) );
			foreach ( $agents_result['items'] as $agent ) {
				$response['available_agents'][] = array(
					'slug'    => $agent->slug,
					'title'   => $agent->title,
					'excerpt' => $agent->excerpt,
				);
			}

			// Courses.
			$courses_result = Document::list_documents( array( 'doc_type' => 'course', 'status' => 'all', 'per_page' => 50 ) );
			foreach ( $courses_result['items'] as $course ) {
				$response['available_courses'][] = array(
					'slug'    => $course->slug,
					'title'   => $course->title,
					'excerpt' => $course->excerpt,
				);
			}

			// Next action — tells the AI exactly what to do next.
			if ( $is_first ) {
				$response['next_action'] = array(
					'ability'  => 'knowledge/get',
					'input'    => array( 'doc_type' => 'skill', 'slug' => 'initial-read' ),
					'sequence' => array(
						'Read the initial-read protocol.',
						'Follow it step by step.',
						'Present findings to the user and wait for their choice before proceeding.',
					),
				);
			} else {
				// Returning visit — check for site state first, then fall back.
				$site_state = Document::find_by_slug( 'site-state', 'current' );
				if ( $site_state ) {
					$response['next_action'] = array(
						'ability'  => 'knowledge/get',
						'input'    => array( 'doc_type' => 'site-state', 'slug' => 'current' ),
						'sequence' => array(
							'Read the site state.',
							'Present a summary of the last session to the user.',
							'Offer choices: 1. Continue where we left off. 2. Health Check. 3. Pick a direction.',
							'Wait for the user to choose before calling any further abilities.',
						),
					);
				} elseif ( $identity ) {
					// No site-state yet, but site-identity exists — previous session did work.
					$response['next_action'] = array(
						'ability'  => 'knowledge/get',
						'input'    => array( 'doc_type' => 'site-identity', 'slug' => $identity->slug ),
						'sequence' => array(
							'Read the site identity.',
							'Present a summary of what is known about this site.',
							'Ask the user what they want to do.',
							'Wait for the user to choose before calling any further abilities.',
						),
					);
				} else {
					// Returning but nothing persisted — restart onboarding.
					$response['next_action'] = array(
						'ability'  => 'knowledge/get',
						'input'    => array( 'doc_type' => 'skill', 'slug' => 'initial-read' ),
						'sequence' => array(
							'Read the initial-read protocol.',
							'Follow it step by step.',
							'Present findings to the user and wait for their choice before proceeding.',
						),
					);
				}
			}

			// Self-check directive — machine-readable, replaces the removed hard boot gate.
			$current_user = wp_get_current_user();
			$roles        = $current_user->roles;

			$response['first_output_to_human'] = array(
				'include'  => 'boot_status_check',
				'template' => array(
					'status'           => 'booted',
					'message_to_human' => sprintf(
						'Connected to %s as %s (%s). Session %d.',
						$response['site_identity']['title'] ?? get_bloginfo( 'name' ),
						$current_user->display_name,
						! empty( $roles ) ? reset( $roles ) : 'none',
						$session_count + 1
					),
					'options'          => array(
						1 => 'I did not complete the boot sequence — let me re-run it now.',
						2 => 'Boot complete. Here is what I found.',
					),
				),
			);

			return $response;
		},
	) );

} );
