<?php
/**
 * JSON Schema Audit Script for WordPress Abilities Suite.
 *
 * Run via: wp eval-file wp-content/plugins/abilities-suite-for-wordpress/audit-schema.php
 *
 * Checks the 3 critical rules that can break the entire MCP tool list:
 * 1. Empty properties must be {} (object), not [] (array)
 * 2. Array-type properties must have 'items'
 * 3. Every property must have a 'type' field
 */

if ( ! function_exists( 'wp_get_abilities' ) ) {
    WP_CLI::error( 'Abilities API not loaded. Is abilities-api plugin active?' );
}

$abilities = wp_get_abilities();
$errors    = array();
$checked   = 0;

foreach ( $abilities as $name => $ability ) {
    if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_input_schema' ) ) {
        continue;
    }

    $checked++;
    $input_schema  = $ability->get_input_schema();
    $output_schema = $ability->get_output_schema();

    // Check input schema
    $input_errors = check_schema( $input_schema, "input_schema" );
    foreach ( $input_errors as $err ) {
        $errors[] = "[{$name}] {$err}";
    }

    // Check output schema
    $output_errors = check_schema( $output_schema, "output_schema" );
    foreach ( $output_errors as $err ) {
        $errors[] = "[{$name}] {$err}";
    }
}

function check_schema( $schema, $path ) {
    $errors = array();

    if ( ! is_array( $schema ) && ! is_object( $schema ) ) {
        return $errors;
    }

    $schema = (array) $schema;

    // Rule 1: Check if 'properties' is an empty JSON array instead of object
    if ( isset( $schema['properties'] ) ) {
        if ( is_array( $schema['properties'] ) && empty( $schema['properties'] ) ) {
            // Check if it would serialize to [] instead of {}
            $encoded = json_encode( $schema['properties'] );
            if ( $encoded === '[]' ) {
                $errors[] = "{$path}.properties is empty array [] — must be {} (use (object) array())";
            }
        }

        // Recurse into properties
        if ( is_array( $schema['properties'] ) || is_object( $schema['properties'] ) ) {
            foreach ( (array) $schema['properties'] as $prop_name => $prop ) {
                $prop = (array) $prop;

                // Rule 3: Every property must have 'type'
                if ( ! isset( $prop['type'] ) ) {
                    $errors[] = "{$path}.properties.{$prop_name} is missing 'type'";
                }

                // Rule 2: Array types must have 'items'
                if ( isset( $prop['type'] ) && $prop['type'] === 'array' && ! isset( $prop['items'] ) ) {
                    $errors[] = "{$path}.properties.{$prop_name} is type 'array' but missing 'items'";
                }

                // Recurse if property has nested properties or items
                if ( isset( $prop['properties'] ) ) {
                    $nested = check_schema( $prop, "{$path}.properties.{$prop_name}" );
                    $errors = array_merge( $errors, $nested );
                }
                if ( isset( $prop['items'] ) && ( is_array( $prop['items'] ) || is_object( $prop['items'] ) ) ) {
                    $nested = check_schema( (array) $prop['items'], "{$path}.properties.{$prop_name}.items" );
                    $errors = array_merge( $errors, $nested );
                }
            }
        }
    }

    // Check items at current level (for array-type schemas)
    if ( isset( $schema['type'] ) && $schema['type'] === 'array' && ! isset( $schema['items'] ) ) {
        $errors[] = "{$path} is type 'array' but missing 'items'";
    }

    if ( isset( $schema['items'] ) && ( is_array( $schema['items'] ) || is_object( $schema['items'] ) ) ) {
        $nested = check_schema( (array) $schema['items'], "{$path}.items" );
        $errors = array_merge( $errors, $nested );
    }

    return $errors;
}

// Output results
WP_CLI::log( '' );
WP_CLI::log( "=== WordPress Abilities Suite — Schema Audit ===" );
WP_CLI::log( "Checked: {$checked} abilities" );
WP_CLI::log( '' );

if ( empty( $errors ) ) {
    WP_CLI::success( "All {$checked} abilities pass JSON Schema validation!" );
} else {
    WP_CLI::warning( count( $errors ) . " schema violations found:" );
    WP_CLI::log( '' );
    foreach ( $errors as $err ) {
        WP_CLI::log( "  ✗ {$err}" );
    }
    WP_CLI::log( '' );
    WP_CLI::error( 'Fix these violations before deploying — they will break the entire MCP tool list.' );
}
