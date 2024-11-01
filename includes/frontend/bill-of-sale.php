<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Bill_Of_Sale {
	public static $cache = array();
	protected static $settings;

	public function __construct() {
		self::$settings = VIWEBPOS_DATA::get_instance();
	}

	public static function viwebpos_coupon_check_usage_limit_per_user() {
		check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result      = array(
			'usage'   => array(),
			'message' => array(),
		);
		$customer_id = isset( $_POST['customer_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['customer_id'] ) ) : 0;
		$coupons     = isset( $_POST['coupons'] ) ? villatheme_sanitize_fields( wp_unslash( $_POST['coupons'] ) ) : array();
		if ( ! empty( $coupons ) && $customer_id ) {
			foreach ( $coupons as $coupon_code ) {
				$coupon        = new WC_Coupon( $coupon_code );
				$error_message = '';
				if ( $coupon && apply_filters( 'viwebpos_woocommerce_coupon_validate_user_usage_limit', $coupon->get_usage_limit_per_user() > 0, $customer_id, $coupon )
				     && $coupon->get_id() && $coupon->get_data_store() ) {
					$data_store  = $coupon->get_data_store();
					$usage_count = $data_store->get_usage_by_user_id( $coupon, $customer_id );
					if ( $usage_count >= $coupon->get_usage_limit_per_user() ) {
						if ( $data_store->get_tentative_usages_for_user( $coupon->get_id(), array( $customer_id ) ) > 0 ) {
							$error_message = $coupon->get_coupon_error( WC_Coupon::E_WC_COUPON_USAGE_LIMIT_COUPON_STUCK );
						} else {
							$error_message = $coupon->get_coupon_error( WC_Coupon::E_WC_COUPON_USAGE_LIMIT_REACHED );
						}
					}
				}
				if ( $error_message ) {
					$result['message'][] = $error_message;
				} else {
					$result['usage'][] = $coupon_code;
				}
			}
		}
		wp_send_json( $result );
	}

	public static function viwebpos_get_coupons_data() {
		check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data' => array(),
			'page' => ''
		);
		$limit  = isset( $_POST['limit'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : 50;
		$limit  = $limit ? $limit : 50;
		$page   = isset( $_POST['page'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['page'] ) ) : 1;
		if ( $page === 1 ) {
			$args                  = apply_filters( 'viwebpos_query_coupon_counts', array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
				'fields'         => 'ids',
			) );
			$the_query             = new WP_Query( $args );
			$counts                = ! empty( $the_query->get_posts() ) ? count( $the_query->get_posts() ) : 0;
			$total_pages           = ceil( $counts / $limit );
			$result['total_pages'] = $total_pages;
			if ( ! $total_pages ) {
				wp_send_json( $result );
				wp_die();
			}
		}
		$args      = apply_filters( 'viwebpos_query_coupons_data', array(
			'post_type'      => 'shop_coupon',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'fields'         => 'ids',
		) );
		$the_query = new WP_Query( $args );
		$ids       = $the_query->get_posts();
		$data      = array();
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				self::$cache[ 'get_coupon_data-' . $id ] = true;
				self::get_coupon_data( $data, $id );
				unset( self::$cache[ 'get_coupon_data-' . $id ] );
			}
		}
		$result['data'] = array_values( $data );
		$result['page'] = $page + 1;
		wp_send_json( $result );
	}

	public static function viwebpos_coupon_search_data() {
		check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data'   => array(),
			'status' => 'error'
		);
		$ids    = isset( $_POST['ids'] ) ? villatheme_sanitize_fields( wp_unslash( $_POST['ids'] ) ) : array();
		$data   = array();
		if ( ! empty( $ids ) ) {
			$result['status'] = 'success';
			foreach ( $ids as $id ) {
				self::$cache[ 'get_coupon_data-' . $id ] = true;
				self::get_coupon_data( $data, $id );
				unset( self::$cache[ 'get_coupon_data-' . $id ] );
			}
		}
		$result['data'] = $data;
		wp_send_json( $result );
	}

	public static function get_coupon_data( &$data, $id ) {
		if ( ! $id || ! ( $coupon = new WC_Coupon( $id ) ) ) {
			return;
		}
		$id = $coupon->get_id();
		do_action( 'viwebpos_before_get_coupon_data', $data, $id );
		$temp = array(
			'id'                              => $id,
			'coupon_code'                     => $coupon->get_code(),
			'type'                            => $coupon->get_discount_type(),
			'amount'                          => $coupon->get_amount(),
			'free_shipping'                   => $coupon->get_free_shipping() ? $coupon->get_free_shipping() : '',
			'expiry_date'                     => $coupon->get_date_expires() ? $coupon->get_date_expires()->getTimestamp() : '',
			'minimum_amount'                  => $coupon->get_minimum_amount(),
			'maximum_amount'                  => $coupon->get_maximum_amount(),
			'individual_use'                  => $coupon->get_individual_use(),
			'exclude_sale_items'              => $coupon->get_exclude_sale_items(),
			'product_ids'                     => $coupon->get_product_ids(),
			'exclude_product_ids'             => $coupon->get_excluded_product_ids(),
			'product_categories'              => $coupon->get_product_categories(),
			'exclude_product_categories'      => $coupon->get_excluded_product_categories(),
			'exclude_product_categories_name' => array(),
			'email'                           => $coupon->get_email_restrictions(),
			'usage_limit'                     => $coupon->get_usage_limit(),
			'usage_count'                     => $coupon->get_usage_count(),
			'limit_usage_to_x_items'          => $coupon->get_limit_usage_to_x_items(),
			'usage_limit_per_user'            => $coupon->get_usage_limit_per_user(),
			'virtual'                         => $coupon->get_virtual(),
		);
		if ( ! $coupon->is_type( wc_get_product_coupon_types() ) && ! empty( $temp['exclude_product_categories'] ) ) {
			foreach ( $temp['exclude_product_categories'] as $cat_id ) {
				$cat                                                = get_term( $cat_id, 'product_cat' );
				$temp['exclude_product_categories_name'][ $cat_id ] = $cat->name;
			}
		}
		$data_store                    = $coupon->get_data_store();
		$temp['tentative_usage_count'] = is_callable( array( $data_store, 'get_tentative_usage_count' ) ) ? $data_store->get_tentative_usage_count( $coupon->get_id() ) : 0;
		$data[ $id ]                   = $temp;
		do_action( 'viwebpos_get_coupon_data', $data, $id );

		return $id;
	}

	public static function viwebpos_get_products_data( $return = false ) {
		if ( ! $return ) {
			check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
			if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
				wp_die();
			}
		}
		$result        = array(
			'data' => array(),
			'page' => ''
		);
		$limit         = isset( $_POST['limit'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : 50;
		$limit         = $limit ?: 50;
		$page          = isset( $_POST['page'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['page'] ) ) : 1;
		$product_types = wc_get_product_types();
		if ( ! isset( $product_types['variation'] ) ) {
			$product_types['variation'] = 'Variation product';
		}
		unset( $product_types['external'] );
		unset( $product_types['grouped'] );
		$product_types = array_keys( $product_types );
		$args          = apply_filters( 'viwebpos_query_products_data', array(
			'status' => 'publish',
			'type'   => $product_types,
			'limit'  => $limit,
			'page'   => $page,
			'return' => 'ids',
		) );
		if ( $page === 1 ) {
			$args['paginate']      = 1;
			$products              = wc_get_products( $args );
			$total_pages           = $products->max_num_pages;
			$result['total_pages'] = $products->max_num_pages;
			if ( ! $total_pages ) {
				if ( $return ) {
					return $result;
				} else {
					wp_send_json( $result );
				}
			}
			$product_ids = $products->products;
		} else {
			$product_ids = wc_get_products( $args );
		}
		$products_data = array();
		if ( ! empty( $product_ids ) ) {
			foreach ( $product_ids as $product_id ) {
				self::$cache[ 'get_product_data-' . $product_id ] = true;
				self::get_product_data( $products_data, $product_id );
				unset( self::$cache[ 'get_product_data-' . $product_id ] );
			}
		}
		$result['data'] = array_values( $products_data );
		$result['page'] = $page + 1;
		if ( $return ) {
			return $result;
		} else {
			wp_send_json( $result );
		}
	}

	public static function posts_where_request( $where, $query ) {
		if ( ! empty( $query->query_vars['viwebpos_product_search'] ) &&
		     ! empty( $query->query_vars['viwebpos_product_search_filter'] ) &&
		     is_array( $query->query_vars['viwebpos_product_search_filter'] ) ) {
			global $wpdb;
			$table_name = $wpdb->postmeta;
			$like       = '%' . $wpdb->esc_like( $query->query_vars['viwebpos_product_search'] ) . '%';
			$searches   = array();
			foreach ( $query->query_vars['viwebpos_product_search_filter'] as $column ) {
				if ( in_array( $column, [ 'post_title' ] ) ) {
					$searches[] = $wpdb->prepare( "$column LIKE %s", $like );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				} else {
					$searches[] = $wpdb->prepare( "( $table_name.meta_key = %s AND $table_name.meta_value LIKE %s )", $column, $like );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}
			$where .= ' AND (' . implode( ' OR ', $searches ) . ')';
		}

		return $where;
	}

	public static function viwebpos_product_search_data() {
		check_ajax_referer( 'viwebpos_nonce', 'viwebpos_nonce' );
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result      = array(
			'data'   => array(),
			'status' => 'error'
		);
		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'floatval', villatheme_sanitize_fields( wp_unslash( $_POST['product_ids'] ) ) ) : array();
		if ( empty( $product_ids ) && ! empty( $_POST['search'] ) ) {
			$search = wc_clean( wp_unslash( $_POST['search'] ) );
			$args   = array(
				'post_status'    => 'publish',
				'post_type'      => [ 'product', 'product_variation' ],
				'posts_per_page' => isset( $_POST['per_page'] ) ? wc_clean( wp_unslash( $_POST['per_page'] ) ) : 30,
				'fields'         => 'ids',
			);
			if ( ! empty( $_POST['search_barcode'] ) ) {
				$args['meta_query'] = array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => 'viwebpos_barcode',
						'compare' => '=',
						'value'   => $search,
					)
				);
				if ( self::$settings->get_params( 'auto_create_barcode_by_sku' ) ) {
					$args['meta_query'][] = array(
						'key'     => '_sku',
						'compare' => '=',
						'value'   => $search,
					);
				}
			} else {
				$args['viwebpos_product_search']        = $search;
				$args['viwebpos_product_search_filter'] = array( 'post_title', 'viwebpos_barcode', '_sku' );
				$args['meta_query']                     = array(// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'OR',
					array(
						'key'     => '_sku',
						'compare' => 'NOT EXISTS',
						'value'   => '',
					),
					array(
						'key'     => '_sku',
						'compare' => 'EXISTS',
						'value'   => '',
					),
					array(
						'key'     => '_sku',
						'compare' => '!=',
						'value'   => '',
					)
				);
				add_filter( 'posts_where_request', array( __CLASS__, 'posts_where_request' ), 10, 2 );
			}
			$the_query   = new WP_Query( $args );
			$product_ids = $the_query->get_posts();
		}
		$products_data = array();
		if ( ! empty( $product_ids ) ) {
			$result['status'] = 'success';
			foreach ( $product_ids as $product_id ) {
				self::$cache[ 'get_product_data-' . $product_id ] = true;
				self::get_product_data( $products_data, $product_id );
				unset( self::$cache[ 'get_product_data-' . $product_id ] );
			}
		}
		$result['data'] = array_values( $products_data );
		wp_send_json( $result );
	}

	public static function get_product_data( &$products_data, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return;
		}
		do_action( 'viwebpos_before_get_product_data', $products_data, $product );
		remove_all_filters( 'woocommerce_dropdown_variation_attribute_options_html' );
		remove_all_filters( 'woocommerce_product_get_image' );
		remove_action( 'wp_get_attachment_image_attributes', 'woodmart_lazy_attributes' );
		$product_id = $product->get_id();
		if ( empty( self::$cache['auto_create_barcode_by_sku'] ) ) {
			self::$cache['auto_create_barcode_by_sku'] = wc_product_sku_enabled() && self::$settings->get_params( 'auto_create_barcode_by_sku' );
		}
		$auto_create_barcode_by_sku = self::$cache['auto_create_barcode_by_sku'];
		$temp                       = array(
			'id'                   => $product_id,
			'name'                 => $product->get_name(),
			'parent_id'            => $product->get_parent_id(),
//			'categories' => $product->get_category_ids(),
			'type'                 => $product_type = $product->get_type(),
			'slug'                 => $product->get_slug(),
			'sku'                  => $product_sku = $product->get_sku(),
			'barcode'              => $auto_create_barcode_by_sku ? $product_sku : get_post_meta( $product_id, 'viwebpos_barcode', true ),
			'regular_price'        => $product->get_regular_price(),
			'sale_price'           => $product->get_sale_price(),
			'price'                => $product->get_price(),
//			'price_html'           => $product->get_price_html(),
			'total_sales'          => $product->get_total_sales(),
			'stock'                => $product->get_stock_quantity(),
			'max_qty'              => 0 < $product->get_max_purchase_quantity() ? $product->get_max_purchase_quantity() : '',
			'min_qty'              => $product->get_min_purchase_quantity(),
			'stock_status'         => $product->get_stock_status(),
			'is_in_stock'          => $product->is_in_stock(),
			'is_purchasable'       => $product->is_purchasable(),
			'is_sold_individually' => $product->is_sold_individually(),
			'image'                => $product->get_image(),
			'attributes'           => $product->get_attributes(),
			'taxable'              => $product->is_taxable(),
			'tax_status'           => $product->get_tax_status(),
			'tax_class'            => $product->get_tax_class(),
			'weight'               => $product->get_weight(),
			'length'               => $product->get_length(),
			'width'                => $product->get_width(),
			'height'               => $product->get_height(),
		);
		$product_parent             = wc_get_product( $temp['parent_id'] );
		if ( $product_type === 'variation' && is_a( $product_parent, 'WC_Product' ) && method_exists( $product_parent, 'get_variation_attributes' ) ) {
			$parent_attr               = $product_parent->get_variation_attributes();
			$temp['parent_attributes'] = $parent_attr;
			$parent_attributes_html    = array();
			foreach ( $temp['parent_attributes'] as $attribute_name => $option ) {
				ob_start();
				wc_dropdown_variation_attribute_options( apply_filters( 'viwebpos_dropdown_variation_attribute_options', array(
					'options'                 => $option,
					'attribute'               => $attribute_name,
					'product'                 => $product_parent,
					'class'                   => 'viwebpos-attribute-options',
					'viwpvs_swatches_disable' => 1,
				), $attribute_name, $product_parent ) );
				$parent_attributes_html[] = ob_get_clean();
			}
			if ( count( $parent_attributes_html ) ) {
				$temp['parent_attributes_html'] = implode( ' ', $parent_attributes_html );
			}
			$attribute_html = array();
			foreach ( $temp['attributes'] as $attribute_name => $option ) {
				if ( $option ) {
					$name             = 'attribute_' . sanitize_title( $attribute_name );
					$attribute_html[] = sprintf( '<input type="hidden" name="%s" data-attribute_name="%s" value="%s">', esc_attr( $name ), esc_attr( $name ), esc_attr( $option ) );
				} else {
					$attribute   = wc_attribute_label( $attribute_name, $product_parent );
					$options     = $parent_attr[ $attribute_name ] ?? $parent_attr[ $attribute ] ?? $option;
					$attribute_t = isset( $parent_attr[ $attribute_name ] ) ? $attribute_name : $attribute;
					$selected    = $product_parent->get_default_attributes()[ $attribute_name ] ?? $options[0];
					ob_start();
					wc_dropdown_variation_attribute_options( apply_filters( 'viwebpos_dropdown_variation_attribute_options', array(
						'options'                 => $options,
						'attribute'               => $attribute_t,
						'product'                 => $product_parent ?? '',
						'selected'                => $selected,
						'class'                   => 'viwebpos-attribute-options',
						'viwpvs_swatches_disable' => 1,
					), $attribute_name, $product ) );
					$attribute_html[] = ob_get_clean();
				}
			}
			if ( count( $attribute_html ) ) {
				$temp['attribute_html'] = implode( ' ', $attribute_html );
			}
		}
		if ( $product->has_child() && $product->is_type( 'variable' ) ) {
			$product_children = $product->get_children();
			if ( count( $product_children ) ) {
				$temp['attributes']              = $product->get_variation_attributes();
				$temp['default_attributes']      = $product->get_default_attributes();
				$temp['available_variation_ids'] = $product_children;
				foreach ( $product_children as $product_child ) {
					self::$cache[ 'get_product_data-' . $product_child ] = true;
					self::get_product_data( $products_data, $product_child );
					unset( self::$cache[ 'get_product_data-' . $product_child ] );
				}
			}
		}
		$products_data[ $product_id ] = apply_filters( 'viwebpos_get_product_data', $temp, $product_id );
		do_action( 'viwebpos_after_get_product_data', $products_data, $product );
	}
}