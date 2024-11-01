<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Settings_General {
	protected $settings;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );
		add_action( 'admin_init', array( $this, 'save_settings' ), 99 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menus' ) );
		add_action( 'plugins_loaded', 'viwebpos_init_gateway_class' );
	}

	public function admin_menu() {
		$manage_role = apply_filters( 'viwebpos_change_role', 'manage_woocommerce' );
		add_menu_page(
			esc_html__( 'WebPOS', 'webpos-point-of-sale-for-woocommerce' ),
			esc_html__( 'WebPOS', 'webpos-point-of-sale-for-woocommerce' ),
			$manage_role,
			'viwebpos',
			array( $this, 'settings_callback' ),
			'dashicons-desktop',
			2 );
		add_submenu_page(
			'viwebpos',
			esc_html__( 'WebPOS', 'webpos-point-of-sale-for-woocommerce' ),
			esc_html__( 'Settings', 'webpos-point-of-sale-for-woocommerce' ),
			$manage_role,
			'viwebpos',
			array( $this, 'settings_callback' )
		);
	}

	public function save_settings() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page !== 'viwebpos' ) {
			return;
		}
		if ( ! current_user_can( apply_filters( 'viwebpos_change_role', 'manage_woocommerce' ) ) ) {
			return;
		}
		if ( ! isset( $_POST['_viwebpos_setting'] ) || ! wp_verify_nonce( $_POST['_viwebpos_setting'], '_viwebpos_setting_action' ) ) {
			return;
		}
		if ( ! isset( $_POST['viwebpos-save'] ) ) {
			return;
		}
		global $viwebpos_settings;
		if ( ! $viwebpos_settings ) {
			$viwebpos_settings = get_option( 'viwebpos_params', array() );
		}
		$arg      = array();
		$args_map = apply_filters( 'viwebpos_map_update_settings_args', array(
			'field'       => array(
				'enable',
				'pos_endpoint',
				'auto_create_barcode_by_sku',
				'atc_custom_pd',
				'update_variation_on_cart',
				'pos_order_status',
				'pos_send_mail',
				'maximum_applied_coupons',
			),
			'field_array' => array(
				'payments',
			)
		) );
		villatheme_map_fields( $arg, $args_map );
		$arg = apply_filters( 'viwebpos_update_settings_args', wp_parse_args( $arg, $viwebpos_settings ) );
		if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
			$cache = new WpFastestCache();
			$cache->deleteCache( true );
		}
		unset( $arg['tables'] );
		unset( $arg['outlets'] );
		unset( $arg['kitchen'] );
		unset( $arg['receipts'] );
		update_option( 'viwebpos_params', $arg );
		$viwebpos_settings = null;
		$this->settings    = VIWEBPOS_DATA::get_instance( true );
	}

	public function settings_callback() {
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'WebPOS Settings', 'webpos-point-of-sale-for-woocommerce' ); ?></h2>
            <div class="vi-ui raised">
                <form class="vi-ui form" method="post">
					<?php
					wp_nonce_field( '_viwebpos_setting_action', '_viwebpos_setting' );
					$tabs       = apply_filters( 'viwebpos_settings_tab_menu', array(
						'general'  => esc_html__( 'General', 'webpos-point-of-sale-for-woocommerce' ),
						'product'  => esc_html__( 'Products', 'webpos-point-of-sale-for-woocommerce' ),
						'order'    => esc_html__( 'Orders', 'webpos-point-of-sale-for-woocommerce' ),
						'customer' => esc_html__( 'Customers', 'webpos-point-of-sale-for-woocommerce' ),
						'payment'  => esc_html__( 'Payments', 'webpos-point-of-sale-for-woocommerce' ),
					) );
					$tab_active = array_key_first( $tabs );
					?>
                    <div class="vi-ui top tabular vi-ui-main attached menu">
						<?php
						foreach ( $tabs as $slug => $text ) {
							$active = $tab_active === $slug ? 'active' : '';
							printf( ' <a class="item %s" data-tab="%s">%s</a>', esc_attr( $active ), esc_attr( $slug ), esc_html( $text ) );
						}
						?>
                    </div>
					<?php
					foreach ( $tabs as $slug => $text ) {
						$active = $tab_active === $slug ? 'active' : '';
						$method = str_replace( '-', '_', $slug ) . '_options';
						printf( '<div class="vi-ui bottom attached %s tab segment" data-tab="%s">', esc_attr( $active ), esc_attr( $slug ) );
						if ( method_exists( $this, $method ) ) {
							villatheme_render_table_field( apply_filters( 'viwebpos_settings_fields', $this->$method(), $slug ) );
						}
						do_action( 'viwebpos_settings_tab', $slug );
						printf( '</div>' );
					}
					?>
                    <p class="viwebpos-save-wrap">
                        <button type="submit" class="viwebpos-save vi-ui primary button" name="viwebpos-save">
							<?php esc_html_e( 'Save', 'webpos-point-of-sale-for-woocommerce' ); ?>
                        </button>
                    </p>
                </form>
				<?php
				do_action( 'villatheme_support_webpos-point-of-sale-for-woocommerce' );
				?>
            </div>
        </div>
		<?php
	}

	public function customer_options() {
		return [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'customer_required_fields' => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Mandatory fields when adding a new customer', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Mandatory fields', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'customer_search_field'    => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Fields being searched when searching customer', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Customer search fields', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'update_customer'          => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Allow the cashier to edit customer information', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Update customer', 'webpos-point-of-sale-for-woocommerce' ),
				],
			],
		];
	}

	public function payment_options() {
		$woo_payments  = WC()->payment_gateways->payment_gateways();
		$payments_args = [];
		if ( is_array( $woo_payments ) ) {
			foreach ( $woo_payments as $k => $v ) {
				if ( ! $k || $k === 'cash' ) {
					continue;
				}
				$payments_args[ $k ] = $v->method_title ?? $v->title;
			}
		}

		return [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'multi_payments' => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Allow your customers to pay with multiple payment methods via POS', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Multiple payment methods', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'payments'       => [
					'type'     => 'select',
					'value'    => $this->settings->get_params( 'payments' ),
					'options'  => $payments_args,
					'multiple' => 1,
					'desc'     => esc_html__( 'Please select the payment methods for POS other than the \'Cash\' gateway', 'webpos-point-of-sale-for-woocommerce' ),
					'title'    => esc_html__( 'Payment', 'webpos-point-of-sale-for-woocommerce' ),
				]
			],
		];
	}

	public function order_options() {
		$woo_order_status = wc_get_order_statuses();
		unset( $woo_order_status['wc-cancelled'] );
		unset( $woo_order_status['wc-refunded'] );
		unset( $woo_order_status['wc-failed'] );

		return [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'order_number_enable'     => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'You can custom orders number to print receipts sequentially. This option only works with the new order', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Sequential invoice numbering', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'maximum_applied_coupons' => [
					'type'              => 'number',
					'custom_attributes' => [
						'min'         => 1,
						'placeholder' => esc_html__( 'Leave blank to not limit this', 'webpos-point-of-sale-for-woocommerce' ),
					],
					'value'             => $this->settings->get_params( 'maximum_applied_coupons' ),
					'desc'              => esc_html__( 'Maximum number of coupons to be applied', 'webpos-point-of-sale-for-woocommerce' ),
					'title'             => esc_html__( 'Maximum applied coupons', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'pos_order_status'        => [
					'type'    => 'select',
					'options' => $woo_order_status,
					'value'   => $pos_order_status = $this->settings->get_params( 'pos_order_status' ),
					'desc'    => esc_html__( 'Status of orders create from POS', 'webpos-point-of-sale-for-woocommerce' ),
					'title'   => esc_html__( 'Order status', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'pos_send_mail'           => [
					'type'       => 'select',
					'options'    => array(
						'0'          => esc_html__( 'None', 'webpos-point-of-sale-for-woocommerce' ),
						'wc_default' => esc_html__( 'Woo default', 'webpos-point-of-sale-for-woocommerce' ),
						'admin'      => esc_html__( 'Only send to admin', 'webpos-point-of-sale-for-woocommerce' ),
						'customer'   => esc_html__( 'Only send to customer', 'webpos-point-of-sale-for-woocommerce' ),
					),
					'value'      => $this->settings->get_params( 'pos_send_mail' ),
					'desc'       => esc_html__( 'Send WooCommecre order email after creating orders from POS', 'webpos-point-of-sale-for-woocommerce' ),
					'title'      => esc_html__( 'Send orders email', 'webpos-point-of-sale-for-woocommerce' ),
					'wrap_class' => 'viwebpos-pos_send_mail-wrap' . ( $pos_order_status === 'wc-pending' ? ' viwebpos-hidden' : '' )
				],
				'pos_receipt_mail'        => [
					'type'  => 'premium_option',
					'value' => $this->settings->get_params( 'pos_receipt_mail' ),
					'title' => esc_html__( 'Show receipt in email', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'show_order_on'           => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Display the orders were created on specific days. Leave blank to show all orders.', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Show orders on POS page', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'update_order'            => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Allow the cashier to update orders', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'order_search_field'      => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Fields being searched when searching order', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Order search fields', 'webpos-point-of-sale-for-woocommerce' )
				],
			]
		];
	}

	public function product_options() {
		return [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'auto_create_barcode_by_sku'         => [
					'type'  => 'checkbox',
					'value' => $auto_create_barcode_by_sku = $this->settings->get_params( 'auto_create_barcode_by_sku' ),
					'title' => esc_html__( 'Use SKU instead of barcode', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'auto_create_barcode_by_custom_meta' => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Override the product barcode with another field. Leave blank to not set', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Use product meta instead of barcode', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'atc_custom_pd'                      => [
					'type'  => 'checkbox',
					'value' => $this->settings->get_params( 'atc_custom_pd' ),
					'title' => esc_html__( 'Allow the cashier to add custom product to the cart', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'update_variation_on_cart'           => [
					'type'  => 'checkbox',
					'value' => $this->settings->get_params( 'update_variation_on_cart' ),
					'title' => esc_html__( 'Allow the cashier to update variation the cart', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'change_pd_price'                    => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Allow the cashier to change the product price', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'print_product_barcode'              => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Print product barcode', 'webpos-point-of-sale-for-woocommerce' ),
				],
			]
		];
	}

	public function general_options() {
		return [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => [
				'enable'                    => [
					'type'  => 'checkbox',
					'value' => $this->settings->get_params( 'enable' ),
					'title' => esc_html__( 'Enable', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'pos_endpoint'              => [
					'type'              => 'text',
					'value'             => $this->settings->get_params( 'pos_endpoint' ),
					'custom_attributes' => [
						'placeholder' => 'pos',
						'required'    => 'required',
					],
					'desc'              => esc_html__( 'Endpoints are appended to your POS page URL', 'webpos-point-of-sale-for-woocommerce' ),
					'title'             => esc_html__( 'Endpoint of the POS page', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'cafe-restaurant'           => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Support Restaurant/ Cafe mode', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'multi_outlet'              => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Multi outlets', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'multi_receipt'             => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Multi receipt', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'pos_tax'                   => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Use WooCommerce tax rates and calculations', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Tax', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'enable_ip'                 => [
					'type'  => 'premium_option',
					'title' => esc_html__( 'Whitelist IP', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'sync_pos_data'             => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Allow to sync POS bills that haven\'t checkout between difference device.', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Auto sync POS bill', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'sync_data'                 => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Auto sync online data on the POS page each time duration. Set 0 to not auto sync data.', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Auto sync time', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'receipt_select_when_print' => [
					'type'  => 'premium_option',
					'desc'  => esc_html__( 'Allow the cashier to select receipt template before checkout', 'webpos-point-of-sale-for-woocommerce' ),
					'title' => esc_html__( 'Select template to print', 'webpos-point-of-sale-for-woocommerce' ),
				],
			]
		];
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page !== 'viwebpos' ) {
			return;
		}
		VIWEBPOS_Admin_Settings::remove_other_script();
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'semantic-ui-button', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-form', 'semantic-ui-segment', 'semantic-ui-icon' ),
			array( 'button', 'checkbox', 'dropdown', 'form', 'segment', 'icon' ),
			array( 1, 1, 1, 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'semantic-ui-menu', 'semantic-ui-message', 'semantic-ui-tab' ),
			array( 'menu', 'message', 'tab' ),
			array( 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'transition', 'viwebpos-admin-settings' ),
			array( 'transition', 'admin-settings' ),
			array( 1, 0 )
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'semantic-ui-address', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-form', 'semantic-ui-tab', 'transition' ),
			array( 'address', 'checkbox', 'dropdown', 'form', 'tab', 'transition' ),
			array( 1, 1, 1, 1, 1, 1 ),
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'viwebpos-admin-settings' ),
			array( 'admin-settings' )
		);
	}

	public function admin_bar_menus( $wp_admin_bar ) {
		if ( ! is_admin() || ! is_admin_bar_showing() ) {
			return;
		}
		// Show only when the user is a member of this site, or they're a super admin.
		if ( ! is_user_member_of_blog() && ! is_super_admin() ) {
			return;
		}
		$this->settings = VIWEBPOS_DATA::get_instance( true );
		if ( ! $this->settings->get_params( 'enable' ) ) {
			return;
		}
		// Add an option to visit the POS page.
		$wp_admin_bar->add_node(
			array(
				'parent' => 'site-name',
				'id'     => 'view-pos',
				'title'  => esc_html__( 'Visit POS', 'webpos-point-of-sale-for-woocommerce' ),
				'href'   => home_url( '/' . $this->settings->get_params( 'pos_endpoint' ) ),
			)
		);
	}
}