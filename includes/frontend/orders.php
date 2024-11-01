<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Orders {
	public static $cache = array();
	protected static $settings;

	public function __construct() {
		self::$settings = VIWEBPOS_DATA::get_instance();
	}

	public static function remove_send_mail_to_admin( $result, $email, $value, $key, $empty_value ) {
		if ( $key === 'recipient' && in_array( self::$settings->get_params( 'pos_send_mail' ), array( '0', 'customer' ) ) ) {
			$result = '';
		}

		return $result;
	}

	public static function remove_send_mail_to_customer( $result, $order ) {
		if ( in_array( self::$settings->get_params( 'pos_send_mail' ), array( '0', 'admin' ) ) ) {
			$result = '';
		}

		return $result;
	}

	public static function viwebpos_create_order() {
		$result = array(
			'status'       => 'error',
			'message'      => '',
			'data_prefix'  => array(),
			'products'     => array(),
			'coupons'      => array(),
			'customers'    => array(),
			'orders'       => array(),
			'transactions' => array()
		);
		if ( ! check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce', false ) ) {
			$result['message'] = 'error nonce';
			wp_send_json( $result );
		}
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			$result['message'] = 'missing role';
			wp_send_json( $result );
		}
		$cart_items = isset( $_POST['cart_items'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['cart_items'] ) ) ) : array();
		if ( ! is_array( $cart_items ) || empty( $cart_items ) ) {
			$result['message'] = esc_html__( 'The cart is empty. Please add some products to the cart!', 'webpos-point-of-sale-for-woocommerce' );
			wp_send_json( $result );
		}
		$cart_fees            = isset( $_POST['cart_fees'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['cart_fees'] ) ) ) : array();
		$payments             = isset( $_POST['payments'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['payments'] ) ) ) : array();
		$payment_method       = ! empty( $payments['is_paid'] ) ? $payments['is_paid'] : 'cash';
		$payment_method_title = ! empty( $payments['is_paid_title'] ) ? $payments['is_paid_title'] : '';
		$totals               = isset( $_POST['totals'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['totals'] ) ) ) : array();
		$coupons              = isset( $_POST['coupons'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['coupons'] ) ) ) : array();
		$customer             = isset( $_POST['customer'] ) ? villatheme_json_decode( villatheme_sanitize_fields( wp_unslash( $_POST['customer'] ) ) ) : array();
		$order_note           = isset( $_POST['order_note'] ) ? wp_kses_post( wp_unslash( $_POST['order_note'] ) ) : '';
		$currency             = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : get_woocommerce_currency();
		$outlet               = 'woo_online';
		if ( empty( $totals ) || ! isset( $totals['total'] ) ) {
			$result['message'] = esc_html__( 'Can\'t get the order total!', 'webpos-point-of-sale-for-woocommerce' );
			wp_send_json( $result );
		}
		try {
			viwebpos_init_set();
			do_action( 'viwebpos_before_create_order' );
			$order = new WC_Order();
			if ( ! empty( $customer ) ) {
				$country_setting = self::$settings->get_current_setting_by_subtitle( 'outlets', $outlet, 'country', apply_filters( 'woocommerce_get_base_location', get_option( 'woocommerce_default_country', 'US:CA' ) ) );
				if ( strstr( $country_setting, ':' ) ) {
					list( $country, $state ) = explode( ':', $country_setting );
				} else {
					$country = $country_setting;
					$state   = '';
				}
				$billing_address = array(
					'first_name' => $customer['first_name'] ?? '',
					'last_name'  => $customer['last_name'] ?? '',
					'company'    => $customer['billing_address']['company'] ?? '',
					'email'      => $customer['email'] ?? '',
					'phone'      => $customer['phone'] ?? '',
					'address_1'  => $customer['billing_address']['address_1'] ?? self::$settings->get_current_setting_by_subtitle( 'outlets', $outlet, 'address', WC()->countries->get_base_address() ),
					'address_2'  => $customer['billing_address']['address_2'] ?? self::$settings->get_current_setting_by_subtitle( 'outlets', $outlet, 'address_2', WC()->countries->get_base_address_2() ),
					'city'       => $customer['billing_address']['city'] ?? self::$settings->get_current_setting_by_subtitle( 'outlets', $outlet, 'city', WC()->countries->get_base_city() ),
					'state'      => $customer['billing_address']['state'] ?? $state ?? WC()->countries->get_base_state(),
					'postcode'   => $customer['billing_address']['postcode'] ?? self::$settings->get_current_setting_by_subtitle( 'outlets', $outlet, 'postcode', WC()->countries->get_base_postcode() ),
					'country'    => $customer['billing_address']['country'] ?? $country ?? WC()->countries->get_base_country()
				);
				$order->set_address( $billing_address, 'billing' );
			}
			$order->set_customer_id( $customer['id'] ?? 0 );
			$order->set_created_via( 'viwebpos' );
			$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
			$order->set_currency( $currency );
			$order->set_customer_note( $order_note );
			$order->set_payment_method( $payment_method );
			if ( $payment_method === 'multi' ) {
				$payment_method_title = esc_html__( 'Multiple Methods', 'webpos-point-of-sale-for-woocommerce' );
			}
			if ( $payment_method_title ) {
				$order->set_payment_method_title( $payment_method_title );
			}
			$order->set_shipping_total( $totals['shipping_total'] );
			$order->set_shipping_tax( $totals['shipping_tax'] );
			$order->set_discount_total( $totals['discount_total'] );
			$order->set_discount_tax( $totals['discount_tax'] );
			$order->set_cart_tax( $totals['cart_contents_tax'] + $totals['fee_tax'] );
			$order->set_total( $totals['total'] );
			$order->add_meta_data( 'pos_total_paid', $payments['total_paid'] ?? 0, true );
			$order->add_meta_data( 'pos_change', $payments['change'] ?? 0, true );
			$order->add_meta_data( 'viwebpos_cashier_id', $cashier_id = get_current_user_id(), true );
			$order->add_meta_data( 'viwebpos_outlet', $outlet, true );
			$order->remove_order_items();
			self::create_order_line_items( $order, $cart_items );
			self::create_order_fee_lines( $order, $cart_fees );
			self::create_order_tax_lines( $order, $totals );
			self::create_order_coupon_lines( $order, $coupons );
			add_filter( 'woocommerce_email_get_option', array(
				__CLASS__,
				'remove_send_mail_to_admin'
			), PHP_INT_MAX, 5 );
			add_filter( 'woocommerce_order_get_billing_email', array(
				__CLASS__,
				'remove_send_mail_to_customer'
			), PHP_INT_MAX, 2 );
			do_action( 'viwebpos_woocommerce_checkout_create_order', $order, array(
				'cart_items' => $cart_items,
				'payments'   => $payments,
				'customer'   => $customer,
				'total'      => $totals
			) );
			$order_id = $order->save();
			if ( ! $order_id ) {
				throw new Exception( esc_html__( 'Can not create the order!', 'webpos-point-of-sale-for-woocommerce' ) );
			}
			$order->update_status( self::$settings->get_params( 'pos_order_status' ), esc_html__( 'Order status changed by WebPOS:', 'webpos-point-of-sale-for-woocommerce' ), true );
			do_action( 'viwebpos_woocommerce_checkout_order_created', $order );
			remove_filter( 'woocommerce_email_get_option', array(
				__CLASS__,
				'remove_send_mail_to_admin'
			), PHP_INT_MAX );
			self::$cache[ 'get_order_data-' . $order_id ] = true;
			$orders                                       = array();
			self::get_order_data( $orders, $order_id );
			unset( self::$cache[ 'get_order_data-' . $order_id ] );
			$transactions = array();
			$transaction         = array(
				'cashier_id'      => $cashier_id,
				'order_id'        => $order_id,
				'in'              => $payments['total_paid'] ?? 0,
				'out'             => $payments['change'] ?? 0,
				'method'          => ! empty( $payments['is_paid'] ) ? $payments['is_paid'] : 'cash',
				'currency'        => $currency,
				'currency_symbol' => get_woocommerce_currency_symbol( $currency ),
				'note'            => '',
				'outlet'          => $outlet,
				'create_at'       => gmdate( 'Y-m-d H:i:s' ),
			);
			$transaction['id']   = VIWEBPOS_Transactions_Table::insert( $transaction );
			$transaction['date'] = gmdate( 'Y-m-d' );
			$transactions[]      = $transaction;
			$products = array();
			foreach ( $cart_items as $values ) {
				$product_id = ! empty( $values['variation_id'] ) ? $values['variation_id'] : ( $values['product_id'] ?? 0 );
				if ( ! $product_id || $product_id === 'custom' ) {
					continue;
				}
				self::$cache[ 'get_product_data-' . $product_id ] = true;
				VIWEBPOS_Frontend_Bill_Of_Sale::get_product_data( $products, $product_id );
				unset( self::$cache[ 'get_product_data-' . $product_id ] );
			}
			$result['status']   = 'success';
			/* translators: %s: order id */
			$result['message']  = sprintf( esc_html__( 'Order #%s was created successfully.', 'webpos-point-of-sale-for-woocommerce' ), $order_id );
			$result['products'] = $products;
			if ( $user_id = $customer['id'] ?? 0 ) {
				$customers                                      = array();
				self::$cache[ 'get_customer_data-' . $user_id ] = true;
				VIWEBPOS_Frontend_Customers::get_customer_data( $customers, $user_id );
				unset( self::$cache[ 'get_customer_data-' . $user_id ] );
				$result['customers'] = $customers;
			}
			if ( ! empty( $coupons['applied_coupons'] ) ) {
				$coupons1 = array();
				foreach ( array_values( $coupons['applied_coupons'] ) as $coupon ) {
					if ( ! empty( $coupon['id'] ) ) {
						self::$cache[ 'get_coupon_data-' . $coupon['id'] ] = true;
						VIWEBPOS_Frontend_Bill_Of_Sale::get_coupon_data( $coupons1, $coupon['id'] );
						$old_usage_count = intval( $coupon['usage_count'] ?? 0 );
						$new_usage_count = intval( $coupons1[ $coupon['id'] ]['usage_count'] ?? 0 );
						if ( $old_usage_count === $new_usage_count ) {
							$coupons1[ $coupon['id'] ]['usage_count'] = $new_usage_count + 1;
						}
						unset( self::$cache[ 'get_coupon_data-' . $coupon['id'] ] );
					}
				}
				$result['coupons'] = $coupons1;
			}
			$result['orders']       = $orders;
			$result['transactions'] = $transactions;
			$prefix                 = array( 'products', 'orders', 'customers', 'transactions', 'coupons' );
			foreach ( $prefix as $type ) {
				$result['data_prefix'][ $type ] = self::$settings::get_data_prefix( $type );
			}
			wp_send_json( $result );
		} catch ( Exception $e ) {
			if ( ! empty( $order ) && $order instanceof WC_Order ) {
				$order->get_data_store()->release_held_coupons( $order );
				do_action( 'woocommerce_checkout_order_exception', $order );
			}
			$result['status']  = 'error';
			$result['message'] = $e->getMessage();
			wp_send_json( $result );
		}
	}

	public static function create_order_line_items( &$order, $cart_items ) {
		$count               = 0;
		$product_qty_in_cart = array();
		foreach ( $cart_items as $values ) {
			$product_id = ! empty( $values['variation_id'] ) ? $values['variation_id'] : ( $values['product_id'] ?? 0 );
			if ( ! $product_id || $product_id === 'custom' ) {
				continue;
			}
			$product                                                    = wc_get_product( $product_id );
			$product_qty_in_cart[ $product->get_stock_managed_by_id() ] = isset( $quantities[ $product->get_stock_managed_by_id() ] ) ? $product_qty_in_cart[ $product->get_stock_managed_by_id() ] + floatval( $values['quantity'] ) : floatval( $values['quantity'] );
		}
		foreach ( $cart_items as $values ) {
			$product_id = ! empty( $values['variation_id'] ) ? $values['variation_id'] : ( $values['product_id'] ?? 0 );
			if ( ! $product_id ) {
				continue;
			}
			$arg = array(
				'order'        => $order,
				'subtotal'     => $values['line_subtotal'] ?? 0,
				'total'        => $values['line_total'] ?? 0,
				'subtotal_tax' => $values['line_subtotal_tax'] ?? 0,
				'total_tax'    => $values['line_tax'] ?? 0,
				'taxes'        => $values['line_tax_data'] ?? array( 'total' => array(), 'subtotal' => array() ),
			);
			if ( $product_id !== 'custom' ) {
				$product = wc_get_product( $product_id );
				if ( ! $product || ! $product->exists() || 'trash' === $product->get_status() ) {
					/* translators: %1s: product name or product id */
					throw new Exception( sprintf( esc_html__( '%1s is no longer available', 'webpos-point-of-sale-for-woocommerce' ), $values['data']['name'] ? esc_html( $values['data']['name'] ) : esc_html( $product_id ) ) );
				}
				if ( 'variable' === $product->get_type() ) {
					/* translators: %1s: product name */
					throw new Exception( sprintf( esc_html__( '%1s is a variable product parent and cannot be added.', 'webpos-point-of-sale-for-woocommerce' ), wp_kses_post( $product->get_name() ) ) );
				}
				if ( ! $product->is_in_stock() ) {
					/* translators: %1s: product name */
					throw new Exception( sprintf( esc_html__( 'Sorry, %1s is not in stock.', 'webpos-point-of-sale-for-woocommerce' ), wp_kses_post( $product->get_name() ) ) );
				}
				if ( $product->managing_stock() && ! $product->backorders_allowed() ) {
					$held_stock     = wc_get_held_stock_quantity( $product, $order->get_id() );
					$required_stock = $product_qty_in_cart[ $product->get_stock_managed_by_id() ];
					if ( apply_filters( 'woocommerce_cart_item_required_stock_is_not_enough', $product->get_stock_quantity() < ( $held_stock + $required_stock ), $product, $values ) ) {
						/* translators: 1: product name 2: quantity in stock */
						throw new Exception( sprintf( esc_html__( 'Sorry, we do not have enough "%1$s" in stock to fulfill this order (%2$s available).', 'webpos-point-of-sale-for-woocommerce' ),
							wp_kses_post( $product->get_name() ), wp_kses_post( wc_format_stock_quantity_for_display( $product->get_stock_quantity() - $held_stock, $product ) ) ) );
					}
				}
				$arg['variation'] = $values['variation'] ?? array();
			} else {
				$product = $values['data'];
				if ( empty( $product['name'] ) ) {
					throw new Exception( esc_html__( 'The custom product must have a name.', 'webpos-point-of-sale-for-woocommerce' ) );
				}
				if ( empty( $arg['name'] ) ) {
					$arg['name'] = $product['name'];
				}
			}
			$qty              = wc_stock_amount( $values['quantity'] ?? 1 );
			$validation_error = new WP_Error();
			$validation_error = apply_filters( 'viwebpos_add_order_item_validation', $validation_error, $product, $order, $qty, $values );
			if ( $validation_error->get_error_code() ) {
				throw new Exception( sprintf( '%s: %s', esc_html__( 'Error', 'webpos-point-of-sale-for-woocommerce' ), wp_kses_post( $validation_error->get_error_message() ) ) );
			}
			$item_id = $order->add_product( $product_id !== 'custom' ? $product : '', $qty, $arg );
			if ( $item_id ) {
				$count ++;
				if ( ! empty( $values['item_note'] ) ) {
					$item = $order->get_item( $item_id );
					$item->add_meta_data( 'line_item_note', $values['item_note'], true );
					$item->save();
				}
			}
		}
		if ( ! $count ) {
			throw new Exception( esc_html__( 'No item add to order!', 'webpos-point-of-sale-for-woocommerce' ) );
		}
	}

	public static function create_order_fee_lines( &$order, $cart_fees = array() ) {
		foreach ( $cart_fees as $fee_key => $fee ) {
			if ( empty( $fee['name'] ) ) {
				continue;
			}
			$item = new WC_Order_Item_Fee();
			$item->set_props(
				array(
					'name'      => $fee['name'],
					'tax_class' => ! empty( $fee['taxable'] ) ? ( $fee['tax_class'] ?? '' ) : 0,
					'amount'    => $fee['amount'] ?? 0,
					'total'     => $fee['total'] ?? 0,
					'total_tax' => $fee['tax'] ?? 0,
					'taxes'     => array(
						'total' => $fee['tax_data'] ?? array(),
					),
				)
			);
			do_action( 'woocommerce_checkout_create_order_fee_item', $item, $fee_key, $fee, $order );
			$order->add_item( $item );
		}
	}

	public static function is_valid_coupon( $coupon ) {
		$coupon_id = $coupon->get_id();
		if ( ! $coupon_id && ! $coupon->get_virtual() ) {
			/* translators: %s: coupon code */
			throw new Exception( sprintf( esc_html__( 'Coupon "%1s" does not exist!', 'webpos-point-of-sale-for-woocommerce' ), esc_html( $coupon->get_code() ) ), 105 );
		}
		$usage_limit = $coupon->get_usage_limit();
		$user_id     = $_POST['customer']['id'] ?? 0;// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $usage_limit ) {
			$usage_count           = $coupon->get_usage_count();
			$data_store            = $coupon->get_data_store();
			$tentative_usage_count = is_callable( array(
				$data_store,
				'get_tentative_usage_count'
			) ) ? $data_store->get_tentative_usage_count( $coupon->get_id() ) : 0;
			if ( $usage_count + $tentative_usage_count >= $usage_limit ) {
				// Coupon usage limit is reached. Let's show as informative error message as we can.
				if ( 0 === $tentative_usage_count ) {
					// No held coupon, usage limit is indeed reached.
					$error_code = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED;
				} elseif ( $user_id ) {
					$recent_pending_orders = wc_get_orders(
						array(
							'limit'       => 1,
							'post_status' => array( 'wc-failed', 'wc-pending' ),
							'customer'    => $_POST['customer']['id'],// phpcs:ignore WordPress.Security.NonceVerification.Missing
							'return'      => 'ids',
						)
					);
					if ( count( $recent_pending_orders ) > 0 ) {
						// User logged in and have a pending order, maybe they are trying to use the coupon.
						$error_code = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK;
					} else {
						$error_code = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED;
					}
				} else {
					// Maybe this user was trying to use the coupon but got stuck. We can't know for sure (performantly). Show a slightly better error message.
					$error_code = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK_GUEST;
				}
				throw new Exception( wp_kses_post( $coupon->get_coupon_error( $error_code ) ), esc_html( $error_code ) );
			}
		}
		$data_store           = $coupon->get_data_store();
		$usage_limit_per_user = $coupon->get_usage_limit_per_user();
		if ( $user_id && apply_filters( 'woocommerce_coupon_validate_user_usage_limit', $usage_limit_per_user > 0, $user_id, $coupon, null ) && $coupon_id && $data_store ) {
			$usage_count = $data_store->get_usage_by_user_id( $coupon, $user_id );
			if ( $usage_count >= $usage_limit_per_user ) {
				if ( $data_store->get_tentative_usages_for_user( $coupon_id, array( $user_id ) ) > 0 ) {
					$error_message = $coupon->get_coupon_error( WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK );
					$error_code    = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK;
				} else {
					$error_message = $coupon->get_coupon_error( WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED );
					$error_code    = WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED;
				}
				throw new Exception( wp_kses_post( $error_message ), esc_html( $error_code ) );
			}
		}
		if ( $coupon->get_date_expires() && apply_filters( 'woocommerce_coupon_validate_expiry_date', time() > $coupon->get_date_expires()->getTimestamp(), $coupon, null ) ) {
			throw new Exception( esc_html__( 'This coupon has expired.', 'webpos-point-of-sale-for-woocommerce' ), 107 );
		}
		if ( ! apply_filters( 'woocommerce_coupon_is_valid', true, $coupon, null ) ) {
			throw new Exception( esc_html__( 'Coupon is not valid.', 'webpos-point-of-sale-for-woocommerce' ), 100 );
		}
	}
	public static function create_order_coupon_lines( &$order, $coupons ) {
		$applied_coupons            = $coupons['applied_coupons'] ?? array();
		$coupon_discount_totals     = $coupons['coupon_discount_totals'] ?? array();
		$coupon_discount_tax_totals = $coupons['coupon_discount_tax_totals'] ?? array();
		if ( empty( $applied_coupons ) ) {
			return;
		}
		foreach ( array_keys( $applied_coupons ) as $coupon_code ) {
			$check = new WC_Coupon( $coupon_code );
			if ( ! isset( $_POST['pos_create_at'] ) ) {// phpcs:ignore WordPress.Security.NonceVerification.Missing
				self::is_valid_coupon( $check );
			}
			$validation_error = apply_filters( 'viwebpos_add_order_coupon_validation', new WP_Error(), $check, $order );
			if ( $validation_error->get_error_code() ) {
				/* translators: %1s: error message */
				throw new Exception( sprintf( esc_html__( 'Error: %1s', 'webpos-point-of-sale-for-woocommerce' ), wp_kses_post( $validation_error->get_error_message() ) ) );
			}
			$applied_coupons[ $coupon_code ] = $check;
		}
		foreach ( $applied_coupons as $code => $coupon ) {
			$item = new WC_Order_Item_Coupon();
			$item->set_props( array(
				'code'         => $code,
				'discount'     => wc_cart_round_discount( $coupon_discount_totals[ $code ] ?? 0, wc_get_price_decimals() ),
				'discount_tax' => wc_cart_round_discount( $coupon_discount_tax_totals[ $code ] ?? 0, wc_get_price_decimals() )
			) );
			$coupon_data = $coupon->get_data();
			unset( $coupon_data['used_by'] );
			$item->add_meta_data( 'coupon_data', $coupon_data );
			do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $code, $coupon, $order );
			// Add item to order and save.
			$order->add_item( $item );
		}
	}

	public static function create_order_tax_lines( &$order, $totals ) {
		foreach ( array_keys( $totals['cart_contents_taxes'] ?? array() ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				$item = new WC_Order_Item_Tax();
				$item->set_props(
					array(
						'rate_id'      => $tax_rate_id,
						'tax_total'    => $totals['cart_contents_taxes'][ $tax_rate_id ],
						'rate_code'    => WC_Tax::get_rate_code( $tax_rate_id ),
						'label'        => WC_Tax::get_rate_label( $tax_rate_id ),
						'compound'     => WC_Tax::is_compound( $tax_rate_id ),
						'rate_percent' => WC_Tax::get_rate_percent_value( $tax_rate_id ),
					)
				);
				do_action( 'woocommerce_checkout_create_order_tax_item', $item, $tax_rate_id, $order );
				// Add item to order and save.
				$order->add_item( $item );
			}
		}
	}

	public static function viwebpos_get_orders_data($return = false) {
		if (!$return) {
			check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
			if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
				wp_die();
			}
		}
		$result       = array(
			'data' => array(),
			'page' => ''
		);
		$limit        = isset( $_POST['limit'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : 50;
		$limit        = $limit ?: 50;
		$page         = isset( $_POST['page'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['page'] ) ) : 1;
		$order_status = wc_get_order_statuses();
		unset( $order_status['wc-failed'] );
		unset( $order_status['wc-cancelled'] );
		$order_status = array_keys( $order_status );
		$args          = apply_filters( 'viwebpos_query_order_counts', array(
			'status'          => $order_status,
			'limit'           => $limit,
			'page'            => $page,
			'return' => 'ids',
		) );
		if ( $page === 1 ) {
			$args['paginate'] = 1;
			$orders                = wc_get_orders( $args );
			$total_pages           = $orders->max_num_pages;
			$result['total_pages'] = $orders->max_num_pages;
			if ( ! $total_pages ) {
				if ($return){
					return $result;
				}else {
					wp_send_json( $result );
				}
			}
			$ids = $orders->orders;
		}else{
			$ids    = wc_get_orders( $args );
		}
		$orders = array();
		do_action( 'viwebpos_before_get_orders_data' );
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				self::$cache[ 'get_order_data-' . $id ] = true;
				self::get_order_data( $orders, $id );
				unset( self::$cache[ 'get_order_data-' . $id ] );
			}
		}
		$result['data'] = apply_filters('viwebpos_get_orders_data',array_values( $orders ), $ids);
		do_action( 'viwebpos_after_get_orders_data' );
		$result['page'] = $page + 1;
		if ($return){
			return $result;
		}else {
			wp_send_json( $result );
		}
	}
	public static function viwebpos_order_search_data() {
		check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data'   => array(),
			'status' => 'error'
		);
		$ids    = isset( $_POST['order_ids'] ) ? array_map( 'floatval', villatheme_sanitize_fields( wp_unslash( $_POST['order_ids'] ) ) ) : array();
		if ( empty( $ids ) && ! empty( $_POST['search'] ) ) {
			$order_status = wc_get_order_statuses();
			unset( $order_status['wc-failed'] );
			unset( $order_status['wc-cancelled'] );
			$order_status       = array_keys( $order_status );
			$search             = wc_clean( wp_unslash( $_POST['search'] ) );
			$args               = array(
				'status'          => $order_status,
				'posts_per_page'  => isset( $_POST['per_page'] ) ? wc_clean( wp_unslash( $_POST['per_page'] ) ) : 30,
				'return'          => 'ids',
			);
			$order_search_field = ['order_id','order_email'];
			if ( ! empty( $order_search_field ) && is_array( $order_search_field ) ) {
				$args['viwebpos_order_search']        = $search;
				$args['viwebpos_order_search_filter'] = $order_search_field;
				add_filter( 'posts_where_request', array( __CLASS__, 'posts_where_request' ), 10, 2 );
			}
			$args = apply_filters( 'viwebpos_query_orders_data', $args );
			add_action( 'woocommerce_orders_table_query_clauses', array(
				__CLASS__,
				'handle_custom_orders_table_query_clauses'
			), 10, 3 );
			$ids = wc_get_orders( $args );
		}
		$data = array();
		do_action( 'viwebpos_before_get_orders_data' );
		if ( ! empty( $ids ) ) {
			$result['status'] = 'success';
			foreach ( $ids as $id ) {
				self::$cache[ 'get_order_data-' . $id ] = true;
				self::get_order_data( $data, $id );
				unset( self::$cache[ 'get_order_data-' . $id ] );
			}
		}
		$result['data'] = apply_filters('viwebpos_get_orders_data',array_values( $data ), $ids);
		do_action( 'viwebpos_after_get_orders_data' );
		wp_send_json( $result );
	}
	public static function handle_custom_orders_table_query_clauses( $args, $query, $query_vars ) {
		if ( ! empty( $query_vars['viwebpos_order_search'] ) &&
		     ! empty( $query_vars['viwebpos_order_search_filter'] ) &&
		     is_array( $query_vars['viwebpos_order_search_filter'] ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'wc_orders';
			$search     = $wpdb->esc_like( $query_vars['viwebpos_order_search'] );
			$like       = "%$search%";
			$searches   = array();
			foreach ( $query_vars['viwebpos_order_search_filter'] as $column ) {
				switch ( $column ) {
					case 'order_id':
						$searches[] = $wpdb->prepare( "$table_name.id = %s", $search );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;
					case 'order_email':
						$searches[] = $wpdb->prepare( "( $table_name.billing_email LIKE %s )", $like );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;
				}
			}
			$args['where'] .= ' AND (' . implode( ' OR ', $searches ) . ')';
		}
		return $args;
	}
	public static function posts_where_request( $where, $query ) {
		if ( ! empty( $query->query_vars['viwebpos_order_search'] ) &&
		     ! empty( $query->query_vars['viwebpos_order_search_filter'] ) &&
		     is_array( $query->query_vars['viwebpos_order_search_filter'] ) ) {
			global $wpdb;
			$table_name = $wpdb->postmeta;
			$search     = $wpdb->esc_like( $query->query_vars['viwebpos_order_search'] );
			$like       = "%$search%";
			$searches   = array();
			foreach ( $query->query_vars['viwebpos_order_search_filter'] as $column ) {
				switch ( $column ) {
					case 'order_id':
						$searches[] = $wpdb->prepare( "ID = %s", $search );
						break;
					case 'order_email':
						$searches[] = $wpdb->prepare( "( $table_name.meta_key = '_billing_email' AND $table_name.meta_value LIKE %s )", $like );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						break;
				}
			}
			$where .= ' AND (' . implode( ' OR ', $searches ) . ')';
		}

		return $where;
	}

	public static function get_order_data( &$orders, $id ) {
		if ( ! $id ) {
			return;
		}
		$order = wc_get_order( $id );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		do_action( 'viwebpos_before_get_order_data' );
		$pos_total_paid = $order->get_meta( 'pos_total_paid', true );
		$pos_change     = $order->get_meta( 'pos_change', true );
		$pos_cashier_id = $order->get_meta( 'viwebpos_cashier_id', true );
		if ( $pos_cashier_id ) {
			$pos_cashier_name = get_user_by( 'id', $pos_cashier_id );
			if ( $pos_cashier_name ) {
				$pos_cashier_name = $pos_cashier_name->data->display_name;
			} else {
				$pos_cashier_name = esc_html__( 'Cashier', 'webpos-point-of-sale-for-woocommerce' );
			}
		} else {
			$pos_cashier_name = esc_html__( 'Online', 'webpos-point-of-sale-for-woocommerce' );
		}
		$temp = array(
			'id'                        => (int) $id,
			'order_number'              => $order->get_order_number(),
			'created_at'                => sprintf( '%1$s %2$s %3$s', $order->get_date_created()->date_i18n( wc_date_format() ), esc_html__( 'at', 'webpos-point-of-sale-for-woocommerce' ), $order->get_date_created()->date_i18n( wc_time_format() ) ),
			'status'                    => $order->get_status(),
			'currency'                  => $order->get_currency(),
			'refund_total'                     => wc_format_decimal($order->get_total_refunded(),2),
			'refund_total_tax'                     => wc_format_decimal($order->get_total_tax_refunded(),2),
			'refund_total_shipping'                     => wc_format_decimal($order->get_total_shipping_refunded(),2),
			'total'                     => wc_format_decimal( $order->get_total(), 2 ),
			'subtotal'                  => wc_format_decimal( $order->get_subtotal(), 2 ),
			'total_line_items_quantity' => $order->get_item_count(),
			'total_tax'                 => wc_format_decimal( $order->get_total_tax(), 2 ),
			'total_shipping'            => wc_format_decimal( $order->get_shipping_total(), 2 ),
			'cart_tax'                  => wc_format_decimal( $order->get_cart_tax(), 2 ),
			'shipping_tax'              => wc_format_decimal( $order->get_shipping_tax(), 2 ),
			'total_discount'            => wc_format_decimal( $order->get_total_discount(), 2 ),
			'payment_details'           => array(
				'method_id'    => $order->get_payment_method(),
				'method_title' => $order->get_payment_method_title(),
				'paid'         => ! is_null( $order->get_date_paid() ),
				'amount'       => '',
			),
			'billing_address'           => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'company'    => $order->get_billing_company(),
				'address_1'  => $order->get_billing_address_1(),
				'address_2'  => $order->get_billing_address_2(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country(),
				'email'      => $order->get_billing_email(),
				'phone'      => $order->get_billing_phone(),
			),
			'shipping_address'          => array(
				'first_name' => $order->get_shipping_first_name(),
				'last_name'  => $order->get_shipping_last_name(),
				'company'    => $order->get_shipping_company(),
				'address_1'  => $order->get_shipping_address_1(),
				'address_2'  => $order->get_shipping_address_2(),
				'city'       => $order->get_shipping_city(),
				'state'      => $order->get_shipping_state(),
				'postcode'   => $order->get_shipping_postcode(),
				'country'    => $order->get_shipping_country(),
			),
			'cashier_id'                => $pos_cashier_id,
			'cashier_name'              => $pos_cashier_name,
			'type'                      => $pos_cashier_id ? 'online_pos' :'online_shop',
			'note'                      => $order->get_customer_note(),
			'customer_id'               => $order->get_user_id(),
			'email'                     => $order->get_billing_email(),
			'line_items'                => array(),
			'tax_lines'                 => array(),
			'fee_lines'                 => array(),
			'coupon_lines'              => array(),
		);
		if ( ! empty( $temp['cashier_id'] ) ) {
			$pos_payment = array();
			$payments    = VIWEBPOS_Transactions_Table::get_transactions( array( 'where' => "order_id = {$id}" ) );
			if ( is_array( $payments ) ) {
				foreach ( $payments as $payment ) {
					if ( empty( $payment['method'] ) || empty( $payment['in'] ) ) {
						continue;
					}
					$pos_payment[ $payment['method'] ] = array(
						'payment_id' => $payment['method'],
						'paid'       => $payment['in'],
						'change'     => $payment['out'] ?? 0,
					);
				}
			}
			$temp['pos_payment_details'] = ! empty( $pos_payment ) ? $pos_payment : '';
		}
		$temp['pos_update_to_order'] = $order->get_meta( 'viwebpos_update_to_order', true ) ? $order->get_meta( 'viwebpos_update_to_order', true ) : '';
		$temp['pos_total_paid']      = $pos_total_paid ? $pos_total_paid : $temp['total'];
		$temp['pos_change']          = $pos_change ? $pos_change : 0;
		$temp['currency_symbol']     = get_woocommerce_currency_symbol( $temp['currency'] );
		// add line items
		remove_all_filters( 'woocommerce_product_get_image' );
		remove_action( 'wp_get_attachment_image_attributes', 'woodmart_lazy_attributes' );
		foreach ( $order->get_items() as $item_id => $item ) {
			$product         = $item->get_product();
			$product_id      = $item->get_product_id();
			$variation_id    = $item->get_variation_id();
			$product_sku     = is_object( $product ) ? $product->get_sku() : null;
			$item_name       = $item->get_name();
			$variation_array = array();
			if ( $variation_id ) {
				$attr_name_exist = false;
				$meta_data       = $item->get_meta_data();
				$exclude_meta_key =[
					'line_item_note','_bopobb_price', '_bopobb_product_note','_bopobb_parent_id',
					'_vi_wot_order_item_tracking_data','_reduced_stock','_tbds_taobao_order_item_status'
				];
				foreach ( $meta_data as $k => $v ) {
					$v->key = rawurldecode( (string) $v->key );
					if ( in_array($v->key, $exclude_meta_key) || empty( $v->value ) || is_array( $v->value ) ) {
						continue;
					}
					$v->value      = rawurldecode( (string) $v->value );
					$attribute_key = str_replace( 'attribute_', '', $v->key );
					$display_value = wp_kses_post( $v->value );
					if ( taxonomy_exists( $attribute_key ) ) {
						$term = get_term_by( 'slug', $v->value, $attribute_key );
						if ( ! is_wp_error( $term ) && is_object( $term ) && $term->name ) {
							$display_value = $term->name;
							array_push( $variation_array, array( 'attribute_' . sanitize_title( $term->taxonomy ) => $term->slug ) );
						} else {
							array_push( $variation_array, array( $v->key => $v->value ) );
						}
					} else {
						array_push( $variation_array, array( $v->key => $v->value ) );
					}
					if ( wc_is_attribute_in_product_name( $display_value, $item_name ) ) {
						$attr_name_exist = true;
						continue;
					}
					if ( ! $attr_name_exist ) {
						$attr_name_exist = true;
						$item_name       .= ' - ' . $display_value;
					} else {
						$item_name .= ', ' . $display_value;
					}
				}
			}
			$temp['line_items'][] = array(
				'id'              => $item_id,
				'note'            => $item->get_meta( 'line_item_note', true ) ?? '',
				'subtotal'        => wc_format_decimal( $order->get_line_subtotal( $item ), 2 ),
				'total'           => wc_format_decimal( $order->get_line_total( $item ), 2 ),
				'total_tax'       => wc_format_decimal( $order->get_line_tax( $item ), 2 ),
				'price'           => wc_format_decimal( $order->get_item_subtotal( $item ), 2 ),
				'refund_total'           => wc_format_decimal( $order->get_total_refunded_for_item( $item_id ), 2 ),
				'refund_qty'           => $order->get_qty_refunded_for_item($item_id),
				'quantity'        => $item->get_quantity(),
				'tax_class'       => $item->get_tax_class(),
				'name'            => $item_name,
				'product_id'      => $variation_id ?: $product_id,
				'parent_id'       => $variation_id ? $product_id : 0,
				'variation_array' => $variation_id ? $variation_array : '',
				'sku'             => $product_sku,
				'image'           => is_object( $product ) ? $product->get_image() : '',
				'barcode'         => apply_filters( 'viwebpos_get_product_barcode', get_post_meta( $variation_id ?: $product_id, 'viwebpos_barcode', true ), $product_id ),
			);
		}
		// add taxes
		foreach ( $order->get_tax_totals() as $tax_code => $tax ) {
			$temp['tax_lines'][] = array(
				'code'     => $tax_code,
				'title'    => $tax->label,
				'total'    => wc_format_decimal( $tax->amount, 2 ),
				'compound' => (bool) $tax->is_compound,
			);
		}
		// add fees
		foreach ( $order->get_fees() as $fee_item_id => $fee_item ) {
			$temp['fee_lines'][] = array(
				'id'        => $fee_item_id,
				'title'     => $fee_item->get_name(),
				'tax_class' => $fee_item->get_tax_class(),
				'total'     => wc_format_decimal( $order->get_line_total( $fee_item ), 2 ),
				'total_tax' => wc_format_decimal( $order->get_line_tax( $fee_item ), 2 ),
			);
		}
		// add coupons
		foreach ( $order->get_items( 'coupon' ) as $coupon_item_id => $coupon_item ) {
			$coupon_code                          = $coupon_item->get_code();
			$temp['coupon_lines'][ $coupon_code ] = array(
				'id'     => $coupon_item_id,
				'code'   => $coupon_code,
				'amount' => wc_format_decimal( $coupon_item->get_discount(), 2 ),
			);
		}
		$temp['coupon_held_keys']           = $order->get_meta( '_coupon_held_keys' );
		$temp['coupon_held_keys_for_users'] = $order->get_meta( '_coupon_held_keys_for_users' );
		$orders[ $id ]                      = apply_filters('viwebpos_get_order_data',$temp,$order->get_id());
		do_action( 'viwebpos_after_get_order_data' );
	}
}