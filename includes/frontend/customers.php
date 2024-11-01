<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Customers {
	public static $cache = array();
	protected static $settings;

	public function __construct() {
		self::$settings = VIWEBPOS_DATA::get_instance();
		if ( ! self::$settings->get_params( 'enable' ) ) {
			return;
		}
	}

	public static function viwebpos_get_customers_data() {
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data' => array(),
			'page' => ''
		);
		//Nonce missing on deprecated function
		$limit  = isset( $_POST['limit'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['limit'] ) ) : 50;// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$limit  = $limit ?: 50;
		$page   = isset( $_POST['page'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['page'] ) ) : 1;// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $page === 1 ) {
			$customer_counts       = intval( count_users()['total_users'] ?? 1 );
			$total_pages           = ceil( $customer_counts / $limit );
			$result['total_pages'] = $total_pages;
			if ( ! $total_pages ) {
				wp_send_json( $result );
				wp_die();
			}
		}
		$args      = apply_filters( 'viwebpos_query_customers_data', array(
			'number'   => $limit,
			'page'     => $page,
			'role__in' => array( 'customer', 'cashier' ),
		) );
		$query     = new WP_User_Query( $args );//$customer      = new WC_Customer( $id );
		$customers = array();
		if ( ! empty( $query->get_results() ) ) {
			foreach ( $query->get_results() as $user ) {
				$user_id                                        = $user->ID;
				self::$cache[ 'get_customer_data-' . $user_id ] = true;
				self::get_customer_data( $customers, $user_id );
				unset( self::$cache[ 'get_customer_data-' . $user_id ] );
			}
		}
		$result['data'] = array_values( $customers );
		$result['page'] = $page + 1;
		wp_send_json( $result );
	}
	public static function viwebpos_customer_search_data() {
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		if ( ! current_user_can(apply_filters( 'viwebpos_frontend_role',  'cashier' ) ) ) {
			wp_die();
		}
		$result = array(
			'data' => array(),
			'status' => 'error'
		);
		$user_ids  = isset( $_POST['user_ids'] ) ? villatheme_sanitize_fields( wp_unslash( $_POST['user_ids'] ) ) : '';
		$customers = array();
		if ( ! empty( $user_ids ) ) {
			$result['status'] = 'success';
			if (is_email($user_ids)){
				$user = get_user_by( 'email',$user_ids );
				if ( $user ) {
					$user_id = $user->ID;
					self::$cache[ 'get_customer_data-' . $user_id ] = true;
					self::get_customer_data( $customers, $user_id );
					unset( self::$cache[ 'get_customer_data-' . $user_id ] );
				}
			}else {
				foreach ( $user_ids as $user_id ) {
					self::$cache[ 'get_customer_data-' . $user_id ] = true;
					self::get_customer_data( $customers, $user_id );
					unset( self::$cache[ 'get_customer_data-' . $user_id ] );
				}
			}
		}
		$result['data'] = array_values( $customers );
		wp_send_json( $result );
	}

	public static function get_customer_data( &$customers, $user_id ) {
		if ( ! $user_id || ! $customer = new WC_Customer( $user_id ) ) {
			return;
		}
		$temp                  = array(
			'id'               => $user_id,
			'username'         => $customer->get_username(),
			'email'            => $customer->get_email(),
			'phone'            => $customer->get_billing_phone(),
			'first_name'       => $customer->get_first_name(),
			'last_name'        => $customer->get_last_name(),
			'avatar_url'       => $customer->get_avatar_url(),
			'billing_address'  => array(
				'first_name' => $customer->get_billing_first_name(),
				'last_name'  => $customer->get_billing_last_name(),
				'company'    => $customer->get_billing_company(),
				'address_1'  => $customer->get_billing_address_1(),
				'address_2'  => $customer->get_billing_address_2(),
				'city'       => $customer->get_billing_city(),
				'state'      => $customer->get_billing_state(),
				'postcode'   => $customer->get_billing_postcode(),
				'country'    => $customer->get_billing_country(),
				'email'      => $customer->get_billing_email(),
				'phone'      => $customer->get_billing_phone(),
			),
			'shipping_address' => array(
				'first_name' => $customer->get_shipping_first_name(),
				'last_name'  => $customer->get_shipping_last_name(),
				'company'    => $customer->get_shipping_company(),
				'address_1'  => $customer->get_shipping_address_1(),
				'address_2'  => $customer->get_shipping_address_2(),
				'city'       => $customer->get_shipping_city(),
				'state'      => $customer->get_shipping_state(),
				'postcode'   => $customer->get_shipping_postcode(),
				'country'    => $customer->get_shipping_country(),
			),
		);
		$customers[ $user_id ] = $temp;
		do_action( 'viwebpos_get_customer_data', $customers, $user_id );
	}


	public static function registration_error_email_exists() {
		return esc_html__( 'An account is already registered with this email address.', 'webpos-point-of-sale-for-woocommerce' );
	}

	public static function viwebpos_create_customer() {
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');

		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) )) {
			wp_die();
		}
		$result             = array(
			'status'  => 'error',
			'message' => '',
			'data'    => array()
		);
		$first_name         = ! empty( $_POST['first_name'] ) ? sanitize_text_field( $_POST['first_name'] ) : '';
		$last_name          = ! empty( $_POST['last_name'] ) ? sanitize_text_field( $_POST['last_name'] ) : '';
		$email              = ! empty( $_POST['email'] ) ? sanitize_text_field( $_POST['email'] ) : '';
		$args               = $billing_address = ! empty( $_POST['billing_address'] ) ? villatheme_sanitize_fields( $_POST['billing_address'] ) : array();
		$args['first_name'] = $first_name;
		$args['last_name']  = $last_name;
		$args['email']      = $email;
		$username           = wc_create_new_customer_username( $email, $args );
		$password           =  wp_generate_password(24);
		$result['username'] = $username;
		$result['pass']     = $password;
		add_filter( 'woocommerce_registration_error_email_exists', array( __CLASS__, 'registration_error_email_exists' ) );
		$customer_id = wp_insert_user( array_merge(
			$args,
			array(
				'user_login' => $username,
				'user_pass'  => $password,
				'user_email' => sanitize_email( $email ),
				'role'       => 'customer',
			)
		) );
		if ( is_wp_error( $customer_id ) ) {
			$result['message'] = $customer_id->get_error_message();
			wp_send_json( $result );
		}
		if ( $customer_id ) {
			if ( is_multisite() && is_user_logged_in() && ! is_user_member_of_blog() ) {
				add_user_to_blog( get_current_blog_id(), $customer_id, 'customer' );
			}
			$customer = new WC_Customer( $customer_id );
			foreach ( $billing_address as $key => $value ) {
				if ( is_callable( array( $customer, "set_billing_{$key}" ) ) ) {
					// Use setters where available.
					$customer->{"set_billing_{$key}"}( $value );
				} elseif ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) ) {
					// Store custom fields prefixed with wither shipping_ or billing_.
					$customer->update_meta_data( $key, $value );
				}
			}
			$customer->save();
			$customers                                          = array();
			self::$cache[ 'get_customer_data-' . $customer_id ] = true;
			self::get_customer_data( $customers, $customer_id );
			unset( self::$cache[ 'get_customer_data-' . $customer_id ] );
			$result['status']      = 'success';
			$result['data']        = $customers;
			$result['message']     = esc_html__( 'An account was created successfully.', 'webpos-point-of-sale-for-woocommerce' );
			$result['data_prefix'] = self::$settings::get_data_prefix( 'customers' );
		}
		wp_send_json( $result );
	}
}