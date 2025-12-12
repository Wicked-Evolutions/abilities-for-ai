<?php
/**
 * WordPress Abilities Suite Dashboard
 * Admin interface for viewing and managing abilities
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_Dashboard {

    public function __construct() {
        add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function add_menu_pages() {
        // Main menu page
        add_menu_page(
            'WordPress Abilities Suite',
            'Abilities Suite',
            'manage_options',
            'wp-abilities-suite',
            array( $this, 'render_dashboard' ),
            'dashicons-admin-tools',
            30
        );

        // Submenu pages
        add_submenu_page(
            'wp-abilities-suite',
            'All Abilities',
            'All Abilities',
            'manage_options',
            'wp-abilities-suite',
            array( $this, 'render_dashboard' )
        );

        add_submenu_page(
            'wp-abilities-suite',
            'Test Abilities',
            'Test Abilities',
            'manage_options',
            'wp-abilities-test',
            array( $this, 'render_test_page' )
        );

        add_submenu_page(
            'wp-abilities-suite',
            'Settings',
            'Settings',
            'manage_options',
            'wp-abilities-settings',
            array( $this, 'render_settings' )
        );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wp-abilities' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'wp-abilities-suite-admin',
            WP_ABILITIES_SUITE_URL . 'admin/css/dashboard.css',
            array(),
            WP_ABILITIES_SUITE_VERSION
        );
    }

    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1>WordPress Abilities Suite</h1>
            <p>Manage and monitor all registered WordPress abilities for MCP integration.</p>

            <?php $this->render_stats_cards(); ?>
            <?php $this->render_abilities_table(); ?>
        </div>
        <?php
    }

    private function render_stats_cards() {
        $abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

        // Convert WP_Ability objects to arrays for display
        $abilities_array = array();
        foreach ( $abilities as $name => $ability ) {
            if ( is_object( $ability ) && method_exists( $ability, 'get_label' ) ) {
                $abilities_array[$name] = array(
                    'label' => $ability->get_label(),
                    'description' => $ability->get_description(),
                    'category' => $ability->get_category(),
                    'meta' => $ability->get_meta(),
                );
            }
        }
        $abilities = $abilities_array;

        $categories = array();
        foreach ( $abilities as $ability ) {
            $cat = $ability['category'] ?? 'uncategorized';
            if ( ! isset( $categories[$cat] ) ) {
                $categories[$cat] = 0;
            }
            $categories[$cat]++;
        }

        ?>
        <div class="wp-abilities-stats">
            <div class="stats-card">
                <h3><?php echo count( $abilities ); ?></h3>
                <p>Total Abilities</p>
            </div>
            <div class="stats-card">
                <h3><?php echo count( $categories ); ?></h3>
                <p>Categories</p>
            </div>
            <div class="stats-card">
                <h3><?php echo WP_ABILITIES_SUITE_VERSION; ?></h3>
                <p>Plugin Version</p>
            </div>
        </div>

        <div class="wp-abilities-categories">
            <h2>Abilities by Category</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $categories as $category => $count ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( ucfirst( $category ) ); ?></strong></td>
                            <td><?php echo esc_html( $count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_abilities_table() {
        $abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

        // Convert WP_Ability objects to arrays for display
        $abilities_array = array();
        foreach ( $abilities as $name => $ability ) {
            if ( is_object( $ability ) && method_exists( $ability, 'get_label' ) ) {
                $abilities_array[$name] = array(
                    'label' => $ability->get_label(),
                    'description' => $ability->get_description(),
                    'category' => $ability->get_category(),
                    'meta' => $ability->get_meta(),
                );
            }
        }
        $abilities = $abilities_array;

        // Filter by category if requested
        $category_filter = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';
        if ( $category_filter ) {
            $abilities = array_filter( $abilities, function( $ability ) use ( $category_filter ) {
                return ( $ability['category'] ?? '' ) === $category_filter;
            });
        }

        ?>
        <div class="wp-abilities-list">
            <h2>All Registered Abilities</h2>

            <?php if ( empty( $abilities ) ) : ?>
                <div class="notice notice-warning">
                    <p>No abilities registered yet. Make sure the WordPress Abilities API plugin is active.</p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Ability Name</th>
                            <th style="width: 20%;">Category</th>
                            <th style="width: 35%;">Description</th>
                            <th style="width: 15%;">Annotations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $abilities as $name => $ability ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $name ); ?></strong>
                                    <div class="row-actions">
                                        <a href="<?php echo admin_url( 'admin.php?page=wp-abilities-test&ability=' . urlencode( $name ) ); ?>">Test</a>
                                    </div>
                                </td>
                                <td>
                                    <span class="ability-category">
                                        <?php echo esc_html( ucfirst( $ability['category'] ?? 'uncategorized' ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html( $ability['description'] ?? 'No description' ); ?></td>
                                <td>
                                    <?php
                                    $annotations = $ability['meta']['annotations'] ?? array();
                                    $badges = array();

                                    if ( ! empty( $annotations['readonly'] ) ) {
                                        $badges[] = '<span class="badge badge-readonly">Read-only</span>';
                                    }
                                    if ( ! empty( $annotations['destructive'] ) ) {
                                        $badges[] = '<span class="badge badge-destructive">Destructive</span>';
                                    }
                                    if ( ! empty( $annotations['idempotent'] ) ) {
                                        $badges[] = '<span class="badge badge-idempotent">Idempotent</span>';
                                    }

                                    echo implode( ' ', $badges );
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_test_page() {
        $ability_name = isset( $_GET['ability'] ) ? sanitize_text_field( $_GET['ability'] ) : '';
        $abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

        // Convert WP_Ability objects to arrays for display
        $abilities_array = array();
        foreach ( $abilities as $name => $ability ) {
            if ( is_object( $ability ) && method_exists( $ability, 'get_label' ) ) {
                $abilities_array[$name] = array(
                    'label' => $ability->get_label(),
                    'description' => $ability->get_description(),
                    'category' => $ability->get_category(),
                    'input_schema' => $ability->get_input_schema(),
                    'output_schema' => $ability->get_output_schema(),
                    'meta' => $ability->get_meta(),
                );
            }
        }
        $abilities = $abilities_array;

        ?>
        <div class="wrap">
            <h1>Test Abilities</h1>

            <div class="wp-abilities-test">
                <form method="get">
                    <input type="hidden" name="page" value="wp-abilities-test">
                    <label for="ability-select">Select Ability:</label>
                    <select name="ability" id="ability-select" onchange="this.form.submit()">
                        <option value="">-- Choose an ability --</option>
                        <?php foreach ( $abilities as $name => $ability ) : ?>
                            <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $ability_name, $name ); ?>>
                                <?php echo esc_html( $name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <?php if ( $ability_name && isset( $abilities[$ability_name] ) ) : ?>
                    <?php $ability = $abilities[$ability_name]; ?>

                    <div class="ability-details">
                        <h2><?php echo esc_html( $ability_name ); ?></h2>
                        <p><strong>Description:</strong> <?php echo esc_html( $ability['description'] ?? 'No description' ); ?></p>
                        <p><strong>Category:</strong> <?php echo esc_html( $ability['category'] ?? 'uncategorized' ); ?></p>

                        <h3>Input Schema</h3>
                        <pre><?php echo esc_html( json_encode( $ability['input_schema'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre>

                        <h3>Output Schema</h3>
                        <pre><?php echo esc_html( json_encode( $ability['output_schema'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre>

                        <div class="notice notice-info">
                            <p><strong>Note:</strong> To test this ability, use the MCP client or WordPress REST API endpoint.</p>
                            <p>Endpoint: <code>/wp-json/mcp/v1/abilities/<?php echo esc_html( $ability_name ); ?></code></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_settings() {
        ?>
        <div class="wrap">
            <h1>Abilities Suite Settings</h1>

            <div class="wp-abilities-settings">
                <table class="form-table">
                    <tr>
                        <th scope="row">Plugin Version</th>
                        <td><?php echo esc_html( WP_ABILITIES_SUITE_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Multisite Network</th>
                        <td><?php echo is_multisite() ? 'Yes' : 'No'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">WordPress Version</th>
                        <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">PHP Version</th>
                        <td><?php echo esc_html( PHP_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Abilities API Active</th>
                        <td>
                            <?php if ( function_exists( 'wp_register_ability' ) ) : ?>
                                <span style="color: green;">✓ Yes</span>
                            <?php else : ?>
                                <span style="color: red;">✗ No - Please activate the WordPress Abilities API plugin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">MCP Adapter Active</th>
                        <td>
                            <?php if ( is_plugin_active( 'wp-mcp-adapter/wp-mcp-adapter.php' ) || is_plugin_active_for_network( 'wp-mcp-adapter/wp-mcp-adapter.php' ) ) : ?>
                                <span style="color: green;">✓ Yes</span>
                            <?php else : ?>
                                <span style="color: orange;">⚠ Not detected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <h2>Debug Information</h2>
                <p>If you're experiencing issues, copy this information when reporting bugs:</p>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">
Plugin: WordPress Abilities Suite v<?php echo WP_ABILITIES_SUITE_VERSION; ?>

WordPress: <?php echo get_bloginfo( 'version' ); ?>

PHP: <?php echo PHP_VERSION; ?>

Multisite: <?php echo is_multisite() ? 'Yes' : 'No'; ?>

Abilities API: <?php echo function_exists( 'wp_register_ability' ) ? 'Active' : 'Inactive'; ?>

Total Abilities: <?php echo count( function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array() ); ?>

Active Plugins: <?php echo count( get_option( 'active_plugins', array() ) ); ?>
</textarea>
            </div>
        </div>
        <?php
    }
}

// Initialize dashboard
new WP_Abilities_Suite_Dashboard();
