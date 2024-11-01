<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_DATA {
	protected static $instance=null ,$allow_html = null;
	private $default, $params;

	public function __construct() {
		global $viwebpos_settings;
		$plugins = array(
			'3rd_curcy_widget_style' => 'layout4'
		);
		$general = array(
			'enable'                           => 0,
			'pos_endpoint'                     => 'pos',
			'auto_create_barcode_by_sku'       => 1,
			'atc_custom_pd'                    => 1,
			'update_variation_on_cart'         => 1,
			'pos_order_status'                 => 'wc-completed',
			'pos_send_mail'                    => 'wc_default',
			'maximum_applied_coupons'      => '',
			'payments'                         => array( 'cash' ),
			//receipt
			'receipts'=> array(
				'default' => array(
					'id'                     => 'default',
					'name'                     => 'Default receipt',
					'active'                   => 1,
					'type'                   => 'pos',
					'direction'               => 'ltr',
					'logo'                     => 0,
					'logo_pos'                 => 'left',
					'logo_id'                  => '',
					'logo_width'               => 22,
					'logo_border'              => 50,
					'font_size'                => 13,
					'page_width'               => '120',
					'page_height'              => '170',
					'page_margin'              => '10',
					'contact_info'             => '{site_title}
{address_1}, {city}',
					'bill_title'               => 'BILL OF SALE',
					'bill_title_size'          => 20,
					'footer_message'           => 'THANK YOU AND SEE YOU AGAIN',
					'order_col'                => 1,
					'date_create'              => 1,
					'date_create_label'        => 'Date: ',
					'order_id'                 => 1,
					'order_id_label'           => 'Order: ',
					'cashier'                  => 1,
					'cashier_label'            => 'Cashier: ',
					'customer'                 => 0,
					'customer_label'           => 'Customer: ',
					'customer_display'         => 'fullname',
					'customer_phone'           => 0,
					'customer_phone_label'     => 'Phone: ',
					'customer_address'         => 0,
					'customer_address_label'   => 'Address: ',
					'customer_address_display' => '{address_line_1}, {city} {country}',
					'product_temp'             => 'block',
					'barcode'                  => 0,
					'barcode_label'            => 'Barcode',
					'product_note'             => 0,
					'product_note_label'       => '',
					'product_id'               => 0,
					'product_id_label'         => 'ID',
					'product_sku'               => 0,
					'product_sku_label'         => 'SKU',
					'product_price'            => 1,
					'product_price_label'      => 'Price',
					'product_quantity'         => 1,
					'product_quantity_label'   => 'Qty',
					'product_subtotal'         => 1,
					'product_subtotal_label'   => 'Total',
					'product_label'            => 'Product name',
					'product_character'        => 0,
					'product_variation'        => 1,
					'order_note'               => 0,
					'order_note_label'         => 'NOTE: ',
					'order_total'              => 1,
					'order_total_label'        => 'TOTAL PRICE',
					'order_tax'                => 1,
					'order_tax_label'          => 'TOTAL TAX',
					'order_ship'               => 0,
					'order_ship_label'         => 'SHIPPING',
					'order_discount'           => 1,
					'order_discount_label'     => 'COUPON',
					'order_pos_discount'       => 1,
					'order_pos_discount_label' => 'POS DISCOUNT',
					'order_paid'               => 1,
					'order_paid_label'         => 'PAID',
					'order_change'             => 1,
					'order_change_label'       => 'CHANGE',
				),
			),
		);
		$this->default = array_merge( $general,  $plugins);
		if ( ! $viwebpos_settings ) {
			$viwebpos_settings = get_option( 'viwebpos_params', array() );
			if (isset($viwebpos_settings['receipt_logo']) ){
				$receipt_default = $this->default['receipts']['default'];
				foreach ($this->default['receipts']['default'] as $k => $v){
					if (isset($viwebpos_settings['receipt_'.$k])){
						$receipt_default[$k] = $viwebpos_settings['receipt_'.$k];
						unset($viwebpos_settings['receipt_'.$k]);
					}
				}
				update_option('viwebpos_params',$viwebpos_settings );
				update_option('viwebpos_receipts_params',['default' => $receipt_default] );
			}
			$viwebpos_settings = array_merge($viwebpos_settings,[
				'receipts' => get_option( 'viwebpos_receipts_params',$this->default['receipts']),
			]);
		}
		$this->params  = apply_filters( 'viwebpos_params', wp_parse_args( $viwebpos_settings, $this->default ) );
	}

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Used to escape html content
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public static function kses_post( $content ) {
		if ( self::$allow_html === null ) {
			self::$allow_html = wp_kses_allowed_html( 'post' );
			self::$allow_html = self::filter_allowed_html( self::$allow_html );
		}

		return wp_kses( $content, self::$allow_html );
	}
	/**
	 * @param $tags
	 *
	 * @return array
	 */
	public static function filter_allowed_html( $tags ) {
		$tags = array_merge_recursive( $tags, array(
				'input'  => array(
					'type'         => 1,
					'id'           => 1,
					'name'         => 1,
					'class'        => 1,
					'placeholder'  => 1,
					'autocomplete' => 1,
					'style'        => 1,
					'value'        => 1,
					'size'         => 1,
					'checked'      => 1,
					'disabled'     => 1,
					'readonly'     => 1,
					'data-*'       => 1,
				),
				'form'   => array(
					'method' => 1,
					'id'     => 1,
					'class'  => 1,
					'action' => 1,
					'data-*' => 1,
				),
				'select' => array(
					'id'       => 1,
					'name'     => 1,
					'class'    => 1,
					'multiple' => 1,
					'data-*'   => 1,
				),
				'option' => array(
					'value'    => 1,
					'selected' => 1,
					'data-*'   => 1,
				),
				'style'  => array(
					'id'    => 1,
					'class' => 1,
					'type'  => 1,
				),
				'source'  => array(
					'type'    => 1,
					'src' => 1
				),
				'video'  => array(
					'width'    => 1,
					'height'    => 1,
					'src' => 1
				),
			)
		);
		foreach ( $tags as $key => $value ) {
			if ( $key === 'input' ) {
				$tags[ $key ]['data-*']   = 1;
				$tags[ $key ]['checked']  = 1;
				$tags[ $key ]['disabled'] = 1;
				$tags[ $key ]['readonly'] = 1;
			} elseif ( in_array( $key, array( 'div', 'span', 'a', 'form', 'select', 'option', 'tr', 'td' ) ) ) {
				$tags[ $key ]['data-*'] = 1;
			}
		}

		return $tags;
	}

	public static function set_data_prefix( $type = '', $value = '' ) {
		if ( ! $type ) {
			return;
		}
		update_option( 'viwebpos_' . $type . '_prefix', $value ?: substr( md5( date( "YmdHis" ) ), 0, 10 ) );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
	}

	public static function get_data_prefix( $type = 'products' ) {
		$date   = date( "Ymd" );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
		$prefix = get_option( 'viwebpos_' . $type . '_prefix', $date );

		return 'viwebpos_' . $type . $prefix;
	}

	public static function data_prefix_exist( $type = 'products' ) {
		return ! $type ? '' : get_option( 'viwebpos_' . $type . '_prefix', '' );
	}

	public static function set( $name = '', $prefix = 'viwebpos-' ) {
		if ( is_array( $name ) ) {
			return implode( ' ', array_map( array( __CLASS__, 'set' ), $name ) );
		} else {
			return $prefix . $name;
		}
	}

	public function get_default( $name = "" ) {
		if ( ! $name ) {
			return $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			return apply_filters( 'viwebpos_params_default-' . $name, $this->default[ $name ] );
		} else {
			return false;
		}
	}

	public function get_params( $name = "" ) {
		if ( ! $name ) {
			return $this->params;
		}
		if ( isset( $this->params[ $name ] ) ) {
			return apply_filters( 'viwebpos_params-' . $name, $this->params[ $name ] );
		}

		return false;
	}

	public function get_current_setting( $name = "", $i = 0, $default = false ) {
		if ( empty( $name ) ) {
			return false;
		}
		if ( $default !== false ) {
			$result = $this->get_params( $name )[ $i ] ?? $default;
		} else {
			$result = $this->get_params( $name )[ $i ] ?? $this->get_default( $name )[0] ?? false;
		}

		return $result;
	}

	public function get_current_setting_by_subtitle( $name = "",$subtitle = "", $i = 0, $default = false ) {
		if ( empty( $name ) ) {
			return false;
		}
		if ( $default !== false ) {
			$result = $this->get_current_setting( $name, $subtitle )[ $i ] ?? $default;
		} else {
			$result = $this->get_current_setting( $name, $subtitle )[ $i ] ?? false;
		}

		return apply_filters( 'viwebpos_get_current_setting_by_subtitle',$result, $name,$subtitle, $i);
	}
}