<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Print_Receipt {
	protected static $instance = null;

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function get_formatted_item_price( $order, $item, $tax_display = '' ) {
		if ( ! isset( $item['line_subtotal'] ) || ! isset( $item['line_subtotal_tax'] ) ) {
			return '';
		}
		if ( ! $tax_display ) {
			$tax_display = get_option( 'woocommerce_tax_display_cart' );
		}
		$order_currency = ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) ? $order->get_currency() : $order->get_order_currency();
		if ( 'excl' === $tax_display ) {
			if ( version_compare( get_option( 'woocommerce_version' ), '3.0.0', '>=' ) ) {
				$ex_tax_label = wc_prices_include_tax() ? 1 : 0;
			} else {
				$ex_tax_label = $order->prices_include_tax ? 1 : 0;
			}
			$subtotal = VIWEBPOS_Plugins_Curcy::curcy_wc_price(
				$order->get_item_subtotal( $item ),
				array(
					'ex_tax_label' => $ex_tax_label,
					'currency'     => $order_currency,
				)
			);
		} else {
			$subtotal = VIWEBPOS_Plugins_Curcy::curcy_wc_price( $order->get_item_subtotal( $item, true ), array( 'currency' => $order_currency ) );
		}

		return apply_filters( 'viwebpos_formatted_item_price', $subtotal, $item, $order );
	}

	public static function print_receipt() {
		$result = array(
			'status'   => 'error',
			'messages' => '',
			'html'     => '',
		);
		try {
			if ( ! apply_filters( 'viwebpos_print_receipt_role', current_user_can( 'cashier' ) ) ) {
				throw new Exception( esc_html__( 'Lack of permission to print receipts.', 'webpos-point-of-sale-for-woocommerce' ) );
			}
			if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
				$result['messages'] = esc_html__( 'We were unable to print the receipt, please reload to try again.', 'webpos-point-of-sale-for-woocommerce' );
				wp_send_json( $result );
			}
			$template     = isset( $_REQUEST['receipt_template'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['receipt_template'] ) ) : '';
			$order_ids    = isset( $_GET['order_ids'] ) ? wc_clean( wp_unslash( $_GET['order_ids'] ) ) : '';
			$call         = 'render_receipt_html';
			$receipt_html = method_exists( __CLASS__, $call ) ? self::$call( $order_ids, $template ) : '';
			if ( is_a( $receipt_html, 'Exception' ) ) {
				throw $receipt_html;
			} elseif ( $receipt_html ) {
				$result['status'] = 'success';
				$result['html']   = $receipt_html;
			}
		} catch ( Exception $e ) {
			$result['messages'] = $e->getMessage();
		}
		if ( $result['status'] === 'error' && ! $result['messages'] ) {
			$result['messages'] = esc_html__( 'We were unable to print the receipt, please reload to try again.', 'webpos-point-of-sale-for-woocommerce' );
		}
		wp_send_json( $result );
	}

	public static function render_receipt_html( $order_ids, $template = '', $echo = false ) {
		if ( ! is_array( $order_ids ) ) {
			$order_ids = explode( ',', trim( $order_ids ) );
		}
		if ( empty( $order_ids ) ) {
			if ( ! $echo ) {
				return new Exception( esc_html__( 'order_id can not be empty', 'webpos-point-of-sale-for-woocommerce' ) );
			}

			return;
		}
		$orders = wc_get_orders( [
			'posts_per_page' => - 1,
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'post__in'       => array_unique( $order_ids ),
		] );
		if ( empty( $orders ) ) {
			if ( ! $echo ) {
				return new Exception( esc_html__( 'Can not find the orders', 'webpos-point-of-sale-for-woocommerce' ) );
			}

			return;
		}
		$receipt_args = array(
			'orders'   => $orders,
			'template' => $template
		);
		if ( ! $echo ) {
			return wc_get_template_html( 'viwebpos-receipt.php', $receipt_args,
				'webpos-woocommerce-pos-point-of-sale' . DIRECTORY_SEPARATOR,
				VIWEBPOS_TEMPLATES . DIRECTORY_SEPARATOR );
		} else {
			wc_get_template( 'viwebpos-receipt.php', $receipt_args,
				'webpos-woocommerce-pos-point-of-sale' . DIRECTORY_SEPARATOR,
				VIWEBPOS_TEMPLATES . DIRECTORY_SEPARATOR );
		}
	}

	public static function receipt_design( $templates ) {
		if ( ! empty( $templates ) ) {
			foreach ( $templates as $template ) {
				self::render_receipt_style( $template );
			}
		}
		?>
        <style id="viwebpos-receipt-inline-css"><?php load_template( VIWEBPOS_DIR . '/assets/css/pos_receipt.min.css' ) ?></style>
		<?php
	}

	public static function render_receipt_style( $template ) {
		$template_id = $template['id'] ?? '';
		if ( ! $template_id ) {
			return;
		}
		$font_size  = $template['font_size'] ?? 13;
		$page_width = $template['page_width'] ?? 120;
		?>
        <style id="<?php echo esc_attr( "viwebpos-receipt-template-{$template_id}-inline-css" ) ?>">
            <?php
            if (!empty($template['custom_css'])){
                echo wp_kses_post($template['custom_css']);
            }
            printf('.viwebpos-bill-content.viwebpos-bill-content-%s{font-size:%spx;width:%smm;}', esc_attr( $template_id ), esc_attr( $font_size ), esc_attr( $page_width ) );
            printf('.viwebpos-bill-content.viwebpos-bill-content-%s .viwebpos-bill-content-inner{padding:%smm;}', esc_attr( $template_id ), $template['page_margin'] ? esc_attr( $template['page_margin'] ) : 10);
            printf('.viwebpos-bill-content.viwebpos-bill-content-%s .viwebpos-bill-content-inner .viwebpos-bill-title{font-size:%spx;}', esc_attr( $template_id ), $template['bill_title_size'] ? esc_attr( $template['bill_title_size'] ) : 20);
            printf('.viwebpos-bill-content.viwebpos-bill-content-%s .viwebpos-bill-body table{font-size:%spx;}', esc_attr( $template_id ), esc_attr( $font_size ));
            printf('.viwebpos-bill-content.viwebpos-bill-content-%s .viwebpos-bill-logo-preview img{width:%smm,border-radius:%s}', esc_attr( $template_id ), $template['logo_width'] ? esc_attr( $template['logo_width'] ) : 22, ($template['logo_border'] ? esc_attr( $template['logo_border'] ) : 50).'%');
             ?>
        </style>
		<?php
	}
}
