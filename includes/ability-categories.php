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
        'label' => __( 'Content Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress content including posts, pages, and custom post types.', 'wordpress-abilities-suite' ),
    ));

    // Taxonomies category
    wp_register_ability_category( 'taxonomies', array(
        'label' => __( 'Taxonomy Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress taxonomies, categories, tags, and terms.', 'wordpress-abilities-suite' ),
    ));

    // Plugins category
    wp_register_ability_category( 'plugins', array(
        'label' => __( 'Plugin Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress plugins including installation, activation, and updates.', 'wordpress-abilities-suite' ),
    ));

    // Media category
    wp_register_ability_category( 'media', array(
        'label' => __( 'Media Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress media library including uploads, attachments, and files.', 'wordpress-abilities-suite' ),
    ));

    // Users category
    wp_register_ability_category( 'users', array(
        'label' => __( 'User Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress users, roles, and permissions.', 'wordpress-abilities-suite' ),
    ));

    // Comments category
    wp_register_ability_category( 'comments', array(
        'label' => __( 'Comment Management', 'wordpress-abilities-suite' ),
        'description' => __( 'Abilities for managing WordPress comments and discussions.', 'wordpress-abilities-suite' ),
    ));

    // Menus category
    wp_register_ability_category( 'menus', array(
        'label' => __( 'Menu Management', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress navigation menu management — menus, items, and location assignments.', 'wordpress-abilities-suite' ),
    ));

    // V3.0 categories.
    wp_register_ability_category( 'blocks', array(
        'label'       => __( 'Block Editor', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress Gutenberg block parsing, serialization, and manipulation.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'patterns', array(
        'label'       => __( 'Block Patterns', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress block pattern registration and management.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'meta', array(
        'label'       => __( 'Meta Fields', 'wordpress-abilities-suite' ),
        'description' => __( 'Post, term, and user meta field management.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'settings', array(
        'label'       => __( 'Settings', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress core settings and options.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'site-health', array(
        'label'       => __( 'Site Health', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress site health diagnostics and debug information.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'cache', array(
        'label'       => __( 'Cache & Transients', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress transient and object cache management.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'cron', array(
        'label'       => __( 'Cron', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress scheduled event and cron management.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'themes', array(
        'label'       => __( 'Themes', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress theme listing, mods, and theme.json access.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'rest', array(
        'label'       => __( 'REST Discovery', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress REST API namespace, route, and schema introspection.', 'wordpress-abilities-suite' ),
    ));

    wp_register_ability_category( 'rewrite', array(
        'label'       => __( 'Rewrite Rules', 'wordpress-abilities-suite' ),
        'description' => __( 'WordPress permalink structure and rewrite rule management.', 'wordpress-abilities-suite' ),
    ));

    error_log( 'WordPress Abilities Suite: Registered 17 ability categories' );
}
