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

	// ===== GET PRICE =====
	$reg->read( 'surecart/get-price', array(
		'label'       => 'Get SureCart Price',
		'description' => 'Returns a single price by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Price ID (e.g. price_abc123).' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$price = \SureCart\Models\Price::find( $input['id'] );

				if ( is_wp_error( $price ) ) {
					return $price;
				}

				return abilities_for_ai_surecart_format_model( $price );
			}, 'get-price' );
		},
	));

	// ===== CREATE PRICE =====
	$reg->write( 'surecart/create-price', array(
		'label'       => 'Create SureCart Price',
		'description' => 'Creates a new price for a product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'product_id'       => array( 'type' => 'string', 'description' => 'Product ID this price belongs to.' ),
				'amount'           => array( 'type' => 'integer', 'description' => 'Price amount in cents (e.g. 1999 = $19.99).' ),
				'currency'         => array( 'type' => 'string', 'description' => 'Currency code (e.g. usd). Default: store currency.' ),
				'recurring_interval'       => array( 'type' => 'string', 'description' => 'Billing interval: day, week, month, year.' ),
				'recurring_interval_count' => array( 'type' => 'integer', 'description' => 'Number of intervals between billings (e.g. 1 = every month).' ),
				'trial_duration_days'      => array( 'type' => 'integer', 'description' => 'Trial period in days.' ),
				'ad_hoc'           => array( 'type' => 'boolean', 'description' => 'Whether this is a pay-what-you-want price.' ),
				'name'             => array( 'type' => 'string', 'description' => 'Price name (optional label).' ),
			),
			'required' => array( 'product_id', 'amount' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array(
					'product' => $input['product_id'],
					'amount'  => $input['amount'],
				);
				if ( isset( $input['currency'] ) )                 $attrs['currency']                 = $input['currency'];
				if ( isset( $input['recurring_interval'] ) )       $attrs['recurring_interval']       = $input['recurring_interval'];
				if ( isset( $input['recurring_interval_count'] ) ) $attrs['recurring_interval_count'] = $input['recurring_interval_count'];
				if ( isset( $input['trial_duration_days'] ) )      $attrs['trial_duration_days']      = $input['trial_duration_days'];
				if ( isset( $input['ad_hoc'] ) )                   $attrs['ad_hoc']                   = $input['ad_hoc'];
				if ( isset( $input['name'] ) )                     $attrs['name']                     = $input['name'];

				$price = \SureCart\Models\Price::create( $attrs );

				if ( is_wp_error( $price ) ) {
					return $price;
				}

				return abilities_for_ai_surecart_format_model( $price );
			}, 'create-price' );
		},
	));

	// ===== UPDATE PRICE =====
	$reg->write( 'surecart/update-price', array(
		'label'       => 'Update SureCart Price',
		'description' => 'Updates an existing price.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'       => array( 'type' => 'string', 'description' => 'Price ID.' ),
				'amount'   => array( 'type' => 'integer', 'description' => 'Price amount in cents.' ),
				'name'     => array( 'type' => 'string', 'description' => 'Price name.' ),
				'ad_hoc'   => array( 'type' => 'boolean', 'description' => 'Whether this is a pay-what-you-want price.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'id' => $input['id'] );
				if ( isset( $input['amount'] ) ) $attrs['amount'] = $input['amount'];
				if ( isset( $input['name'] ) )   $attrs['name']   = $input['name'];
				if ( isset( $input['ad_hoc'] ) ) $attrs['ad_hoc'] = $input['ad_hoc'];

				$price = \SureCart\Models\Price::update( $attrs );

				if ( is_wp_error( $price ) ) {
					return $price;
				}

				return abilities_for_ai_surecart_format_model( $price );
			}, 'update-price' );
		},
	));

	// ===== DELETE PRICE =====
	$reg->delete( 'surecart/delete-price', array(
		'label'       => 'Delete SureCart Price',
		'description' => 'Deletes a price. This action cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Price ID to delete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\Price::delete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array( 'deleted' => true, 'id' => $input['id'] );
			}, 'delete-price' );
		},
	));

	// ===== GET VARIANT =====
	$reg->read( 'surecart/get-variant', array(
		'label'       => 'Get SureCart Variant',
		'description' => 'Returns a single product variant by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Variant ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$variant = \SureCart\Models\Variant::find( $input['id'] );

				if ( is_wp_error( $variant ) ) {
					return $variant;
				}

				return abilities_for_ai_surecart_format_model( $variant );
			}, 'get-variant' );
		},
	));

	// ===== LIST VARIANT OPTIONS =====
	$reg->read( 'surecart/list-variant-options', array(
		'label'       => 'List SureCart Variant Options',
		'description' => 'Returns variant options (size, color, etc.) for a product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'product_id' => array( 'type' => 'string', 'description' => 'Product ID to list variant options for.' ),
			),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$query = array();
				if ( ! empty( $input['product_id'] ) ) {
					$query['product_ids'] = array( $input['product_id'] );
				}

				$result = \SureCart\Models\VariantOption::where( $query )->get();

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$items = array();
				if ( is_array( $result ) ) {
					foreach ( $result as $model ) {
						$items[] = abilities_for_ai_surecart_format_model( $model );
					}
				}

				return array( 'data' => $items );
			}, 'list-variant-options' );
		},
	));

	// ===== GET PRODUCT COLLECTION =====
	$reg->read( 'surecart/get-product-collection', array(
		'label'       => 'Get SureCart Product Collection',
		'description' => 'Returns a single product collection by ID.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Product collection ID.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$collection = \SureCart\Models\ProductCollection::find( $input['id'] );

				if ( is_wp_error( $collection ) ) {
					return $collection;
				}

				return abilities_for_ai_surecart_format_model( $collection );
			}, 'get-product-collection' );
		},
	));

	// ===== CREATE PRODUCT COLLECTION =====
	$reg->write( 'surecart/create-product-collection', array(
		'label'       => 'Create SureCart Product Collection',
		'description' => 'Creates a new product collection.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'name'        => array( 'type' => 'string', 'description' => 'Collection name.' ),
				'description' => array( 'type' => 'string', 'description' => 'Collection description.' ),
			),
			'required' => array( 'name' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'name' => $input['name'] );
				if ( isset( $input['description'] ) ) $attrs['description'] = $input['description'];

				$collection = \SureCart\Models\ProductCollection::create( $attrs );

				if ( is_wp_error( $collection ) ) {
					return $collection;
				}

				return abilities_for_ai_surecart_format_model( $collection );
			}, 'create-product-collection' );
		},
	));

	// ===== UPDATE PRODUCT COLLECTION =====
	$reg->write( 'surecart/update-product-collection', array(
		'label'       => 'Update SureCart Product Collection',
		'description' => 'Updates an existing product collection.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id'          => array( 'type' => 'string', 'description' => 'Product collection ID.' ),
				'name'        => array( 'type' => 'string', 'description' => 'Collection name.' ),
				'description' => array( 'type' => 'string', 'description' => 'Collection description.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$attrs = array( 'id' => $input['id'] );
				if ( isset( $input['name'] ) )        $attrs['name']        = $input['name'];
				if ( isset( $input['description'] ) ) $attrs['description'] = $input['description'];

				$collection = \SureCart\Models\ProductCollection::update( $attrs );

				if ( is_wp_error( $collection ) ) {
					return $collection;
				}

				return abilities_for_ai_surecart_format_model( $collection );
			}, 'update-product-collection' );
		},
	));

	// ===== DELETE PRODUCT COLLECTION =====
	$reg->delete( 'surecart/delete-product-collection', array(
		'label'       => 'Delete SureCart Product Collection',
		'description' => 'Deletes a product collection. This action cannot be undone.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Product collection ID to delete.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$result = \SureCart\Models\ProductCollection::delete( $input['id'] );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				return array( 'deleted' => true, 'id' => $input['id'] );
			}, 'delete-product-collection' );
		},
	));

	// ===== DUPLICATE PRODUCT =====
	$reg->write( 'surecart/duplicate-product', array(
		'label'       => 'Duplicate SureCart Product',
		'description' => 'Creates a copy of an existing product.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'string', 'description' => 'Product ID to duplicate.' ),
			),
			'required' => array( 'id' ),
		),
		'callback' => function( $input ) {
			return abilities_for_ai_surecart_call( function() use ( $input ) {
				$product = \SureCart\Models\Product::duplicate( $input['id'] );

				if ( is_wp_error( $product ) ) {
					return $product;
				}

				return abilities_for_ai_surecart_format_model( $product );
			}, 'duplicate-product' );
		},
	));

});
