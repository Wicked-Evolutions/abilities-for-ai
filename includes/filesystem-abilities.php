<?php
/**
 * Filesystem Abilities
 *
 * Read and write files within the WordPress installation directory.
 * Uses native PHP filesystem functions (not WP_Filesystem) — on CageFS/CloudLinux
 * environments PHP runs as the file owner, making the transport layer unnecessary.
 *
 * Security: extension whitelist, ABSPATH containment, traversal rejection.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

// ============================================================
// Security Helpers
// ============================================================

/**
 * Validate that a path is safe — within ABSPATH, no traversal.
 *
 * @param string $relative_path Relative path from ABSPATH.
 * @param bool   $must_exist    Whether the target must already exist.
 * @return string|WP_Error Absolute path on success, WP_Error on failure.
 */
function wp_abilities_filesystem_validate_path( $relative_path, $must_exist = true ) {
	if ( empty( $relative_path ) || ! is_string( $relative_path ) ) {
		return wp_abilities_error( 'ability_invalid_input', 'Path is required and must be a string.' );
	}

	if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $relative_path ) ) {
		return wp_abilities_error( 'ability_invalid_input', 'Path traversal (../) is not allowed.' );
	}

	// Denylist: sensitive files that must never be read or exposed.
	$basename = strtolower( basename( $relative_path ) );
	$denied_files = array(
		'wp-config.php',
		'.htaccess',
		'.htpasswd',
		'.env',
		'.user.ini',
		'php.ini',
	);
	$denied_extensions = array( 'sql', 'pem', 'key', 'crt' );
	$denied_patterns   = array( 'debug.log', 'error_log', 'error.log' );

	if ( in_array( $basename, $denied_files, true ) ) {
		return wp_abilities_error( 'rest_forbidden', "Access to '{$basename}' is denied. This file contains sensitive configuration data." );
	}

	$ext = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
	if ( in_array( $ext, $denied_extensions, true ) ) {
		return wp_abilities_error( 'rest_forbidden', "Access to .{$ext} files is denied. These files may contain sensitive data." );
	}

	if ( in_array( $basename, $denied_patterns, true ) ) {
		return wp_abilities_error( 'rest_forbidden', "Access to '{$basename}' is denied. Log files may contain sensitive information." );
	}

	$normalized = wp_normalize_path( ABSPATH . ltrim( $relative_path, '/' ) );

	if ( $must_exist ) {
		$resolved = realpath( $normalized );
		if ( $resolved === false ) {
			return wp_abilities_error( 'not_found', 'Path does not exist.' );
		}
		$resolved = wp_normalize_path( $resolved );
		if ( strpos( $resolved, wp_normalize_path( ABSPATH ) ) !== 0 ) {
			return wp_abilities_error( 'ability_invalid_input', 'Path resolves outside the WordPress installation.' );
		}
		return $resolved;
	}

	$parent          = dirname( $normalized );
	$resolved_parent = realpath( $parent );
	if ( $resolved_parent === false ) {
		return wp_abilities_error( 'parent_not_found', 'Parent directory does not exist.' );
	}
	$resolved_parent = wp_normalize_path( $resolved_parent );
	if ( strpos( $resolved_parent, wp_normalize_path( ABSPATH ) ) !== 0 ) {
		return wp_abilities_error( 'ability_invalid_input', 'Target directory resolves outside the WordPress installation.' );
	}

	return $resolved_parent . '/' . basename( $normalized );
}

/**
 * Check whether a file extension is allowed for write operations.
 *
 * @param string $path File path to check.
 * @return true|WP_Error True if allowed, WP_Error if blocked.
 */
function wp_abilities_filesystem_check_extension( $path ) {
	$allowed = array( 'css', 'js', 'json', 'md', 'txt', 'html', 'php' );
	$blocked  = array( 'phtml', 'htaccess', 'sh', 'exe', 'bat' );
	$ext      = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	if ( in_array( $ext, $blocked, true ) ) {
		return wp_abilities_error( 'ability_invalid_input', "Writing to .{$ext} files is not allowed." );
	}
	if ( ! in_array( $ext, $allowed, true ) ) {
		return wp_abilities_error( 'ability_invalid_input', "Extension .{$ext} is not in the allowed list: " . implode( ', ', $allowed ) );
	}

	return true;
}

// ============================================================
// Ability Registration
// ============================================================

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'filesystem', 'manage_options' );

	// ===== FILESYSTEM — READ =====

	$reg->read( 'filesystem/list-directory', array(
		'label'       => 'List Directory',
		'description' => 'List files and folders in a directory within the WordPress installation.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'path' => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH (default: root)' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'path'  => array( 'type' => 'string' ),
			'total' => array( 'type' => 'integer' ),
			'items' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => function( $params ) {
			$path = $params['path'] ?? '';
			if ( empty( $path ) ) {
				$path = '.';
			}
			$abs = wp_abilities_filesystem_validate_path( $path, true );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}
			if ( ! is_dir( $abs ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path is not a directory.' );
			}
			$entries = @scandir( $abs );
			if ( $entries === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not read directory.' );
			}
			$items = array();
			foreach ( $entries as $entry ) {
				if ( $entry === '.' || $entry === '..' ) {
					continue;
				}
				$full    = $abs . '/' . $entry;
				$stat    = @stat( $full );
				$items[] = array(
					'name'        => $entry,
					'type'        => is_dir( $full ) ? 'folder' : 'file',
					'size'        => $stat ? $stat['size'] : 0,
					'modified'    => $stat ? date( 'Y-m-d H:i:s', $stat['mtime'] ) : null,
					'permissions' => $stat ? decoct( $stat['mode'] & 0777 ) : null,
				);
			}
			return array(
				'path'  => $path,
				'items' => $items,
				'total' => count( $items ),
			);
		},
	) );

	$reg->read( 'filesystem/read-file', array(
		'label'       => 'Read File',
		'description' => 'Read the content of a file within the WordPress installation. Limited to 1MB.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'path' ),
			'properties' => array(
				'path' => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'content'  => array( 'type' => 'string' ),
			'encoding' => array( 'type' => 'string' ),
			'metadata' => array( 'type' => 'object' ),
		) ),
		'callback' => function( $params ) {
			$abs = wp_abilities_filesystem_validate_path( $params['path'] ?? '', true );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}
			if ( ! is_file( $abs ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path is not a file.' );
			}
			$size = filesize( $abs );
			if ( $size > MB_IN_BYTES ) {
				return wp_abilities_error( 'ability_invalid_input', 'File exceeds 1MB limit (' . size_format( $size ) . ').' );
			}
			$content = @file_get_contents( $abs );
			if ( $content === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not read file.' );
			}
			return array(
				'content'  => $content,
				'encoding' => 'utf-8',
				'metadata' => array(
					'size'     => $size,
					'modified' => date( 'Y-m-d H:i:s', filemtime( $abs ) ),
				),
			);
		},
	) );

	// ===== FILESYSTEM — WRITE =====

	$reg->write( 'filesystem/write-file', array(
		'label'       => 'Write File',
		'description' => 'Write or append content to a file within the WordPress installation. Allowed extensions: css, js, json, md, txt, html, php.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'path', 'content' ),
			'properties' => array(
				'path'    => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
				'content' => array( 'type' => 'string', 'description' => 'The file content to write' ),
				'append'  => array( 'type' => 'boolean', 'description' => 'Append instead of overwrite (default: false)' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'bytes_written' => array( 'type' => 'integer' ),
			'path'          => array( 'type' => 'string' ),
			'mode'          => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'rest_forbidden', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return wp_abilities_error( 'rest_forbidden', 'File editing is disabled (DISALLOW_FILE_EDIT is set in wp-config.php).' );
			}

			$path    = $params['path'] ?? '';
			$content = $params['content'] ?? '';
			$append  = ! empty( $params['append'] );

			$ext_check = wp_abilities_filesystem_check_extension( $path );
			if ( is_wp_error( $ext_check ) ) {
				return $ext_check;
			}

			$abs = wp_abilities_filesystem_validate_path( $path, false );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			$flags  = $append ? FILE_APPEND : 0;
			$result = @file_put_contents( $abs, $content, $flags );
			if ( $result === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not write to file. Check permissions.' );
			}

			return array(
				'success'       => true,
				'bytes_written' => $result,
				'path'          => $path,
				'mode'          => $append ? 'append' : 'overwrite',
			);
		},
	) );

	$reg->write( 'theme/update-asset', array(
		'label'       => 'Update Theme Asset',
		'description' => "Write a file to the active theme's assets/ directory. Restricted to css, js, json, md extensions. Asset type is auto-detected from the filename extension if not provided.",
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'filename', 'content' ),
			'properties' => array(
				'asset_type' => array( 'type' => 'string', 'description' => 'Asset type: css, js, json, or md. Optional — auto-detected from filename extension if omitted.' ),
				'filename'   => array( 'type' => 'string', 'description' => 'Filename (e.g., custom.css)' ),
				'content'    => array( 'type' => 'string', 'description' => 'File content to write' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'theme' => array( 'type' => 'string' ),
			'path'  => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'rest_forbidden', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return wp_abilities_error( 'rest_forbidden', 'File editing is disabled (DISALLOW_FILE_EDIT is set in wp-config.php).' );
			}

			$filename      = sanitize_file_name( $params['filename'] ?? '' );
			$content       = $params['content'] ?? '';
			$allowed_types = array( 'css', 'js', 'json', 'md' );
			$ext           = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			// Auto-detect asset_type from extension if not provided.
			$asset_type = sanitize_text_field( $params['asset_type'] ?? $ext );

			if ( ! in_array( $asset_type, $allowed_types, true ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Asset type must be: ' . implode( ', ', $allowed_types ) );
			}

			if ( $ext !== $asset_type ) {
				return wp_abilities_error( 'ability_invalid_input', "Filename extension .{$ext} does not match asset_type '{$asset_type}'." );
			}

			$theme_dir  = get_stylesheet_directory();
			$assets_dir = $theme_dir . '/assets/' . $asset_type;

			if ( ! is_dir( $assets_dir ) ) {
				if ( ! wp_mkdir_p( $assets_dir ) ) {
					return wp_abilities_error( 'ability_invalid_input', "Could not create directory: assets/{$asset_type}/" );
				}
			}

			$target     = $assets_dir . '/' . $filename;
			$resolved   = wp_normalize_path( realpath( dirname( $target ) ) . '/' . basename( $target ) );
			$theme_norm = wp_normalize_path( $theme_dir );
			if ( strpos( $resolved, $theme_norm ) !== 0 ) {
				return wp_abilities_error( 'ability_invalid_input', 'Resolved path escapes the theme directory.' );
			}

			$result = @file_put_contents( $target, $content );
			if ( $result === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not write theme asset. Check permissions.' );
			}

			return array(
				'success' => true,
				'theme'   => wp_get_theme()->get( 'Name' ),
				'path'    => "assets/{$asset_type}/{$filename}",
			);
		},
	) );

	$reg->write( 'filesystem/create-directory', array(
		'label'       => 'Create Directory',
		'description' => 'Create a directory within the WordPress installation. Creates parent directories recursively (like mkdir -p). Permissions set to 0755.',
		'tier'        => 'free',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'path' ),
			'properties' => array(
				'path' => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'path'    => array( 'type' => 'string' ),
			'created' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'rest_forbidden', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}

			$path = $params['path'] ?? '';
			if ( empty( $path ) || ! is_string( $path ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path is required and must be a string.' );
			}

			if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $path ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path traversal (../) is not allowed.' );
			}

			$normalized = wp_normalize_path( ABSPATH . ltrim( $path, '/' ) );
			$abspath    = wp_normalize_path( ABSPATH );

			if ( strpos( $normalized, $abspath ) !== 0 ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path resolves outside the WordPress installation.' );
			}

			if ( is_dir( $normalized ) ) {
				return array( 'success' => true, 'path' => $path, 'created' => false );
			}

			$result = wp_mkdir_p( $normalized );
			if ( ! $result ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not create directory. Check permissions.' );
			}

			return array( 'success' => true, 'path' => $path, 'created' => true );
		},
	) );

	$reg->write( 'filesystem/write-binary', array(
		'label'       => 'Write Binary File',
		'description' => 'Write base64-encoded binary content to a file within the WordPress installation. Allowed extensions: woff2, woff, ttf, otf, eot, ico, png, jpg, jpeg, webp, gif, svg, avif.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'path', 'content' ),
			'properties' => array(
				'path'    => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
				'content' => array( 'type' => 'string', 'description' => 'Base64-encoded binary content' ),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'bytes_written' => array( 'type' => 'integer' ),
			'path'          => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'rest_forbidden', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return wp_abilities_error( 'rest_forbidden', 'File editing is disabled (DISALLOW_FILE_EDIT is set in wp-config.php).' );
			}

			$path    = $params['path'] ?? '';
			$content = $params['content'] ?? '';

			$allowed_binary = array( 'woff2', 'woff', 'ttf', 'otf', 'eot', 'ico', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif' );
			$ext            = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed_binary, true ) ) {
				return wp_abilities_error( 'ability_invalid_input', "Extension .{$ext} is not in the allowed binary list: " . implode( ', ', $allowed_binary ) );
			}

			$abs = wp_abilities_filesystem_validate_path( $path, false );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			$decoded = base64_decode( $content, true );
			if ( $decoded === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Content is not valid base64.' );
			}

			$result = @file_put_contents( $abs, $decoded );
			if ( $result === false ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not write to file. Check permissions.' );
			}

			return array(
				'success'       => true,
				'bytes_written' => $result,
				'path'          => $path,
			);
		},
	) );

	// ===== FILESYSTEM — DELETE =====

	$reg->delete( 'filesystem/delete-file', array(
		'label'       => 'Delete File',
		'description' => 'Delete a file within the WordPress installation. Allowed extensions: css, js, json, md, txt, html, php.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'path' ),
			'properties' => array(
				'path' => array(
					'type'        => 'string',
					'description' => 'Relative path from ABSPATH',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_success_output( array(
			'path'    => array( 'type' => 'string' ),
			'deleted' => array( 'type' => 'boolean' ),
		) ),
		'callback' => function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'rest_forbidden', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}

			$path = $params['path'] ?? '';

			$ext_check = wp_abilities_filesystem_check_extension( $path );
			if ( is_wp_error( $ext_check ) ) {
				return $ext_check;
			}

			$abs = wp_abilities_filesystem_validate_path( $path, true );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			if ( ! is_file( $abs ) ) {
				return wp_abilities_error( 'ability_invalid_input', 'Path is not a file.' );
			}

			$result = @unlink( $abs );
			if ( ! $result ) {
				return wp_abilities_error( 'ability_invalid_input', 'Could not delete file. Check permissions.' );
			}

			return array( 'success' => true, 'path' => $path, 'deleted' => true );
		},
	) );
} );
