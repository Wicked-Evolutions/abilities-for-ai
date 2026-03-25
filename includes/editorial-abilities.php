<?php
/**
 * Editorial Abilities — Compiled Content Analysis
 *
 * Content analysis and editorial intelligence scripts.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
	$reg = new Abilities_For_AI_Registrar( 'editorial', 'manage_options' );

	$reg->read( 'editorial/site-voice', array(
		'label'       => 'Editorial Voice Fingerprint',
		'description' => 'Compiled editorial analysis. Extracts voice signatures, opening patterns, headline analysis, series arcs, content depth, and structural patterns from all published content. Reads content server-side — returns intelligence, not raw text.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to analyse (default: post).',
				),
				'max_posts' => array(
					'type'        => 'integer',
					'description' => 'Maximum posts to analyse (default: 200, max: 500).',
				),
				'first_words' => array(
					'type'        => 'integer',
					'description' => 'Words to extract from each post opening (default: 50, max: 200). Used for voice/tone analysis.',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
		) ),
		'callback' => 'abilities_for_ai_editorial_site_voice',
	));

	$reg->read( 'editorial/content-samples', array(
		'label'       => 'Content Samples',
		'description' => 'Selective deep reading. Returns full plaintext content (block markup stripped) for a curated sample of posts. Use after editorial/site-voice to read representative content.',
		'input_schema' => array(
			'type'       => 'object',
			'properties' => array(
				'strategy' => array(
					'type'        => 'string',
					'enum'        => array( 'recent_per_series', 'longest_per_series', 'by_ids', 'recent_overall' ),
					'description' => 'Selection strategy. recent_per_series: N most recent per category. longest_per_series: single longest per category. by_ids: specific post IDs. recent_overall: N most recent posts. Default: recent_per_series.',
				),
				'post_ids' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Specific post IDs to read. Only used with strategy=by_ids.',
				),
				'posts_per_series' => array(
					'type'        => 'integer',
					'description' => 'Posts per series/category for recent_per_series strategy (default: 2, max: 5).',
				),
				'max_words_per_post' => array(
					'type'        => 'integer',
					'description' => 'Maximum words to return per post (default: 1000, max: 3000). Content truncated with [...] marker.',
				),
				'max_total_posts' => array(
					'type'        => 'integer',
					'description' => 'Hard cap on total posts returned (default: 15, max: 30).',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type (default: post).',
				),
			),
		),
		'output_schema' => abilities_for_ai_schema_item_output( array(
			'generated_at' => array( 'type' => 'string' ),
			'strategy'     => array( 'type' => 'string' ),
			'total_posts'  => array( 'type' => 'integer' ),
			'samples'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
		) ),
		'callback' => 'abilities_for_ai_editorial_content_samples',
	));
});

// ============================================================
// Site voice fingerprint
// ============================================================

/**
 * Strip block markup, shortcodes, and HTML from post content.
 *
 * @param string $content Raw post content.
 * @return string Plain text.
 */
function abilities_for_ai_editorial_strip( $content ) {
	$text = preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );
	$text = wp_strip_all_tags( strip_shortcodes( $text ) );
	return trim( preg_replace( '/\s+/', ' ', $text ) );
}

/**
 * Compiled editorial voice fingerprint callback.
 *
 * @param array|null $input Optional input.
 * @return array Editorial fingerprint.
 */
function abilities_for_ai_editorial_site_voice( $input = null ) {
	$post_type   = $input['post_type'] ?? 'post';
	$max_posts   = min( intval( $input['max_posts'] ?? 200 ), 500 );
	$first_words = min( intval( $input['first_words'] ?? 50 ), 200 );

	$posts = get_posts( array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => $max_posts,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	// Bulk-fetch categories for all post IDs.
	$post_ids    = wp_list_pluck( $posts, 'ID' );
	$terms_map   = array();
	if ( ! empty( $post_ids ) ) {
		$all_terms = wp_get_object_terms( $post_ids, 'category', array( 'fields' => 'all_with_object_id' ) );
		if ( ! is_wp_error( $all_terms ) ) {
			foreach ( $all_terms as $t ) {
				$terms_map[ $t->object_id ][] = $t->name;
			}
		}
	}

	// Author cache.
	$author_cache = array();

	// Per-post extraction.
	$per_author    = array(); // author_id => array of post data
	$openings      = array();
	$headlines     = array();
	$series_data   = array(); // category_name => array of posts
	$depth_buckets = array( 'under_500' => 0, '500_to_1000' => 0, '1000_to_2000' => 0, '2000_to_3000' => 0, 'over_3000' => 0 );
	$total_words   = 0;
	$all_word_counts = array();
	$structure_data  = array();

	foreach ( $posts as $post ) {
		$stripped = abilities_for_ai_editorial_strip( $post->post_content );
		$word_array = explode( ' ', $stripped );
		$word_count = count( $word_array );
		if ( $stripped === '' ) {
			$word_count = 0;
			$word_array = array();
		}

		$sentences = preg_split( '/[.!?]+/', $stripped, -1, PREG_SPLIT_NO_EMPTY );
		$sentence_count = count( $sentences );
		$avg_sl = $sentence_count > 0 ? round( $word_count / $sentence_count, 1 ) : 0;

		$opening = implode( ' ', array_slice( $word_array, 0, $first_words ) );
		$first_sentence = isset( $sentences[0] ) ? trim( $sentences[0] ) . '.' : '';

		// Headings from raw content.
		preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/i', $post->post_content, $heading_matches );
		$h2_count = 0;
		$h3_count = 0;
		if ( ! empty( $heading_matches[1] ) ) {
			foreach ( $heading_matches[1] as $level ) {
				if ( $level === '2' ) $h2_count++;
				if ( $level === '3' ) $h3_count++;
			}
		}

		// Also check block headings: <!-- wp:heading {"level":2} -->
		preg_match_all( '/<!-- wp:heading[^>]*-->\s*<h([23])[^>]*>/i', $post->post_content, $block_heading_matches );
		if ( ! empty( $block_heading_matches[1] ) ) {
			foreach ( $block_heading_matches[1] as $level ) {
				if ( $level === '2' ) $h2_count++;
				if ( $level === '3' ) $h3_count++;
			}
		}

		// Author.
		$author_id = (int) $post->post_author;
		if ( ! isset( $author_cache[ $author_id ] ) ) {
			$user = get_userdata( $author_id );
			$author_cache[ $author_id ] = $user ? $user->display_name : "User $author_id";
		}
		$author_name = $author_cache[ $author_id ];

		// Categories.
		$cats = $terms_map[ $post->ID ] ?? array();
		$primary_cat = ! empty( $cats ) ? $cats[0] : null;

		// Aggregate per author.
		if ( ! isset( $per_author[ $author_id ] ) ) {
			$per_author[ $author_id ] = array(
				'name'             => $author_name,
				'post_count'       => 0,
				'word_counts'      => array(),
				'sentence_lengths' => array(),
				'h2_counts'        => array(),
				'openings'         => array(),
				'opening_questions' => 0,
				'first_person'     => 0,
			);
		}
		$per_author[ $author_id ]['post_count']++;
		$per_author[ $author_id ]['word_counts'][] = $word_count;
		$per_author[ $author_id ]['sentence_lengths'][] = $avg_sl;
		$per_author[ $author_id ]['h2_counts'][] = $h2_count;
		if ( count( $per_author[ $author_id ]['openings'] ) < 3 ) {
			$per_author[ $author_id ]['openings'][] = $first_sentence;
		}
		if ( preg_match( '/\?/', $first_sentence ) ) {
			$per_author[ $author_id ]['opening_questions']++;
		}
		if ( preg_match( '/\bI\b/', mb_substr( $stripped, 0, 200 ) ) ) {
			$per_author[ $author_id ]['first_person']++;
		}

		// Openings (cap at 50).
		if ( count( $openings ) < 50 ) {
			$openings[] = array(
				'title'          => $post->post_title,
				'first_sentence' => $first_sentence,
				'author'         => $author_name,
				'series'         => $primary_cat,
			);
		}

		// Headlines.
		$headlines[] = $post->post_title;

		// Series.
		if ( $primary_cat ) {
			if ( ! isset( $series_data[ $primary_cat ] ) ) {
				$series_data[ $primary_cat ] = array( 'posts' => array(), 'word_counts' => array() );
			}
			$series_data[ $primary_cat ]['posts'][] = array(
				'title'  => $post->post_title,
				'date'   => substr( $post->post_date, 0, 10 ),
				'words'  => $word_count,
				'author' => $author_name,
			);
			$series_data[ $primary_cat ]['word_counts'][] = $word_count;
		}

		// Depth.
		$total_words += $word_count;
		$all_word_counts[] = $word_count;
		if ( $word_count < 500 ) $depth_buckets['under_500']++;
		elseif ( $word_count < 1000 ) $depth_buckets['500_to_1000']++;
		elseif ( $word_count < 2000 ) $depth_buckets['1000_to_2000']++;
		elseif ( $word_count < 3000 ) $depth_buckets['2000_to_3000']++;
		else $depth_buckets['over_3000']++;

		// Structure.
		$structure_data[] = array(
			'h2'     => $h2_count,
			'h3'     => $h3_count,
			'words'  => $word_count,
			'series' => $primary_cat,
		);
	}

	$post_count = count( $posts );

	// --- 1. Voice signatures per author ---
	$authors_output = array();
	foreach ( $per_author as $aid => $ad ) {
		$avg_wc = $ad['post_count'] > 0 ? round( array_sum( $ad['word_counts'] ) / $ad['post_count'] ) : 0;
		$avg_sl_arr = array_filter( $ad['sentence_lengths'], function( $v ) { return $v > 0; } );
		$avg_sl_val = ! empty( $avg_sl_arr ) ? round( array_sum( $avg_sl_arr ) / count( $avg_sl_arr ), 1 ) : 0;
		$avg_h2 = $ad['post_count'] > 0 ? round( array_sum( $ad['h2_counts'] ) / $ad['post_count'], 1 ) : 0;

		// Tone indicator heuristic.
		$tone = 'general';
		$question_ratio = $ad['post_count'] > 0 ? $ad['opening_questions'] / $ad['post_count'] : 0;
		$fp_ratio = $ad['post_count'] > 0 ? $ad['first_person'] / $ad['post_count'] : 0;

		if ( $question_ratio > 0.3 ) {
			$tone = 'inquiry-driven';
		} elseif ( $fp_ratio > 0.6 && $avg_sl_val < 20 ) {
			$tone = 'personal-narrative';
		} elseif ( $avg_sl_val > 20 ) {
			$tone = 'analytical-reflective';
		} elseif ( $avg_sl_val < 15 ) {
			$tone = 'direct-conversational';
		} else {
			$tone = 'balanced-expository';
		}

		$authors_output[] = array(
			'name'                => $ad['name'],
			'post_count'          => $ad['post_count'],
			'avg_word_count'      => $avg_wc,
			'avg_sentence_length' => $avg_sl_val,
			'avg_h2_count'        => $avg_h2,
			'tone_indicator'      => $tone,
			'sample_openings'     => $ad['openings'],
		);
	}
	// Sort by post count desc.
	usort( $authors_output, function( $a, $b ) { return $b['post_count'] - $a['post_count']; } );

	// --- 2. Opening patterns ---
	// Already built in $openings (capped at 50).

	// --- 3. Headline patterns ---
	$headline_words = array_map( 'str_word_count', $headlines );
	$dash_count = 0;
	$question_count = 0;
	$colon_count = 0;
	foreach ( $headlines as $h ) {
		if ( strpos( $h, '—' ) !== false || strpos( $h, '-' ) !== false ) $dash_count++;
		if ( strpos( $h, '?' ) !== false ) $question_count++;
		if ( strpos( $h, ':' ) !== false ) $colon_count++;
	}

	// Per-series headline samples (up to 5 per series).
	$headlines_by_series = array();
	foreach ( $series_data as $sname => $sdata ) {
		$headlines_by_series[ $sname ] = array_slice( array_column( $sdata['posts'], 'title' ), 0, 5 );
	}

	$headline_output = array(
		'total'           => count( $headlines ),
		'avg_word_count'  => count( $headline_words ) > 0 ? round( array_sum( $headline_words ) / count( $headline_words ), 1 ) : 0,
		'contains_dash'   => $dash_count,
		'contains_question' => $question_count,
		'contains_colon'  => $colon_count,
		'longest'         => ! empty( $headlines ) ? $headlines[ array_search( max( $headline_words ), $headline_words ) ] : null,
		'shortest'        => ! empty( $headlines ) ? $headlines[ array_search( min( $headline_words ), $headline_words ) ] : null,
		'sample_by_series' => $headlines_by_series,
	);

	// --- 4. Series arcs ---
	$series_output = array();
	foreach ( $series_data as $sname => $sdata ) {
		// Reverse to chronological order (posts were fetched DESC).
		$arc = array_reverse( $sdata['posts'] );
		$wc  = $sdata['word_counts'];
		$series_output[] = array(
			'name'           => $sname,
			'post_count'     => count( $arc ),
			'date_range'     => array(
				'first' => $arc[0]['date'] ?? null,
				'last'  => end( $arc )['date'] ?? null,
			),
			'avg_word_count' => count( $wc ) > 0 ? round( array_sum( $wc ) / count( $wc ) ) : 0,
			'total_words'    => array_sum( $wc ),
			'arc'            => $arc,
		);
	}
	// Sort by post count desc.
	usort( $series_output, function( $a, $b ) { return $b['post_count'] - $a['post_count']; } );

	// --- 5. Content depth ---
	sort( $all_word_counts );
	$median = 0;
	$n = count( $all_word_counts );
	if ( $n > 0 ) {
		$median = $n % 2 === 0
			? (int) round( ( $all_word_counts[ $n / 2 - 1 ] + $all_word_counts[ $n / 2 ] ) / 2 )
			: $all_word_counts[ (int) floor( $n / 2 ) ];
	}

	$depth_output = array_merge( $depth_buckets, array(
		'total_words' => $total_words,
		'avg_words'   => $post_count > 0 ? round( $total_words / $post_count ) : 0,
		'median_words' => $median,
	) );

	// --- 6. Structural patterns ---
	$total_h2 = 0;
	$total_h3 = 0;
	$no_headings = 0;
	$many_h2 = 0;
	foreach ( $structure_data as $sd ) {
		$total_h2 += $sd['h2'];
		$total_h3 += $sd['h3'];
		if ( $sd['h2'] === 0 && $sd['h3'] === 0 ) $no_headings++;
		if ( $sd['h2'] >= 5 ) $many_h2++;
	}

	$structure_by_series = array();
	foreach ( $structure_data as $sd ) {
		if ( $sd['series'] ) {
			if ( ! isset( $structure_by_series[ $sd['series'] ] ) ) {
				$structure_by_series[ $sd['series'] ] = array( 'h2_sum' => 0, 'word_sum' => 0, 'count' => 0 );
			}
			$structure_by_series[ $sd['series'] ]['h2_sum'] += $sd['h2'];
			$structure_by_series[ $sd['series'] ]['word_sum'] += $sd['words'];
			$structure_by_series[ $sd['series'] ]['count']++;
		}
	}
	$structure_series_output = array();
	foreach ( $structure_by_series as $sname => $ss ) {
		$structure_series_output[ $sname ] = array(
			'avg_h2'    => $ss['count'] > 0 ? round( $ss['h2_sum'] / $ss['count'], 1 ) : 0,
			'avg_words' => $ss['count'] > 0 ? round( $ss['word_sum'] / $ss['count'] ) : 0,
		);
	}

	$structure_output = array(
		'avg_h2_per_post'        => $post_count > 0 ? round( $total_h2 / $post_count, 1 ) : 0,
		'avg_h3_per_post'        => $post_count > 0 ? round( $total_h3 / $post_count, 1 ) : 0,
		'posts_with_no_headings' => $no_headings,
		'posts_with_5plus_h2'    => $many_h2,
		'by_series'              => $structure_series_output,
	);

	return array(
		'generated_at' => gmdate( 'Y-m-d H:i:s' ),
		'posts_analysed' => $post_count,
		'authors'      => $authors_output,
		'openings'     => $openings,
		'headlines'    => $headline_output,
		'series'       => $series_output,
		'depth'        => $depth_output,
		'structure'    => $structure_output,
	);
}

// ============================================================
// Content samples — selective deep reading
// ============================================================

/**
 * Compiled content samples callback.
 *
 * @param array|null $input Optional input.
 * @return array Content samples.
 */
function abilities_for_ai_editorial_content_samples( $input = null ) {
	$strategy       = $input['strategy'] ?? 'recent_per_series';
	$post_type      = $input['post_type'] ?? 'post';
	$max_words      = min( intval( $input['max_words_per_post'] ?? 1000 ), 3000 );
	$max_total      = min( intval( $input['max_total_posts'] ?? 15 ), 30 );
	$per_series     = min( intval( $input['posts_per_series'] ?? 2 ), 5 );

	$selected_posts = array();

	switch ( $strategy ) {
		case 'by_ids':
			$ids = ! empty( $input['post_ids'] ) ? array_map( 'intval', $input['post_ids'] ) : array();
			if ( ! empty( $ids ) ) {
				$selected_posts = get_posts( array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'post__in'       => array_slice( $ids, 0, $max_total ),
					'orderby'        => 'post__in',
					'posts_per_page' => $max_total,
				) );
			}
			break;

		case 'recent_overall':
			$selected_posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => $max_total,
				'orderby'        => 'date',
				'order'          => 'DESC',
			) );
			break;

		case 'longest_per_series':
			$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => $max_total ) );
			if ( ! is_wp_error( $categories ) ) {
				foreach ( $categories as $cat ) {
					if ( count( $selected_posts ) >= $max_total ) break;
					// Get posts in this category, sorted by content length (approximate).
					$cat_posts = get_posts( array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'category'       => $cat->term_id,
						'posts_per_page' => 10,
						'orderby'        => 'date',
						'order'          => 'DESC',
					) );
					if ( ! empty( $cat_posts ) ) {
						// Find the longest by word count.
						$longest     = null;
						$longest_wc  = 0;
						foreach ( $cat_posts as $cp ) {
							$wc = str_word_count( abilities_for_ai_editorial_strip( $cp->post_content ) );
							if ( $wc > $longest_wc ) {
								$longest    = $cp;
								$longest_wc = $wc;
							}
						}
						if ( $longest ) {
							$selected_posts[] = $longest;
						}
					}
				}
			}
			break;

		case 'recent_per_series':
		default:
			$categories = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 50 ) );
			if ( ! is_wp_error( $categories ) ) {
				foreach ( $categories as $cat ) {
					if ( count( $selected_posts ) >= $max_total ) break;
					$cat_posts = get_posts( array(
						'post_type'      => $post_type,
						'post_status'    => 'publish',
						'category'       => $cat->term_id,
						'posts_per_page' => $per_series,
						'orderby'        => 'date',
						'order'          => 'DESC',
					) );
					foreach ( $cat_posts as $cp ) {
						if ( count( $selected_posts ) >= $max_total ) break;
						// Avoid duplicates (post in multiple categories).
						$already = false;
						foreach ( $selected_posts as $sp ) {
							if ( $sp->ID === $cp->ID ) {
								$already = true;
								break;
							}
						}
						if ( ! $already ) {
							$selected_posts[] = $cp;
						}
					}
				}
			}
			break;
	}

	// Build samples.
	$samples      = array();
	$author_cache = array();

	foreach ( $selected_posts as $post ) {
		$text       = abilities_for_ai_editorial_strip( $post->post_content );
		$word_array = explode( ' ', $text );
		$word_count = $text === '' ? 0 : count( $word_array );
		$truncated  = false;

		if ( $word_count > $max_words ) {
			$text      = implode( ' ', array_slice( $word_array, 0, $max_words ) ) . ' [...]';
			$truncated = true;
		}

		// Author.
		$author_id = (int) $post->post_author;
		if ( ! isset( $author_cache[ $author_id ] ) ) {
			$user = get_userdata( $author_id );
			$author_cache[ $author_id ] = $user ? $user->display_name : "User $author_id";
		}

		// Series (primary category).
		$cats = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$series = ( ! is_wp_error( $cats ) && ! empty( $cats ) ) ? $cats[0] : null;

		$samples[] = array(
			'id'         => $post->ID,
			'title'      => $post->post_title,
			'date'       => substr( $post->post_date, 0, 10 ),
			'author'     => $author_cache[ $author_id ],
			'series'     => $series,
			'word_count' => $word_count,
			'truncated'  => $truncated,
			'text'       => $text,
		);
	}

	return array(
		'generated_at'       => gmdate( 'Y-m-d H:i:s' ),
		'strategy'           => $strategy,
		'posts_per_series'   => $strategy === 'recent_per_series' ? $per_series : null,
		'max_words_per_post' => $max_words,
		'total_posts'        => count( $samples ),
		'samples'            => $samples,
	);
}
