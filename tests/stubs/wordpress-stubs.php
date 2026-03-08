<?php
/**
 * WordPress Function Stubs for Unit Tests
 *
 * Minimal stubs that let pure helper functions load and run without a
 * full WordPress environment. Integration tests use the real WP test suite.
 *
 * Only stub what the tested code actually calls.
 */

// Core constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wp/' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'WP_ABILITIES_SUITE_VERSION' ) ) {
	define( 'WP_ABILITIES_SUITE_VERSION', 'test' );
}

// WP_Error class.
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $errors      = array();
		public $error_data  = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[0] ?? '';
		}

		public function get_error_message( $code = '' ) {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][0] ?? '';
		}

		public function get_error_data( $code = '' ) {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->error_data[ $code ] ?? null;
		}
	}
}

// Stub: is_wp_error().
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

// Stub: wp_parse_args().
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

// Stub: sanitize_email().
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

// Stub: sanitize_text_field().
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

// Stub: absint().
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

// Stub: add_query_arg().
if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = '' ) {
		if ( is_array( $key ) ) {
			$url   = $value ?? '';
			$query = http_build_query( $key );
		} else {
			$query = http_build_query( array( $key => $value ) );
		}
		$sep = strpos( $url, '?' ) !== false ? '&' : '?';
		return $url . $sep . $query;
	}
}

// Stub: get_option() / update_option() / delete_option() — in-memory store.
$_wp_options_store = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_wp_options_store;
		return $_wp_options_store[ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_wp_options_store;
		$_wp_options_store[ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_wp_options_store;
		unset( $_wp_options_store[ $option ] );
		return true;
	}
}

// Stub: get_transient() / set_transient() / delete_transient() — in-memory store.
$_wp_transients_store = array();

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		global $_wp_transients_store;
		return $_wp_transients_store[ $transient ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		global $_wp_transients_store;
		$_wp_transients_store[ $transient ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		global $_wp_transients_store;
		unset( $_wp_transients_store[ $transient ] );
		return true;
	}
}

// Stub: wp_register_ability() — records calls for assertion in tests.
$_wp_registered_abilities = array();

if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {
		global $_wp_registered_abilities;
		$_wp_registered_abilities[ $name ] = $args;
	}
}
if ( ! function_exists( 'wp_get_abilities' ) ) {
	function wp_get_abilities() {
		global $_wp_registered_abilities;
		return $_wp_registered_abilities;
	}
}

// Stub: current_user_can() — returns true by default in unit tests.
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return true;
	}
}

// Stub: wp_abilities_suite_pro_gate() — passthrough in unit tests.
if ( ! function_exists( 'wp_abilities_suite_pro_gate' ) ) {
	function wp_abilities_suite_pro_gate( $name, $callback ) {
		return $callback;
	}
}

// Stub: add_action() / apply_filters() — no-ops for unit tests.
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		return 'https://example.com' . $path;
	}
}
