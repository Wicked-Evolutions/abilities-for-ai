<?php
/**
 * Knowledge Layer — Markdown to Gutenberg Blocks Transformer.
 *
 * Converts markdown content to WordPress block markup for the publish pipeline.
 * Handles headings, paragraphs, code blocks, lists, blockquotes, tables,
 * horizontal rules, and inline formatting (bold, italic, code, links).
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package WickedEvolutions\AbilitiesForAI\Knowledge
 */

namespace WickedEvolutions\AbilitiesForAI\Knowledge;

defined( 'ABSPATH' ) || exit;

class MarkdownToBlocks {

	/**
	 * Convert markdown to Gutenberg block markup.
	 *
	 * @param string $markdown Raw markdown content.
	 * @return string Gutenberg block markup.
	 */
	public static function convert( $markdown ) {
		if ( empty( $markdown ) ) {
			return '';
		}

		$lines  = explode( "\n", str_replace( "\r\n", "\n", $markdown ) );
		$blocks = array();
		$i      = 0;
		$count  = count( $lines );

		while ( $i < $count ) {
			$line = $lines[ $i ];

			// Skip empty lines.
			if ( trim( $line ) === '' ) {
				$i++;
				continue;
			}

			// Fenced code block.
			if ( preg_match( '/^```(.*)$/', trim( $line ), $m ) ) {
				$code_lines = array();
				$i++;
				while ( $i < $count && trim( $lines[ $i ] ) !== '```' ) {
					$code_lines[] = $lines[ $i ];
					$i++;
				}
				$i++; // skip closing ```
				$code = esc_html( implode( "\n", $code_lines ) );
				$blocks[] = "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>{$code}</code></pre>\n<!-- /wp:code -->";
				continue;
			}

			// Horizontal rule.
			if ( preg_match( '/^---+$/', trim( $line ) ) ) {
				$blocks[] = '<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->';
				$i++;
				continue;
			}

			// Headings.
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$text  = self::inline( trim( $m[2] ) );
				$tag   = "h{$level}";
				$blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n<{$tag}>{$text}</{$tag}>\n<!-- /wp:heading -->";
				$i++;
				continue;
			}

			// Blockquote.
			if ( preg_match( '/^>\s?(.*)$/', $line, $m ) ) {
				$quote_lines = array( trim( $m[1] ) );
				$i++;
				while ( $i < $count && preg_match( '/^>\s?(.*)$/', $lines[ $i ], $m ) ) {
					$quote_lines[] = trim( $m[1] );
					$i++;
				}
				$text = self::inline( implode( ' ', $quote_lines ) );
				$blocks[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><p>{$text}</p></blockquote>\n<!-- /wp:quote -->";
				continue;
			}

			// Unordered list.
			if ( preg_match( '/^[-*]\s+(.+)$/', $line ) ) {
				$items = array();
				while ( $i < $count && preg_match( '/^[-*]\s+(.+)$/', $lines[ $i ], $m ) ) {
					$items[] = '<li>' . self::inline( trim( $m[1] ) ) . '</li>';
					$i++;
				}
				$list = implode( '', $items );
				$blocks[] = "<!-- wp:list -->\n<ul>{$list}</ul>\n<!-- /wp:list -->";
				continue;
			}

			// Ordered list.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line ) ) {
				$items = array();
				while ( $i < $count && preg_match( '/^\d+\.\s+(.+)$/', $lines[ $i ], $m ) ) {
					$items[] = '<li>' . self::inline( trim( $m[1] ) ) . '</li>';
					$i++;
				}
				$list = implode( '', $items );
				$blocks[] = "<!-- wp:list {\"ordered\":true} -->\n<ol>{$list}</ol>\n<!-- /wp:list -->";
				continue;
			}

			// Table.
			if ( preg_match( '/^\|(.+)\|$/', trim( $line ) ) ) {
				$rows   = array();
				$header = true;
				while ( $i < $count && preg_match( '/^\|(.+)\|$/', trim( $lines[ $i ] ) ) ) {
					$row_text = trim( $lines[ $i ] );
					// Skip separator row (|---|---|).
					if ( preg_match( '/^\|[\s\-:|]+\|$/', $row_text ) ) {
						$i++;
						continue;
					}
					$cells = array_map( 'trim', explode( '|', trim( $row_text, '|' ) ) );
					$tag   = $header ? 'th' : 'td';
					$row   = '<tr>' . implode( '', array_map( function( $c ) use ( $tag ) {
						return "<{$tag}>" . MarkdownToBlocks::inline( $c ) . "</{$tag}>";
					}, $cells ) ) . '</tr>';
					if ( $header ) {
						$rows[] = '<thead>' . $row . '</thead><tbody>';
						$header = false;
					} else {
						$rows[] = $row;
					}
					$i++;
				}
				$rows[] = '</tbody>';
				$table  = '<figure class="wp-block-table"><table>' . implode( '', $rows ) . '</table></figure>';
				$blocks[] = "<!-- wp:table -->\n{$table}\n<!-- /wp:table -->";
				continue;
			}

			// Paragraph (default): collect consecutive non-empty, non-special lines.
			$para_lines = array();
			while ( $i < $count && trim( $lines[ $i ] ) !== '' &&
					! preg_match( '/^(#{1,6}\s|[-*]\s|\d+\.\s|>\s?|```|---+$|\|.+\|$)/', $lines[ $i ] ) ) {
				$para_lines[] = trim( $lines[ $i ] );
				$i++;
			}
			if ( ! empty( $para_lines ) ) {
				$text = self::inline( implode( ' ', $para_lines ) );
				$blocks[] = "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
			}
		}

		return implode( "\n\n", $blocks );
	}

	/**
	 * Process inline markdown formatting.
	 *
	 * @param string $text Raw inline text.
	 * @return string HTML with inline formatting.
	 */
	public static function inline( $text ) {
		// Links: [text](url)
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text );

		// Bold: **text**
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );

		// Italic: *text*
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text );

		// Inline code: `text`
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		return $text;
	}
}
