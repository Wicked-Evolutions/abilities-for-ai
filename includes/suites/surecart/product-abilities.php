<?php
/**
 * SureCart Suite — Product Abilities (P0)
 *
 * Products, prices, variants, and product collections.
 * SureCart is a trademark of SureCart Inc. This module is not affiliated with or endorsed by SureCart Inc.
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {

	if ( ! defined( 'SURECART_PLUGIN_FILE' ) ) {
		return;
	}

	$reg = new Abilities_For_AI_Registrar( 'surecart', 'manage_options' );

	// ===== LIST PRODUCTS =====
	$reg->read( 'surecart/list-products', array(
		'label'       => 'List SureCart Products',
		'description' => 'Returns a paginated list of SureCart products with optional filters.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'status' => array( 'type' => 'string', 'description' => 'Filter by status: published, draft.' ),
					'query'  => array( 'type' => 'string', 'description' => 'Search query.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['status'] ) ) {
					$query['status'] = array( $input['status'] );
				}
				if ( ! empty( $input['query'] ) ) {
					$query['query'] = $input['query'];
				}

				$result = \SureCart\Models\Product::where( $query )
					->with( array( 'prices', 'product_collections' ) )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-products' );
		},
	));

	// ===== GET PRODUCT =====
	$reg->read( 'surecart/get-product', array(
		'label'       => 'Get SureCart Product',
		'description' => 'Returns a single SureCart product by ID with prices, variants, and collections.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Product ID (e.g. prod_abc123).' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$product = \SureCart\Models\Product::with( array( 'prices', 'variants', 'variant_options', 'product_collections' ) )
					->find( $input['id'] );

				if ( is_wp_error( $product ) ) {
					return $product;
				}

				return abilities_for_ai_surecart_format_model( $product );
			}, 'get-product' );
		},
	));

	// ===== CREATE PRODUCT =====
	$reg->write( 'surecart/create-product', array(
		'label'       => 'Create SureCart Product',
		'description' => 'Creates a new product in the SureCart store.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'           => array( 'type' => 'string', 'description' => 'Product name.' ),
				'description'    => array( 'type' => 'string', 'description' => 'Product description.' ),
				'status'         => array( 'type' => 'string', 'description' => 'Product status: published, draft. Default: draft.' ),
				'recurring'      => array( 'type' => 'boolean', 'description' => 'Whether the product is recurring (subscription).' ),
				'metadata'       => array( 'type' => 'object', 'description' => 'Custom metadata key-value pairs.' ),
			),
			'required' => array( 'name' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'name' => $input['name'] );
				if ( isset( $input['description'] ) )  $attrs['description']  = $input['description'];
				if ( isset( $input['status'] ) )        $attrs['status']       = $input['status'];
				if ( isset( $input['recurring'] ) )     $attrs['recurring']    = $input['recurring'];
				if ( isset( $input['metadata'] ) )      $attrs['metadata']     = $input['metadata'];

				$product = \SureCart\Models\Product::create( $attrs );

				if ( is_wp_error( $product ) ) {
					return $product;
				}

				return abilities_for_ai_surecart_format_model( $product );
			}, 'create-product' );
		},
	));

	// ===== UPDATE PRODUCT =====
	$reg->write( 'surecart/update-product', array(
		'label'       => 'Update SureCart Product',
		'description' => 'Updates an existing SureCart product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'string', 'description' => 'Product ID.' ),
				'name'           => array( 'type' => 'string', 'description' => 'Product name.' ),
				'description'    => array( 'type' => 'string', 'description' => 'Product description.' ),
				'status'         => array( 'type' => 'string', 'description' => 'Product status: published, draft.' ),
				'metadata'       => array( 'type' => 'object', 'description' => 'Custom metadata key-value pairs.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$id    = $input['id'];
				$attrs = array();
				if ( isset( $input['name'] ) )        $attrs['name']        = $input['name'];
				if ( isset( $input['description'] ) )  $attrs['description']  = $input['description'];
				if ( isset( $input['status'] ) )       $attrs['status']       = $input['status'];
				if ( isset( $input['metadata'] ) )     $attrs['metadata']     = $input['metadata'];

				$product = \SureCart\Models\Product::update( array_merge( $attrs, array( 'id' => $id ) ) );

				if ( is_wp_error( $product ) ) {
					return $product;
				}

				return abilities_for_ai_surecart_format_model( $product );
			}, 'update-product' );
		},
	));

	// ===== DELETE PRODUCT =====
	$reg->delete( 'surecart/delete-product', array(
		'label'       => 'Delete SureCart Product',
		'description' => 'Deletes a SureCart product. This action cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Product ID to delete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Product::delete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array( 'deleted' => true, 'id' => $input['id'] );
			}, 'delete-product' );
		},
	));

	// ===== LIST PRICES =====
	$reg->read( 'surecart/list-prices', array(
		'label'       => 'List SureCart Prices',
		'description' => 'Returns prices, optionally filtered by product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'product_ids' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => 'Filter by product IDs.',
					),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['product_ids'] ) ) {
					$query['product_ids'] = $input['product_ids'];
				}

				$result = \SureCart\Models\Price::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-prices' );
		},
	));

	// ===== LIST VARIANTS =====
	$reg->read( 'surecart/list-variants', array(
		'label'       => 'List SureCart Variants',
		'description' => 'Returns variants for a product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				abilities_for_ai_surecart_pagination_schema(),
				array(
					'product_id' => array( 'type' => 'string', 'description' => 'Product ID to list variants for.' ),
				)
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['product_id'] ) ) {
					$query['product_ids'] = array( $input['product_id'] );
				}

				$result = \SureCart\Models\Variant::where( $query )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-variants' );
		},
	));

	// ===== LIST PRODUCT COLLECTIONS =====
	$reg->read( 'surecart/list-collections', array(
		'label'       => 'List SureCart Product Collections',
		'description' => 'Returns product collections (groups of products).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => abilities_for_ai_surecart_pagination_schema(),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\ProductCollection::where( array() )
					->paginate( array(
						'page'     => $input['page'] ?? 1,
						'per_page' => min( $input['per_page'] ?? 20, 100 ),
					) );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return abilities_for_ai_surecart_format_paginated( $result );
			}, 'list-collections' );
		},
	));

});
