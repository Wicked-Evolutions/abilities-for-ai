<?php
/**
 * Cron Abilities
 *
 * Read-only scheduled event listing for V1.0.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'cron', 'manage_options' );

	$reg->read( 'cron/list-events', array(
		'label'       => 'List Cron Events',
		'description' => 'List all scheduled cron events with next run times, recurrence, and arguments.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => wp_abilities_suite_schema_search( 'Filter by hook name (partial match)' ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_collection_output( 'events', array(
			'hook'      => array( 'type' => 'string' ),
			'next_run'  => array( 'type' => 'string' ),
			'timestamp' => array( 'type' => 'integer' ),
			'schedule'  => array( 'type' => 'string' ),
			'interval'  => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
			$crons = _get_cron_array();
			if ( ! $crons ) {
				return array( 'events' => array(), 'total' => 0 );
			}

			$events = array();
			foreach ( $crons as $timestamp => $hooks ) {
				foreach ( $hooks as $hook => $schedules ) {
					if ( ! empty( $params['search'] ) && stripos( $hook, $params['search'] ) === false ) {
						continue;
					}
					foreach ( $schedules as $key => $data ) {
						$events[] = array(
							'hook'       => $hook,
							'next_run'   => date( 'Y-m-d H:i:s', $timestamp ),
							'timestamp'  => $timestamp,
							'schedule'   => $data['schedule'] ?? false,
							'interval'   => $data['interval'] ?? null,
							'args'       => '[stripped for security]',
						);
					}
				}
			}

			usort( $events, function( $a, $b ) {
				return $a['timestamp'] - $b['timestamp'];
			});

			return array( 'events' => $events, 'total' => count( $events ) );
		},
	));

	$reg->read( 'cron/list-schedules', array(
		'label'       => 'List Cron Schedules',
		'description' => 'List available cron recurrence schedules (hourly, twicedaily, daily, etc.).',
		'output_schema' => wp_abilities_suite_schema_collection_output( 'schedules', array(
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
		'output_schema' => wp_abilities_suite_schema_item_output( array(
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
});
