<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Receipt {
	protected $settings, $page, $type, $action, $recept_id;

	public function __construct() {
		$this->settings = VIWEBPOS_DATA::get_instance();
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 20 );
		add_action( 'admin_init', array( $this, 'save_settings' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );

		add_action( 'wp_ajax_viwebpos_print_order', array( 'VIWEBPOS_Print_Receipt', 'print_receipt' ) );
		add_action( 'viwebpos_print_head', array( 'VIWEBPOS_Print_Receipt', 'receipt_design' ), 10, 1 );
	}

	public function admin_menu() {
		$manage_role = apply_filters( 'viwebpos_change_role', 'manage_woocommerce' );
		add_submenu_page(
			'viwebpos',
			esc_html__( 'WebPOS Receipt', 'webpos-point-of-sale-for-woocommerce' ),
			esc_html__( 'Receipt', 'webpos-point-of-sale-for-woocommerce' ),
			$manage_role,
			'viwebpos-receipt',
			array( $this, 'settings_callback' )
		);
	}

	public function save_settings() {
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page !== 'viwebpos-receipt' ) {
			return;
		}
		if ( ! current_user_can( apply_filters( 'viwebpos_change_role', 'manage_woocommerce' ) ) ) {
			return;
		}
		if ( ! isset( $_POST['_viwebpos_setting'] ) || ! wp_verify_nonce( wc_clean( wp_unslash( $_POST['_viwebpos_setting'] ) ), '_viwebpos_setting_action' ) ) {
			return;
		}
		if ( ! isset( $_POST['viwebpos-save'] ) ) {
			return;
		}
		global $viwebpos_settings;
		$args = $viwebpos_settings['receipts'] ?? [];
		if ( ! is_array( $args ) ) {
			$args = $this->settings->get_default( 'receipts' );
		}
		$arg     = array();
		$type    = 'pos';
		$arg_map = apply_filters( 'viwebpos_receipt_map_update_settings_args', array(
			'field' => array(
				'id',
				'active',
				'logo',
				'logo_id',
				'page_width',
				'page_margin',
				'date_create',
				'order_id',
				'cashier',
				'customer',
				'customer_display',
				'customer_phone',
				'customer_address',
				'product_id',
				'product_sku',
				'product_price',
				'product_quantity',
				'product_subtotal',
				'order_total',
				'order_tax',
				'order_discount',
				'order_paid',
				'order_change',
			),
			'kses'  => array(
				'order_change_label',
				'order_paid_label',
				'order_discount_label',
				'order_tax_label',
				'order_total_label',
				'product_subtotal_label',
				'product_label',
				'product_quantity_label',
				'product_price_label',
				'product_sku_label',
				'product_id_label',
				'contact_info',
				'bill_title',
				'footer_message',
				'date_create_label',
				'order_id_label',
				'cashier_label',
				'customer_label',
				'customer_phone_label',
				'customer_address_label',
				'customer_address_display',
				'barcode_label',
				'product_note_label',
			)
		) );
		villatheme_map_fields( $arg, $arg_map );
		$id            = 'default';
		$arg['active'] = 1;
		$arg['type']   = $type;
		$args["$id"]   = $arg;
		update_option( 'viwebpos_receipts_params', $args );
		if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
			$cache = new WpFastestCache();
			$cache->deleteCache( true );
		}
		$viwebpos_settings['receipts'] = $args;
		$this->settings                = VIWEBPOS_DATA::get_instance( true );
	}

	public function settings_callback() {
		$tabs       = apply_filters( "viwebpos_{$this->type}_receipt_settings_tab_menu", array(
			'general'     => esc_html__( 'General', 'webpos-point-of-sale-for-woocommerce' ),
			'order_item'  => esc_html__( 'Order item details', 'webpos-point-of-sale-for-woocommerce' ),
			'order_total' => esc_html__( 'Order total', 'webpos-point-of-sale-for-woocommerce' ),
		) );
		$tab_active = array_key_first( $tabs );
		$id         = $this->recept_id;
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'WebPOS Receipt', 'webpos-point-of-sale-for-woocommerce' ); ?></h2>
            <div class="vi-ui raised viwebpos-receipt-settings-wrap">
                <form class="vi-ui form" method="post">
					<?php
					wp_nonce_field( '_viwebpos_setting_action', '_viwebpos_setting' );
					printf( '<div class="vi-ui top tabular vi-ui-main attached menu">' );
					foreach ( $tabs as $slug => $text ) {
						$active = $tab_active === $slug ? 'active' : '';
						printf( ' <a class="item %s" data-tab="%s">%s</a>', esc_attr( $active ), esc_attr( $slug ), esc_html( $text ) );
					}
					printf( '</div>' );
					foreach ( $tabs as $slug => $text ) {
						$active = $tab_active === $slug ? 'active' : '';
						$method = $this->type . '_' . str_replace( '-', '_', $slug ) . '_options';
						printf( '<div class="vi-ui bottom attached %s tab segment" data-tab="%s">', esc_attr( $active ), esc_attr( $slug ) );
						if ( method_exists( $this, $method ) ) {
							villatheme_render_table_field( apply_filters( "viwebpos_{$this->type}_receipt_settings_fields", $this->$method( $id ), $slug, $id ) );
						}
						do_action( 'viwebpos_receipt_settings_tab', $slug, $id );
						printf( '</div>' );
					}
					?>
                    <p class="viwebpos-save-wrap">
                        <button type="submit" class="viwebpos-save vi-ui primary button" name="viwebpos-save">
							<?php esc_html_e( 'Save', 'webpos-point-of-sale-for-woocommerce' ); ?>
                        </button>
                        <button type="button" class="viwebpos-print-preview vi-ui green button">
							<?php esc_html_e( 'Print sample', 'webpos-point-of-sale-for-woocommerce' ); ?>
                        </button>
                    </p>
                </form>
				<?php
				printf( '<div class="viwebpos-receipt-preview-wrap"><div class="viwebpos-receipt-preview"><div class="viwebpos-receipt-inner viwebpos-receipt-%s %s">', esc_attr( $id ), esc_attr( $this->type === 'pos' ? 'viwebpos-bill-content-inner' : 'viwebpos-' . $this->type . '-receipt-wrap' ) );
				$line_items                 = array();
				$product_types              = wc_get_product_types();
				$product_types['variation'] = 1;
				unset( $product_types['variable'] );
				unset( $product_types['external'] );
				unset( $product_types['grouped'] );
				$products = wc_get_products( array(
					'status'         => 'publish',
					'type'           => array_keys( $product_types ),
					'posts_per_page' => 3,
					'orderby'        => 'desc',
				) );
				if ( ! empty( $products ) ) {
					foreach ( $products as $k => $product ) {
						$product_id   = $product->get_id();
						$line_items[] = array(
							'id'       => $product_id,
							'sku'      => $product->get_sku(),
							'name'     => $product->get_name(),
							'barcode'  => apply_filters( 'viwebpos_get_product_barcode', $product->get_meta( 'viwebpos_barcode', true ), $product_id ),
							'price'    => $product_price = $product->get_price(),
							'qty'      => $k + 1,
							'subtotal' => ( $k + 1 ) * floatval( $product_price ),
						);
					}
				}
				?>
                <div class="viwebpos-bill-header-wrap">
                    <div class="viwebpos-bill-logo"><span class="viwebpos-bill-logo-preview"></span></div>
                    <div class="viwebpos-bill-contact-wrap">
                        <div class="viwebpos-bill-contact"></div>
                    </div>
                </div>
                <div class="viwebpos-bill-title viwebpos-font-bold viwebpos-hidden"></div>
                <div class="viwebpos-bill-top">
                    <div class="viwebpos-bill-order-date-wrap">
                        <div class="viwebpos-bill-date-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-order-date">
							<?php echo sprintf( '%1$s %2$s %3$s', esc_html( date_i18n( wc_date_format() ) ), esc_html__( 'at', 'webpos-point-of-sale-for-woocommerce' ), esc_html( date_i18n( wc_time_format() ) ) ) ?>
                        </div>
                    </div>
                    <div class="viwebpos-bill-order-id-wrap">
                        <div class="viwebpos-bill-id-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-order-id"><?php echo esc_html( '#' . time() ); ?></div>
                    </div>
                    <div class="viwebpos-bill-cashier-wrap">
                        <div class="viwebpos-bill-cashier-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-cashier"><?php echo wp_kses_post( wp_get_current_user()->display_name ); ?></div>
                    </div>
					<?php
					$customer_info = [
						'email'           => '{customer_email}',
						'phone'           => '{customer_phone}',
						'display_name'    => '{customer_display_name}',
						'first_name'      => '{customer_first_name}',
						'last_name'       => '{customer_last_name}',
						'company'         => '{customer_company}',
						'companyfullname' => '{customer_company_or_fullname}',
						'address_1'       => '{customer_address_1}',
						'address_2'       => '{customer_address_2}',
						'city'            => '{customer_city}',
						'state'           => '{customer_state}',
						'country'         => '{customer_country}',
					];
					$user_s        = get_users( array(
						'role'    => 'customer',
						'orderby' => 'user_nicename',
						'number'  => 1,
						'order'   => 'ASC'
					) );
					if ( ! empty( $user_s ) ) {
						$user_query       = $user_s[0]->data;
						$shipping_company = get_user_meta( $user_query->ID, 'shipping_company', true );
						$billing_company  = get_user_meta( $user_query->ID, 'billing_company', true );
						$user_company     = $shipping_company ?: $billing_company;
						$customer_info    = [
							'phone'           => $user_s[0]->billing_phone ?? $user_s[0]->shipping_phone ?? esc_html__( 'No phone number', 'webpos-point-of-sale-for-woocommerce' ),
							'email'           => $user_query->user_email,
							'display_name'    => $user_query->display_name,
							'first_name'      => $user_s[0]->first_name,
							'last_name'       => $user_s[0]->last_name,
							'company'         => $user_company,
							'companyfullname' => $user_company ?: $user_query->display_name,
							'address_1'       => $user_s[0]->billing_address_1 ?? $user_s[0]->shipping_address_1 ?? '',
							'address_2'       => $user_s[0]->billing_address_2 ?? $user_s[0]->shipping_address_2 ?? '',
							'city'            => $user_s[0]->billing_city ?? $user_s[0]->shipping_city ?? '',
							'state'           => $user_s[0]->billing_state ?? $user_s[0]->shipping_state ?? '',
							'country'         => $user_s[0]->billing_country ?? $user_s[0]->shipping_country ?? '',
						];
					}
					?>
                    <div class="viwebpos-bill-customer-wrap">
                        <div class="viwebpos-bill-customer-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-customer-info">
                            <span class="viwebpos-bill-customer-email viwebpos-hidden"><?php echo esc_html( $customer_info['email'] ) ?></span>
                            <span class="viwebpos-bill-customer-fullname viwebpos-hidden"><?php echo esc_html( $customer_info['display_name'] ) ?></span>
                            <span class="viwebpos-bill-customer-firstname viwebpos-hidden"><?php echo esc_html( $customer_info['first_name'] ) ?></span>
                            <span class="viwebpos-bill-customer-lastname viwebpos-hidden"><?php echo esc_html( $customer_info['last_name'] ) ?></span>
                            <span class="viwebpos-bill-customer-company viwebpos-hidden"><?php echo esc_html( $customer_info['company'] ) ?></span>
                            <span class="viwebpos-bill-customer-companyfullname viwebpos-hidden"><?php echo esc_html( $customer_info['companyfullname'] ) ?></span>
                        </div>
                    </div>
                    <div class="viwebpos-bill-customer-phone-wrap">
                        <div class="viwebpos-bill-customer-phone-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-customer-phone-info"><?php echo esc_html( $customer_info['phone'] ); ?></div>
                    </div>
                    <div class="viwebpos-bill-customer-address-wrap">
                        <div class="viwebpos-bill-customer-address-label viwebpos-hidden"></div>
                        <div class="viwebpos-bill-customer-address-info"
                             data-address1="<?php echo esc_attr( $customer_info['address_1'] ) ?>"
                             data-address2="<?php echo esc_attr( $customer_info['address_2'] ) ?>"
                             data-city="<?php echo esc_attr( $customer_info['city'] ) ?>"
                             data-state="<?php echo esc_attr( $customer_info['state'] ) ?>"
                             data-country="<?php echo esc_attr( $customer_info['country'] ) ?>">
                        </div>
                    </div>
                </div>
                <div class="viwebpos-bill-col-body">
                    <table>
                        <tr class="viwebpos-bill-col-product-wrap">
                            <th class="viwebpos-bill-col-product-title-label"></th>
                            <th class="viwebpos-bill-col-product-id-label viwebpos-align-right"></th>
                            <th class="viwebpos-bill-col-product-sku-label viwebpos-align-right"></th>
                            <th class="viwebpos-bill-col-product-price-label viwebpos-align-right"></th>
                            <th class="viwebpos-bill-col-product-quantity-label viwebpos-align-right"></th>
                            <th class="viwebpos-bill-col-product-subtotal-label viwebpos-align-right"></th>
                        </tr>
						<?php
						$total = 0;
						foreach ( $line_items as $item ) {
							$original_title = $item['name'] ?? esc_html__( 'The product name', 'webpos-point-of-sale-for-woocommerce' );
							?>
                            <tr class="viwebpos-bill-col-product-wrap">
                                <td class="viwebpos-bill-col-product-title" data-title="<?php echo wp_kses_post( $original_title ) ?>">
									<?php echo wp_kses_post( $original_title ); ?>
                                    <div class="viwebpos-bill-product-note-wrap">
                                        <span class="viwebpos-bill-product-note-label"></span>
                                        <span class="viwebpos-bill-product-note">
                                                        <?php echo esc_html__( 'product note', 'webpos-point-of-sale-for-woocommerce' ); ?>
                                                    </span>
                                    </div>
                                </td>
                                <td class="viwebpos-bill-col-product-id viwebpos-align-right"><?php echo esc_html( $item['id'] ?? 10 ); ?></td>
                                <td class="viwebpos-bill-col-product-sku viwebpos-align-right"><?php echo esc_html( $item['sku'] ?? '' ); ?></td>
                                <td class="viwebpos-bill-col-product-price viwebpos-align-right">
									<?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( $item['price'] ?? 10 ) ); ?>
                                </td>
                                <td class="viwebpos-bill-col-product-quantity viwebpos-align-right"><?php echo esc_html( $item['qty'] ?? 1 ); ?></td>
                                <td class="viwebpos-bill-col-product-subtotal viwebpos-align-right">
									<?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( $subtotal = $item['subtotal'] ?? 10 ) ); ?>
                                </td>
                            </tr>
							<?php
							$total += floatval( $subtotal );
						}
						?>
                    </table>
                </div>
                <div class="viwebpos-bill-bottom">
                    <div class="viwebpos-bill-order-tax-wrap">
                        <div class="viwebpos-bill-order-tax-label"></div>
                        <div class="viwebpos-bill-order-tax viwebpos-font-bold"><?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( 0 ) ); ?></div>
                    </div>
                    <div class="viwebpos-bill-order-discount-wrap">
                        <div class="viwebpos-bill-order-discount-label"></div>
                        <div class="viwebpos-bill-order-discount viwebpos-font-bold"><?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( 0 ) ); ?></div>
                    </div>
                    <div class="viwebpos-bill-order-paid-wrap">
                        <div class="viwebpos-bill-order-paid-label"></div>
                        <div class="viwebpos-bill-order-paid viwebpos-font-bold"><?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( $total + 10 ) ); ?></div>
                    </div>
                    <div class="viwebpos-bill-order-change-wrap">
                        <div class="viwebpos-bill-order-change-label"></div>
                        <div class="viwebpos-bill-order-change viwebpos-font-bold"><?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( 10 ) ); ?></div>
                    </div>
                    <div class="viwebpos-bill-order-total-wrap">
                        <div class="viwebpos-bill-order-total-label"></div>
                        <div class="viwebpos-bill-order-total viwebpos-font-bold"> <?php echo wp_kses_post( VIWEBPOS_Plugins_Curcy::curcy_wc_price( $total ) ); ?></div>
                    </div>
                </div>
                <div class="viwebpos-bill-footer">
                    <div class="viwebpos-bill-footer-message"></div>
                </div>
				<?php
				printf( '</div></div></div>' );
				?>
            </div>
        </div>
		<?php
	}

	public function pos_order_total_options( $id ) {
		$arg             = [
			'order_note'         => [
				'title'         => esc_html__( 'Enable order note', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 0,
				'label_title'   => esc_html__( 'Order note label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'NOTE: ',
			],
			'order_tax'          => [
				'title'         => esc_html__( 'Enable order total tax', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'Total tax label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'TOTAL TAX',
			],
			'order_ship'         => [
				'title'         => esc_html__( 'Enable order shipping', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 0,
				'label_title'   => esc_html__( 'Shipping label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'SHIPPING',
			],
			'order_discount'     => [
				'title'         => esc_html__( 'Enable order coupon', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'Coupon label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'COUPON',
			],
			'order_pos_discount' => [
				'title'         => esc_html__( 'Enable order POS discount', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'POS discount label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'POS DISCOUNT',
			],
			'order_paid'         => [
				'title'         => esc_html__( 'Enable the paid', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'The paid label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'PAID',
			],
			'order_change'       => [
				'title'         => esc_html__( 'Enable the change', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'The change label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'CHANGE',
			],
			'order_total'        => [
				'title'         => esc_html__( 'Enable order total', 'webpos-point-of-sale-for-woocommerce' ),
				'default'       => 1,
				'label_title'   => esc_html__( 'Order total label', 'webpos-point-of-sale-for-woocommerce' ),
				'label_default' => 'TOTAL PRICE',
			],
		];
		$premium_options = [ 'order_note', 'order_ship', 'order_pos_discount' ];
		$field_args      = [];
		foreach ( $arg as $k => $v ) {
			if ( in_array( $k, $premium_options ) ) {
				$field_args[ $k ] = [
					'prefix' => 'receipts',
					'type'   => 'premium_option',
					'title'  => $v['title'] ?? $k,
				];
				continue;
			}
			$field_args[ $k ]            = [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $enable = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, $k, $v['default'] ?? '' ),
				'title'  => $v['title'] ?? $k,
			];
			$field_args[ $k . '_label' ] = [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, $k . '_label', $v['label_default'] ?? '' ),
				'wrap_class' => 'viwebpos-receipts-' . $k . '-enable' . ( $enable ? '' : ' viwebpos-hidden' ),
				'title'      => $v['label_title'] ?? $k . '_label',
			];
		}
		$fields = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => $field_args
		];

		return $fields;
	}

	public function pos_order_item_options( $id ) {
		$field_args = [
			'product_temp'           => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Template of items field', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_note'           => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Enable product notes', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_id'             => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_product_id = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_id', 0 ),
				'title'  => esc_html__( 'Enable product ID', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_id_label'       => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_id_label', 'ID' ),
				'wrap_class' => 'viwebpos-receipts-product_id-enable' . ( $receipt_product_id ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Product ID label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_sku'            => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_product_sku = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_sku', 0 ),
				'title'  => esc_html__( 'Enable product SKU', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_sku_label'      => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_sku_label', 'SKU' ),
				'wrap_class' => 'viwebpos-receipts-product_sku-enable' . ( $receipt_product_sku ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Product SKU label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_price'          => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_product_price = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_price', 1 ),
				'title'  => esc_html__( 'Enable product price', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_price_label'    => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_price_label', 'Price' ),
				'wrap_class' => 'viwebpos-receipts-product_price-enable' . ( $receipt_product_price ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Product price label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_quantity'       => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_product_quantity = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_quantity', 1 ),
				'title'  => esc_html__( 'Enable product quantity', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_quantity_label' => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_quantity_label', 'Qty' ),
				'wrap_class' => 'viwebpos-receipts-product_quantity-enable' . ( $receipt_product_quantity ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Product quantity label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_subtotal'       => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_product_subtotal = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_subtotal', 1 ),
				'title'  => esc_html__( 'Enable product subtotal', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_subtotal_label' => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_subtotal_label', 'Total' ),
				'wrap_class' => 'viwebpos-receipts-product_subtotal-enable' . ( $receipt_product_subtotal ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Product subtotal label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_label'          => [
				'prefix' => 'receipts',
				'type'   => 'text',
				'value'  => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'product_label', 'Product name' ),
				'title'  => esc_html__( 'Product label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_variation'      => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Enable product variation', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'product_character'      => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Limit in product name', 'webpos-point-of-sale-for-woocommerce' ),
			],
		];
		$fields     = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => $field_args
		];

		return $fields;
	}

	public function pos_general_options( $id ) {
		$placeholder_logo_src = VIWEBPOS_IMAGES . 'icon-256x256.png';
		$receipt_logo_id      = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'logo_id', '' );
		$logo_src             = $receipt_logo_id ? wp_get_attachment_image_url( $receipt_logo_id, 'woocommerce_thumbnail', true ) : $placeholder_logo_src;
		$field_args           = [
			'active'                   => [
				'prefix'     => 'receipts',
				'type'       => 'hidden',
				'value'      => 1,
				'title'      => esc_html__( 'Activate', 'webpos-point-of-sale-for-woocommerce' ),
				'wrap_class' => 'viwebpos-hidden',
			],
			'id'                       => [
				'prefix'     => 'receipts',
				'type'       => 'hidden',
				'value'      => $id,
				'wrap_class' => 'viwebpos-hidden',
			],
			'name'                     => [
				'prefix'     => 'receipts',
				'type'       => 'hidden',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'name', 'Default' ),
				'title'      => esc_html__( 'Receipt name', 'webpos-point-of-sale-for-woocommerce' ),
				'wrap_class' => 'viwebpos-hidden',
			],
			'direction'                => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Direction', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'logo'                     => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_logo = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'logo', '' ),
				'title'  => esc_html__( 'Enable logo', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'logo_id'                  => [
				'prefix'     => 'receipts',
				'type'       => 'upload_image',
				'html'       => sprintf( '<div class="viwebpos-upload-logo-wrap">
                                        <input type="hidden" name="logo_id" class="receipt_logo_id" value="%s">
                                        <span class="viwebpos-upload-logo-preview"><img src="%s" data-src_placeholder="%s"></span>
                                        <i class="viwebpos-upload-logo-remove times circle outline icon%s"></i>
                                        <span class="viwebpos-upload-logo-add-new">%s</span>
                                    </div>',
					esc_attr( $receipt_logo_id ), esc_url( $logo_src ), esc_url( $placeholder_logo_src ),
					esc_attr( $receipt_logo_id ? '' : ' viwebpos-hidden' ),
					esc_html__( 'Upload / Add image', 'webpos-point-of-sale-for-woocommerce' ) ),
				'wrap_class' => $logo_class = 'viwebpos-receipt_logo-enable' . ( $receipt_logo ? '' : ' viwebpos-hidden' ),
				'desc'       => esc_html__( 'Choose an image', 'webpos-point-of-sale-for-woocommerce' ),
				'title'      => esc_html__( 'Logo', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'logo_pos'                 => [
				'prefix'     => 'receipts',
				'type'       => 'premium_option',
				'wrap_class' => $logo_class,
				'title'      => esc_html__( 'Logo position', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'logo_width'               => [
				'prefix'     => 'receipts',
				'type'       => 'premium_option',
				'wrap_class' => $logo_class,
				'title'      => esc_html__( 'Logo width', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'logo_border'              => [
				'prefix'     => 'receipts',
				'type'       => 'premium_option',
				'wrap_class' => $logo_class,
				'title'      => esc_html__( 'Logo border radius', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'page_width'               => [
				'prefix'            => 'receipts',
				'type'              => 'number',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'page_width', 120 ),
				'custom_attributes' => [
					'min' => '30',
					'max' => '500',
				],
				'input_label'       => [
					'type'        => 'right',
					'fluid'       => 1,
					'label'       => 'mm',
					'label_class' => 'vi-ui viwebpos-label basic label',
				],
				'title'             => esc_html__( 'Page width', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'page_margin'              => [
				'prefix'            => 'receipts',
				'type'              => 'number',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'page_margin', 10 ),
				'custom_attributes' => [
					'min' => '0',
					'max' => '50',
				],
				'input_label'       => [
					'type'        => 'right',
					'fluid'       => 1,
					'label'       => 'mm',
					'label_class' => 'vi-ui viwebpos-label basic label',
				],
				'title'             => esc_html__( 'Page margin', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'contact_info'             => [
				'prefix'            => 'receipts',
				'type'              => 'textarea',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'contact_info', '{site_title}
{address_1}, {city}' ),
				'custom_attributes' => [
					'rows' => '5',
				],
				'custom_desc'       => sprintf( '<p class="description">{site_title} - %s</p>
                                    <p class="description">{address_1} - %s</p><p class="description">{address_2} - %s</p>
                                    <p class="description">{city} - %s</p><p class="description">{state} - %s</p><p class="description">{country} - %s</p>',
					esc_html__( 'Site Title', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'Address line 1', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'Address line 2', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'City', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'State', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'Country', 'webpos-point-of-sale-for-woocommerce' ), ),
				'title'             => esc_html__( 'Contact information', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'bill_title'               => [
				'prefix'            => 'receipts',
				'type'              => 'text',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'bill_title', 'BILL OF SALE' ),
				'custom_attributes' => [
					'maxlength' => '50',
				],
				'title'             => esc_html__( 'Bill title', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'bill_title_size'          => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Bill title font size', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'font_size'                => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Bill font size', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'footer_message'           => [
				'prefix'            => 'receipts',
				'type'              => 'textarea',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'footer_message', 'THANK YOU AND SEE YOU AGAIN' ),
				'custom_attributes' => [
					'rows' => '5',
				],
				'title'             => esc_html__( 'Footer message', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'order_col'                => [
				'prefix' => 'receipts',
				'type'   => 'premium_option',
				'title'  => esc_html__( 'Order info column', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'date_create'              => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_date_create = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'date_create', 1 ),
				'title'  => esc_html__( 'Enable order date create', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'date_create_label'        => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'date_create_label', 'Date: ' ),
				'wrap_class' => 'viwebpos-receipts-date_create-enable' . ( $receipt_date_create ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Order date create label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'order_id'                 => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_order_id = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'order_id', 1 ),
				'title'  => esc_html__( 'Enable order number', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'order_id_label'           => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'order_id_label', 'Order: ' ),
				'wrap_class' => 'viwebpos-receipts-order_id-enable' . ( $receipt_order_id ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Order number label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'cashier'                  => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_cashier = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'cashier', 1 ),
				'title'  => esc_html__( 'Enable cashier', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'cashier_label'            => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'cashier_label', 'Cashier: ' ),
				'wrap_class' => 'viwebpos-receipts-cashier-enable' . ( $receipt_cashier ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Cashier label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer'                 => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_customer = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer', 0 ),
				'title'  => esc_html__( 'Enable customer', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_label'           => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_label', 'Customer: ' ),
				'wrap_class' => 'viwebpos-receipts-customer-enable' . ( $receipt_customer ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Customer label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_display'         => [
				'prefix'     => 'receipts',
				'type'       => 'select',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_display', 'fullname' ),
				'options'    => [
					'email'     => esc_html__( 'Email', 'webpos-point-of-sale-for-woocommerce' ),
					'fullname'  => esc_html__( 'Full name', 'webpos-point-of-sale-for-woocommerce' ),
					'firstname' => esc_html__( 'First name', 'webpos-point-of-sale-for-woocommerce' ),
					'lastname'  => esc_html__( 'Last name', 'webpos-point-of-sale-for-woocommerce' ),
				],
				'wrap_class' => 'viwebpos-receipts-customer-enable' . ( $receipt_customer ? '' : ' viwebpos-hidden' ),
				'desc'       => esc_html__( 'Choose display customer data', 'webpos-point-of-sale-for-woocommerce' ),
				'title'      => esc_html__( 'Customer information', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_phone'           => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_customer_phone = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_phone', 0 ),
				'title'  => esc_html__( 'Enable customer phone', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_phone_label'     => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_phone_label', 'Phone: ' ),
				'wrap_class' => 'viwebpos-receipts-customer_phone-enable' . ( $receipt_customer_phone ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Customer phone label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_address'         => [
				'prefix' => 'receipts',
				'type'   => 'checkbox',
				'value'  => $receipt_customer_address = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_address', 0 ),
				'title'  => esc_html__( 'Enable customer address', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_address_label'   => [
				'prefix'     => 'receipts',
				'type'       => 'text',
				'value'      => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_address_label', 'Address: ' ),
				'wrap_class' => 'viwebpos-receipts-customer_address-enable' . ( $receipt_customer_address ? '' : ' viwebpos-hidden' ),
				'title'      => esc_html__( 'Customer address label', 'webpos-point-of-sale-for-woocommerce' ),
			],
			'customer_address_display' => [
				'prefix'            => 'receipts',
				'type'              => 'textarea',
				'value'             => $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'customer_address_display', '{address_line_1}, {city} {country}' ),
				'custom_attributes' => [
					'rows' => '5',
				],
				'custom_desc'       => sprintf( '<p class="description">{address_line_1} - %s</p>
                                    <p class="description">{address_line_2} - %s</p>
                                    <p class="description">{city} - %s</p><p class="description">{state} - %s</p><p class="description">{country} - %s</p>',
					esc_html__( 'Address line 1', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'Address line 2', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'City', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'State', 'webpos-point-of-sale-for-woocommerce' ),
					esc_html__( 'Country', 'webpos-point-of-sale-for-woocommerce' ) ),
				'wrap_class'        => 'viwebpos-receipts-customer_address-enable' . ( $receipt_customer_address ? '' : ' viwebpos-hidden' ),
				'title'             => esc_html__( 'Customer address', 'webpos-point-of-sale-for-woocommerce' ),
			]
		];
		$fields               = [
			'section_start' => [],
			'section_end'   => [],
			'fields'        => $field_args
		];

		return $fields;
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( $page !== 'viwebpos-receipt' ) {
			return;
		}
		$this->action    = 'edit';
		$this->type      = 'pos';
		$this->recept_id = $id = 'default';
		VIWEBPOS_Admin_Settings::remove_other_script();
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'semantic-ui-button', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-form', 'semantic-ui-segment', 'semantic-ui-icon' ),
			array( 'button', 'checkbox', 'dropdown', 'form', 'segment', 'icon' ),
			array( 1, 1, 1, 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'semantic-ui-label', 'semantic-ui-input', 'semantic-ui-menu', 'semantic-ui-message', 'semantic-ui-tab' ),
			array( 'label', 'input', 'menu', 'message', 'tab' ),
			array( 1, 1, 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'transition', 'viwebpos-admin-settings', 'viwebpos-receipt' ),
			array( 'transition', 'admin-settings', 'pos_receipt' ),
			array( 1, 0, 0 )
		);
		wp_enqueue_media();
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'semantic-ui-address', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-form', 'semantic-ui-tab', 'transition' ),
			array( 'address', 'checkbox', 'dropdown', 'form', 'tab', 'transition' ),
			array( 1, 1, 1, 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'viwebpos-admin-receipt-settings' ),
			array( 'admin-receipt-settings' )
		);
		$font_size       = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'font_size', 13 );
		$bill_title_size = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'bill_title_size', 20 );
		$page_margin     = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'page_margin', 10 );
		$page_width      = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'page_width', 120 );
		$logo_width      = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'logo_width', 22 );
		$logo_border     = $this->settings->get_current_setting_by_subtitle( 'receipts', $id, 'logo_border', 50 );
		$css             = ".viwebpos-receipt-preview .viwebpos-bill-logo-preview img{width: {$logo_width}mm;border-radius: {$logo_border}%;}";
		$css             .= ".viwebpos-receipt-preview{width: {$page_width}mm;}";
		$css             .= ".viwebpos-receipt-inner{padding: {$page_margin}mm;font-size: {$font_size}px;}";
		$css             .= ".viwebpos-receipt-inner .viwebpos-bill-title{font-size: {$bill_title_size}px;}";
		wp_add_inline_style( 'viwebpos-receipt', esc_attr( $css ) );
		$location_address  = wc_get_base_location();
		$viwebpos_receipts = [
			'action'            => $this->action,
			'type'              => $this->type,
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'site_title'        => get_bloginfo( 'name' ),
			'primary_address'   => get_option( 'woocommerce_store_address', '' ),
			'secondary_address' => get_option( 'woocommerce_store_address_2', '' ),
			'city'              => get_option( 'woocommerce_store_city', '' ),
			'state'             => $location_address['state'] ?? '',
			'country'           => $location_address['country'] ?? ''
		];
		wp_localize_script( 'viwebpos-admin-receipt-settings', 'viwebpos_receipts', $viwebpos_receipts );
	}
}