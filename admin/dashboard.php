<?php
/**
 * Abilities for WordPress — Unified Dashboard
 *
 * Single admin page with two tabs:
 *   1. Explorer — filterable table of ALL registered abilities with inline R/W/D
 *                 permission toggles (per-module bulk + per-ability individual).
 *   2. License — activate/deactivate the Pro license key.
 *
 * Replaces the previous separate Explorer + Settings pages.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_Dashboard {

	/**
	 * Source detection map: category slug → human-readable source.
	 */
	private static $source_map = array(
		// WordPress Core (abilities-suite-for-wordpress).
		'content'     => 'WordPress Core',
		'taxonomies'  => 'WordPress Core',
		'plugins'     => 'WordPress Core',
		'media'       => 'WordPress Core',
		'users'       => 'WordPress Core',
		'comments'    => 'WordPress Core',
		'menus'       => 'WordPress Core',
		'blocks'      => 'WordPress Core',
		'patterns'    => 'WordPress Core',
		'meta'        => 'WordPress Core',
		'settings'    => 'WordPress Core',
		'site-health' => 'WordPress Core',
		'cache'       => 'WordPress Core',
		'cron'        => 'WordPress Core',
		'themes'      => 'WordPress Core',
		'rest'        => 'WordPress Core',
		'rewrite'     => 'WordPress Core',
		'filesystem'  => 'WordPress Core',
		'site'        => 'WordPress Core',
		'user'        => 'WordPress Core',
		// MCP Adapter.
		'mcp-adapter' => 'MCP Adapter',
		// Fluent Suite.
		'fluent-crm'       => 'Fluent Plugins',
		'fluent-community' => 'Fluent Plugins',
		'fluent-forms'     => 'Fluent Plugins',
		'fluent-support'   => 'Fluent Plugins',
		'fluent-boards'    => 'Fluent Plugins',
		'fluent-booking'   => 'Fluent Plugins',
		'fluent-smtp'      => 'Fluent Plugins',
		'fluent-auth'      => 'Fluent Plugins',
		'fluent-snippets'  => 'Fluent Plugins',
		'fluent-messaging' => 'Fluent Plugins',
		'fluent'           => 'Fluent Plugins',
	);

	/**
	 * Source CSS class map.
	 */
	private static $source_css = array(
		'WordPress Core' => 'source-wp-core',
		'Fluent Plugins'  => 'source-fluent',
		'MCP Adapter'    => 'source-mcp',
	);

	public function __construct() {
		add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
	}

	public function add_menu_pages() {
		add_menu_page(
			'Abilities for WordPress',
			'Abilities for WordPress',
			'manage_options',
			'wp-abilities-suite',
			array( $this, 'render_page' ),
			'dashicons-admin-tools',
			30
		);

		// Single submenu that mirrors the parent (removes "Abilities for WordPress" duplicate).
		add_submenu_page(
			'wp-abilities-suite',
			'Abilities for WordPress',
			'Dashboard',
			'manage_options',
			'wp-abilities-suite',
			array( $this, 'render_page' )
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

	/**
	 * Handle license activate/deactivate form submissions.
	 */
	public function handle_license_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Activate.
		if ( isset( $_POST['wp_abilities_license_activate'] ) ) {
			check_admin_referer( 'wp_abilities_suite_license_nonce' );
			$key    = sanitize_text_field( wp_unslash( $_POST['wp_abilities_license_key'] ?? '' ) );
			$result = WP_Abilities_Suite_License_Manager::activate( $key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'wp_abilities_license', 'activation_failed', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'wp_abilities_license', 'activated', __( 'License activated successfully.', 'wp-abilities-suite' ), 'success' );
			}
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-abilities-suite', 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Deactivate.
		if ( isset( $_POST['wp_abilities_license_deactivate'] ) ) {
			check_admin_referer( 'wp_abilities_suite_license_nonce' );
			WP_Abilities_Suite_License_Manager::deactivate();
			add_settings_error( 'wp_abilities_license', 'deactivated', __( 'License deactivated.', 'wp-abilities-suite' ), 'info' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( add_query_arg( array( 'page' => 'wp-abilities-suite', 'tab' => 'license', 'settings-updated' => 'true' ), admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	// ─── Source Helpers ──────────────────────────────────────────

	public static function get_source( $category ) {
		return self::$source_map[ $category ] ?? 'Other';
	}

	public static function get_source_css( $source ) {
		return self::$source_css[ $source ] ?? 'source-other';
	}

	// ─── Data Loading ────────────────────────────────────────────

	/**
	 * Load and normalise all abilities into arrays.
	 */
	private function get_abilities_array() {
		$abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		$result    = array();

		foreach ( $abilities as $name => $ability ) {
			if ( ! is_object( $ability ) || ! method_exists( $ability, 'get_label' ) ) {
				continue;
			}

			$category = $ability->get_category();
			$meta     = $ability->get_meta();
			$source   = self::get_source( $category );
			$readonly = ! empty( $meta['annotations']['readonly'] );

			$result[ $name ] = array(
				'label'         => $ability->get_label(),
				'description'   => $ability->get_description(),
				'category'      => $category,
				'source'        => $source,
				'readonly'      => $readonly,
				'destructive'   => ! empty( $meta['annotations']['destructive'] ),
				'idempotent'    => ! empty( $meta['annotations']['idempotent'] ),
				'tier'          => ! empty( $meta['tier'] ) ? $meta['tier'] : 'free',
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $meta,
			);
		}

		// Sort by source → category → name.
		uasort( $result, function( $a, $b ) {
			$cmp = strcmp( $a['source'], $b['source'] );
			if ( $cmp !== 0 ) return $cmp;
			return strcmp( $a['category'], $b['category'] );
		});

		return $result;
	}

	/**
	 * Determine the operation type string for an ability.
	 *
	 * @param array $ability Normalised ability data.
	 * @return string 'read', 'write', or 'delete'.
	 */
	private function get_ability_op( $ability ) {
		if ( $ability['readonly'] ) {
			return 'read';
		}
		if ( $ability['destructive'] ) {
			return 'delete';
		}
		return 'write';
	}

	// ─── Main Render ─────────────────────────────────────────────

	/**
	 * Render the unified dashboard page.
	 */
	public function render_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'explorer';
		$saved      = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];
		?>
		<div class="wrap">
			<h1>Abilities for WordPress</h1>
			<p class="wp-abilities-subtitle">v<?php echo esc_html( WP_ABILITIES_SUITE_VERSION ); ?> — Universal control panel for all registered WordPress abilities</p>

			<?php if ( $saved ) : ?>
				<?php settings_errors( 'wp_abilities_license' ); ?>
			<?php endif; ?>

			<nav class="nav-tab-wrapper wp-abilities-tabs">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-abilities-suite', 'tab' => 'explorer' ), admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'explorer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					Explorer
				</a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wp-abilities-suite', 'tab' => 'license' ), admin_url( 'admin.php' ) ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					License
				</a>
			</nav>

			<?php
			if ( 'license' === $active_tab ) {
				$this->render_license_tab();
			} else {
				$this->render_explorer_tab();
			}
			?>
		</div>
		<?php
	}

	// ─── Explorer Tab ────────────────────────────────────────────

	private function render_explorer_tab() {
		$abilities = $this->get_abilities_array();

		// Collect unique sources and categories for filter dropdowns.
		$all_sources    = array();
		$all_categories = array();
		foreach ( $abilities as $a ) {
			$all_sources[ $a['source'] ]     = ( $all_sources[ $a['source'] ] ?? 0 ) + 1;
			$all_categories[ $a['category'] ] = ( $all_categories[ $a['category'] ] ?? 0 ) + 1;
		}
		ksort( $all_sources );
		ksort( $all_categories );

		// Read filters from query string.
		$source_filter   = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$type_filter     = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : '';
		$search_filter   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Apply filters.
		$filtered = $abilities;
		if ( $source_filter ) {
			$filtered = array_filter( $filtered, function( $a ) use ( $source_filter ) {
				return $a['source'] === $source_filter;
			});
		}
		if ( $category_filter ) {
			$filtered = array_filter( $filtered, function( $a ) use ( $category_filter ) {
				return $a['category'] === $category_filter;
			});
		}
		if ( 'read' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return $a['readonly']; });
		} elseif ( 'write' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return ! $a['readonly'] && ! $a['destructive']; });
		} elseif ( 'destructive' === $type_filter ) {
			$filtered = array_filter( $filtered, function( $a ) { return $a['destructive']; });
		}
		if ( $search_filter ) {
			$search_lower = strtolower( $search_filter );
			$filtered = array_filter( $filtered, function( $a ) use ( $search_lower ) {
				return false !== strpos( strtolower( $a['label'] ), $search_lower )
					|| false !== strpos( strtolower( $a['description'] ), $search_lower )
					|| false !== strpos( strtolower( $a['category'] ), $search_lower );
			});
		}

		// Categories available for current source filter (cascading dropdown).
		$available_categories = $all_categories;
		if ( $source_filter ) {
			$available_categories = array();
			foreach ( $abilities as $a ) {
				if ( $a['source'] === $source_filter ) {
					$available_categories[ $a['category'] ] = ( $available_categories[ $a['category'] ] ?? 0 ) + 1;
				}
			}
			ksort( $available_categories );
		}

		// Permission & count data.
		$enabled_info = wp_abilities_suite_enabled_count();
		$perm_saved   = isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'];

		$this->render_stats( $abilities, $all_sources, $all_categories, $enabled_info );
		$this->render_filter_bar( $all_sources, $available_categories, $source_filter, $category_filter, $type_filter, $search_filter );
		$this->render_explorer_table( $filtered, $source_filter, $category_filter, $type_filter );
		$this->render_debug_info( $abilities );
	}

	/**
	 * Stats cards row.
	 */
	private function render_stats( $abilities, $sources, $categories, $enabled_info ) {
		?>
		<div class="wp-abilities-stats">
			<div class="stats-card">
				<h3><?php echo count( $abilities ); ?></h3>
				<p>Total Abilities</p>
			</div>
			<div class="stats-card stats-card-green">
				<h3 id="enabled-count"><?php echo $enabled_info['enabled']; ?></h3>
				<p>Enabled</p>
			</div>
			<div class="stats-card">
				<h3><?php echo count( $categories ); ?></h3>
				<p>Categories</p>
			</div>
			<div class="stats-card">
				<h3><?php echo count( $sources ); ?></h3>
				<p>Sources</p>
			</div>
			<div class="stats-card">
				<h3><?php echo esc_html( WP_ABILITIES_SUITE_VERSION ); ?></h3>
				<p>Suite Version</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Filter bar with Source, Category, Type dropdowns + search.
	 */
	private function render_filter_bar( $sources, $categories, $source_filter, $category_filter, $type_filter, $search_filter ) {
		$has_filters = $source_filter || $category_filter || $type_filter || $search_filter;
		?>
		<div class="abilities-filter-bar">
			<form method="get">
				<input type="hidden" name="page" value="wp-abilities-suite">
				<input type="hidden" name="tab" value="explorer">

				<label for="filter-source">Source:</label>
				<select name="source" id="filter-source">
					<option value="">All Sources</option>
					<?php foreach ( $sources as $source => $count ) : ?>
						<option value="<?php echo esc_attr( $source ); ?>" <?php selected( $source_filter, $source ); ?>>
							<?php echo esc_html( $source ); ?> (<?php echo $count; ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter-category">Category:</label>
				<select name="category" id="filter-category">
					<option value="">All Categories</option>
					<?php foreach ( $categories as $cat => $count ) : ?>
						<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $category_filter, $cat ); ?>>
							<?php echo esc_html( $cat ); ?> (<?php echo $count; ?>)
						</option>
					<?php endforeach; ?>
				</select>

				<label for="filter-type">Type:</label>
				<select name="type" id="filter-type">
					<option value="">All Types</option>
					<option value="read" <?php selected( $type_filter, 'read' ); ?>>Read-only</option>
					<option value="write" <?php selected( $type_filter, 'write' ); ?>>Write</option>
					<option value="destructive" <?php selected( $type_filter, 'destructive' ); ?>>Destructive</option>
				</select>

				<input type="text" name="s" placeholder="Search abilities…" value="<?php echo esc_attr( $search_filter ); ?>">
				<button type="submit" class="button button-primary">Filter</button>
				<?php if ( $has_filters ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-abilities-suite&tab=explorer' ) ); ?>" class="button">Clear</a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Explorer table with module group headers + inline R/W/D checkboxes.
	 */
	private function render_explorer_table( $abilities, $source_filter, $category_filter, $type_filter ) {
		$has_filters   = $source_filter || $category_filter || $type_filter;
		$defaults      = wp_abilities_suite_permission_defaults();
		$module_labels = wp_abilities_suite_module_labels();
		$counts        = wp_abilities_suite_get_ability_counts();
		?>
		<form method="post" action="options.php" id="wp-abilities-perm-form">
			<?php settings_fields( 'wp_abilities_suite_permissions_group' ); ?>

			<div class="wp-abilities-list">
				<p class="abilities-count">
					Showing <strong><?php echo count( $abilities ); ?></strong> abilities<?php
					if ( $has_filters ) echo ' (filtered)';
					?>
				</p>

				<?php if ( empty( $abilities ) ) : ?>
					<div class="notice notice-warning inline"><p>No abilities match the current filters.</p></div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed">
						<thead>
							<tr>
								<th style="width: 22%;">Ability</th>
								<th style="width: 9%;">Source</th>
								<th style="width: 9%;">Category</th>
								<th style="width: 28%;">Description</th>
								<th style="width: 11%;">Annotations</th>
								<th style="width: 6%;">Tier</th>
								<th class="perm-col" title="Read permission">R</th>
								<th class="perm-col" title="Write permission">W</th>
								<th class="perm-col" title="Delete permission">D</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$current_module = '';
							foreach ( $abilities as $name => $ability ) :
								$module = $this->get_ability_module( $ability['category'] );

								// Module group header row.
								if ( $module && $module !== $current_module ) :
									$current_module = $module;
									$label          = $module_labels[ $module ] ?? $module;
									$perms          = wp_abilities_suite_get_permissions( $module );
									$module_counts  = $counts[ $module ] ?? array( 'read' => 0, 'write' => 0, 'delete' => 0, 'total' => 0 );
									$module_ops     = $defaults[ $module ] ?? array();
									$has_write      = array_key_exists( 'write', $module_ops );
									$has_delete     = array_key_exists( 'delete', $module_ops );
									$source_badge   = self::get_source( $ability['category'] );
									$source_css     = self::get_source_css( $source_badge );
								?>
									<tr class="module-header">
										<td colspan="6">
											<span class="module-name"><?php echo esc_html( $label ); ?></span>
											<?php if ( 'WordPress Core' !== $source_badge ) : ?>
												<span class="source-badge <?php echo esc_attr( $source_css ); ?>" style="margin-left:8px;font-size:10px;">
													<?php echo esc_html( $source_badge ); ?>
												</span>
											<?php endif; ?>
											<span class="module-controls">
												<label title="All Read in <?php echo esc_attr( $label ); ?>">
													<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][read]" value="0">
													<input type="checkbox"
														name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][read]"
														value="1"
														class="perm-checkbox"
														data-module="<?php echo esc_attr( $module ); ?>"
														data-op="read"
														data-count="<?php echo $module_counts['read']; ?>"
														<?php checked( ! empty( $perms['read'] ) ); ?>>
													All R
												</label>
												<?php if ( $has_write ) : ?>
													<label title="All Write in <?php echo esc_attr( $label ); ?>">
														<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][write]" value="0">
														<input type="checkbox"
															name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][write]"
															value="1"
															class="perm-checkbox"
															data-module="<?php echo esc_attr( $module ); ?>"
															data-op="write"
															data-count="<?php echo $module_counts['write']; ?>"
															<?php checked( ! empty( $perms['write'] ) ); ?>>
														All W
													</label>
												<?php endif; ?>
												<?php if ( $has_delete ) : ?>
													<label title="All Delete in <?php echo esc_attr( $label ); ?>">
														<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][delete]" value="0">
														<input type="checkbox"
															name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][delete]"
															value="1"
															class="perm-checkbox perm-delete"
															data-module="<?php echo esc_attr( $module ); ?>"
															data-op="delete"
															data-count="<?php echo $module_counts['delete']; ?>"
															<?php checked( ! empty( $perms['delete'] ) ); ?>>
														All D
													</label>
												<?php endif; ?>
											</span>
											<span class="module-meta"><?php echo $module_counts['total']; ?> abilities</span>
										</td>
										<td class="perm-col"></td>
										<td class="perm-col"></td>
										<td class="perm-col"></td>
									</tr>
								<?php
								endif; // End module header.

								// Individual ability row.
								$detail_id  = 'detail-' . sanitize_html_class( str_replace( '/', '-', $name ) );
								$source_css = self::get_source_css( $ability['source'] );
								$op         = $this->get_ability_op( $ability );
								$is_enabled = $module ? wp_abilities_suite_ability_enabled( $name, $module, $op ) : true;
								$module_enabled = $module ? ! empty( wp_abilities_suite_get_permissions( $module )[ $op ] ) : true;
								$has_override   = $is_enabled !== $module_enabled;
								$safe_name      = esc_attr( $name );
								?>
								<tr<?php if ( $has_override ) echo ' class="has-override"'; ?>>
									<td>
										<strong><?php echo esc_html( $name ); ?></strong>
										<div class="row-actions">
											<a href="#" onclick="toggleAbilityDetail('<?php echo esc_js( $detail_id ); ?>'); return false;">Inspect</a>
										</div>
									</td>
									<td><span class="source-badge <?php echo esc_attr( $source_css ); ?>"><?php echo esc_html( $ability['source'] ); ?></span></td>
									<td><span class="ability-category"><?php echo esc_html( $ability['category'] ); ?></span></td>
									<td class="description-cell"><?php echo esc_html( $ability['description'] ?: 'No description' ); ?></td>
									<td>
										<?php if ( $ability['readonly'] ) : ?>
											<span class="badge badge-readonly">Read</span>
										<?php else : ?>
											<span class="badge badge-write">Write</span>
										<?php endif; ?>
										<?php if ( $ability['idempotent'] ) : ?>
											<span class="badge badge-idempotent">Idem</span>
										<?php endif; ?>
										<?php if ( $ability['destructive'] ) : ?>
											<span class="badge badge-destructive">Destruct</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'pro' === $ability['tier'] ) : ?>
											<span class="badge badge-pro">Pro</span>
										<?php else : ?>
											<span class="badge badge-free">Free</span>
										<?php endif; ?>
									</td>
									<td class="perm-col"><?php if ( 'read' === $op ) : ?>
										<input type="checkbox" class="ability-perm-checkbox" data-module="<?php echo esc_attr( $module ); ?>" data-ability="<?php echo $safe_name; ?>" data-op="read" <?php checked( $is_enabled ); ?> <?php if ( ! $module_enabled ) echo 'disabled title="Enable module Read first"'; ?>>
									<?php endif; ?></td>
									<td class="perm-col"><?php if ( 'write' === $op ) : ?>
										<input type="checkbox" class="ability-perm-checkbox" data-module="<?php echo esc_attr( $module ); ?>" data-ability="<?php echo $safe_name; ?>" data-op="write" <?php checked( $is_enabled ); ?> <?php if ( ! $module_enabled ) echo 'disabled title="Enable module Write first"'; ?>>
									<?php endif; ?></td>
									<td class="perm-col"><?php if ( 'delete' === $op ) : ?>
										<input type="checkbox" class="ability-perm-checkbox destructive-check" data-module="<?php echo esc_attr( $module ); ?>" data-ability="<?php echo $safe_name; ?>" data-op="delete" <?php checked( $is_enabled ); ?> <?php if ( ! $module_enabled ) echo 'disabled title="Enable module Delete first"'; ?>>
									<?php endif; ?></td>
								</tr>

								<!-- Inline detail panel (hidden by default) -->
								<tr id="<?php echo esc_attr( $detail_id ); ?>" class="ability-detail-row" style="display:none;">
									<td colspan="9">
										<div class="ability-detail-panel">
											<h3><?php echo esc_html( $name ); ?></h3>
											<p>
												<span class="source-badge <?php echo esc_attr( $source_css ); ?>"><?php echo esc_html( $ability['source'] ); ?></span>
												<?php if ( $ability['readonly'] ) : ?>
													<span class="badge badge-readonly">Read-only</span>
												<?php else : ?>
													<span class="badge badge-write">Write</span>
												<?php endif; ?>
												<?php if ( $ability['idempotent'] ) : ?>
													<span class="badge badge-idempotent">Idempotent</span>
												<?php endif; ?>
												<?php if ( $ability['destructive'] ) : ?>
													<span class="badge badge-destructive">Destructive</span>
												<?php endif; ?>
												<?php if ( 'pro' === $ability['tier'] ) : ?>
													<span class="badge badge-pro">Pro</span>
												<?php else : ?>
													<span class="badge badge-free">Free</span>
												<?php endif; ?>
											</p>
											<p><?php echo esc_html( $ability['description'] ); ?></p>

											<div class="schema-columns">
												<div class="schema-column">
													<h4>Input Schema</h4>
													<pre><?php echo esc_html( wp_json_encode( $ability['input_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
												</div>
												<div class="schema-column">
													<h4>Output Schema</h4>
													<pre><?php echo esc_html( wp_json_encode( $ability['output_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
												</div>
											</div>

											<p class="endpoint-hint">
												<strong>MCP Tool:</strong> <code><?php echo esc_html( 'mcp__wordpress__' . str_replace( '/', '-', $name ) ); ?></code>
											</p>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<!-- Sticky save bar -->
				<div class="save-bar">
					<?php submit_button( 'Save Permissions', 'primary', 'submit', false ); ?>
					<span class="save-summary">
						<strong id="save-enabled"><?php echo wp_abilities_suite_enabled_count()['enabled']; ?></strong>
						of <?php echo wp_abilities_suite_enabled_count()['total']; ?> abilities enabled
						· Changes take effect on next request
					</span>
				</div>
			</div>
		</form>
		<?php
	}

	/**
	 * Map a category slug to its permission module.
	 *
	 * @param string $category Category slug.
	 * @return string|null Module slug or null if not a WP Suite module.
	 */
	private function get_ability_module( $category ) {
		// WP Suite modules map 1:1 with category slugs.
		$defaults = wp_abilities_suite_permission_defaults();
		if ( isset( $defaults[ $category ] ) ) {
			return $category;
		}
		return null;
	}

	// ─── License Tab ─────────────────────────────────────────────

	private function render_license_tab() {
		$status = WP_Abilities_Suite_License_Manager::get_status();
		$is_active   = $status['activated'];
		$has_key     = ! empty( $status['key'] );
		?>

		<div class="license-card">
			<h3>
				Abilities for WordPress
				<?php if ( $is_active ) : ?>
					<span class="badge badge-pro" style="vertical-align:middle;">Pro</span>
				<?php endif; ?>
			</h3>

			<?php if ( $is_active ) : ?>
				<div class="license-status">
					<span class="dot dot-active"></span>
					<strong style="color:#00a32a;"><?php esc_html_e( 'Active', 'wp-abilities-suite' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'wp_abilities_suite_license_nonce' ); ?>
					<div class="key-field">
						<input type="text" value="<?php echo esc_attr( $status['key'] ); ?>" disabled>
						<button type="submit" name="wp_abilities_license_deactivate" class="button button-danger button-sm">Deactivate</button>
					</div>
				</form>
				<p class="license-meta">
					<span>Product ID: <?php echo WP_Abilities_Suite_License_Manager::PRODUCT_ID; ?></span>
					<?php if ( ! empty( $status['last_valid'] ) ) : ?>
						<span>Last validated: <?php echo esc_html( $status['last_valid'] ); ?> UTC</span>
					<?php endif; ?>
				</p>

			<?php elseif ( $has_key ) : ?>
				<div class="license-status">
					<span class="dot dot-inactive"></span>
					<strong style="color:#d63638;"><?php esc_html_e( 'Inactive', 'wp-abilities-suite' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'wp_abilities_suite_license_nonce' ); ?>
					<div class="key-field">
						<input type="text" value="<?php echo esc_attr( $status['key'] ); ?>" disabled>
						<button type="submit" name="wp_abilities_license_deactivate" class="button button-danger button-sm">Remove</button>
					</div>
				</form>
				<p class="license-meta">
					<span>License key stored but not active. Re-enter to activate.</span>
				</p>

			<?php else : ?>
				<div class="license-status">
					<span class="dot dot-unlicensed"></span>
					<strong style="color:#dba617;"><?php esc_html_e( 'No License', 'wp-abilities-suite' ); ?></strong>
				</div>
				<form method="post">
					<?php wp_nonce_field( 'wp_abilities_suite_license_nonce' ); ?>
					<div class="key-field">
						<input type="text" name="wp_abilities_license_key" placeholder="Enter your license key…">
						<button type="submit" name="wp_abilities_license_activate" class="button button-primary button-sm">Activate</button>
					</div>
				</form>
				<p class="license-meta">
					<span>Pro abilities are locked until a valid license is activated.</span>
				</p>
			<?php endif; ?>
		</div>

		<?php $this->render_pro_breakdown(); ?>
		<?php
	}

	/**
	 * Render the "What Pro Unlocks" breakdown table.
	 */
	private function render_pro_breakdown() {
		$abilities = $this->get_abilities_array();

		// Count free vs pro per module.
		$module_labels = wp_abilities_suite_module_labels();
		$module_tiers  = array();

		foreach ( $abilities as $a ) {
			$module = $a['category'];
			if ( ! isset( $module_labels[ $module ] ) ) {
				$module = 'other';
			}
			if ( ! isset( $module_tiers[ $module ] ) ) {
				$module_tiers[ $module ] = array( 'free' => 0, 'pro' => 0 );
			}
			if ( 'pro' === $a['tier'] ) {
				$module_tiers[ $module ]['pro']++;
			} else {
				$module_tiers[ $module ]['free']++;
			}
		}

		$total_free = 0;
		$total_pro  = 0;
		?>
		<div class="wp-abilities-settings" style="margin-top:20px;">
			<h2 style="margin-top:0;padding-top:0;border-top:none;">What Pro Unlocks</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><th>Module</th><th>Free</th><th>Pro</th><th>Total</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $module_tiers as $mod => $tier_counts ) :
						$label = $module_labels[ $mod ] ?? ucfirst( $mod );
						$total = $tier_counts['free'] + $tier_counts['pro'];
						$total_free += $tier_counts['free'];
						$total_pro  += $tier_counts['pro'];
					?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong></td>
							<td><?php echo $tier_counts['free']; ?></td>
							<td><?php echo $tier_counts['pro']; ?></td>
							<td><?php echo $total; ?></td>
						</tr>
					<?php endforeach; ?>
					<tr style="background:#f6f7f7;font-weight:600;">
						<td>Total</td>
						<td><?php echo $total_free; ?></td>
						<td><?php echo $total_pro; ?></td>
						<td><?php echo $total_free + $total_pro; ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	// ─── Debug ───────────────────────────────────────────────────

	private function render_debug_info( $abilities ) {
		$source_counts = array();
		foreach ( $abilities as $a ) {
			$source_counts[ $a['source'] ] = ( $source_counts[ $a['source'] ] ?? 0 ) + 1;
		}
		ksort( $source_counts );

		$enabled_info = wp_abilities_suite_enabled_count();
		$license      = WP_Abilities_Suite_License_Manager::get_status();
		?>
		<div class="wp-abilities-settings" style="margin-top:20px;">
			<h2 style="margin-top:0;padding-top:0;border-top:none;">Debug Information</h2>
			<p>Copy this when reporting issues:</p>
			<textarea class="debug-area" readonly>Plugin: Abilities for WordPress v<?php echo esc_html( WP_ABILITIES_SUITE_VERSION ); ?>

WordPress: <?php echo get_bloginfo( 'version' ); ?> | PHP: <?php echo PHP_VERSION; ?> | Multisite: <?php echo is_multisite() ? 'Yes' : 'No'; ?>

Total: <?php echo count( $abilities ); ?> | Enabled: <?php echo $enabled_info['enabled']; ?>
<?php foreach ( $source_counts as $source => $count ) : ?>
<?php echo $source; ?>: <?php echo $count; ?>
<?php endforeach; ?>
License: <?php echo esc_html( $license['status'] ); ?><?php if ( ! empty( $license['last_valid'] ) ) : ?> | Last valid: <?php echo esc_html( $license['last_valid'] ); ?><?php endif; ?>

Active Plugins: <?php echo count( get_option( 'active_plugins', array() ) ); ?></textarea>
		</div>

		<script>
		function toggleAbilityDetail(id) {
			var row = document.getElementById(id);
			if (row) {
				row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
			}
		}
		(function() {
			var moduleCheckboxes  = document.querySelectorAll('.perm-checkbox');
			var abilityCheckboxes = document.querySelectorAll('.ability-perm-checkbox');
			var enabledEl         = document.getElementById('save-enabled');
			var form              = document.getElementById('wp-abilities-perm-form');

			// Track each ability's initial checked state to detect user changes.
			var initialState = {};
			abilityCheckboxes.forEach(function(cb) {
				initialState[cb.getAttribute('data-ability')] = cb.checked;
			});

			function recalc() {
				var total = 0;
				abilityCheckboxes.forEach(function(cb) {
					if (cb.checked && !cb.disabled) total++;
				});
				if (enabledEl) enabledEl.textContent = total;
			}

			// Module toggle: check/uncheck all ability checkboxes in that module+op.
			moduleCheckboxes.forEach(function(mcb) {
				mcb.addEventListener('change', function() {
					var mod = mcb.getAttribute('data-module');
					var op  = mcb.getAttribute('data-op');
					abilityCheckboxes.forEach(function(acb) {
						if (acb.getAttribute('data-module') === mod && acb.getAttribute('data-op') === op) {
							acb.disabled = !mcb.checked;
							acb.checked  = mcb.checked;
						}
					});
					recalc();
				});
			});

			// Individual ability checkbox: update count on change.
			abilityCheckboxes.forEach(function(acb) {
				acb.addEventListener('change', recalc);
			});

			// On form submit: inject hidden inputs ONLY for abilities that are unchecked.
			// This way only disabled abilities get submitted as overrides.
			if (form) {
				form.addEventListener('submit', function() {
					// Remove any previously injected override inputs.
					form.querySelectorAll('.injected-override').forEach(function(el) { el.remove(); });

					abilityCheckboxes.forEach(function(cb) {
						var ability = cb.getAttribute('data-ability');
						if (!cb.checked && !cb.disabled) {
							// Ability is explicitly unchecked — inject hidden input.
							var hidden = document.createElement('input');
							hidden.type = 'hidden';
							hidden.name = 'wp_abilities_suite_permissions[_overrides][' + ability + ']';
							hidden.value = '0';
							hidden.className = 'injected-override';
							form.appendChild(hidden);
						}
					});
				});
			}

			recalc();
		})();
		</script>
		<?php
	}
}

new WP_Abilities_Suite_Dashboard();
