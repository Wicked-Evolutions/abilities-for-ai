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
 * @package WordPress_Abilities_Suite
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
		return wp_abilities_error( 'invalid_path', 'Path is required and must be a string.' );
	}

	// Reject traversal sequences before any resolution.
	if ( preg_match( '#(^|[/\\\\])\.\.([/\\\\]|$)#', $relative_path ) ) {
		return wp_abilities_error( 'traversal_rejected', 'Path traversal (../) is not allowed.' );
	}

	$normalized = wp_normalize_path( ABSPATH . ltrim( $relative_path, '/' ) );

	if ( $must_exist ) {
		$resolved = realpath( $normalized );
		if ( $resolved === false ) {
			return wp_abilities_error( 'not_found', 'Path does not exist.' );
		}
		$resolved = wp_normalize_path( $resolved );
		// Must be within ABSPATH.
		if ( strpos( $resolved, wp_normalize_path( ABSPATH ) ) !== 0 ) {
			return wp_abilities_error( 'outside_abspath', 'Path resolves outside the WordPress installation.' );
		}
		return $resolved;
	}

	// For new files: validate the parent directory exists and is inside ABSPATH.
	$parent = dirname( $normalized );
	$resolved_parent = realpath( $parent );
	if ( $resolved_parent === false ) {
		return wp_abilities_error( 'parent_not_found', 'Parent directory does not exist.' );
	}
	$resolved_parent = wp_normalize_path( $resolved_parent );
	if ( strpos( $resolved_parent, wp_normalize_path( ABSPATH ) ) !== 0 ) {
		return wp_abilities_error( 'outside_abspath', 'Target directory resolves outside the WordPress installation.' );
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
	$allowed  = array( 'css', 'js', 'json', 'md', 'txt', 'html' );
	$blocked  = array( 'php', 'phtml', 'htaccess', 'sh', 'exe', 'bat' );

	$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	if ( in_array( $ext, $blocked, true ) ) {
		return wp_abilities_error( 'extension_blocked', "Writing to .{$ext} files is not allowed." );
	}
	if ( ! in_array( $ext, $allowed, true ) ) {
		return wp_abilities_error( 'extension_not_allowed', "Extension .{$ext} is not in the allowed list: " . implode( ', ', $allowed ) );
	}

	return true;
}

// ============================================================
// Ability Registration
// ============================================================

add_action( 'wp_abilities_api_init', 'wp_abilities_suite_register_filesystem_abilities' );

function wp_abilities_suite_register_filesystem_abilities() {

	$perms = wp_abilities_suite_get_permissions( 'filesystem' );

	// ===== FILESYSTEM — READ =====
	if ( $perms['read'] ) {

	// ---- filesystem/list-directory ----
	wp_register_ability( 'filesystem/list-directory', array(
		'label'       => 'List Directory',
		'description' => 'List files and folders in a directory within the WordPress installation.',
		'category'    => 'filesystem',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'path' => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH (default: root)' ),
			),
		),
		'execute_callback' => function( $params ) {
			$path = $params['path'] ?? '';
			if ( empty( $path ) ) {
				$path = '.';
			}

			$abs = wp_abilities_filesystem_validate_path( $path, true );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			if ( ! is_dir( $abs ) ) {
				return wp_abilities_error( 'not_a_directory', 'Path is not a directory.' );
			}

			$entries = @scandir( $abs );
			if ( $entries === false ) {
				return wp_abilities_error( 'read_failed', 'Could not read directory.' );
			}

			$items = array();
			foreach ( $entries as $entry ) {
				if ( $entry === '.' || $entry === '..' ) {
					continue;
				}
				$full = $abs . '/' . $entry;
				$stat = @stat( $full );
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
				'count' => count( $items ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	// ---- filesystem/read-file ----
	wp_register_ability( 'filesystem/read-file', array(
		'label'       => 'Read File',
		'description' => 'Read the content of a file within the WordPress installation. Limited to 1MB.',
		'category'    => 'filesystem',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'path' => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
			),
			'required' => array( 'path' ),
		),
		'execute_callback' => function( $params ) {
			$abs = wp_abilities_filesystem_validate_path( $params['path'] ?? '', true );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			if ( ! is_file( $abs ) ) {
				return wp_abilities_error( 'not_a_file', 'Path is not a file.' );
			}

			$size = filesize( $abs );
			if ( $size > MB_IN_BYTES ) {
				return wp_abilities_error( 'file_too_large', 'File exceeds 1MB limit (' . size_format( $size ) . ').' );
			}

			$content = @file_get_contents( $abs );
			if ( $content === false ) {
				return wp_abilities_error( 'read_failed', 'Could not read file.' );
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
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'free',),
	));

	} // end read

	// ===== FILESYSTEM — WRITE =====
	// Always register write abilities — permission check happens at execution time (pro gate + DISALLOW_FILE_EDIT).
	// ---- filesystem/write-file ----
	wp_register_ability( 'filesystem/write-file', array(
		'label'       => 'Write File',
		'description' => 'Write or append content to a file within the WordPress installation. Restricted to safe extensions (css, js, json, md, txt, html). PHP files are blocked.',
		'category'    => 'filesystem',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'path'    => array( 'type' => 'string', 'description' => 'Relative path from ABSPATH' ),
				'content' => array( 'type' => 'string', 'description' => 'The file content to write' ),
				'append'  => array( 'type' => 'boolean', 'description' => 'Append instead of overwrite (default: false)' ),
			),
			'required' => array( 'path', 'content' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('filesystem/write-file', function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'file_mods_disabled', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return wp_abilities_error( 'file_edit_disabled', 'File editing is disabled (DISALLOW_FILE_EDIT is set in wp-config.php).' );
			}

			$path    = $params['path'] ?? '';
			$content = $params['content'] ?? '';
			$append  = ! empty( $params['append'] );

			// Extension check first (before path resolution, for early rejection).
			$ext_check = wp_abilities_filesystem_check_extension( $path );
			if ( is_wp_error( $ext_check ) ) {
				return $ext_check;
			}

			// For writes, target may not exist yet.
			$abs = wp_abilities_filesystem_validate_path( $path, false );
			if ( is_wp_error( $abs ) ) {
				return $abs;
			}

			$flags  = $append ? FILE_APPEND : 0;
			$result = @file_put_contents( $abs, $content, $flags );

			if ( $result === false ) {
				return wp_abilities_error( 'write_failed', 'Could not write to file. Check permissions.' );
			}

			return array(
				'success'       => true,
				'bytes_written' => $result,
				'path'          => $path,
				'mode'          => $append ? 'append' : 'overwrite',
			);
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'pro',),
	));

	// ---- theme/update-asset ----
	wp_register_ability( 'theme/update-asset', array(
		'label'       => 'Update Theme Asset',
		'description' => 'Write a file to the active theme\'s assets/ directory. Restricted to css, js, json, md extensions.',
		'category'    => 'filesystem',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'asset_type' => array( 'type' => 'string', 'description' => 'Asset type: css, js, json, or md' ),
				'filename'   => array( 'type' => 'string', 'description' => 'Filename (e.g., custom.css)' ),
				'content'    => array( 'type' => 'string', 'description' => 'File content to write' ),
			),
			'required' => array( 'asset_type', 'filename', 'content' ),
		),
		'execute_callback' => wp_abilities_suite_pro_gate('theme/update-asset', function( $params ) {
			if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) {
				return wp_abilities_error( 'file_mods_disabled', 'File modifications are disabled (DISALLOW_FILE_MODS is set in wp-config.php).' );
			}
			if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
				return wp_abilities_error( 'file_edit_disabled', 'File editing is disabled (DISALLOW_FILE_EDIT is set in wp-config.php).' );
			}

			$asset_type = sanitize_text_field( $params['asset_type'] ?? '' );
			$filename   = sanitize_file_name( $params['filename'] ?? '' );
			$content    = $params['content'] ?? '';

			$allowed_types = array( 'css', 'js', 'json', 'md' );
			if ( ! in_array( $asset_type, $allowed_types, true ) ) {
				return wp_abilities_error( 'invalid_asset_type', 'Asset type must be: ' . implode( ', ', $allowed_types ) );
			}

			// Verify file extension matches declared asset_type.
			$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			if ( $ext !== $asset_type ) {
				return wp_abilities_error( 'extension_mismatch', "Filename extension .{$ext} does not match asset_type '{$asset_type}'." );
			}

			$theme_dir = get_stylesheet_directory();
			$assets_dir = $theme_dir . '/assets/' . $asset_type;

			// Create subdirectory if needed.
			if ( ! is_dir( $assets_dir ) ) {
				if ( ! wp_mkdir_p( $assets_dir ) ) {
					return wp_abilities_error( 'mkdir_failed', "Could not create directory: assets/{$asset_type}/" );
				}
			}

			$target = $assets_dir . '/' . $filename;

			// Verify the resolved path is still inside the theme.
			$resolved = wp_normalize_path( realpath( dirname( $target ) ) . '/' . basename( $target ) );
			$theme_norm = wp_normalize_path( $theme_dir );
			if ( strpos( $resolved, $theme_norm ) !== 0 ) {
				return wp_abilities_error( 'outside_theme', 'Resolved path escapes the theme directory.' );
			}

			$result = @file_put_contents( $target, $content );
			if ( $result === false ) {
				return wp_abilities_error( 'write_failed', 'Could not write theme asset. Check permissions.' );
			}

			return array(
				'success' => true,
				'theme'   => wp_get_theme()->get( 'Name' ),
				'path'    => "assets/{$asset_type}/{$filename}",
			);
		}),
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) , 'tier' => 'pro',),
	));

}
