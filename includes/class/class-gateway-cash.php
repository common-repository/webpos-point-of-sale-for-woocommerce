<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
function viwebpos_add_gateway_class( $methods ) {
	viwebpos_init_gateway_class();
	$methods[] = 'VIWEBPOS_Gateway_Cash';

	return $methods;
}

function viwebpos_init_gateway_class() {
	if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'VIWEBPOS_Gateway_Cash' ) ) {
		/**
		 * @extends WC_Payment_Gateway
		 */
		class VIWEBPOS_Gateway_Cash extends \WC_Payment_Gateway {

			public function __construct() {
				$this->id                 = 'cash';
				$this->has_fields         = false;
				$this->method_title       = esc_html__( 'Cash', 'webpos-point-of-sale-for-woocommerce' );
				$this->method_description = esc_html__( 'This offline gateway is used for POS only.', 'webpos-point-of-sale-for-woocommerce' );
				$this->title              = $this->method_title;
				$this->description        = $this->instructions = $this->method_description;
			}

			public function is_available() {
				return false;
			}
		}
	}
}