<?php
/**
 * Media Abilities
 *
 * WordPress media library management.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'media', 'upload_files' );

	// ===== MEDIA — READ =====

	$reg->read( 'media/list', array(
		'label'       => 'List Media',
		'description' => 'List media items from the WordPress media library with filtering and pagination',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Items per page',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => 100,
				),
				'page' => array(
					'type'        => 'integer',
					'description' => 'Page number',
					'default'     => 1,
					'minimum'     => 1,
				),
				'mime_type' => array(
					'type'        => 'string',
					'description' => 'Filter by MIME type (e.g., "image", "image/jpeg", "video", "application/pdf")',
				),
				'search' => array(
					'type'        => 'string',
					'description' => 'Search term for filename or title',
				),
				'orderby' => array(
					'type'        => 'string',
					'enum'        => array( 'date', 'title', 'modified', 'ID' ),
					'default'     => 'date',
					'description' => 'Field to order by',
				),
				'order' => array(
					'type'        => 'string',
					'enum'        => array( 'ASC', 'DESC' ),
					'default'     => 'DESC',
					'description' => 'Sort order',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_list_output( 'media', array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'url'       => array( 'type' => 'string' ),
			'mime_type' => array( 'type' => 'string' ),
			'date'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$per_page = min( $input['per_page'] ?? 20, 100 );
			$page     = $input['page'] ?? 1;

			$args = array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page,
				'paged'          => $page,
				'orderby'        => $input['orderby'] ?? 'date',
				'order'          => $input['order'] ?? 'DESC',
			);

			if ( ! empty( $input['mime_type'] ) ) {
				$args['post_mime_type'] = $input['mime_type'];
			}
			if ( ! empty( $input['search'] ) ) {
				$args['s'] = $input['search'];
			}

			$query = new WP_Query( $args );
			$media = array();
			foreach ( $query->posts as $attachment ) {
				$file_path = get_attached_file( $attachment->ID );
				$file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
				$media[]   = array(
					'id'          => $attachment->ID,
					'title'       => $attachment->post_title,
					'caption'     => $attachment->post_excerpt,
					'description' => $attachment->post_content,
					'alt_text'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
					'mime_type'   => $attachment->post_mime_type,
					'url'         => wp_get_attachment_url( $attachment->ID ),
					'date'        => $attachment->post_date,
					'modified'    => $attachment->post_modified,
					'author'      => $attachment->post_author,
					'file_size'   => $file_size,
					'filename'    => basename( $file_path ),
					'metadata'    => wp_get_attachment_metadata( $attachment->ID ),
				);
			}
			return array(
				'media' => $media,
				'total' => $query->found_posts,
				'pages' => $query->max_num_pages,
			);
		},
	) );

	$reg->read( 'media/get', array(
		'label'       => 'Get Media Item',
		'description' => 'Get detailed information about a specific media attachment by ID, including metadata, sizes, and file info.',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array( 'type' => 'integer', 'description' => 'Attachment ID' ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'id'        => array( 'type' => 'integer' ),
			'title'     => array( 'type' => 'string' ),
			'url'       => array( 'type' => 'string' ),
			'mime_type' => array( 'type' => 'string' ),
			'date'      => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$attachment = get_post( $input['id'] );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				return new WP_Error( 'not_found', 'Media item not found' );
			}

			$file_path = get_attached_file( $attachment->ID );
			$file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
			$metadata  = wp_get_attachment_metadata( $attachment->ID );

			$result = array(
				'id'          => $attachment->ID,
				'title'       => $attachment->post_title,
				'caption'     => $attachment->post_excerpt,
				'description' => $attachment->post_content,
				'alt_text'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
				'mime_type'   => $attachment->post_mime_type,
				'url'         => wp_get_attachment_url( $attachment->ID ),
				'date'        => $attachment->post_date,
				'modified'    => $attachment->post_modified,
				'author'      => (int) $attachment->post_author,
				'file_size'   => $file_size,
				'filename'    => basename( $file_path ),
				'parent'      => (int) $attachment->post_parent,
				'metadata'    => $metadata,
			);

			// Add image sizes if it's an image.
			if ( wp_attachment_is_image( $attachment->ID ) && ! empty( $metadata['sizes'] ) ) {
				$sizes = array();
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					$sizes[ $size_name ] = array(
						'width'  => $size_data['width'],
						'height' => $size_data['height'],
						'url'    => wp_get_attachment_image_url( $attachment->ID, $size_name ),
					);
				}
				$result['sizes'] = $sizes;
			}

			return $result;
		},
	) );

	// ===== MEDIA — WRITE =====

	$reg->write( 'media/create', array(
		'label'       => 'Upload Media',
		'description' => 'Upload media from a URL to the WordPress media library',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'url' ),
			'properties' => array(
				'url' => array(
					'type'        => 'string',
					'description' => 'URL of the file to upload',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Title for the media item',
				),
				'caption' => array(
					'type'        => 'string',
					'description' => 'Caption for the media item',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Description for the media item',
				),
				'alt_text' => array(
					'type'        => 'string',
					'description' => 'Alt text for images',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Attach to a specific post (optional)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id'        => array( 'type' => 'integer' ),
			'url'       => array( 'type' => 'string' ),
			'title'     => array( 'type' => 'string' ),
			'mime_type' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'download_url' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			if ( ! function_exists( 'wp_read_image_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$url    = esc_url_raw( $input['url'] );
			$parsed = wp_parse_url( $url );
			if ( ! $parsed || ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
				return new WP_Error( 'ability_invalid_input', 'Only http and https URLs are allowed.' );
			}

			$host        = $parsed['host'] ?? '';
			$resolved_ip = gethostbyname( $host );
			if ( $resolved_ip === $host ) {
				return new WP_Error( 'ability_invalid_input', 'Could not resolve hostname.' );
			}
			if ( wp_abilities_suite_is_private_ip( $resolved_ip ) ) {
				return new WP_Error( 'ability_invalid_input', 'URLs pointing to private/internal IP addresses are not allowed.' );
			}

			$pin_dns = function( $args ) use ( $host, $resolved_ip, &$pin_dns ) {
				remove_filter( 'http_request_args', $pin_dns, 1 );
				if ( ! isset( $args['curl'] ) || ! is_array( $args['curl'] ) ) {
					$args['curl'] = array();
				}
				$args['curl'][ CURLOPT_RESOLVE ] = array(
					"{$host}:443:{$resolved_ip}",
					"{$host}:80:{$resolved_ip}",
				);
				$args['reject_unsafe_urls'] = true;
				$args['redirection']        = 3;
				return $args;
			};
			add_filter( 'http_request_args', $pin_dns, 1 );

			$tmp = download_url( $url );
			remove_filter( 'http_request_args', $pin_dns, 1 );

			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}

			$max_size = 10 * MB_IN_BYTES;
			if ( filesize( $tmp ) > $max_size ) {
				@unlink( $tmp );
				return new WP_Error( 'ability_invalid_input', 'Downloaded file exceeds maximum allowed size of ' . size_format( $max_size ) . '.' );
			}

			$file_array = array(
				'name'     => basename( parse_url( $url, PHP_URL_PATH ) ),
				'tmp_name' => $tmp,
			);

			$attachment_id = media_handle_sideload( $file_array, $input['post_id'] ?? 0 );
			@unlink( $tmp );

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			if ( ! empty( $input['title'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_title' => sanitize_text_field( $input['title'] ) ) );
			}
			if ( ! empty( $input['caption'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_excerpt' => sanitize_textarea_field( $input['caption'] ) ) );
			}
			if ( ! empty( $input['description'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_content' => sanitize_textarea_field( $input['description'] ) ) );
			}
			if ( ! empty( $input['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
			}

			$attachment = get_post( $attachment_id );
			return array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'title'     => $attachment->post_title,
				'mime_type' => $attachment->post_mime_type,
			);
		},
	) );

	$reg->write( 'media/upload', array(
		'label'       => 'Upload Media from Base64',
		'description' => 'Upload media directly from base64-encoded file data',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'file_data', 'filename' ),
			'properties' => array(
				'file_data' => array(
					'type'        => 'string',
					'description' => 'Base64-encoded file content',
				),
				'filename' => array(
					'type'        => 'string',
					'description' => 'Filename with extension (e.g., "image.png")',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'Title for the media item',
				),
				'caption' => array(
					'type'        => 'string',
					'description' => 'Caption for the media item',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'Description for the media item',
				),
				'alt_text' => array(
					'type'        => 'string',
					'description' => 'Alt text for images',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Attach to a specific post (optional)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id'        => array( 'type' => 'integer' ),
			'url'       => array( 'type' => 'string' ),
			'title'     => array( 'type' => 'string' ),
			'mime_type' => array( 'type' => 'string' ),
		) ),
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		'callback' => function( $input ) {
			if ( ! function_exists( 'wp_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( ! function_exists( 'media_handle_sideload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
			}
			if ( ! function_exists( 'wp_read_image_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$max_size       = 10 * MB_IN_BYTES;
			$estimated_size = (int) ( strlen( $input['file_data'] ) * 0.75 );
			if ( $estimated_size > $max_size ) {
				return new WP_Error( 'ability_invalid_input', 'Base64 data exceeds maximum allowed size of ' . size_format( $max_size ) . '.' );
			}

			$file_data = base64_decode( $input['file_data'], true );
			if ( $file_data === false ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid base64 data provided' );
			}
			if ( strlen( $file_data ) > $max_size ) {
				return new WP_Error( 'ability_invalid_input', 'Decoded file exceeds maximum allowed size of ' . size_format( $max_size ) . '.' );
			}

			$filename = sanitize_file_name( $input['filename'] );
			if ( empty( $filename ) ) {
				return new WP_Error( 'ability_invalid_input', 'Invalid filename provided' );
			}

			$upload_dir    = wp_upload_dir();
			$temp_file     = $upload_dir['basedir'] . '/temp_' . uniqid() . '_' . $filename;
			$bytes_written = file_put_contents( $temp_file, $file_data );
			if ( $bytes_written === false ) {
				return new WP_Error( 'ability_invalid_input', 'Failed to write temporary file' );
			}

			$file_array    = array(
				'name'     => $filename,
				'tmp_name' => $temp_file,
				'size'     => $bytes_written,
			);
			$attachment_id = media_handle_sideload( $file_array, $input['post_id'] ?? 0 );
			@unlink( $temp_file );

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			if ( ! empty( $input['title'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_title' => sanitize_text_field( $input['title'] ) ) );
			}
			if ( ! empty( $input['caption'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_excerpt' => sanitize_textarea_field( $input['caption'] ) ) );
			}
			if ( ! empty( $input['description'] ) ) {
				wp_update_post( array( 'ID' => $attachment_id, 'post_content' => sanitize_textarea_field( $input['description'] ) ) );
			}
			if ( ! empty( $input['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
			}

			$attachment = get_post( $attachment_id );
			return array(
				'id'        => $attachment_id,
				'url'       => wp_get_attachment_url( $attachment_id ),
				'title'     => $attachment->post_title,
				'mime_type' => $attachment->post_mime_type,
			);
		},
	) );

	$reg->write( 'media/update', array(
		'label'       => 'Update Media',
		'description' => 'Update media item metadata (title, caption, description, alt text)',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Attachment ID',
				),
				'title' => array(
					'type'        => 'string',
					'description' => 'New title',
				),
				'caption' => array(
					'type'        => 'string',
					'description' => 'New caption',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'New description',
				),
				'alt_text' => array(
					'type'        => 'string',
					'description' => 'New alt text (for images)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'id' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $input ) {
			$attachment_id = (int) $input['id'];
			$attachment    = get_post( $attachment_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				return new WP_Error( 'not_found', 'Attachment not found' );
			}
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to edit this attachment.' );
			}
			$update_data = array( 'ID' => $attachment_id );
			if ( isset( $input['title'] ) ) {
				$update_data['post_title'] = sanitize_text_field( $input['title'] );
			}
			if ( isset( $input['caption'] ) ) {
				$update_data['post_excerpt'] = sanitize_textarea_field( $input['caption'] );
			}
			if ( isset( $input['description'] ) ) {
				$update_data['post_content'] = sanitize_textarea_field( $input['description'] );
			}
			if ( count( $update_data ) > 1 ) {
				$result = wp_update_post( $update_data, true );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			if ( isset( $input['alt_text'] ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
			}
			return array( 'success' => true, 'id' => $attachment_id );
		},
	) );

	// ===== MEDIA — DELETE =====

	$reg->delete( 'media/delete', array(
		'capability'  => 'delete_posts',
		'label'       => 'Delete Media',
		'description' => 'Delete a media item from the library',
		'input_schema' => array(
			'type'       => 'object',
			'required'   => array( 'id' ),
			'properties' => array(
				'id' => array(
					'type'        => 'integer',
					'description' => 'Attachment ID to delete',
				),
				'force' => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Force permanent deletion (skip trash)',
				),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'message' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $input ) {
			$attachment_id = (int) $input['id'];
			$force         = $input['force'] ?? false;
			$attachment    = get_post( $attachment_id );
			if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
				return new WP_Error( 'not_found', 'Attachment not found' );
			}
			if ( ! current_user_can( 'delete_post', $attachment_id ) ) {
				return new WP_Error( 'rest_forbidden', 'You do not have permission to delete this attachment.' );
			}
			$result = wp_delete_attachment( $attachment_id, $force );
			if ( ! $result ) {
				return new WP_Error( 'ability_invalid_input', 'Failed to delete attachment' );
			}
			return array(
				'success' => true,
				'message' => $force ? 'Attachment permanently deleted' : 'Attachment moved to trash',
			);
		},
	) );
} );
