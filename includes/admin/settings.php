<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Settings {
	public function __construct() {
		$update_prefix = array(
			'product'  => array(
				'woocommerce_after_product_object_save',
				'woocommerce_before_delete_product',
				'woocommerce_before_delete_product_variation',
				'wp_trash_post',
				'untrashed_post',
			),
			'order'    => array(
				'woocommerce_after_order_object_save',
				'woocommerce_trash_order',
				'wp_trash_post',
				'untrashed_post',
			),
			'customer' => array(
				'user_register',
				'profile_update',
				'deleted_user'
			),
			'coupon'   => array(
				'woocommerce_increase_coupon_usage_count',
				'woocommerce_decrease_coupon_usage_count',
				'woocommerce_update_coupon',
				'woocommerce_new_coupon',
				'wp_trash_post',
				'untrashed_post',
			),
		);
		foreach ( $update_prefix as $type => $actions ) {
			foreach ( $actions as $action ) {
				add_action( $action, array( $this, $type . '_update_prefix' ), 10, 1 );
			}
		}
	}

	public function product_update_prefix( $id ) {
		if ( in_array( current_action(), [ 'wp_trash_post', 'untrashed_post' ] ) ) {
			if ( get_post_type( $id ) === 'product' ) {
				VIWEBPOS_DATA::set_data_prefix( 'products' );
			}
		} else {
			VIWEBPOS_DATA::set_data_prefix( 'products' );
		}
	}

	public function order_update_prefix( $id ) {
		if ( in_array( current_action(), [ 'wp_trash_post', 'untrashed_post' ] ) ) {
			if ( get_post_type( $id ) === 'shop_order' ) {
				VIWEBPOS_DATA::set_data_prefix( 'orders' );
			}
		} else {
			VIWEBPOS_DATA::set_data_prefix( 'orders' );
		}
	}

	public function customer_update_prefix( $user_id ) {
		if ( in_array( current_action(), [ 'user_register', 'profile_update', ] ) ) {
			$user = get_user_by( 'id', $user_id );
			if ( in_array( 'administrator', $user->roles ) || in_array( 'shop_manager', $user->roles ) || in_array( 'cashier', $user->roles ) ) {
				$user->add_cap( 'cashier' );
			} else {
				$user->remove_cap( 'cashier' );
			}
		}
		VIWEBPOS_DATA::set_data_prefix( 'customers' );
	}

	public function coupon_update_prefix( $id ) {
		if ( in_array( current_action(), [ 'wp_trash_post', 'untrashed_post' ] ) ) {
			if ( get_post_type( $id ) === 'shop_coupon' ) {
				VIWEBPOS_DATA::set_data_prefix( 'coupons' );
			}
		} else {
			VIWEBPOS_DATA::set_data_prefix( 'coupons' );
		}
	}

	public function upgrade_capability( $user_id ) {
		$user = get_user_by( 'id', $user_id );
		if ( in_array( 'administrator', $user->roles ) || in_array( 'shop_manager', $user->roles ) || in_array( 'cashier', $user->roles ) ) {
			$user->add_cap( 'cashier' );
		} else {
			$user->remove_cap( 'cashier' );
		}
	}

	public static function remove_other_script( $pattern = '', $style = false ) {
		if ( ! $pattern ) {
			$pattern = '/^(\/wp-content\/plugins|\/wp-content\/themes)/i';
		}
		if ( $style ) {
			global $wp_styles;
			$styles = $wp_styles->registered;
			foreach ( $styles as $style ) {
				preg_match( $pattern, $style->src, $result1 );
				if ( count( array_filter( $result1 ) ) ) {
					wp_dequeue_style( $style->handle );
				}
			}
		}
		global $wp_scripts;
		$scripts = $wp_scripts->registered;
		if ( is_admin() ) {
			foreach ( $scripts as $k => $script ) {
				if ( in_array( $script->handle, array( 'query-monitor', 'uip-app', 'uip-vue', 'uip-toolbar-app' ) ) ) {
					continue;
				}
				preg_match( '/^\/wp-/i', $script->src, $result );
				if ( count( array_filter( $result ) ) ) {
					preg_match( $pattern, $script->src, $result1 );
					if ( count( array_filter( $result1 ) ) ) {
						wp_dequeue_script( $script->handle );
					}
				} else {
					wp_dequeue_script( $script->handle );
				}
			}
		} else {
			foreach ( $scripts as $k => $script ) {
				preg_match( $pattern, $script->src, $result1 );
				if ( count( array_filter( $result1 ) ) ) {
					wp_dequeue_script( $script->handle );
				}
			}
		}
	}

	public static function enqueue_style( $handles = array(), $srcs = array(), $is_lib = array(), $des = array(), $type = 'enqueue' ) {
		if ( empty( $handles ) || empty( $srcs ) ) {
			return;
		}
		$action = $type === 'enqueue' ? 'wp_enqueue_style' : 'wp_register_style';
		$suffix = WP_DEBUG ? '' : '.min';
		foreach ( $handles as $i => $handle ) {
			if ( ! $handle || empty( $srcs[ $i ] ) ) {
				continue;
			}
			$suffix_t = ! empty( $is_lib[ $i ] ) ? '.min' : $suffix;
			$action( $handle, VIWEBPOS_CSS . $srcs[ $i ] . $suffix_t . '.css', $des[ $i ] ?? array(), VIWEBPOS_VERSION );
		}
	}

	public static function enqueue_script( $handles = array(), $srcs = array(), $is_lib = array(), $des = array(), $type = 'enqueue' ) {
		if ( empty( $handles ) || empty( $srcs ) ) {
			return;
		}
		$action = $type === 'enqueue' ? 'wp_enqueue_script' : 'wp_register_script';
		$suffix = WP_DEBUG ? '' : '.min';
		foreach ( $handles as $i => $handle ) {
			if ( ! $handle || empty( $srcs[ $i ] ) ) {
				continue;
			}
			$suffix_t = ! empty( $is_lib[ $i ] ) ? '.min' : $suffix;
			$action( $handle, VIWEBPOS_JS . $srcs[ $i ] . $suffix_t . '.js', $des[ $i ] ?? array( 'jquery' ), VIWEBPOS_VERSION );
		}
	}
}