<?php
/**
 * Rewrite Rules Abilities
 *
 * Permalink structure and rewrite rule management.
 *
 * @package WordPress_Native_Abilities
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', 'wp_native_register_rewrite_abilities' );

function wp_native_register_rewrite_abilities() {

	// ---- rewrite/get-structure ----
	wp_register_ability( 'rewrite/get-structure', array(
		'label'       => 'Get Permalink Structure',
		'description' => 'Get the current permalink structure and rewrite configuration.',
		'category'    => 'rewrite',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => (object) array(),
		),
		'execute_callback' => function() {
			global $wp_rewrite;
			return array(
				'permalink_structure' => $wp_rewrite->permalink_structure,
				'front'              => $wp_rewrite->front,
				'root'               => $wp_rewrite->root,
				'category_base'      => $wp_rewrite->get_category_permastruct(),
				'tag_base'           => $wp_rewrite->get_tag_permastruct(),
				'author_base'        => $wp_rewrite->author_base,
				'search_base'        => $wp_rewrite->search_base,
				'page_base'          => $wp_rewrite->pagination_base,
				'using_permalinks'   => $wp_rewrite->using_permalinks(),
				'using_index'        => $wp_rewrite->using_index_permalinks(),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- rewrite/list-rules ----
	wp_register_ability( 'rewrite/list-rules', array(
		'label'       => 'List Rewrite Rules',
		'description' => 'List all active rewrite rules (regex → query pairs).',
		'category'    => 'rewrite',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'search' => array( 'type' => 'string', 'description' => 'Filter rules by regex or query pattern' ),
				),
				wp_native_pagination_schema()
			),
		),
		'execute_callback' => function( $params ) {
			global $wp_rewrite;
			$rules = $wp_rewrite->wp_rewrite_rules();
			if ( ! $rules ) {
				$rules = get_option( 'rewrite_rules', array() );
			}
			if ( ! is_array( $rules ) ) {
				return array( 'rules' => array(), 'total' => 0 );
			}

			$result = array();
			foreach ( $rules as $regex => $query ) {
				if ( ! empty( $params['search'] ) ) {
					if ( stripos( $regex, $params['search'] ) === false && stripos( $query, $params['search'] ) === false ) {
						continue;
					}
				}
				$result[] = array( 'regex' => $regex, 'query' => $query );
			}

			$pag   = wp_native_pagination( $params, 50 );
			$slice = array_slice( $result, $pag['offset'], $pag['per_page'] );

			return array(
				'rules' => $slice,
				'total' => count( $result ),
				'page'  => $pag['page'],
				'pages' => ceil( count( $result ) / $pag['per_page'] ),
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ) ),
	));

	// ---- rewrite/flush ----
	wp_register_ability( 'rewrite/flush', array(
		'label'       => 'Flush Rewrite Rules',
		'description' => 'Flush and regenerate rewrite rules. Safe operation — just rebuilds the rule set.',
		'category'    => 'rewrite',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'hard' => array( 'type' => 'boolean', 'description' => 'Hard flush — also update .htaccess (default: false)', 'default' => false ),
			),
		),
		'execute_callback' => function( $params ) {
			$hard = ! empty( $params['hard'] );
			flush_rewrite_rules( $hard );
			global $wp_rewrite;
			$rules = $wp_rewrite->wp_rewrite_rules();
			return array(
				'flushed'    => true,
				'hard_flush' => $hard,
				'rule_count' => is_array( $rules ) ? count( $rules ) : 0,
			);
		},
		'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ) ),
	));
}
