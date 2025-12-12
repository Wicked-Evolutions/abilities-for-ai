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

    error_log( 'WordPress Abilities Suite: Registered 6 ability categories' );
}
