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

			return (array) $doc;
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
			return (array) $result;
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
			return (array) $result;
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
			return (array) $result;
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
				'session_id' => array( 'type' => 'string', 'description' => 'Unique session identifier.' ),
			),
		),
		'callback' => function( $input ) {
			$session = Session::find( $input['session_id'] );
			if ( ! $session ) {
				return new \WP_Error( 'not_found', 'Session not found.' );
			}
			return (array) $session;
		},
	) );

	$reg->write( 'knowledge/log-session', array(
		'label'        => 'Log Knowledge Session',
		'description'  => 'Write an append-only session log entry. Called at end of each AI session.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'session_id', 'agent_type', 'model', 'summary' ),
			'properties' => array(
				'session_id'         => array( 'type' => 'string', 'description' => 'Unique session identifier.' ),
				'agent_type'         => array( 'type' => 'string', 'description' => 'Which agent mode was active.' ),
				'model'              => array( 'type' => 'string', 'description' => 'AI model used.' ),
				'started_at'         => array( 'type' => 'string', 'description' => 'ISO datetime — session start.' ),
				'ended_at'           => array( 'type' => 'string', 'description' => 'ISO datetime — session end.' ),
				'summary'            => array( 'type' => 'string', 'description' => 'What happened in this session.' ),
				'protocols_run'      => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Protocol slugs executed.' ),
				'documents_modified' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ), 'description' => 'Document IDs created or updated.' ),
				'findings'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Key findings from this session.' ),
				'whats_next'         => array( 'type' => 'string', 'description' => 'Suggested follow-up for next session.' ),
			),
		),
		'callback' => function( $input ) {
			$result = Session::log( $input );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return (array) $result;
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
			return (array) $result;
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
			return (array) $result;
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
			return (array) $result;
		},
	) );

	// ─── Boot ─────────────────────────────────────────────────

	$reg->read( 'knowledge/boot', array(
		'label'       => 'Knowledge Layer Boot',
		'description' => 'AI entry point — call this first when connecting to a site. Returns boot sequence, session history, site identity, ESSENCE, active observations, available agents, and courses. Determines if this is a first visit (bootstrap) or returning visit.',
		'callback'    => function() {
			$session_count = Session::count();
			$is_first      = $session_count === 0;

			$response = array(
				'is_first_visit'      => $is_first,
				'session_count'       => $session_count,
				'boot_sequence'       => null,
				'last_session'        => null,
				'site_identity'       => null,
				'essence'             => null,
				'active_observations' => Observation::count_open(),
				'available_agents'    => array(),
				'available_courses'   => array(),
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
						'whats_next' => $latest->whats_next,
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
					'ability'     => 'knowledge/get',
					'input'       => array( 'doc_type' => 'skill', 'slug' => 'initial-read' ),
					'instruction' => 'Read this protocol fully before doing anything else. It defines how to behave on first contact. Follow it step by step.',
				);
			} else {
				// Returning visit — check for site state first, then fall back.
				$site_state = Document::find_by_slug( 'site-state', 'current' );
				if ( $site_state ) {
					$response['next_action'] = array(
						'ability'     => 'knowledge/get',
						'input'       => array( 'doc_type' => 'site-state', 'slug' => 'current' ),
						'instruction' => 'Read the site state to understand where the last session left off. Present what happened last time and suggest next steps.',
					);
				} elseif ( $identity ) {
					// No site-state yet, but site-identity exists — previous session did work.
					$response['next_action'] = array(
						'ability'     => 'knowledge/get',
						'input'       => array( 'doc_type' => 'site-identity', 'slug' => $identity->slug ),
						'instruction' => 'A previous session built this site identity. Read it to understand what is already known about this site, then present a summary and ask what the human wants to do next.',
					);
				} else {
					// Returning but nothing persisted — restart onboarding.
					$response['next_action'] = array(
						'ability'     => 'knowledge/get',
						'input'       => array( 'doc_type' => 'skill', 'slug' => 'initial-read' ),
						'instruction' => 'Previous sessions left no knowledge documents. Start the Introduction Course from the beginning.',
					);
				}
			}

			return $response;
		},
	) );

} );
