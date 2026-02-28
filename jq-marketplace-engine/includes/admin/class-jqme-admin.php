<?php
/**
 * Admin module — registers admin menus, pages, and assets.
 *
 * @package JQ_Marketplace_Engine
 */

namespace JQME\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the top-level admin menu and submenus.
	 */
	public function register_menus(): void {
		$capability = 'jqme_manage_marketplace';

		// Top-level menu.
		add_menu_page(
			__( 'Marketplace', 'jq-marketplace-engine' ),
			__( 'Marketplace', 'jq-marketplace-engine' ),
			$capability,
			'jqme-dashboard',
			[ $this, 'render_dashboard' ],
			'dashicons-store',
			30
		);

		// Submenus.
		$submenus = [
			[ 'jqme-dashboard',      __( 'Dashboard', 'jq-marketplace-engine' ),       $capability,                  'render_dashboard' ],
			[ 'jqme-providers',      __( 'Providers', 'jq-marketplace-engine' ),        'jqme_manage_providers',      'render_providers' ],
			[ 'jqme-listings',       __( 'Listings', 'jq-marketplace-engine' ),         'jqme_manage_listings',       'render_listings' ],
			[ 'jqme-verifications',  __( 'Verification Queue', 'jq-marketplace-engine' ), 'jqme_manage_verifications', 'render_verifications' ],
			[ 'jqme-bookings',       __( 'Bookings', 'jq-marketplace-engine' ),         'jqme_manage_bookings',       'render_bookings' ],
			[ 'jqme-claims',         __( 'Claims', 'jq-marketplace-engine' ),           'jqme_manage_claims',         'render_claims' ],
			[ 'jqme-payouts',        __( 'Payouts', 'jq-marketplace-engine' ),          'jqme_manage_payments',       'render_payouts' ],
			[ 'jqme-reviews',        __( 'Reviews', 'jq-marketplace-engine' ),          'jqme_manage_reviews',        'render_reviews' ],
			[ 'jqme-settings',       __( 'Settings', 'jq-marketplace-engine' ),         'jqme_manage_settings',       'render_settings' ],
			[ 'jqme-reports',        __( 'Reports', 'jq-marketplace-engine' ),          'jqme_view_reports',          'render_reports' ],
			[ 'jqme-audit-log',      __( 'Audit Log', 'jq-marketplace-engine' ),        'jqme_view_audit_log',        'render_audit_log' ],
		];

		foreach ( $submenus as $sub ) {
			add_submenu_page(
				'jqme-dashboard',
				$sub[1],
				$sub[1],
				$sub[2],
				$sub[0],
				[ $this, $sub[3] ]
			);
		}
	}

	/**
	 * Enqueue admin CSS/JS on marketplace pages only.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( strpos( $hook_suffix, 'jqme-' ) === false && strpos( $hook_suffix, 'marketplace' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'jqme-admin',
			JQME_PLUGIN_URL . 'admin/css/admin.css',
			[],
			JQME_VERSION
		);

		wp_enqueue_script(
			'jqme-admin',
			JQME_PLUGIN_URL . 'admin/js/admin.js',
			[ 'jquery' ],
			JQME_VERSION,
			true
		);

		wp_localize_script( 'jqme-admin', 'jqmeAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'jqme_admin_nonce' ),
		] );
	}

	/* ---------------------------------------------------------------
	 * RENDER STUBS — each will load a view file from admin/views/
	 * ------------------------------------------------------------- */

	public function render_dashboard(): void {
		$this->render_view( 'dashboard' );
	}

	public function render_providers(): void {
		$this->render_view( 'providers' );
	}

	public function render_listings(): void {
		$this->render_view( 'listings' );
	}

	public function render_verifications(): void {
		$this->render_view( 'verifications' );
	}

	public function render_bookings(): void {
		$this->render_view( 'bookings' );
	}

	public function render_claims(): void {
		$this->render_view( 'claims' );
	}

	public function render_payouts(): void {
		$this->render_view( 'payouts' );
	}

	public function render_reviews(): void {
		$this->render_view( 'reviews' );
	}

	public function render_settings(): void {
		$this->render_view( 'settings' );
	}

	public function render_reports(): void {
		$this->render_view( 'reports' );
	}

	public function render_audit_log(): void {
		$this->render_view( 'audit-log' );
	}

	/**
	 * Load a view file from admin/views/.
	 */
	private function render_view( string $view ): void {
		$file = JQME_PLUGIN_DIR . "admin/views/{$view}.php";
		if ( file_exists( $file ) ) {
			include $file;
		} else {
			printf(
				'<div class="wrap"><h1>%s</h1><p>%s</p></div>',
				esc_html( ucwords( str_replace( '-', ' ', $view ) ) ),
				esc_html__( 'This admin page is under construction.', 'jq-marketplace-engine' )
			);
		}
	}
}
