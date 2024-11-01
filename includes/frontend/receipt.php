<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Receipt {
	public function __construct() {
		$this->settings = VIWEBPOS_DATA::get_instance();
		if ( ! $this->settings->get_params( 'enable' ) ) {
			return;
		}
		add_action( 'viwebpos_before_enqueue_scripts', array( $this, 'viwebpos_before_enqueue_scripts' ) );
	}

	public function viwebpos_before_enqueue_scripts() {
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'viwebpos-receipt' ),
			array( 'pos_receipt' )
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'viwebpos-receipt' ),
			array( 'frontend-receipt' )
		);
		$viwebpos_receipts = array(
			'site_title'               => get_bloginfo( 'name' ),
			'change_receipts_title'    => esc_html__( 'Template', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_name_default'    => esc_html__( 'Guest', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_phone_default'   => esc_html__( 'No phone number', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_address_default' => esc_html__( 'No address', 'webpos-point-of-sale-for-woocommerce' ),
		);
		wp_localize_script( 'viwebpos-receipt', 'viwebpos_receipts', apply_filters( 'viwebpos_frontend_params_receipts', $viwebpos_receipts ) );
	}
}