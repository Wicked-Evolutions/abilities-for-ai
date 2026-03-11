<?php
/**
 * Rewrite Rules Abilities
 *
 * Permalink structure and rewrite rule management.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new WP_Abilities_Suite_Registrar( 'rewrite', 'manage_options' );

	$reg->read( 'rewrite/get-structure', array(
		'label'       => 'Get Permalink Structure',
		'description' => 'Get the current permalink structure and rewrite configuration.',
		'output_schema' => wp_abilities_suite_schema_item_output( array(
			'permalink_structure' => array( 'type' => 'string' ),
			'using_permalinks'    => array( 'type' => 'boolean' ),
			'using_index'         => array( 'type' => 'boolean' ),
		) ),
		'callback' => function() {
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
	));

	$reg->read( 'rewrite/list-rules', array(
		'label'       => 'List Rewrite Rules',
		'description' => 'List all active rewrite rules (regex → query pairs).',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'search' => wp_abilities_suite_schema_search( 'Filter rules by regex or query pattern' ),
				),
				wp_abilities_suite_schema_pagination( 50 )
			),
		),
		'output_schema' => wp_abilities_suite_schema_list_output( 'rules', array(
			'regex' => array( 'type' => 'string' ),
			'query' => array( 'type' => 'string' ),
		) ),
		'callback' => function( $params ) {
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

			$pag   = wp_abilities_pagination( $params, 50 );
			$slice = array_slice( $result, $pag['offset'], $pag['per_page'] );

			return array(
				'total'    => count( $result ),
				'pages'    => max( 1, (int) ceil( count( $result ) / $pag['per_page'] ) ),
				'page'     => $pag['page'],
				'per_page' => $pag['per_page'],
				'rules'    => $slice,
			);
		},
	));

	$reg->write( 'rewrite/flush', array(
		'label'       => 'Flush Rewrite Rules',
		'description' => 'Flush and regenerate rewrite rules. Safe operation — just rebuilds the rule set.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'hard' => array( 'type' => 'boolean', 'description' => 'Hard flush — also update .htaccess (default: false)', 'default' => false ),
			),
		),
		'output_schema' => wp_abilities_suite_schema_success_output( array(
			'flushed'    => array( 'type' => 'boolean' ),
			'hard_flush' => array( 'type' => 'boolean' ),
			'rule_count' => array( 'type' => 'integer' ),
		) ),
		'callback' => function( $params ) {
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
		'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
	));
});
