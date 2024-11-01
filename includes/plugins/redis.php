<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class VIWEBPOS_Plugins_Redis {
	public static $settings, $pd_enable, $cart_enable, $cache=[];
	public function __construct() {
		if ( ! is_plugin_active('redis-woo-dynamic-pricing-and-discounts/redis-woo-dynamic-pricing-and-discounts.php') ) {
			return;
		}
		self::$settings = VIREDIS_DATA::get_instance();
		self::$pd_enable = self::$settings->get_params( 'pd_enable' );
		self::$cart_enable = self::$settings->get_params( 'cart_enable' );
		if (!apply_filters('viwebpos_redis_enable', self::$pd_enable || self::$cart_enable )) {
			return;
		}
		add_filter('viredis_get_product_qty_in_cart',array($this,'viredis_get_product_qty_in_cart'),10,3);
		add_filter('viredis_may_be_apply_to_user',array($this,'viredis_may_be_apply_to_user'),10,4);
		add_filter('viredis_may_be_apply_to_cart',array($this,'viredis_may_be_apply_to_cart'),10,7);
		add_filter('viwebpos_set_ajax_events',array($this,'viwebpos_set_ajax_events'),10,1);
		add_action( 'viwebpos_before_enqueue_scripts', array( $this, 'viwebpos_before_enqueue_scripts' ) );
		add_filter('viwebpos_frontend_params',array($this,'viwebpos_frontend_params'), 99, 1);
	}
	public static function get_current_discount($rules, $wc_cart_data, $cart_subtotal){
		if ( empty( $rules ) || empty($wc_cart_data) || ! $cart_subtotal ) {
			return false;
		}
		$current_discounts = array();
		foreach ( $rules as $rule_id => $params ) {
			$type           = $params['type'] ?? 0;
			$discount_value = $params['discount_value'] ?? 0;
			$discount_value = VIREDIS_Frontend_Product::get_fixed_discount_value( $type, $discount_value, $cart_subtotal );
			if ( ! $discount_value ) {
				continue;
			}
			$current_discounts[ $rule_id ] = $discount_value;
		}
		if ( empty( $current_discounts ) ) {
			return false;
		}
		return $current_discounts;
	}
	public static function get_cart_rules(){
		$available_rule_ids = self::$settings->get_params( 'cart_id' );
		if ( empty( $available_rule_ids ) ) {
			return false;
		}
		$rules = array();
		foreach ( $available_rule_ids as $i => $id ) {
			if ( ! self::$settings->get_current_setting( 'cart_active', $i, '' ) ) {
				continue;
			}
			if ( ! isset( self::$cache['may_be_apply_to_time'][ $id ] ) ) {
				$from                                       = self::$settings->get_current_setting( 'cart_from', $i );
				$to                                         = self::$settings->get_current_setting( 'cart_to', $i );
				$from                                       = $from ? strtotime( $from ) + villatheme_convert_time( self::$settings->get_current_setting( 'cart_from_time', $i ) ) : '';
				$to                                         = $to ? strtotime( $to ) + villatheme_convert_time( self::$settings->get_current_setting( 'cart_to_time', $i ) ) : '';
				$time_conditions                            = array(
					'days'  => self::$settings->get_current_setting( 'pd_day', $id, array() ),
					'start' => $from,
					'end'   => $to,
				);
				self::$cache['may_be_apply_to_time'][ $id ] = VIREDIS_Frontend_Product::may_be_apply_to_time( $time_conditions );
			}
			if ( ! self::$cache['may_be_apply_to_time'][ $id ] ) {
				continue;
			}
			if ( ! isset( self::$cache['may_be_apply_to_user'][ $id ] ) ) {
				$user_rule_type  = self::$settings->get_current_setting( 'cart_user_rule_type', $id, array() );
				$user_conditions = array();
				if ( ! empty( $user_rule_type ) ) {
					foreach ( $user_rule_type as $type ) {
						$user_conditions[ $type ] = self::$settings->get_current_setting( 'cart_user_rule_' . $type, $id, $type === 'logged' ? '' : array() );
					}
				}
				self::$cache['may_be_apply_to_user'][ $id ] = VIREDIS_Frontend_Product::may_be_apply_to_user( $id, $user_conditions, true );
			}
			if ( ! self::$cache['may_be_apply_to_user'][ $id ] ) {
				continue;
			}
			if ( ! isset( self::$cache['may_be_apply_to_cart'][ $id ] ) ) {
				$cart_rule_type  = self::$settings->get_current_setting( 'cart_cart_rule_type', $id, array() );
				$cart_conditions = array();
				if ( ! empty( $cart_rule_type ) ) {
					foreach ( $cart_rule_type as $type ) {
						switch ( $type ) {
							case 'cart_subtotal':
								$cart_conditions[ $type ] = array(
									'subtotal_min' => self::$settings->get_current_setting( 'cart_cart_rule_subtotal_min', $id, 0 ),
									'subtotal_max' => self::$settings->get_current_setting( 'cart_cart_rule_subtotal_max', $id, '' )
								);
								break;
							case 'count_item':
								$cart_conditions[ $type ] = array(
									'count_item_min' => self::$settings->get_current_setting( 'cart_cart_rule_count_item_min', $id, 0 ),
									'count_item_max' => self::$settings->get_current_setting( 'cart_cart_rule_count_item_max', $id, '' )
								);
								break;
							case 'qty_item':
								$cart_conditions[ $type ] = array(
									'qty_item_min' => self::$settings->get_current_setting( 'cart_cart_rule_qty_item_min', $id, 0 ),
									'qty_item_max' => self::$settings->get_current_setting( 'cart_cart_rule_qty_item_max', $id, '' )
								);
								break;
							default:
								$cart_conditions[ $type ] = self::$settings->get_current_setting( 'cart_cart_rule_' . $type, $id, array() );
						}
					}
				}
				self::$cache['may_be_apply_to_cart'][ $id ] = VIREDIS_Frontend_Product::may_be_apply_to_cart( $id, $cart_conditions, '', '', '', true );
			}
			if ( ! self::$cache['may_be_apply_to_cart'][ $id ] ) {
				continue;
			}
			$temp         = array(
				'title'          => self::$settings->get_current_setting( 'cart_discount_title', $i, '' ),
				'type'           => self::$settings->get_current_setting( 'cart_discount_type', $i, 0 ),
				'discount_value' => self::$settings->get_current_setting( 'cart_discount_value', $i, 0 ),
			);
			$rules[ $id ] = $temp;
			if ( VIREDIS_Frontend_Cart::$apply_rule_type ) {
				break;
			}
			$apply_type = self::$settings->get_current_setting( 'cart_apply', $i, 1 );
			if ( ! empty( $rules ) ) {
				if ( ! $apply_type ) {
					$rules = array( $id => $temp );
					break;
				} elseif ( $apply_type === '1' ) {
					continue;
				}
			} else {
				if ( ! $apply_type || $apply_type === '1' ) {
					$rules = array( $id => $temp );
					break;
				}
			}
			if ( $apply_type === '2' ) {
				$rules[ $id ] = $temp;
			}
		}
		return $rules;
	}
	public static function get_cart_discount(){
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX || !isset(self::$cache['viwebpos_redis_data'])){
			return false;
		}
		$wc_cart_empty = empty(self::$cache['viwebpos_redis_data']['cart_data']);
		$wc_cart_data = $wc_cart_empty ? array(): self::$cache['viwebpos_redis_data']['cart_data'];
		if ($wc_cart_empty || empty($wc_cart_data) ){
			return false;
		}
		$rules = self::get_cart_rules();
		if ( empty( $rules ) ) {
			return false;
		}
		$cart_subtotal = self::$cache['cart_subtotal']['pos_cart'] ?? apply_filters( 'viredis_condition_get_cart_subtotal', self::get_cart_subtotal( $wc_cart_data, $wc_cart_empty ), true );
		$current_discounts = self::get_current_discount( $rules, $wc_cart_data, $cart_subtotal );
		if ( empty( $current_discounts ) ) {
			return false;
		}
		$maximum_discount = ! empty( VIREDIS_Frontend_Cart::$maximum_discount['value'] ) ? (float) VIREDIS_Frontend_Cart::$maximum_discount['value'] : '';
		if ( is_numeric( $maximum_discount ) ) {
			$maximum_discount_type = VIREDIS_Frontend_Cart::$maximum_discount['type'] ?? 1;
			$maximum_discount      = apply_filters( 'viredis_cart_get_maximum_discount_value', $maximum_discount_type ? $maximum_discount : $maximum_discount * $cart_subtotal / 100, VIREDIS_Frontend_Cart::$maximum_discount, $cart_subtotal, $wc_cart_data );
		}
		$result = array();
		$taxable = '';
		$tax_class = '';
		if ( self::$settings->get_params( 'cart_combine_all_discount' ) ) {
			$current_discount = array_sum( $current_discounts );
			$current_discount = is_numeric( $maximum_discount ) && $current_discount > $maximum_discount ? $maximum_discount : $current_discount;
			$result['viredis_cart_discount']=array(
				'title' => self::$settings->get_params( 'cart_combine_all_discount_title' ),
				'amount' => ( - 1 ) * $current_discount,
				'taxable'   => $taxable,
				'tax_class' => $tax_class,
			);
		} else {
			foreach ( $current_discounts as $rule_id => $discount ) {
				if ( is_numeric( $maximum_discount ) && $maximum_discount <= 0 ) {
					break;
				}
				if ( is_numeric( $maximum_discount ) ) {
					$current_discount = $discount > $maximum_discount ? $maximum_discount : $discount;
					$maximum_discount -= $current_discount;
				} else {
					$current_discount = $discount;
				}
				$result[$rule_id]=array(
					'title' =>  $rules[ $rule_id ]['title'] ?? '',
					'amount' => ( - 1 ) *$current_discount,
					'taxable'   => $taxable,
					'tax_class' => $tax_class,
				);
			}
		}
		return $result;
	}
	public static function viredis_get_product_qty_in_cart($result, $product_id, $product_qty){
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX ){
			return $result;
		}
		if (!$product_id || empty(self::$cache['viwebpos_redis_data']['cart_data'])){
			return 0;
		}
		$result = 0;
		foreach (self::$cache['viwebpos_redis_data']['cart_data'] as $item){
			$product_id_t = !empty($item['variation_id']) ? $item['variation_id'] : ($item['product_id'] ?? '');
			if ($product_id == $product_id_t){
				$result = (float) $item['quantity'] ?? 0;
				break;
			}
		}
		return $result;
	}
	public static function viredis_may_be_apply_to_user($result,$rule_id, $conditions, $is_cart){
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX ){
			return $result;
		}
		if (  ! $rule_id || !isset(self::$cache['viwebpos_redis_data'])  ) {
			return false;
		}
		if ( empty( $conditions ) ) {
			return true;
		}
		$customer_id = self::$cache['viwebpos_redis_data']['customer_id'] ?? 0;
		$current_user = get_user_by('ID', $customer_id );
		$customer_id_t = $current_user ? $customer_id : 'guest';
		if ( isset( self::$cache['may_be_apply_to_user'][ $rule_id ][$customer_id_t] ) ) {
			return self::$cache['may_be_apply_to_user'][ $rule_id ][$customer_id_t];
		}
		$session_name = 'viredis_may_be_apply_to_user'. $is_cart ?'_cart':'';
		$session_cache        = WC()->session->get($session_name , array() );
		$session_cache_prefix = $session_cache['prefix'] ?? '';
		$prefix               = self::$settings::get_data_prefix($is_cart ? 'cart' :'');
		if ( $prefix && $session_cache_prefix !== $prefix ) {
			$session_cache = array( 'prefix' => $prefix );
		}
		if ( ! empty( $session_cache[ $rule_id ] ) && ( $session_cache[ $rule_id ]['conditions'] ?? array() === $conditions ) && isset($session_cache[ $rule_id ][$customer_id_t]) ) {
			return self::$cache['may_be_apply_to_user'][ $rule_id ][$customer_id_t] = $session_cache[ $rule_id ][$customer_id_t] ?: false;
		}
		$result = true;
		$order_status   = $conditions['order_status'] ?? '';
		$orders_check   = array();
		$is_logged_user = $current_user ? true : false;
		foreach ( $conditions as $type => $params ) {
			switch ( $type ) {
				case 'logged':
					if ( $params && ! $is_logged_user ) {
						$result = false;
						break;
					}
					if ( ! $params && $is_logged_user ) {
						$result = false;
					}
					break;
				case 'user_role_include':
					if ( is_array( $params ) && count( $params ) &&
					     (!$is_logged_user || ! count( array_intersect( VIREDIS_Frontend_Product::get_user_allcaps($current_user, $customer_id ?: 0), $params ) )) ) {
						$result = false;
					}
					break;
				case 'user_role_exclude':
					if ( is_array( $params ) && count( $params ) && $is_logged_user &&
					     count( array_intersect( VIREDIS_Frontend_Product::get_user_allcaps($current_user, $customer_id ?: 0), $params ) ) ) {
						$result = false;
					}
					break;
				case 'user_include':
					if ( is_array( $params ) && count( $params ) && ! in_array( $current_user->ID, $params ) ) {
						$result = false;
					}
					break;
				case 'user_exclude':
					if ( is_array( $params ) && count( $params ) && in_array( $current_user->ID, $params ) ) {
						$result = false;
					}
					break;
				case 'order_status':
					if ( ! empty( $orders_check ) ) {
						break;
					}
					if ( $orders_check === false ) {
						$result = false;
						break;
					}
					$args           = array(
						'order_count',
						'order_total',
						'last_order',
						'product_include',
						'product_exclude',
						'cats_include',
						'cats_exclude',
					);
					$check_continue = false;
					foreach ( $args as $item ) {
						if ( isset( $conditions[ $item ] ) ) {
							$check_continue = true;
							break;
						}
					}
					if ( $check_continue ) {
						break;
					}
					if ( is_array( $orders_check ) && ! count( $orders_check ) ) {
						$orders_check  = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID );
					}
					if ( $orders_check === false ) {
						$result = false;
					}
					break;
				case 'order_count':
					$params_from = $params['from'] ?? array();
					if ( $params_from && is_array( $params_from ) && count( $params_from ) ) {
						foreach ( $params_from as $type_k => $type_v ) {
							$params_to = $params['to'][ $type_k ] ?? '';
							if ( $params_to && $type_v && strtotime( $type_v ) > strtotime( $params_to ) ) {
								continue;
							}
							$params_min = $params['min'][ $type_k ] ?? 0;
							$params_max = $params['max'][ $type_k ] ?? '';
							$params_min = floatval( $params_min ?: 0 );
							$params_max = $params_max ? floatval( $params_max ) : '';
							if ( $params_max === '' && ! $params_min ) {
								continue;
							}
							if ( is_numeric( $params_max ) && $params_max < $params_min ) {
								continue;
							}
							$tmp_orders = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID, $type_v, $params_to );
							if ( empty( $tmp_orders ) || !is_array($tmp_orders)) {
								$result = false;
								break;
							}
							$order_count = count($tmp_orders);
							if ( $params_min && $params_min > $order_count ) {
								$result = false;
								break;
							}
							if ( is_numeric( $params_max ) && $params_max < $order_count ) {
								$result = false;
								break;
							}
						}
					}
					break;
				case 'order_total':
					$params_from = $params['from'] ?? array();
					if ( $params_from && is_array( $params_from ) && count( $params_from ) ) {
						foreach ( $params_from as $type_k => $type_v ) {
							$params_to = $params['to'][ $type_k ] ?? '';
							if ( $params_to && $type_v && strtotime( $type_v ) > strtotime( $params_to ) ) {
								continue;
							}
							$params_min = $params['min'][ $type_k ] ?? 0;
							$params_max = $params['max'][ $type_k ] ?? '';
							$params_min = floatval( $params_min ?: 0 );
							$params_max = $params_max ? floatval( $params_max ) : '';
							if ( $params_max === '' && ! $params_min ) {
								continue;
							}
							if ( is_numeric( $params_max ) && $params_max < $params_min ) {
								continue;
							}
							$tmp_orders = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID, $type_v, $params_to );
							if ( empty( $tmp_orders ) ) {
								$result = false;
								break;
							}
							$order_total = 0;
							foreach ($tmp_orders as $tmp_order) {
								$order       = wc_get_order( $tmp_order );
								$order_total += $order->get_total( 'edit' );
							}
							if ( $params_min && $params_min > $order_total ) {
								$result = false;
								break;
							}
							if ( is_numeric( $params_max ) && $params_max < $order_total ) {
								$result = false;
								break;
							}
						}
					}
					break;
				case 'last_order':
					$params_type = $params['type'] ?? '';
					$params_date = $params['date'] ?? '';
					if ( ! $params_type || ! $params_date ) {
						break;
					}
					if ( $orders_check === false ) {
						$result = false;
						break;
					}
					if ( $params_type === 'before' ) {
						$tmp_orders = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID, '', $params_date, '', '00:00:00' );
						if ( empty($tmp_orders) ) {
							$result = false;
							break;
						}
						$tmp_orders = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID, $params_date );
						if ( !empty($tmp_orders) ) {
							$result = false;
						}
					} else {
						$tmp_orders = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID, $params_date );
						if ( empty($tmp_orders) ) {
							$result = false;
						}
					}
					break;
				case 'product_include':
					if ( ! is_array( $params ) || ! count( $params ) ) {
						break;
					}
					if ( $orders_check === false ) {
						break;
					}
					if ( is_array( $orders_check ) && ! count( $orders_check ) ) {
						$orders_check = $orders  = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID );
					} else {
						$orders = $orders_check;
					}
					$result = false;
					if ( is_array( $orders ) && count( $orders ) ) {
						foreach ( $orders as $order ) {
							$items = $order->get_items();
							if ( empty( $items ) ) {
								continue;
							}
							foreach ( $items as $item ) {
								$variation_id = $item->get_variation_id() ?? 0;
								if ( $variation_id && in_array( $variation_id, $params ) ) {
									$result = true;
									break;
								}
								$product_id = $item->get_product_id();
								if ( in_array( $product_id, $params ) ) {
									$result = true;
									break;
								}
							}
							if ( $result ) {
								break;
							}
						}
					}
					break;
				case 'product_exclude':
					if ( ! is_array( $params ) || ! count( $params ) ) {
						break;
					}
					if ( $orders_check === false ) {
						break;
					}
					if ( is_array( $orders_check ) && ! count( $orders_check ) ) {
						$orders_check = $orders  = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID );
					} else {
						$orders = $orders_check;
					}
					if ( is_array( $orders ) && count( $orders ) ) {
						foreach ( $orders as $order ) {
							$items = $order->get_items();
							if ( empty( $items ) ) {
								continue;
							}
							foreach ( $items as $item ) {
								$variation_id = $item->get_variation_id() ?? 0;
								if ( $variation_id && in_array( $variation_id, $params ) ) {
									$result = false;
									break;
								}
								$product_id = $item->get_product_id();
								if ( in_array( $product_id, $params ) ) {
									$result = false;
									break;
								}
							}
							if ( ! $result ) {
								break;
							}
						}
					}
					break;
				case 'cats_include':
					if ( ! is_array( $params ) || ! count( $params ) ) {
						break;
					}
					if ( $orders_check === false ) {
						break;
					}
					if ( is_array( $orders_check ) && ! count( $orders_check ) ) {
						$orders_check = $orders  = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID );
					} else {
						$orders = $orders_check;
					}
					$result = false;
					if ( is_array( $orders ) && count( $orders ) ) {
						foreach ( $orders as $order ) {
							$items = $order->get_items();
							if ( empty( $items ) ) {
								continue;
							}
							foreach ( $items as $item ) {
								$product_id = $item->get_product_id();
								$cate_ids   = wc_get_product_cat_ids( $product_id );
								if ( ! empty( $cate_ids ) && count( array_intersect( $cate_ids, $params ) ) ) {
									$result = true;
									break;
								}
							}
							if ( $result ) {
								break;
							}
						}
					}
					break;
				case 'cats_exclude':
					if ( ! is_array( $params ) || ! count( $params ) ) {
						break;
					}
					if ( $orders_check === false ) {
						break;
					}
					if ( is_array( $orders_check ) && ! count( $orders_check ) ) {
						$orders_check = $orders  = VIREDIS_Frontend_Product::get_order_query( $order_status, $current_user->ID );
					} else {
						$orders = $orders_check;
					}
					if ( is_array( $orders ) && count( $orders ) ) {
						foreach ( $orders as $order ) {
							$items = $order->get_items();
							if ( empty( $items ) ) {
								continue;
							}
							foreach ( $items as $item ) {
								$product_id = $item->get_product_id();
								$cate_ids   = wc_get_product_cat_ids( $product_id );
								if ( ! empty( $cate_ids ) && count( array_intersect( $cate_ids, $params ) ) ) {
									$result = false;
									break;
								}
							}
							if ( ! $result ) {
								break;
							}
						}
					}
					break;
			}
			if ( ! $result ) {
				break;
			}
		}
		if (!isset($session_cache[$rule_id])){
			$session_cache[$rule_id] = array();
		}
		$session_cache[$rule_id]['conditions'] = $conditions;
		$session_cache[$rule_id][$customer_id_t] = $result ? 1 : 0;
		WC()->session->set( $session_name, $session_cache );
		return self::$cache['may_be_apply_to_user'][ $rule_id ][$customer_id_t] = $result;
	}
	public static function get_cart_subtotal( $wc_cart_data, $wc_cart_empty ){
		if ( ! $wc_cart_data || $wc_cart_empty ) {
			return 0;
		}
		$result = 0;
		foreach ( $wc_cart_data as $cart_item ) {
			$product       = wc_get_product( !empty($cart_item['variation_id']) ? $cart_item['variation_id'] : ($cart_item['product_id'] ?? 0) );
			if (!$product){
				continue;
			}
			$product->viredis_cart_item = 'viwebpos_'.$cart_item['cart_item_key'];
			$product->viredis_cart_item_qty = $cart_item['quantity'] ?? 1;
			$result += (float) VIREDIS_Frontend_Product::product_get_price_tax( $product, $product->get_price(), $product->viredis_cart_item_qty );
		}
		return apply_filters( 'viredis_get_cart_subtotal', $result );
	}
	public static function viredis_may_be_apply_to_cart($result,$rule_id, $conditions, $product, $product_id, $product_qty, $is_cart){
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX ){
			return $result;
		}
		if ( empty( $conditions ) ) {
			return true;
		}
		if ( !isset(self::$cache['viwebpos_redis_data'])) {
			return false;
		}
		if ( ! $is_cart && ( ! $rule_id || ! $product_id || ! $product || ! is_a( $product, 'WC_Product' ) ) ) {
			return false;
		}
		$wc_cart_empty = empty(self::$cache['viwebpos_redis_data']['cart_data']);
		$wc_cart_data = $wc_cart_empty ? array(): self::$cache['viwebpos_redis_data']['cart_data'];
		foreach ( $conditions as $type => $params ) {
			switch ( $type ) {
				case 'cart_subtotal':
					$subtotal_min = $params['subtotal_min'] ?? 0;
					$subtotal_min = apply_filters( 'viredis_condition_get_cart_subtotal_min', $subtotal_min ? apply_filters( 'viredis_change_3rd_plugin_price', (float) $subtotal_min ) : 0, $rule_id, $params );
					$subtotal_max = $params['subtotal_max'] ?? '';
					$subtotal_max = apply_filters( 'viredis_condition_get_cart_subtotal_max', $subtotal_max ? apply_filters( 'viredis_change_3rd_plugin_price', (float) $subtotal_max ) : '', $rule_id, $params );
					if ( ! $subtotal_min && ! $subtotal_max ) {
						break;
					}
					if ( $wc_cart_empty && ! $product_qty ) {
						$result = false;
						break;
					}
					if ( $is_cart ) {
						if ( ! isset( self::$cache['cart_subtotal']['pos_cart'] ) ) {
							self::$cache['cart_subtotal']['pos_cart'] = apply_filters( 'viredis_condition_get_cart_subtotal', self::get_cart_subtotal( $wc_cart_data, $wc_cart_empty ), true );
						}
						$wc_cart_subtotal = self::$cache['cart_subtotal']['pos_cart'] ?: 0;
					} else {
						if ( ! isset( self::$cache['cart_subtotal']['pos_product'] ) ) {
							self::$cache['cart_subtotal']['pos_product'] = apply_filters( 'viredis_condition_get_cart_subtotal', self::get_cart_subtotal( $wc_cart_data, $wc_cart_empty ), false );
						}
						$wc_cart_subtotal = self::$cache['cart_subtotal']['pos_product'] ?: 0;
					}
					$wc_cart_subtotal = $wc_cart_subtotal ? (float) $wc_cart_subtotal : 0;
					$wc_cart_subtotal += $product_qty ? (float) apply_filters( 'viredis_condition_get_cart_product_price', VIREDIS_Frontend_Product::product_get_price_tax( $product, $product->get_price(), $product_qty ), $product_id, $product_qty, $product, $rule_id, $conditions ) : 0;
					if ( $subtotal_min && $subtotal_min > $wc_cart_subtotal ) {
						$result = false;
						break;
					}
					if ( is_numeric( $subtotal_max ) && $subtotal_max < $wc_cart_subtotal ) {
						$result = false;
					}
					break;
				case 'qty_item':
					$qty_item_min = $params['qty_item_min'] ?? 0;
					$qty_item_min = $qty_item_min ? (int) $qty_item_min : 0;
					$qty_item_max = $params['qty_item_max'] ?? '';
					$qty_item_max = $qty_item_max ? (int) $qty_item_max : '';
					if ( ! $qty_item_min && ! $qty_item_max ) {
						break;
					}
					if ( $wc_cart_empty && ! $product_qty ) {
						$result = false;
						break;
					}
					$wc_cart_qty_item = ( $wc_cart_empty ? 0 : array_sum( wp_list_pluck( $wc_cart_data, 'quantity' )) ) + $product_qty;
					if ( $qty_item_min && $qty_item_min > $wc_cart_qty_item ) {
						$result = false;
						break;
					}
					if ( $qty_item_max && $qty_item_max < $wc_cart_qty_item ) {
						$result = false;
					}
					break;
				case 'item_include':
					if ( is_array( $params ) && count( $params ) ) {
						$result = false;
						if ( $product_id && in_array( $product_id, $params ) ) {
							$result = true;
							break;
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id = $cart_item['product_id'] ?? 0;
							if ( $product_id && in_array( $product_id, $params ) ) {
								$result = true;
								break;
							}
							$variation_id = $cart_item['variation_id'] ?? 0;
							if ( $variation_id && in_array( $variation_id, $params ) ) {
								$result = true;
								break;
							}
						}
					}
					break;
				case 'item_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( $product_id && in_array( $product_id, $params ) ) {
							$result = false;
							break;
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id_t = $cart_item['product_id'] ?? 0;
							if ( $product_id_t && in_array( $product_id, $params ) ) {
								$result = false;
								break;
							}
							$variation_id = $cart_item['variation_id'] ?? 0;
							if ( $variation_id && in_array( $variation_id, $params ) ) {
								$result = false;
								break;
							}
						}
					}
					break;
				case 'cats_include':
					if ( is_array( $params ) && count( $params ) ) {
						$result = false;
						if ( ! $is_cart && $product_id ) {
							$cats_id = wc_get_product_cat_ids( $product_id );
							if ( is_array( $cats_id ) && count( $cats_id ) && count( array_intersect( $cats_id, $params ) ) ) {
								$result = true;
								break;
							}
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id_t = $cart_item['product_id'];
							$cats_id      = wc_get_product_cat_ids( $product_id_t );
							if ( is_array( $cats_id ) && count( $cats_id ) && count( array_intersect( $cats_id, $params ) ) ) {
								$result = true;
								break;
							}
						}
					}
					break;
				case 'cats_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( ! $is_cart && $product_id ) {
							$cats_id = wc_get_product_cat_ids( $product_id );
							if ( is_array( $cats_id ) && count( $cats_id ) && count( array_intersect( $cats_id, $params ) ) ) {
								$result = false;
								break;
							}
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id_t = $cart_item['product_id'];
							$cats_id      = wc_get_product_cat_ids( $product_id_t );
							if ( is_array( $cats_id ) && count( $cats_id ) && count( array_intersect( $cats_id, $params ) ) ) {
								$result = false;
								break;
							}
						}
					}
					break;
				case 'tag_include':
					if ( is_array( $params ) && count( $params ) ) {
						$result = false;
						if ( ! $is_cart && $product_id ) {
							$tags = get_the_terms( $product_id, 'product_tag' );
							if ( ! empty( $tags ) ) {
								$tags_id = array();
								foreach ( $tags as $tag ) {
									$tags_id[] = $tag->term_id;
								}
								if ( ! empty( $tags_id ) && count( array_intersect( $tags_id, $params ) ) ) {
									$result = true;
									break;
								}
							}
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id_t = $cart_item['product_id'];
							$tags         = get_the_terms( $product_id_t, 'product_tag' );
							if ( empty( $tags ) ) {
								continue;
							}
							$tags_id = array();
							foreach ( $tags as $tag ) {
								$tags_id[] = $tag->term_id;
							}
							if ( ! empty( $tags_id ) && count( array_intersect( $tags_id, $params ) ) ) {
								$result = true;
								break;
							}
						}
					}
					break;
				case 'tag_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( ! $is_cart && $product_id ) {
							$tags = get_the_terms( $product_id, 'product_tag' );
							if ( ! empty( $tags ) ) {
								$tags_id = array();
								foreach ( $tags as $tag ) {
									$tags_id[] = $tag->term_id;
								}
								if ( ! empty( $tags_id ) && count( array_intersect( $tags_id, $params ) ) ) {
									$result = false;
									break;
								}
							}
						}
						if ( $wc_cart_empty ) {
							break;
						}
						foreach ( $wc_cart_data as $cart_item ) {
							$product_id_t = $cart_item['product_id'];
							$tags         = get_the_terms( $product_id_t, 'product_tag' );
							if ( empty( $tags ) ) {
								continue;
							}
							$tags_id = array();
							foreach ( $tags as $tag ) {
								$tags_id[] = $tag->term_id;
							}
							if ( ! empty( $tags_id ) && count( array_intersect( $tags_id, $params ) ) ) {
								$result = false;
								break;
							}
						}
					}
					break;
				case 'coupon_include':
					if ( is_array( $params ) && count( $params ) ) {
						if ( $wc_cart_empty ) {
							$result = false;
							break;
						}
						if ( empty( $coupons ) ) {
							$coupons = self::$cache['viwebpos_redis_data']['coupons'] ?? array();
						}
						if ( empty( $coupons ) ) {
							$result = false;
							break;
						}
						$coupons = array_map( 'strtolower', $coupons );
						if ( ! count( array_intersect( $coupons, $params ) ) ) {
							$result = false;
						}
					}
					break;
				case 'coupon_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( $wc_cart_empty ) {
							break;
						}
						if ( empty( $coupons ) ) {
							$coupons = self::$cache['viwebpos_redis_data']['coupons'] ?? array();
						}
						if ( empty( $coupons ) ) {
							break;
						}
						$coupons = array_map( 'strtolower', $coupons );
						if ( count( array_intersect( $coupons, $params ) ) ) {
							$result = false;
						}
					}
					break;
				case 'billing_country_include':
					if ( is_array( $params ) && count( $params ) ) {
						if ( empty( $billing_country ) ) {
							$billing_country='';
							if (empty($customer_object) && !empty(self::$cache['viwebpos_redis_data']['customer_id'])){
								$customer_object = new WC_Customer( self::$cache['viwebpos_redis_data']['customer_id'] );
							}
							if ($customer_object) {
								if ( is_callable( array( $customer_object, "get_billing_country" ) ) ) {
									$billing_country = $customer_object->get_billing_country();
								} elseif ( $customer_object->meta_exists( 'billing_country' ) ) {
									$billing_country = $customer_object->get_meta( 'billing_country', true );
								}
							}
						}
						if ( ! $billing_country || ! in_array( $billing_country, $params ) ) {
							$result = false;
						}
					}
					break;
				case 'billing_country_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( empty( $billing_country ) ) {
							$billing_country='';
							if (empty($customer_object) && !empty(self::$cache['viwebpos_redis_data']['customer_id'])){
								$customer_object = new WC_Customer( self::$cache['viwebpos_redis_data']['customer_id'] );
							}
							if ($customer_object) {
								if ( is_callable( array( $customer_object, "get_billing_country" ) ) ) {
									$billing_country = $customer_object->get_billing_country();
								} elseif ( $customer_object->meta_exists( 'billing_country' ) ) {
									$billing_country = $customer_object->get_meta( 'billing_country', true );
								}
							}
						}
						if ( $billing_country && in_array( $billing_country, $params ) ) {
							$result = false;
						}
					}
					break;
				case 'shipping_country_include':
					if ( is_array( $params ) && count( $params ) ) {
						if ( empty( $shipping_country ) ) {
							$shipping_country = !empty(self::$cache['viwebpos_redis_data']['shipping_address']['country']) ? self::$cache['viwebpos_redis_data']['shipping_address']['country'] :'';
						}
						if ( ! $shipping_country || ! in_array( $shipping_country, $params ) ) {
							$result = false;
						}
					}
					break;
				case 'shipping_country_exclude':
					if ( is_array( $params ) && count( $params ) ) {
						if ( empty( $shipping_country ) ) {
							$shipping_country = !empty(self::$cache['viwebpos_redis_data']['shipping_address']['country']) ? self::$cache['viwebpos_redis_data']['shipping_address']['country'] :'';
						}
						if ( $shipping_country && in_array( $shipping_country, $params ) ) {
							$result = false;
						}
					}
					break;
			}
			if ( ! $result ) {
				break;
			}
		}
		return $result;
	}
	public static function viwebpos_redis_get_discount(){
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		$result =array(
			'status' =>'error',
			'pd_discount' =>array(),
			'cart_discount' =>array(),
		);
		$data = isset($_POST['data']) ? villatheme_sanitize_fields($_POST['data']) :array();
		self::$cache['viwebpos_redis_data'] = array(
			'coupons' =>isset($_POST['coupons']) ? villatheme_sanitize_fields($_POST['coupons']) : array(),
			'customer_id' =>isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '',
			'shipping_address' =>isset($_POST['shipping_address']) ? villatheme_sanitize_kses($_POST['shipping_address']) : array(),
			'cart_data' =>$data,
			'cart_id' =>isset($_POST['cart_id']) ? villatheme_sanitize_kses($_POST['cart_id']) : '',
		);
		do_action('viwebpos_redis_before_get_discount');
		$prices = array();
		if (self::$pd_enable ) {
			foreach ( $data as $item ) {
				$product_id = ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? '' );
				$qty        = (float) $item['quantity'] ?? 0;
				if ( empty( $item['cart_item_key'] ) || ! $product_id  ) {
					continue;
				}
				$product                          = wc_get_product( $product_id );
				if (!$product){
					if (!empty($item['only_convert']) && !empty($item['price'])){
						$prices[ $item['cart_item_key'] ]['price'] = apply_filters('viredis_change_3rd_plugin_price',sanitize_text_field($item['price']));
						$prices[ $item['cart_item_key'] ]['only_convert'] = 1;
					}
					continue;
				}
				if (!empty($item['only_convert']) && !empty($item['price'])){
					$prices[ $item['cart_item_key'] ]['regular_price'] =  $product->get_regular_price() ;
					$prices[ $item['cart_item_key'] ]['price'] =  apply_filters('viredis_change_3rd_plugin_price',sanitize_text_field($item['price']));
					$prices[ $item['cart_item_key'] ]['only_convert'] = 1;
				}else {
					$price                                             = $product->get_price();
					$product->viredis_cart_item                        = 'viwebpos_' . $item['cart_item_key'];
					$current_price                                     = VIREDIS_Frontend_Product_Pricing_Store::get_price( $price, $product, $qty );
					$prices[ $item['cart_item_key'] ]['regular_price'] = $product->get_regular_price();
					$prices[ $item['cart_item_key'] ]['price']         = $current_price;
					$prices[ $item['cart_item_key'] ]['only_convert'] = '';
				}
			}
		}
		if (!self::$pd_enable && !empty($_POST['current_currency']) && VIWEBPOS_Plugins_Curcy::get_enable()){
			$prices = VIWEBPOS_Plugins_Curcy::viwebpos_cury_cart_item_price(true);
		}
		if ( ! empty( $prices ) ) {
			$result['pd_discount']   = $prices;
			$result['status'] = 'success';
		}
		if (self::$cart_enable){
			$cart_discount = self::get_cart_discount();
			if (!empty($cart_discount)){
				$result['cart_discount']   = $cart_discount;
				$result['status'] = 'success';
			}
		}
		$result = apply_filters('viwebpos_redis_get_discount_result',$result);
		unset(self::$cache['viwebpos_redis_data']);
		wp_send_json($result);
	}
	public function viwebpos_set_ajax_events($events){
		if (empty($events['viwebpos_redis_get_discount'])) {
			$events['viwebpos_redis_get_discount'] = array( 'nopriv' => false, 'class' => 'VIWEBPOS_Plugins_Redis' );
		}
		return $events;
	}
	public function viwebpos_before_enqueue_scripts(){
		if (!did_action('wp_enqueue_scripts')){
			do_action('wp_enqueue_scripts');
		}
		VIWEBPOS_Admin_Settings::enqueue_script(
			array(  'viwebpos-redis' ),
			array( 'frontend-redis')
		);
		$viwebpos_redis = array(
			'pd_enable' => self::$pd_enable ?: '',
			'cart_enable' => self::$cart_enable ?: '',
		);
		wp_localize_script( 'viwebpos-redis', 'viwebpos_redis', apply_filters('viwebpos_frontend_params_redis',$viwebpos_redis ));
	}
	public function viwebpos_frontend_params($args){
		if (!isset($args['filter_before_calculate_totals'])){
			$args['filter_before_calculate_totals'] = [];
		}
		$args['filter_before_calculate_totals']['redis']=['type'=>'redis','priority'=>1];
		if (isset($args['filter_before_calculate_totals']['curcy'])){
			unset( $args['filter_before_calculate_totals']['curcy']);
		}
		return $args;
	}
}