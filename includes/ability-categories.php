<?php
/**
 * Register Ability Categories
 *
 * Categories must be registered BEFORE abilities that reference them.
 * This file runs on the 'wp_abilities_api_categories_init' hook.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_categories_init', 'abilities_for_ai_register_categories' );

/**
 * Register all ability categories for the Abilities for AI
 */
function abilities_for_ai_register_categories() {

    // Content category
    wp_register_ability_category( 'content', array(
        'label' => __( 'Content Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress content including posts, pages, and custom post types.', 'abilities-for-ai' ),
    ));

    // Taxonomies category
    wp_register_ability_category( 'taxonomies', array(
        'label' => __( 'Taxonomy Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress taxonomies, categories, tags, and terms.', 'abilities-for-ai' ),
    ));

    // Plugins category
    wp_register_ability_category( 'plugins', array(
        'label' => __( 'Plugin Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress plugins including installation, activation, and updates.', 'abilities-for-ai' ),
    ));

    // Media category
    wp_register_ability_category( 'media', array(
        'label' => __( 'Media Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress media library including uploads, attachments, and files.', 'abilities-for-ai' ),
    ));

    // Users category
    wp_register_ability_category( 'users', array(
        'label' => __( 'User Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress users, roles, and permissions.', 'abilities-for-ai' ),
    ));

    // Comments category
    wp_register_ability_category( 'comments', array(
        'label' => __( 'Comment Management', 'abilities-for-ai' ),
        'description' => __( 'Abilities for managing WordPress comments and discussions.', 'abilities-for-ai' ),
    ));

    // Menus category
    wp_register_ability_category( 'menus', array(
        'label' => __( 'Menu Management', 'abilities-for-ai' ),
        'description' => __( 'WordPress navigation menu management — menus, items, and location assignments.', 'abilities-for-ai' ),
    ));

    // V3.0 categories.
    wp_register_ability_category( 'blocks', array(
        'label'       => __( 'Block Editor', 'abilities-for-ai' ),
        'description' => __( 'WordPress Gutenberg block parsing, serialization, and manipulation.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'patterns', array(
        'label'       => __( 'Block Patterns', 'abilities-for-ai' ),
        'description' => __( 'WordPress block pattern registration and management.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'meta', array(
        'label'       => __( 'Meta Fields', 'abilities-for-ai' ),
        'description' => __( 'Post, term, and user meta field management.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'settings', array(
        'label'       => __( 'Settings', 'abilities-for-ai' ),
        'description' => __( 'WordPress core settings and options.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'site-health', array(
        'label'       => __( 'Site Health', 'abilities-for-ai' ),
        'description' => __( 'WordPress site health diagnostics and debug information.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'cache', array(
        'label'       => __( 'Cache & Transients', 'abilities-for-ai' ),
        'description' => __( 'WordPress transient and object cache management.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'cron', array(
        'label'       => __( 'Cron', 'abilities-for-ai' ),
        'description' => __( 'WordPress scheduled event and cron management.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'themes', array(
        'label'       => __( 'Themes', 'abilities-for-ai' ),
        'description' => __( 'WordPress theme listing, mods, and theme.json access.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'rest', array(
        'label'       => __( 'REST Discovery', 'abilities-for-ai' ),
        'description' => __( 'WordPress REST API namespace, route, and schema introspection.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'rewrite', array(
        'label'       => __( 'Rewrite Rules', 'abilities-for-ai' ),
        'description' => __( 'WordPress permalink structure and rewrite rule management.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'filesystem', array(
        'label'       => __( 'Filesystem', 'abilities-for-ai' ),
        'description' => __( 'Read and write files within the WordPress installation directory.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'revisions', array(
        'label'       => __( 'Revisions', 'abilities-for-ai' ),
        'description' => __( 'WordPress post revision listing, comparison, restoration, and cleanup.', 'abilities-for-ai' ),
    ));

    wp_register_ability_category( 'multisite', array(
        'label'       => __( 'Multisite', 'abilities-for-ai' ),
        'description' => __( 'WordPress multisite network site management and settings.', 'abilities-for-ai' ),
    ));

    // Diagnostics — compiled scripts.
    wp_register_ability_category( 'diagnostic', array(
        'label'       => __( 'Diagnostics', 'abilities-for-ai' ),
        'description' => __( 'Compiled diagnostic scripts for single-call site assessment.', 'abilities-for-ai' ),
    ));

    // Editorial — content analysis and editorial intelligence.
    wp_register_ability_category( 'editorial', array(
        'label'       => __( 'Editorial', 'abilities-for-ai' ),
        'description' => __( 'Content analysis and editorial intelligence scripts.', 'abilities-for-ai' ),
    ));

    // === THIRD-PARTY SUITE CATEGORIES ===
    // Suite categories MUST be registered here (during wp_abilities_api_categories_init).
    // WP core rejects wp_register_ability_category() calls outside this hook.
    // Each category is gated by plugin/theme detection so it only registers when relevant.

    // Presto Player — video and media player.
    if ( class_exists( 'PrestoPlayer\Plugin' ) ) {
        wp_register_ability_category( 'presto-player', array(
            'label'       => __( 'Presto Player', 'abilities-for-ai' ),
            'description' => __( 'Video and media player abilities for Presto Player — videos, presets, analytics, and player configuration. This is an independent integration and is not affiliated with or endorsed by Starter Starter LLC.', 'abilities-for-ai' ),
        ));
    }

    // Spectra — block operations.
    if ( class_exists( 'UAGB_Loader' ) ) {
        wp_register_ability_category( 'spectra', array(
            'label'       => __( 'Spectra', 'abilities-for-ai' ),
            'description' => __( 'Block operation abilities for Spectra — parse, inspect, validate, insert, and manage Spectra blocks in page content. This is an independent integration and is not affiliated with or endorsed by Brainstorm Force.', 'abilities-for-ai' ),
        ));
    }

    // SureCart — e-commerce.
    if ( defined( 'SURECART_PLUGIN_FILE' ) ) {
        wp_register_ability_category( 'surecart', array(
            'label'       => __( 'SureCart', 'abilities-for-ai' ),
            'description' => __( 'E-commerce abilities for SureCart — products, orders, customers, subscriptions, and store management. This is an independent integration and is not affiliated with or endorsed by SureCart Inc.', 'abilities-for-ai' ),
        ));
    }

    // Astra — theme configuration.
    if ( defined( 'ASTRA_THEME_VERSION' ) ) {
        wp_register_ability_category( 'astra', array(
            'label'       => __( 'Astra', 'abilities-for-ai' ),
            'description' => __( 'Theme configuration abilities for Astra — design tokens, layouts, headers, footers, and Pro modules. This is an independent integration and is not affiliated with or endorsed by Brainstorm Force.', 'abilities-for-ai' ),
        ));
    }
}
