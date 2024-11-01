<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Transactions {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 50 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );
		add_filter( 'set-screen-option', array( $this, 'save_screen_options' ), 10, 3 );
	}

	public function admin_menu() {
		$manage_role       = apply_filters( 'viwebpos_change_role', 'manage_woocommerce' );
		$transactions_page = add_submenu_page(
			'viwebpos',
			esc_html__( 'WebPOS Transactions', 'webpos-point-of-sale-for-woocommerce' ),
			esc_html__( 'Transactions', 'webpos-point-of-sale-for-woocommerce' ),
			$manage_role,
			'viwebpos-transactions',
			array( $this, 'settings_callback' )
		);
		add_action( "load-$transactions_page", array( $this, 'screen_options' ) );
	}

	/**
	 * Add Screen Options
	 */
	public function screen_options() {
		$option = 'per_page';
		$args = array(
			'label'   => esc_html__( 'Number of items per page', 'webpos-point-of-sale-for-woocommerce' ),
			'default' => 20,
			'option'  => 'viwebpos_transactions_per_page'
		);
		add_screen_option( $option, $args );
	}

	public function save_screen_options( $status, $option, $value ) {
		if ( 'viwebpos_transactions_per_page' == $option ) {
			return $value;
		}
		return $status;
	}

	public function settings_callback() {
		printf( "<h2>%s</h2>", esc_html__( "Transactions", 'webpos-point-of-sale-for-woocommerce' ) );
		printf( '<div class="wrap"><form method="post" class="viwebpos-transaction-tabblenav-form">' );
		$transactions = VIWEBPOS_Transactions::get_instance();
		$transactions->prepare_items();
		$transactions->display();
		printf( '</form></div>' );
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page !== 'viwebpos-transactions' ) {
			return;
		}
		VIWEBPOS_Admin_Settings::remove_other_script();
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'viwebpos-admin-transactions' ),
			array( 'admin-transactions' )
		);
	}
}
