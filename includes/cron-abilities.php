<?php
/**
 * Cron Abilities
 *
 * Read-only scheduled event listing for V1.0.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_cron_abilities' );

function wp_native_register_cron_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'cron' );

	// ===== CRON — READ =====
	if ( $perms['read'] ) {

	// ---- cron/list-events ----
	wp_register_ability( 'cron/list-events', array(
		'label'       => 'List Cron Events',
		'description' => 'List all scheduled cron events with next run times, recurrence, and arguments.',
		'category'    => 'cron',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'search' => array( 'type' => 'string', 'description' => 'Filter by hook name' ),
			),
		),
		'execute_callback' => function( $params ) {
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
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- cron/list-schedules ----
	wp_register_ability( 'cron/list-schedules', array(
		'label'       => 'List Cron Schedules',
		'description' => 'List available cron recurrence schedules (hourly, twicedaily, daily, etc.).',
		'category'    => 'cron',
		'input_schema' => array(
			'type'       => 'object',
		),
		'execute_callback' => function() {
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
			return array( 'schedules' => $result, 'count' => count( $result ) );
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- cron/get-event ----
	wp_register_ability( 'cron/get-event', array(
		'label'       => 'Get Cron Event',
		'description' => 'Get details for a specific cron hook including all scheduled instances.',
		'category'    => 'cron',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'hook' => array( 'type' => 'string', 'description' => 'Cron hook name' ),
			),
			'required' => array( 'hook' ),
		),
		'execute_callback' => function( $params ) {
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
				'instances' => $instances,
				'count'     => count( $instances ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	} // end read
}
