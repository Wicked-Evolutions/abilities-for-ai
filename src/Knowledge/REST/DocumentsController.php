<?php
/**
 * Knowledge Layer — Documents REST Controller.
 *
 * Thin REST layer over Document, Revision, Tag, and Taggable models.
 * Namespace: abilities-kl/v1/documents
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge\REST
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge\REST;

use WickedEvolutions\AbilitiesForAI\Knowledge\Document;
use WickedEvolutions\AbilitiesForAI\Knowledge\MarkdownToBlocks;
use WickedEvolutions\AbilitiesForAI\Knowledge\Revision;
use WickedEvolutions\AbilitiesForAI\Knowledge\Tag;
use WickedEvolutions\AbilitiesForAI\Knowledge\Taggable;

defined( 'ABSPATH' ) || exit;

class DocumentsController extends \WP_REST_Controller {

	protected $namespace = 'abilities-kl/v1';
	protected $rest_base = 'documents';

	public function register_routes() {
		// GET /documents
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'admin_check' ),
				'args'                => $this->get_collection_params(),
			),
			// POST /documents
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
		) );

		// GET|PUT|DELETE /documents/{id}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'admin_check' ),
			),
		) );

		// POST /documents/{id}/fork
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/fork', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'fork_item' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// GET /documents/{id}/revisions
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/revisions', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_revisions' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// POST /documents/{id}/restore/{version}
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/restore/(?P<version>[\d]+)', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'restore_version' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// POST /documents/bulk-action
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/bulk-action', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'bulk_action' ),
			'permission_callback' => array( $this, 'admin_check' ),
		) );

		// POST|PUT /documents/{id}/publish
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/publish', array(
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_item' ),
				'permission_callback' => function() {
					return current_user_can( 'publish_posts' );
				},
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'publish_item' ),
				'permission_callback' => function() {
					return current_user_can( 'publish_posts' );
				},
			),
		) );
	}

	/**
	 * Permission callback — manage_options required.
	 */
	public function admin_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /documents — paginated list with filters.
	 */
	public function get_items( $request ) {
		$args = array(
			'per_page' => $request->get_param( 'per_page' ) ?? 20,
			'page'     => $request->get_param( 'page' ) ?? 1,
		);

		if ( $request->get_param( 'doc_type' ) ) {
			$args['doc_type'] = $request->get_param( 'doc_type' );
		}
		if ( $request->get_param( 'status' ) ) {
			$args['status'] = $request->get_param( 'status' );
		}
		if ( $request->get_param( 'search' ) ) {
			$args['search'] = $request->get_param( 'search' );
		}

		$result = Document::list_documents( $args );

		// Enrich items with tags if requested or by default.
		foreach ( $result['items'] as &$item ) {
			$item->tags = Taggable::getFor( (int) $item->id, 'document' );
		}

		// Filter by tags if requested.
		$tag_filter = $request->get_param( 'tags' );
		if ( ! empty( $tag_filter ) ) {
			$tag_ids = array_map( 'intval', explode( ',', $tag_filter ) );
			$result['items'] = array_values( array_filter( $result['items'], function( $item ) use ( $tag_ids ) {
				$item_tag_ids = array_map( function( $t ) { return (int) $t->id; }, $item->tags );
				return ! empty( array_intersect( $tag_ids, $item_tag_ids ) );
			} ) );
			$result['total'] = count( $result['items'] );
		}

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $result['per_page'] ) ) );

		return $response;
	}

	/**
	 * GET /documents/{id} — single document with tags and revision count.
	 */
	public function get_item( $request ) {
		$id  = (int) $request->get_param( 'id' );
		$doc = Document::find( $id );

		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.', array( 'status' => 404 ) );
		}

		$data = (array) $doc;
		$data['tags'] = Taggable::getFor( $id, 'document' );

		$revisions = Revision::list_for_document( $id, 1, 1 );
		$data['revision_count'] = $revisions['total'];

		return rest_ensure_response( $data );
	}

	/**
	 * POST /documents — create.
	 */
	public function create_item( $request ) {
		$body   = $request->get_json_params();
		$result = Document::create( $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = (array) $result;
		$data['tags'] = array();

		// Auto-assign tags if provided.
		if ( ! empty( $body['tag_ids'] ) ) {
			Taggable::assign( array_map( 'intval', $body['tag_ids'] ), $result->id, 'document' );
			$data['tags'] = Taggable::getFor( $result->id, 'document' );
		}

		return new \WP_REST_Response( $data, 201 );
	}

	/**
	 * PUT /documents/{id} — update.
	 */
	public function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();

		$result = Document::update( $id, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Sync tags if provided.
		if ( isset( $body['tag_ids'] ) ) {
			Taggable::sync( array_map( 'intval', $body['tag_ids'] ), $id, 'document' );
		}

		$data = (array) $result;
		$data['tags'] = Taggable::getFor( $id, 'document' );

		return rest_ensure_response( $data );
	}

	/**
	 * DELETE /documents/{id} — soft delete (archive).
	 */
	public function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$result = Document::archive( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'id' => $id, 'status' => 'archived' ) );
	}

	/**
	 * POST /documents/{id}/fork — fork a locked document.
	 */
	public function fork_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();

		$result = Document::fork( $id, $body ?: array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( (array) $result, 201 );
	}

	/**
	 * GET /documents/{id}/revisions — revision list.
	 */
	public function get_revisions( $request ) {
		$id       = (int) $request->get_param( 'id' );
		$per_page = (int) ( $request->get_param( 'per_page' ) ?? 20 );
		$page     = (int) ( $request->get_param( 'page' ) ?? 1 );

		$doc = Document::find( $id );
		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.', array( 'status' => 404 ) );
		}

		$result = Revision::list_for_document( $id, $per_page, $page );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', $result['total'] );
		$response->header( 'X-WP-TotalPages', ceil( $result['total'] / max( 1, $per_page ) ) );

		return $response;
	}

	/**
	 * POST /documents/{id}/restore/{version} — restore to version.
	 */
	public function restore_version( $request ) {
		$id      = (int) $request->get_param( 'id' );
		$version = (int) $request->get_param( 'version' );

		$result = Revision::restore( $id, $version );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = (array) $result;
		$data['tags'] = Taggable::getFor( $id, 'document' );

		return rest_ensure_response( $data );
	}

	/**
	 * POST /documents/bulk-action — bulk tag, archive, status change.
	 *
	 * Body: { action: "tag"|"archive"|"status", ids: [1,2,3], tag_ids?: [1,2], status?: "draft" }
	 */
	public function bulk_action( $request ) {
		$body   = $request->get_json_params();
		$action = $body['action'] ?? '';
		$ids    = array_map( 'intval', $body['ids'] ?? array() );

		if ( empty( $ids ) ) {
			return new \WP_Error( 'missing_ids', 'No document IDs provided.', array( 'status' => 400 ) );
		}

		$results = array( 'processed' => 0, 'errors' => array() );

		switch ( $action ) {
			case 'tag':
				$tag_ids = array_map( 'intval', $body['tag_ids'] ?? array() );
				if ( empty( $tag_ids ) ) {
					return new \WP_Error( 'missing_tag_ids', 'No tag IDs provided for bulk tag action.', array( 'status' => 400 ) );
				}
				foreach ( $ids as $id ) {
					Taggable::assign( $tag_ids, $id, 'document' );
					$results['processed']++;
				}
				break;

			case 'archive':
				foreach ( $ids as $id ) {
					$r = Document::archive( $id );
					if ( is_wp_error( $r ) ) {
						$results['errors'][] = array( 'id' => $id, 'error' => $r->get_error_message() );
					} else {
						$results['processed']++;
					}
				}
				break;

			case 'status':
				$status = $body['status'] ?? '';
				if ( empty( $status ) ) {
					return new \WP_Error( 'missing_status', 'No status provided for bulk status action.', array( 'status' => 400 ) );
				}
				foreach ( $ids as $id ) {
					$r = Document::update( $id, array( 'status' => $status ) );
					if ( is_wp_error( $r ) ) {
						$results['errors'][] = array( 'id' => $id, 'error' => $r->get_error_message() );
					} else {
						$results['processed']++;
					}
				}
				break;

			default:
				return new \WP_Error( 'invalid_action', "Unknown action: {$action}", array( 'status' => 400 ) );
		}

		return rest_ensure_response( $results );
	}

	/**
	 * POST|PUT /documents/{id}/publish — publish as WordPress post.
	 *
	 * POST creates a new post. PUT updates existing (if wp_post_id set).
	 * Converts markdown to Gutenberg blocks, maps categories and tags.
	 */
	public function publish_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$body = $request->get_json_params();
		$doc  = Document::find( $id );

		if ( ! $doc ) {
			return new \WP_Error( 'not_found', 'Document not found.', array( 'status' => 404 ) );
		}

		// Convert markdown content to Gutenberg blocks.
		$block_content = MarkdownToBlocks::convert( $doc->content );

		// Determine post status.
		$post_status = $body['post_status'] ?? 'draft';
		if ( ! in_array( $post_status, array( 'draft', 'publish', 'private' ), true ) ) {
			$post_status = 'draft';
		}

		// Build post data.
		$post_data = array(
			'post_title'   => $body['post_title'] ?? $doc->title,
			'post_content' => $block_content,
			'post_excerpt' => $body['post_excerpt'] ?? $doc->excerpt,
			'post_name'    => $doc->slug,
			'post_status'  => $post_status,
			'post_type'    => 'post',
		);

		// Category: use provided or auto-map from doc_type.
		$category_ids = array();
		if ( ! empty( $body['post_category'] ) ) {
			$category_ids = array_map( 'intval', (array) $body['post_category'] );
		} else {
			$auto_cat = $this->get_category_for_type( $doc->doc_type );
			if ( $auto_cat ) {
				$category_ids = array( $auto_cat );
			}
		}
		if ( ! empty( $category_ids ) ) {
			$post_data['post_category'] = $category_ids;
		}

		// Determine if create or update.
		$is_update = ! empty( $doc->wp_post_id ) && get_post( $doc->wp_post_id );

		// Per-post edit check on update path: the route's permission_callback
		// only enforces publish_posts, but the linked WP post may be owned by a
		// different user and require edit_others_posts to modify.
		if ( $is_update && ! current_user_can( 'edit_post', $doc->wp_post_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You cannot edit the WordPress post linked to this document.', 'abilities-for-ai' ),
				array( 'status' => 403 )
			);
		}

		// post_author handling: assigning a post to a different author requires
		// edit_others_posts on either path. Same-author assignment (assigning to
		// yourself) is the legitimate publish-my-own-doc path and stays
		// unrestricted. On the update path, the edit_post check above has
		// already gated access to the linked post.
		if ( ! empty( $body['post_author'] ) ) {
			$requested_author = (int) $body['post_author'];
			if ( $requested_author > 0 && $requested_author !== get_current_user_id() ) {
				if ( ! current_user_can( 'edit_others_posts' ) ) {
					return new \WP_Error(
						'rest_forbidden',
						__( 'You cannot assign a post to another author.', 'abilities-for-ai' ),
						array( 'status' => 403 )
					);
				}
			}
			$post_data['post_author'] = $requested_author;
		}

		if ( $is_update ) {
			$post_data['ID'] = $doc->wp_post_id;
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Map tags: KL tags → WordPress tags.
		$wp_tags = array();
		if ( ! empty( $body['post_tags'] ) ) {
			$wp_tags = (array) $body['post_tags'];
		} else {
			// Auto-map from KL tags.
			$kl_tags = Taggable::getFor( $id, 'document' );
			foreach ( $kl_tags as $tag ) {
				$wp_tags[] = $tag->title ?? $tag->name ?? $tag->slug;
			}
		}
		if ( ! empty( $wp_tags ) ) {
			wp_set_post_tags( $post_id, $wp_tags );
		}

		// Store wp_post_id back on the KL document.
		if ( ! $is_update ) {
			Document::set_wp_post_id( $id, $post_id );
		}

		$post      = get_post( $post_id );
		$permalink = get_permalink( $post_id );
		$edit_url  = get_edit_post_link( $post_id, 'raw' );

		return rest_ensure_response( array(
			'post_id'     => $post_id,
			'post_status' => $post->post_status,
			'permalink'   => $permalink,
			'edit_url'    => $edit_url,
			'is_update'   => $is_update,
			'document_id' => $id,
		) );
	}

	/**
	 * Auto-map doc_type to a WordPress category ID.
	 * Creates the category if it doesn't exist.
	 *
	 * @param string $doc_type Document type.
	 * @return int|null Category ID or null.
	 */
	private function get_category_for_type( $doc_type ) {
		$map = array(
			'skill'         => 'SKILLs',
			'agent'         => 'Agents',
			'knowledge'     => 'Knowledge',
			'course'        => 'Courses',
			'config'        => 'Protocols',
			'template'      => 'Templates',
			'boot'          => 'System',
			'diagnostic'    => 'Diagnostics',
			'essence'       => 'About',
			'site-identity' => 'About',
			'site-state'    => 'System',
			'capabilities'  => 'System',
		);

		$cat_name = $map[ $doc_type ] ?? null;
		if ( ! $cat_name ) {
			return null;
		}

		$term = get_term_by( 'name', $cat_name, 'category' );
		if ( $term ) {
			return (int) $term->term_id;
		}

		// Create the category.
		$result = wp_insert_term( $cat_name, 'category' );
		if ( is_wp_error( $result ) ) {
			return null;
		}

		return (int) $result['term_id'];
	}

	/**
	 * Collection params for GET /documents.
	 */
	public function get_collection_params() {
		return array(
			'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
			'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
			'doc_type' => array( 'type' => 'string' ),
			'status'   => array( 'type' => 'string', 'default' => 'active' ),
			'search'   => array( 'type' => 'string' ),
			'tags'     => array( 'type' => 'string', 'description' => 'Comma-separated tag IDs to filter by.' ),
			'order_by' => array( 'type' => 'string', 'default' => 'updated_at' ),
			'order'    => array( 'type' => 'string', 'default' => 'desc', 'enum' => array( 'asc', 'desc' ) ),
		);
	}
}
