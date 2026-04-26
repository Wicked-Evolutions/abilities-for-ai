<?php
/**
 * Knowledge Layer — MCP Boundary Event Logger.
 *
 * Implements McpObservabilityHandlerInterface from abilities-mcp-adapter.
 * Persists boundary.* events emitted by the adapter into the kl_boundary
 * table. Sister to the kl_activity logger — kl_activity logs ability
 * executions; kl_boundary logs the protocol-layer events that happen
 * before/around them (session lifecycle, auth denials, transport errors,
 * rate-limit hits).
 *
 * Hard rule (synthesis Decision 10): metadata only. Never store request
 * bodies, response bodies, or raw sensitive params. Sanitization happens
 * upstream in the adapter; this writer applies the same allowlist
 * defensively at write time.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class BoundaryEventLogger {

	/**
	 * v0.1 event taxonomy. Events not on this list are ignored.
	 *
	 * Default severities apply when the upstream emitter does not pass a
	 * `severity` tag. The four `boundary.*.changed` and
	 * `boundary.confirmation.failed` events come from DB-3 (adapter-side
	 * Safety Settings UI + AI-callable settings abilities); per synthesis
	 * Decision 11 they belong in the audit trail. They default to `info`
	 * here; the adapter raises individual emissions to `warn` when the
	 * change weakens the safety posture (master toggle off, Bucket 2 mod,
	 * confirmation token rejected).
	 *
	 * @var array<string,string> event_name => default_severity
	 */
	const EVENT_SEVERITY = array(
		'boundary.session.init'              => 'info',
		'boundary.session.terminated'        => 'info',
		'boundary.auth.denied'               => 'warn',
		'boundary.transport.error'           => 'warn',
		'boundary.rate_limit_hit'            => 'warn',
		// Settings audit (DB-3, synthesis Decision 11).
		'boundary.master_toggle.changed'     => 'info',
		'boundary.redaction_keywords.changed' => 'info',
		'boundary.ability_exemption.changed' => 'info',
		'boundary.confirmation.failed'       => 'info',
	);

	/**
	 * Allowlist of metadata fields that may appear in detail_json.
	 *
	 * Anything outside this list is dropped at write time. This is the
	 * defense-in-depth backstop — the adapter is supposed to sanitize
	 * before invoking us, but we never trust upstream.
	 *
	 * @var string[]
	 */
	const DETAIL_ALLOWLIST = array(
		'name',
		'protocolVersion',
		'uri',
		'client_name',
		'arguments_count',
		'arguments_keys',
		'reason',
		'limit',
		'window',
		'dimension',
		'retry_after_ms',
		'batch_size',
		'body_size',
	);

	/**
	 * Emit a countable event. Implements McpObservabilityHandlerInterface.
	 *
	 * Boundary events (event prefix `boundary.`) get persisted. Everything
	 * else is silently ignored — the adapter also fires non-boundary events
	 * like `mcp.request` through this same handler.
	 *
	 * @param string     $event       The event name to record.
	 * @param array      $tags        Tags attached to the event.
	 * @param float|null $duration_ms Optional duration in milliseconds.
	 *
	 * @return void
	 */
	public function record_event( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		// We only persist boundary.* events. Other events (mcp.request etc)
		// are handled elsewhere in the stack — kl_activity for ability calls.
		if ( strpos( $event, 'boundary.' ) !== 0 ) {
			return;
		}

		$this->persist( $event, $tags, $duration_ms );
	}

	/**
	 * Persist a boundary event row. Public so the action-hook adapter
	 * (Path 2) can route into the same writer without reaching through
	 * the interface.
	 *
	 * @param string     $event
	 * @param array      $tags
	 * @param float|null $duration_ms
	 *
	 * @return void
	 */
	public function persist( string $event, array $tags = array(), ?float $duration_ms = null ): void {
		global $wpdb;

		// Only known events count. Unknown boundary.* events are dropped
		// rather than silently inflating the table.
		if ( ! isset( self::EVENT_SEVERITY[ $event ] ) ) {
			return;
		}

		$table = $wpdb->prefix . 'kl_boundary';

		// Bail if the schema migration has not run yet (e.g. on the very
		// first activation hit). dbDelta is idempotent — table will exist
		// on the next request.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$severity = isset( $tags['severity'] ) && is_string( $tags['severity'] )
			? sanitize_key( $tags['severity'] )
			: self::EVENT_SEVERITY[ $event ];

		$ip_truncated = isset( $tags['ip'] ) && is_string( $tags['ip'] )
			? self::truncate_ip( $tags['ip'] )
			: '';

		$user_id = isset( $tags['user_id'] ) ? (int) $tags['user_id'] : 0;

		$session_id = isset( $tags['session_id'] ) && is_string( $tags['session_id'] )
			? substr( $tags['session_id'], 0, 64 )
			: '';

		$api_key_hash = isset( $tags['api_key'] ) && is_string( $tags['api_key'] ) && $tags['api_key'] !== ''
			? hash( 'xxh128', $tags['api_key'] )
			: ( isset( $tags['api_key_hash'] ) && is_string( $tags['api_key_hash'] )
				? substr( $tags['api_key_hash'], 0, 64 )
				: '' );

		$client_name = isset( $tags['client_name'] ) && is_string( $tags['client_name'] )
			? substr( $tags['client_name'], 0, 128 )
			: '';

		$user_agent = isset( $tags['user_agent'] ) && is_string( $tags['user_agent'] )
			? substr( $tags['user_agent'], 0, 255 )
			: '';

		$method = isset( $tags['method'] ) && is_string( $tags['method'] )
			? substr( $tags['method'], 0, 64 )
			: '';

		$request_id = isset( $tags['request_id'] ) ? substr( (string) $tags['request_id'], 0, 64 ) : '';

		$transport = isset( $tags['transport'] ) && is_string( $tags['transport'] )
			? substr( $tags['transport'], 0, 16 )
			: 'HTTP';

		$status_code = isset( $tags['status_code'] ) ? max( 0, (int) $tags['status_code'] ) : 0;

		$error_code = isset( $tags['error_code'] ) ? substr( (string) $tags['error_code'], 0, 64 ) : '';

		$detail_json = self::build_detail_json( $tags );

		$duration = (int) round( (float) ( $duration_ms ?? 0 ) );

		$wpdb->insert(
			$table,
			array(
				'event'        => $event,
				'severity'     => $severity,
				'ip_truncated' => $ip_truncated,
				'user_id'      => $user_id,
				'session_id'   => $session_id,
				'api_key_hash' => $api_key_hash,
				'client_name'  => $client_name,
				'user_agent'   => $user_agent,
				'method'       => $method,
				'request_id'   => $request_id,
				'transport'    => $transport,
				'status_code'  => $status_code,
				'error_code'   => $error_code,
				'detail_json'  => $detail_json,
				'duration_ms'  => $duration,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Filter the tags down to allowlisted, non-sensitive fields and encode
	 * as JSON. Defense-in-depth: even if a sensitive value somehow survives
	 * adapter-side sanitization, it never reaches the table.
	 *
	 * @param array $tags
	 * @return string|null JSON or null if nothing to store.
	 */
	private static function build_detail_json( array $tags ): ?string {
		$detail = array();
		foreach ( self::DETAIL_ALLOWLIST as $key ) {
			if ( ! array_key_exists( $key, $tags ) ) {
				continue;
			}
			$value = $tags[ $key ];

			// Scalars and arrays of scalars only. Objects/closures/resources
			// are dropped to keep the column compact and never carry
			// surprises.
			if ( is_scalar( $value ) ) {
				$detail[ $key ] = $value;
				continue;
			}
			if ( is_array( $value ) ) {
				$flat = array();
				foreach ( $value as $v ) {
					if ( is_scalar( $v ) ) {
						$flat[] = $v;
					}
				}
				$detail[ $key ] = $flat;
			}
		}

		if ( empty( $detail ) ) {
			return null;
		}

		$json = wp_json_encode( $detail );
		if ( $json === false ) {
			return null;
		}

		// Hard cap on detail_json length to keep table compact and prevent
		// log poisoning via inflated values from upstream that slipped past
		// the allowlist size constraints.
		if ( strlen( $json ) > 4096 ) {
			$json = substr( $json, 0, 4093 ) . '...';
		}

		return $json;
	}

	/**
	 * Truncate an IP address to a privacy-respecting prefix.
	 *
	 * IPv4 → /24 (last octet zeroed).
	 * IPv6 → /48 (everything past the third hextet zeroed).
	 *
	 * @param string $ip
	 * @return string Truncated form, or empty string if input is invalid.
	 */
	public static function truncate_ip( string $ip ): string {
		$ip = trim( $ip );
		if ( $ip === '' ) {
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$parts = explode( '.', $ip );
			if ( count( $parts ) === 4 ) {
				$parts[3] = '0';
				return implode( '.', $parts );
			}
			return '';
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$packed = inet_pton( $ip );
			if ( $packed === false ) {
				return '';
			}
			// Keep the first 48 bits (6 bytes), zero the rest.
			$mask     = str_repeat( "\xff", 6 ) . str_repeat( "\x00", 10 );
			$masked   = $packed & $mask;
			$readable = inet_ntop( $masked );
			return $readable === false ? '' : $readable;
		}

		return '';
	}
}
