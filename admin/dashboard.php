<?php
/**
 * WordPress Abilities Suite Dashboard
 *
 * Filterable explorer for ALL registered WordPress abilities across all plugins.
 * Replaces the flat listing with multi-level Source → Category → Type filtering.
 *
 * @package WordPress_Abilities_Suite
 */

defined( 'ABSPATH' ) || exit;

class WP_Abilities_Suite_Dashboard {

	/**
	 * Source detection map: category slug → human-readable source.
	 *
	 * Each abilities plugin uses unique category slugs, so mapping is deterministic.
	 */
	private static $source_map = array(
		// WordPress Core (wordpress-abilities-suite).
		'content'     => 'WordPress Core',
		'taxonomies'  => 'WordPress Core',
		'plugins'     => 'WordPress Core',
		'media'       => 'WordPress Core',
		'users'       => 'WordPress Core',
		'comments'    => 'WordPress Core',
		'menus'       => 'WordPress Core',
		'site'        => 'WordPress Core',
		'user'        => 'WordPress Core',
		// MCP Adapter (wp-mcp-adapter).
		'mcp-adapter' => 'MCP Adapter',
		// Fluent Suite (fluent-abilities).
		'fluent-crm'       => 'FluentCRM',
		'fluent-community' => 'Fluent Community',
		'fluent-forms'     => 'Fluent Forms',
		'fluent-support'   => 'Fluent Support',
		'fluent-boards'    => 'Fluent Boards',
		'fluent-booking'   => 'FluentBooking',
		'fluent-smtp'      => 'FluentSMTP',
		'fluent-auth'      => 'FluentAuth',
		'fluent-snippets'  => 'Fluent Snippets',
		'fluent-messaging' => 'Fluent Messaging',
		'fluent'           => 'Fluent Suite',
		// Theme & Blocks.
		'astra'   => 'Astra',
		'spectra' => 'Spectra',
	);

	/**
	 * Source CSS class map.
	 */
	private static $source_css = array(
		'WordPress Core'  => 'source-wp-core',
		'FluentCRM'       => 'source-fluent',
		'Fluent Community' => 'source-fluent',
		'Fluent Forms'    => 'source-fluent',
		'Fluent Support'  => 'source-fluent',
		'Fluent Boards'   => 'source-fluent',
		'FluentBooking'   => 'source-fluent',
		'FluentSMTP'      => 'source-fluent',
		'FluentAuth'      => 'source-fluent',
		'Fluent Snippets'  => 'source-fluent',
		'Fluent Messaging' => 'source-fluent',
		'Fluent Suite'    => 'source-fluent',
		'Astra'           => 'source-astra',
		'Spectra'         => 'source-spectra',
		'MCP Adapter'     => 'source-mcp',
	);

	public function __construct() {
		add_action( 'network_admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_pages() {
		add_menu_page(
			'WordPress Abilities Suite',
			'Abilities Suite',
			'manage_options',
			'wp-abilities-suite',
			array( $this, 'render_explorer' ),
			'dashicons-admin-tools',
			30
		);

		add_submenu_page(
			'wp-abilities-suite',
			'All Abilities',
			'All Abilities',
			'manage_options',
			'wp-abilities-suite',
			array( $this, 'render_explorer' )
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

	/**
	 * Get the source label for a category slug.
	 */
	public static function get_source( $category ) {
		return self::$source_map[ $category ] ?? 'Other';
	}

	/**
	 * Get the CSS class for a source label.
	 */
	public static function get_source_css( $source ) {
		return self::$source_css[ $source ] ?? 'source-other';
	}

	/**
	 * Load and convert all abilities to arrays.
	 */
	private function get_abilities_array() {
		$abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		$result = array();

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
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
				'meta'          => $meta,
			);
		}

		// Sort by source → category → name.
		uasort( $result, function( $a, $b ) {
			$cmp = strcmp( $a['source'], $b['source'] );
			if ( $cmp !== 0 ) return $cmp;
			$cmp = strcmp( $a['category'], $b['category'] );
			if ( $cmp !== 0 ) return $cmp;
			return 0; // Keep original key order within same category.
		});

		return $result;
	}

	/**
	 * Render the main explorer page.
	 */
	public function render_explorer() {
		$abilities = $this->get_abilities_array();

		// Collect unique sources and categories.
		$all_sources    = array();
		$all_categories = array();
		foreach ( $abilities as $a ) {
			$all_sources[ $a['source'] ] = ( $all_sources[ $a['source'] ] ?? 0 ) + 1;
			$all_categories[ $a['category'] ] = ( $all_categories[ $a['category'] ] ?? 0 ) + 1;
		}
		ksort( $all_sources );
		ksort( $all_categories );

		// Read filters.
		$source_filter   = isset( $_GET['source'] ) ? sanitize_text_field( $_GET['source'] ) : '';
		$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';
		$type_filter     = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : '';

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
		if ( $type_filter === 'read' ) {
			$filtered = array_filter( $filtered, function( $a ) { return $a['readonly']; });
		} elseif ( $type_filter === 'write' ) {
			$filtered = array_filter( $filtered, function( $a ) { return ! $a['readonly']; });
		}

		// Categories available for current source filter.
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

		?>
		<div class="wrap">
			<h1>Abilities Explorer</h1>
			<p>Browse and inspect all <?php echo count( $abilities ); ?> registered WordPress abilities across all plugins.</p>

			<?php $this->render_stats( $abilities, $all_sources, $all_categories ); ?>
			<?php $this->render_filter_bar( $all_sources, $available_categories, $source_filter, $category_filter, $type_filter ); ?>
			<?php $this->render_table( $filtered, $source_filter, $category_filter, $type_filter ); ?>
		</div>

		<script>
		function toggleAbilityDetail(id) {
			var row = document.getElementById(id);
			if (row) {
				row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
			}
		}
		</script>
		<?php
	}

	/**
	 * Stats cards.
	 */
	private function render_stats( $abilities, $sources, $categories ) {
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
	 * Filter bar with three dropdowns.
	 */
	private function render_filter_bar( $sources, $categories, $source_filter, $category_filter, $type_filter ) {
		?>
		<div class="abilities-filter-bar">
			<form method="get">
				<input type="hidden" name="page" value="wp-abilities-suite">

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
				</select>

				<button type="submit" class="button button-primary">Filter</button>
				<?php if ( $source_filter || $category_filter || $type_filter ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-abilities-suite' ) ); ?>" class="button">Clear</a>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Abilities table with inline test panels.
	 */
	private function render_table( $abilities, $source_filter, $category_filter, $type_filter ) {
		$has_filters = $source_filter || $category_filter || $type_filter;
		?>
		<div class="wp-abilities-list">
			<p class="abilities-count">
				Showing <strong><?php echo count( $abilities ); ?></strong> abilities<?php
				if ( $has_filters ) echo ' (filtered)';
				?>
			</p>

			<?php if ( empty( $abilities ) ) : ?>
				<div class="notice notice-warning inline">
					<p>No abilities match the current filters.</p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 25%;">Name</th>
							<th style="width: 13%;">Source</th>
							<th style="width: 13%;">Category</th>
							<th style="width: 34%;">Description</th>
							<th style="width: 15%;">Type</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $abilities as $name => $ability ) :
							$detail_id = 'detail-' . sanitize_html_class( str_replace( '/', '-', $name ) );
							$source_css = self::get_source_css( $ability['source'] );
						?>
							<tr>
								<td>
									<strong><?php echo esc_html( $name ); ?></strong>
									<div class="row-actions">
										<a href="#" onclick="toggleAbilityDetail('<?php echo esc_attr( $detail_id ); ?>'); return false;">Test</a>
									</div>
								</td>
								<td>
									<span class="source-badge <?php echo esc_attr( $source_css ); ?>">
										<?php echo esc_html( $ability['source'] ); ?>
									</span>
								</td>
								<td>
									<span class="ability-category">
										<?php echo esc_html( $ability['category'] ); ?>
									</span>
								</td>
								<td class="description-cell">
									<?php echo esc_html( $ability['description'] ?: 'No description' ); ?>
								</td>
								<td>
									<?php if ( $ability['readonly'] ) : ?>
										<span class="badge badge-readonly">Read</span>
									<?php else : ?>
										<span class="badge badge-write">Write</span>
									<?php endif; ?>
									<?php if ( $ability['destructive'] ) : ?>
										<span class="badge badge-destructive">Destructive</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr id="<?php echo esc_attr( $detail_id ); ?>" class="ability-detail-row" style="display: none;">
								<td colspan="5">
									<div class="ability-detail-panel">
										<h3><?php echo esc_html( $name ); ?></h3>
										<p><strong>Description:</strong> <?php echo esc_html( $ability['description'] ); ?></p>
										<p>
											<strong>Source:</strong> <?php echo esc_html( $ability['source'] ); ?> &nbsp;|&nbsp;
											<strong>Category:</strong> <?php echo esc_html( $ability['category'] ); ?> &nbsp;|&nbsp;
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
										</p>

										<div class="schema-columns">
											<div class="schema-column">
												<h4>Input Schema</h4>
												<pre><?php echo esc_html( json_encode( $ability['input_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
											</div>
											<div class="schema-column">
												<h4>Output Schema</h4>
												<pre><?php echo esc_html( json_encode( $ability['output_schema'] ?? array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
											</div>
										</div>

										<p class="endpoint-hint">
											<strong>MCP Tool:</strong> <code><?php echo esc_html( $name ); ?></code>
										</p>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Settings page with permission toggles.
	 */
	public function render_settings() {
		$defaults     = wp_abilities_suite_permission_defaults();
		$labels       = wp_abilities_suite_module_labels();
		$counts       = wp_abilities_suite_get_ability_counts();
		$enabled_info = wp_abilities_suite_enabled_count();
		$saved        = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';

		?>
		<div class="wrap">
			<h1>Abilities Suite — Permissions</h1>
			<p>Control what AI clients can do on your site. Abilities that are disabled won't appear in the MCP tool list.</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p>Permissions saved.</p></div>
			<?php endif; ?>

			<div class="wp-abilities-stats" style="margin-bottom: 24px;">
				<div class="stats-card">
					<h3 id="enabled-count"><?php echo $enabled_info['enabled']; ?></h3>
					<p>Enabled</p>
				</div>
				<div class="stats-card">
					<h3><?php echo $enabled_info['total']; ?></h3>
					<p>Total Abilities</p>
				</div>
				<div class="stats-card">
					<h3><?php echo count( $defaults ); ?></h3>
					<p>Modules</p>
				</div>
				<div class="stats-card">
					<h3><?php echo esc_html( WP_ABILITIES_SUITE_VERSION ); ?></h3>
					<p>Suite Version</p>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'wp_abilities_suite_permissions_group' ); ?>

				<div class="wp-abilities-permissions-grid">
					<table class="wp-list-table widefat fixed">
						<thead>
							<tr>
								<th style="width: 25%;">Module</th>
								<th style="width: 15%; text-align: center;">Read</th>
								<th style="width: 15%; text-align: center;">Write</th>
								<th style="width: 15%; text-align: center;">Delete</th>
								<th style="width: 30%;">Abilities</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $defaults as $module => $ops ) :
								$perms        = wp_abilities_suite_get_permissions( $module );
								$label        = $labels[ $module ] ?? $module;
								$module_count = $counts[ $module ] ?? array( 'read' => 0, 'write' => 0, 'delete' => 0, 'total' => 0 );
								$has_write    = array_key_exists( 'write', $ops );
								$has_delete   = array_key_exists( 'delete', $ops );
							?>
								<tr>
									<td>
										<strong><?php echo esc_html( $label ); ?></strong>
									</td>
									<td class="perm-cell">
										<label class="perm-toggle">
											<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][read]" value="0">
											<input type="checkbox"
												name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][read]"
												value="1"
												class="perm-checkbox"
												data-module="<?php echo esc_attr( $module ); ?>"
												data-op="read"
												data-count="<?php echo $module_count['read']; ?>"
												<?php checked( ! empty( $perms['read'] ) ); ?>>
											<span class="perm-count"><?php echo $module_count['read']; ?></span>
										</label>
									</td>
									<td class="perm-cell">
										<?php if ( $has_write ) : ?>
											<label class="perm-toggle">
												<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][write]" value="0">
												<input type="checkbox"
													name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][write]"
													value="1"
													class="perm-checkbox"
													data-module="<?php echo esc_attr( $module ); ?>"
													data-op="write"
													data-count="<?php echo $module_count['write']; ?>"
													<?php checked( ! empty( $perms['write'] ) ); ?>>
												<span class="perm-count"><?php echo $module_count['write']; ?></span>
											</label>
										<?php else : ?>
											<span class="perm-na">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="perm-cell">
										<?php if ( $has_delete ) : ?>
											<label class="perm-toggle <?php echo empty( $perms['delete'] ) ? '' : 'perm-delete-on'; ?>">
												<input type="hidden" name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][delete]" value="0">
												<input type="checkbox"
													name="wp_abilities_suite_permissions[<?php echo esc_attr( $module ); ?>][delete]"
													value="1"
													class="perm-checkbox perm-delete"
													data-module="<?php echo esc_attr( $module ); ?>"
													data-op="delete"
													data-count="<?php echo $module_count['delete']; ?>"
													<?php checked( ! empty( $perms['delete'] ) ); ?>>
												<span class="perm-count"><?php echo $module_count['delete']; ?></span>
											</label>
										<?php else : ?>
											<span class="perm-na">&mdash;</span>
										<?php endif; ?>
									</td>
									<td class="module-total">
										<?php echo $module_count['total']; ?> total
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<?php submit_button( 'Save Permissions' ); ?>
			</form>

			<?php $this->render_debug_info(); ?>
		</div>

		<script>
		(function() {
			var checkboxes = document.querySelectorAll('.perm-checkbox');
			var enabledEl  = document.getElementById('enabled-count');

			function recalc() {
				var total = 0;
				checkboxes.forEach(function(cb) {
					if (cb.checked) {
						total += parseInt(cb.getAttribute('data-count') || 0);
					}
				});
				if (enabledEl) enabledEl.textContent = total;
			}

			checkboxes.forEach(function(cb) {
				cb.addEventListener('change', recalc);
			});
		})();
		</script>
		<?php
	}

	/**
	 * Debug information section (moved from old settings page).
	 */
	private function render_debug_info() {
		$abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();

		$source_counts = array();
		foreach ( $abilities as $ability ) {
			if ( is_object( $ability ) && method_exists( $ability, 'get_category' ) ) {
				$source = self::get_source( $ability->get_category() );
				$source_counts[ $source ] = ( $source_counts[ $source ] ?? 0 ) + 1;
			}
		}
		ksort( $source_counts );

		?>
		<div class="wp-abilities-settings" style="margin-top: 30px;">
			<h2 style="margin-top: 0; padding-top: 0; border-top: none;">Debug Information</h2>
			<p>Copy this when reporting issues:</p>
			<textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;">
Plugin: WordPress Abilities Suite v<?php echo WP_ABILITIES_SUITE_VERSION; ?>

WordPress: <?php echo get_bloginfo( 'version' ); ?>

PHP: <?php echo PHP_VERSION; ?>

Multisite: <?php echo is_multisite() ? 'Yes' : 'No'; ?>

Total Abilities: <?php echo count( $abilities ); ?>

<?php foreach ( $source_counts as $source => $count ) : ?>
<?php echo $source; ?>: <?php echo $count; ?>

<?php endforeach; ?>
Active Plugins: <?php echo count( get_option( 'active_plugins', array() ) ); ?>
</textarea>
		</div>
		<?php
	}
}

new WP_Abilities_Suite_Dashboard();
