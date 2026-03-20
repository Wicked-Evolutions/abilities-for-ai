<?php
/**
 * Cron Abilities
 *
 * Read-only scheduled event listing for V1.0.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'cron', 'manage_options' );

	$reg->read( 'cron/list-events', array(
		'label'       => 'List Cron Events',
		'description' => 'List scheduled cron events with next run times, recurrence, and arguments. Paginated — returns up to 100 events per page.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search'   => abilities_for_ai_schema_search( 'Filter by hook name (partial match)' ),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Events per page (default 100, max 200).',
					'default'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number (default 1).',
					'default'     => 1,
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_collection_output( 'events', array(
			'hook'      => array( 'type' => 'string' ),
			'next_run'  => array( 'type' => 'string' ),
			'timestamp' => array( 'type' => 'integer' ),
			'schedule'  => array( 'type' => 'string' ),
			'interval'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			$crons = _get_cron_array();
			if ( ! $crons ) {
				return array( 'events' => array(), 'total' => 0, 'page' => 1, 'pages' => 0 );
			}

			$events = array();
			foreach ( $crons as $timestamp => $hooks ) {
				if ( ! is_array( $hooks ) ) {
					continue;
				}
				foreach ( $hooks as $hook => $schedules ) {
					if ( ! is_array( $schedules ) ) {
						continue;
					}
					if ( ! empty( $params['search'] ) && stripos( $hook, $params['search'] ) === false ) {
						continue;
					}
					foreach ( $schedules as $key => $data ) {
						if ( ! is_array( $data ) ) {
							continue;
						}
						$schedule = $data['schedule'] ?? false;
						$events[] = array(
							'hook'       => (string) $hook,
							'next_run'   => gmdate( 'Y-m-d H:i:s', $timestamp ),
							'timestamp'  => (int) $timestamp,
							'schedule'   => $schedule ? (string) $schedule : 'single',
							'interval'   => isset( $data['interval'] ) ? (int) $data['interval'] : null,
						);
					}
				}
			}

			usort( $events, function( $a, $b ) {
				return $a['timestamp'] - $b['timestamp'];
			});

			$total    = count( $events );
			$per_page = min( max( (int) ( $params['per_page'] ?? 100 ), 1 ), 200 );
			$page     = max( (int) ( $params['page'] ?? 1 ), 1 );
			$pages    = (int) ceil( $total / $per_page );
			$offset   = ( $page - 1 ) * $per_page;
			$events   = array_slice( $events, $offset, $per_page );

			return array( 'events' => $events, 'total' => $total, 'page' => $page, 'pages' => $pages );
		},
	));

	$reg->read( 'cron/list-schedules', array(
		'label'       => 'List Cron Schedules',
		'description' => 'List available cron recurrence schedules (hourly, twicedaily, daily, etc.).',
		'output_schema' => abilities_for_ai_schema_collection_output( 'schedules', array(
			'name'     => array( 'type' => 'string' ),
			'interval' => array( 'type' => 'integer' ),
			'display'  => array( 'type' => 'string' ),
		) ),
		'callback' => function() {
			$schedules = wp_get_schedules();
			$result = array();
			foreach ( $schedules as $key => $schedule ) {
				$result[] = array(
					'name'     => $key,
					'interval' => $schedule['interval'] ?? 0,
					'display'  => $schedule['display'] ?? $key,
				);
			}
			usort( $result, function( $a, $b ) {
				return $a['interval'] - $b['interval'];
			});
			return array( 'total' => count( $result ), 'schedules' => $result );
		},
	));

	$reg->read( 'cron/get-event', array(
		'label'       => 'Get Cron Event',
		'description' => 'Get details for a specific cron hook including all scheduled instances.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'hook' => array( 'type' => 'string', 'description' => 'Cron hook name' ),
			),
			'required' => array( 'hook' ),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'hook'      => array( 'type' => 'string' ),
			'total'     => array( 'type' => 'integer' ),
			'instances' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$hook  = sanitize_text_field( $params['hook'] ?? '' );
			$crons = _get_cron_array();
			if ( ! $crons ) {
				return wp_abilities_error( 'not_found', 'No cron events found.' );
			}

			$instances = array();
			foreach ( $crons as $timestamp => $hooks ) {
				if ( isset( $hooks[ $hook ] ) ) {
					foreach ( $hooks[ $hook ] as $key => $data ) {
						$instances[] = array(
							'next_run'  => date( 'Y-m-d H:i:s', $timestamp ),
							'timestamp' => $timestamp,
							'schedule'  => $data['schedule'] ?? false,
							'interval'  => $data['interval'] ?? null,
							'args'      => '[stripped for security]',
						);
					}
				}
			}

			if ( empty( $instances ) ) {
				return wp_abilities_error( 'not_found', "No scheduled events for hook '{$hook}'." );
			}

			return array(
				'hook'      => $hook,
				'total'     => count( $instances ),
				'instances' => $instances,
			);
		},
	));

	// ===== CRON — WRITE =====

	$reg->write( 'cron/create-event', array(
		'label'       => 'Schedule Cron Event',
		'description' => 'Schedule a new cron event. Use recurrence for recurring events or omit for single-fire events. The hook must already be registered in WordPress (this schedules when it runs, not what it does).',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'hook', 'timestamp' ),
			'properties' => array(
				'hook' => array(
					'type'        => 'string',
					'description' => 'Hook name to schedule (must be a registered WordPress action)',
				),
				'timestamp' => array(
					'type'        => 'integer',
					'description' => 'Unix timestamp for first/only execution',
				),
				'recurrence' => array(
					'type'        => 'string',
					'description' => 'Recurrence schedule name (e.g., "hourly", "twicedaily", "daily"). Omit for single event.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'hook'      => array( 'type' => 'string' ),
			'timestamp' => array( 'type' => 'integer' ),
			'next_run'  => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'idempotent' => false ),
		'callback' => function( $input ) {
			$hook      = sanitize_text_field( $input['hook'] );
			$timestamp = intval( $input['timestamp'] );

			if ( $timestamp < time() ) {
				return new WP_Error( 'ability_invalid_input', 'Timestamp must be in the future' );
			}

			if ( ! empty( $input['recurrence'] ) ) {
				$recurrence = sanitize_text_field( $input['recurrence'] );
				$schedules  = wp_get_schedules();
				if ( ! isset( $schedules[ $recurrence ] ) ) {
					return new WP_Error( 'ability_invalid_input', "Unknown recurrence schedule: {$recurrence}. Use cron/list-schedules to see available options." );
				}
				$result = wp_schedule_event( $timestamp, $recurrence, $hook );
			} else {
				$result = wp_schedule_single_event( $timestamp, $hook );
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $result === false ) {
				return new WP_Error( 'ability_invalid_input', 'Failed to schedule event. The event may already be scheduled.' );
			}

			return array(
				'success'   => true,
				'hook'      => $hook,
				'timestamp' => $timestamp,
				'next_run'  => date( 'Y-m-d H:i:s', $timestamp ),
			);
		},
	) );

	// ===== CRON — DELETE =====

	$reg->delete( 'cron/delete-event', array(
		'label'       => 'Unschedule Cron Event',
		'description' => 'Remove a scheduled cron event by hook name and timestamp. Use cron/list-events to find the exact timestamp.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'hook', 'timestamp' ),
			'properties' => array(
				'hook' => array(
					'type'        => 'string',
					'description' => 'Hook name of the scheduled event',
				),
				'timestamp' => array(
					'type'        => 'integer',
					'description' => 'Unix timestamp of the specific event instance to remove',
				),
				'all' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'If true, removes ALL scheduled instances of this hook (ignores timestamp)',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'hook'    => array( 'type' => 'string' ),
			'removed' => array( 'type' => 'integer', 'description' => 'Number of instances removed' ),
		) ),
		'callback' => function( $input ) {
			$hook = sanitize_text_field( $input['hook'] );
			$all  = $input['all'] ?? false;

			if ( $all ) {
				$count = wp_unschedule_hook( $hook );
				if ( $count === false ) {
					return new WP_Error( 'ability_invalid_input', 'Failed to unschedule hook' );
				}
				return array( 'success' => true, 'hook' => $hook, 'removed' => (int) $count );
			}

			$timestamp = intval( $input['timestamp'] );
			$result    = wp_unschedule_event( $timestamp, $hook );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $result === false ) {
				return new WP_Error( 'not_found', 'No event found for this hook at this timestamp' );
			}

			return array( 'success' => true, 'hook' => $hook, 'removed' => 1 );
		},
	) );
});
