<?php

defined( 'ABSPATH' ) || exit;

// Register all WordPress media management abilities
add_action( 'wp_abilities_api_init', function() {

    $perms = wp_abilities_suite_get_permissions( 'media' );

    // ===== MEDIA — READ =====
    if ( $perms['read'] ) {

    // List media
    wp_register_ability( 'media/list', array(
        'label' => 'List Media',
        'description' => 'List media items from the WordPress media library with filtering and pagination',
        'category' => 'media',
        'input_schema' => array(
            'type' => 'object',
            'properties' => array(
                'per_page' => array(
                    'type' => 'integer',
                    'description' => 'Items per page',
                    'default' => 20,
                    'minimum' => 1,
                    'maximum' => 100
                ),
                'page' => array(
                    'type' => 'integer',
                    'description' => 'Page number',
                    'default' => 1,
                    'minimum' => 1
                ),
                'mime_type' => array(
                    'type' => 'string',
                    'description' => 'Filter by MIME type (e.g., "image", "image/jpeg", "video", "application/pdf")'
                ),
                'search' => array(
                    'type' => 'string',
                    'description' => 'Search term for filename or title'
                ),
                'orderby' => array(
                    'type' => 'string',
                    'enum' => array('date', 'title', 'modified', 'ID'),
                    'default' => 'date',
                    'description' => 'Field to order by'
                ),
                'order' => array(
                    'type' => 'string',
                    'enum' => array('ASC', 'DESC'),
                    'default' => 'DESC',
                    'description' => 'Sort order'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'media' => array('type' => 'array', 'items' => array('type' => 'object')),
                'total' => array('type' => 'integer'),
                'pages' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $per_page = min( $input['per_page'] ?? 20, 100 );
            $page = $input['page'] ?? 1;

            $args = array(
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => $per_page,
                'paged' => $page,
                'orderby' => $input['orderby'] ?? 'date',
                'order' => $input['order'] ?? 'DESC'
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

                $media[] = array(
                    'id' => $attachment->ID,
                    'title' => $attachment->post_title,
                    'caption' => $attachment->post_excerpt,
                    'description' => $attachment->post_content,
                    'alt_text' => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
                    'mime_type' => $attachment->post_mime_type,
                    'url' => wp_get_attachment_url( $attachment->ID ),
                    'date' => $attachment->post_date,
                    'modified' => $attachment->post_modified,
                    'author' => $attachment->post_author,
                    'file_size' => $file_size,
                    'filename' => basename( $file_path ),
                    'metadata' => wp_get_attachment_metadata( $attachment->ID )
                );
            }

            return array(
                'media' => $media,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'upload_files' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end read

    // ===== MEDIA — WRITE =====
    if ( ! empty( $perms['write'] ) ) {

    // Create (upload) media
    wp_register_ability( 'media/create', array(
        'label' => 'Upload Media',
        'description' => 'Upload media from a URL to the WordPress media library',
        'category' => 'media',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('url'),
            'properties' => array(
                'url' => array(
                    'type' => 'string',
                    'description' => 'URL of the file to upload'
                ),
                'title' => array(
                    'type' => 'string',
                    'description' => 'Title for the media item'
                ),
                'caption' => array(
                    'type' => 'string',
                    'description' => 'Caption for the media item'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Description for the media item'
                ),
                'alt_text' => array(
                    'type' => 'string',
                    'description' => 'Alt text for images'
                ),
                'post_id' => array(
                    'type' => 'integer',
                    'description' => 'Attach to a specific post (optional)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'url' => array('type' => 'string'),
                'title' => array('type' => 'string'),
                'mime_type' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'download_url' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'media_handle_sideload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            if ( ! function_exists( 'wp_read_image_metadata' ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            $url = esc_url_raw( $input['url'] );

            // Download file to temp location
            $tmp = download_url( $url );

            if ( is_wp_error( $tmp ) ) {
                return $tmp;
            }

            // Get file info
            $file_array = array(
                'name' => basename( parse_url( $url, PHP_URL_PATH ) ),
                'tmp_name' => $tmp
            );

            // Upload to media library
            $attachment_id = media_handle_sideload( $file_array, $input['post_id'] ?? 0 );

            // Clean up temp file
            @unlink( $tmp );

            if ( is_wp_error( $attachment_id ) ) {
                return $attachment_id;
            }

            // Update title if provided
            if ( ! empty( $input['title'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_title' => sanitize_text_field( $input['title'] )
                ));
            }

            // Update caption if provided
            if ( ! empty( $input['caption'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_excerpt' => sanitize_textarea_field( $input['caption'] )
                ));
            }

            // Update description if provided
            if ( ! empty( $input['description'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_content' => sanitize_textarea_field( $input['description'] )
                ));
            }

            // Update alt text if provided (for images)
            if ( ! empty( $input['alt_text'] ) ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
            }

            $attachment = get_post( $attachment_id );

            return array(
                'id' => $attachment_id,
                'url' => wp_get_attachment_url( $attachment_id ),
                'title' => $attachment->post_title,
                'mime_type' => $attachment->post_mime_type
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'upload_files' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    // Upload media from base64
    wp_register_ability( 'media/upload', array(
        'label' => 'Upload Media from Base64',
        'description' => 'Upload media directly from base64-encoded file data',
        'category' => 'media',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('file_data', 'filename'),
            'properties' => array(
                'file_data' => array(
                    'type' => 'string',
                    'description' => 'Base64-encoded file content'
                ),
                'filename' => array(
                    'type' => 'string',
                    'description' => 'Filename with extension (e.g., "image.png")'
                ),
                'title' => array(
                    'type' => 'string',
                    'description' => 'Title for the media item'
                ),
                'caption' => array(
                    'type' => 'string',
                    'description' => 'Caption for the media item'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'Description for the media item'
                ),
                'alt_text' => array(
                    'type' => 'string',
                    'description' => 'Alt text for images'
                ),
                'post_id' => array(
                    'type' => 'integer',
                    'description' => 'Attach to a specific post (optional)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer'),
                'url' => array('type' => 'string'),
                'title' => array('type' => 'string'),
                'mime_type' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            if ( ! function_exists( 'wp_handle_sideload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'media_handle_sideload' ) ) {
                require_once ABSPATH . 'wp-admin/includes/media.php';
            }
            if ( ! function_exists( 'wp_read_image_metadata' ) ) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }

            // Decode base64 data
            $file_data = base64_decode( $input['file_data'], true );

            if ( $file_data === false ) {
                return new WP_Error( 'invalid_base64', 'Invalid base64 data provided' );
            }

            // Validate filename
            $filename = sanitize_file_name( $input['filename'] );
            if ( empty( $filename ) ) {
                return new WP_Error( 'invalid_filename', 'Invalid filename provided' );
            }

            // Create temporary file
            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['basedir'] . '/temp_' . uniqid() . '_' . $filename;

            // Write decoded data to temp file
            $bytes_written = file_put_contents( $temp_file, $file_data );

            if ( $bytes_written === false ) {
                return new WP_Error( 'file_write_failed', 'Failed to write temporary file' );
            }

            // Prepare file array for sideload
            $file_array = array(
                'name' => $filename,
                'tmp_name' => $temp_file,
                'size' => $bytes_written
            );

            // Upload to media library
            $attachment_id = media_handle_sideload( $file_array, $input['post_id'] ?? 0 );

            // Clean up temp file
            @unlink( $temp_file );

            if ( is_wp_error( $attachment_id ) ) {
                return $attachment_id;
            }

            // Update title if provided
            if ( ! empty( $input['title'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_title' => sanitize_text_field( $input['title'] )
                ));
            }

            // Update caption if provided
            if ( ! empty( $input['caption'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_excerpt' => sanitize_textarea_field( $input['caption'] )
                ));
            }

            // Update description if provided
            if ( ! empty( $input['description'] ) ) {
                wp_update_post( array(
                    'ID' => $attachment_id,
                    'post_content' => sanitize_textarea_field( $input['description'] )
                ));
            }

            // Update alt text if provided (for images)
            if ( ! empty( $input['alt_text'] ) ) {
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
            }

            $attachment = get_post( $attachment_id );

            return array(
                'id' => $attachment_id,
                'url' => wp_get_attachment_url( $attachment_id ),
                'title' => $attachment->post_title,
                'mime_type' => $attachment->post_mime_type
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'upload_files' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => false
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
        )
    ));

    // Update media
    wp_register_ability( 'media/update', array(
        'label' => 'Update Media',
        'description' => 'Update media item metadata (title, caption, description, alt text)',
        'category' => 'media',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Attachment ID'
                ),
                'title' => array(
                    'type' => 'string',
                    'description' => 'New title'
                ),
                'caption' => array(
                    'type' => 'string',
                    'description' => 'New caption'
                ),
                'description' => array(
                    'type' => 'string',
                    'description' => 'New description'
                ),
                'alt_text' => array(
                    'type' => 'string',
                    'description' => 'New alt text (for images)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'id' => array('type' => 'integer')
            )
        ),
        'execute_callback' => function( $input ) {
            $attachment_id = (int) $input['id'];

            $attachment = get_post( $attachment_id );
            if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                return new WP_Error( 'not_found', 'Attachment not found' );
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

            return array(
                'success' => true,
                'id' => $attachment_id
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'upload_files' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => false,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end write

    // ===== MEDIA — DELETE =====
    if ( ! empty( $perms['delete'] ) ) {

    // Delete media
    wp_register_ability( 'media/delete', array(
        'label' => 'Delete Media',
        'description' => 'Delete a media item from the library',
        'category' => 'media',
        'input_schema' => array(
            'type' => 'object',
            'required' => array('id'),
            'properties' => array(
                'id' => array(
                    'type' => 'integer',
                    'description' => 'Attachment ID to delete'
                ),
                'force' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Force permanent deletion (skip trash)'
                )
            )
        ),
        'output_schema' => array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean'),
                'message' => array('type' => 'string')
            )
        ),
        'execute_callback' => function( $input ) {
            $attachment_id = (int) $input['id'];
            $force = $input['force'] ?? false;

            $attachment = get_post( $attachment_id );
            if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
                return new WP_Error( 'not_found', 'Attachment not found' );
            }

            $result = wp_delete_attachment( $attachment_id, $force );

            if ( ! $result ) {
                return new WP_Error( 'delete_failed', 'Failed to delete attachment' );
            }

            return array(
                'success' => true,
                'message' => $force ? 'Attachment permanently deleted' : 'Attachment moved to trash'
            );
        },
        'permission_callback' => function() {
            return current_user_can( 'delete_posts' );
        },
        'meta' => array(
            'annotations' => array(
                'readonly' => false,
                'destructive' => true,
                'idempotent' => true
            ),
            'show_in_rest' => true,
            'mcp' => array( 'public' => true, 'type' => 'tool' ),
                    )
    ));

    } // end delete

    error_log( 'WordPress Media Abilities: Registered 5 media management abilities' );

}, 100 );
