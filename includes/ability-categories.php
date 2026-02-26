<?php
/**
 * Register Ability Categories
 *
 * Categories must be registered BEFORE abilities that reference them.
 * This file runs on the 'wp_abilities_api_categories_init' hook.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_categories_init', 'wp_abilities_suite_register_categories' );

/**
 * Register all ability categories for the WordPress Abilities Suite
 */
function wp_abilities_suite_register_categories() {

    // Content category
    wp_register_ability_category( 'content', array(
        'label' => __( 'Content Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress content including posts, pages, and custom post types.', 'abilities-suite-for-wordpress' ),
    ));

    // Taxonomies category
    wp_register_ability_category( 'taxonomies', array(
        'label' => __( 'Taxonomy Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress taxonomies, categories, tags, and terms.', 'abilities-suite-for-wordpress' ),
    ));

    // Plugins category
    wp_register_ability_category( 'plugins', array(
        'label' => __( 'Plugin Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress plugins including installation, activation, and updates.', 'abilities-suite-for-wordpress' ),
    ));

    // Media category
    wp_register_ability_category( 'media', array(
        'label' => __( 'Media Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress media library including uploads, attachments, and files.', 'abilities-suite-for-wordpress' ),
    ));

    // Users category
    wp_register_ability_category( 'users', array(
        'label' => __( 'User Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress users, roles, and permissions.', 'abilities-suite-for-wordpress' ),
    ));

    // Comments category
    wp_register_ability_category( 'comments', array(
        'label' => __( 'Comment Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Abilities for managing WordPress comments and discussions.', 'abilities-suite-for-wordpress' ),
    ));

    // Menus category
    wp_register_ability_category( 'menus', array(
        'label' => __( 'Menu Management', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress navigation menu management — menus, items, and location assignments.', 'abilities-suite-for-wordpress' ),
    ));

    // V3.0 categories.
    wp_register_ability_category( 'blocks', array(
        'label'       => __( 'Block Editor', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress Gutenberg block parsing, serialization, and manipulation.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'patterns', array(
        'label'       => __( 'Block Patterns', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress block pattern registration and management.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'meta', array(
        'label'       => __( 'Meta Fields', 'abilities-suite-for-wordpress' ),
        'description' => __( 'Post, term, and user meta field management.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'settings', array(
        'label'       => __( 'Settings', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress core settings and options.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'site-health', array(
        'label'       => __( 'Site Health', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress site health diagnostics and debug information.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'cache', array(
        'label'       => __( 'Cache & Transients', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress transient and object cache management.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'cron', array(
        'label'       => __( 'Cron', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress scheduled event and cron management.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'themes', array(
        'label'       => __( 'Themes', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress theme listing, mods, and theme.json access.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'rest', array(
        'label'       => __( 'REST Discovery', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress REST API namespace, route, and schema introspection.', 'abilities-suite-for-wordpress' ),
    ));

    wp_register_ability_category( 'rewrite', array(
        'label'       => __( 'Rewrite Rules', 'abilities-suite-for-wordpress' ),
        'description' => __( 'WordPress permalink structure and rewrite rule management.', 'abilities-suite-for-wordpress' ),
    ));

    error_log( 'WordPress Abilities Suite: Registered 17 ability categories' );
}
