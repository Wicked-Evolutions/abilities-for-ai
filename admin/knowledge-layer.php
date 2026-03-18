<?php
/**
 * Abilities for AI — Knowledge Layer Admin Page
 *
 * Registers the "Knowledge Layer" submenu under the Abilities for AI top-level menu.
 * Renders a minimal PHP shell for the Vue SPA to mount into (Issue #65).
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

class Abilities_For_AI_Knowledge_Layer {

	/**
	 * The hook suffix returned by add_submenu_page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'network_admin_menu', array( $this, 'add_submenu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Knowledge Layer submenu page.
	 */
	public function add_submenu() {
		$this->hook_suffix = add_submenu_page(
			'abilities-for-ai',
			'Knowledge Layer',
			'Knowledge Layer',
			'manage_options',
			'abilities-for-ai-knowledge',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the PHP shell for the Vue SPA.
	 */
	public function render_page() {
		?>
		<div class="wrap" id="abilities-kl-app"></div>
		<?php
	}

	/**
	 * Conditionally enqueue assets only on the Knowledge Layer page.
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'abilities-kl-admin',
			ABILITIES_FOR_AI_URL . 'admin/kl/css/knowledge-layer.css',
			array(),
			ABILITIES_FOR_AI_VERSION
		);

		// Register a placeholder script handle for wp_localize_script.
		// The Vue SPA build will replace this with the actual JS bundle.
		wp_register_script(
			'abilities-kl-app',
			ABILITIES_FOR_AI_URL . 'admin/kl/js/knowledge-layer.js',
			array(),
			ABILITIES_FOR_AI_VERSION,
			true
		);

		wp_localize_script( 'abilities-kl-app', 'abilitiesKL', array(
			'rest'      => array(
				'url'   => rest_url( 'abilities-kl/v1' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			),
			'admin_url' => admin_url( 'admin.php?page=abilities-for-ai-knowledge#/' ),
			'auth'      => array(
				'user_id'  => get_current_user_id(),
				'can_edit' => current_user_can( 'manage_options' ),
			),
			'version'   => ABILITIES_FOR_AI_VERSION,
		) );

		wp_enqueue_script( 'abilities-kl-app' );
	}
}

new Abilities_For_AI_Knowledge_Layer();
