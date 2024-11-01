<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Frontend_Frontend {
	protected static $settings, $ajax_events, $data_version = 5;
	public static $page_request, $pos_endpoint;

	public function __construct() {
		self::$settings = VIWEBPOS_DATA::get_instance();
		if ( ! self::$settings->get_params( 'enable' ) ) {
			return;
		}
		self::$pos_endpoint = self::$settings->get_params( 'pos_endpoint' );
		add_filter( 'auth_cookie_expiration', array( $this, 'auth_cookie_expiration' ), PHP_INT_MAX, 3 );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_pos_link_to_my_account' ), 10, 2 );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'get_pos_link' ), PHP_INT_MAX, 4 );
		add_action( 'parse_request', array( $this, 'parse_request' ), PHP_INT_MIN );
		add_action( 'init', array( $this, 'define_ajax' ), 0 );
		add_action( 'send_headers', array( $this, 'viwebpos_ajax' ), 0 );
		$this->add_ajax_events();
	}

	public function auth_cookie_expiration( $time, $user_id, $remember ) {
		if ( current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			$time = $remember ? 14 * DAY_IN_SECONDS : 2 * DAY_IN_SECONDS;
		}

		return $time;
	}

	public function get_pos_link( $url, $endpoint ) {
		if ( $endpoint === 'viwebpos' ) {
			$url = apply_filters( 'viwebpos_get_pos_link', home_url( '/' . self::$pos_endpoint ), self::$pos_endpoint );
		}

		return $url;
	}

	public function add_pos_link_to_my_account( $items, $endpoints ) {
		if ( ! current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) ) ) {
			return $items;
		}
		$keys  = array_keys( $items );
		$index = array_search( 'customer-logout', $keys );
		if ( $index !== false ) {
			$items = array_merge( array_slice( $items, 0, $index ), array( 'viwebpos' => esc_html__( 'Visit POS', 'webpos-point-of-sale-for-woocommerce' ) ), array_slice( $items, $index ) );
		} else {
			$items['viwebpos'] = esc_html__( 'Visit POS', 'webpos-point-of-sale-for-woocommerce' );
		}

		return $items;
	}

	public function parse_request() {
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		if ( wp_doing_ajax() || ! empty( $_REQUEST['viwebpos-ajax'] ) ) {
			return;
		}
		global $wp;
		$page_name    = urldecode( $wp->query_vars['name'] ?? $wp->query_vars['pagename'] ?? $wp->request ?? '' );
		$pos_endpoint = self::$pos_endpoint;
		if ( ! $pos_endpoint || ! $page_name ) {
			return;
		}
		if ( ! apply_filters( 'viwebpos_is_pos_page', $page_name === $pos_endpoint || strpos( $page_name, $pos_endpoint . '/' ) === 0, $page_name, $pos_endpoint ) ) {
			return;
		}
		if ( $this->allow() ) {
			$request_uri = ! empty( $_SERVER['REQUEST_URI'] ) ? urldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( strpos( $request_uri, '/' . $pos_endpoint ) !== false && strpos( $request_uri, 'viwebpos-pwa=1' ) !== false ) {
				$this->print_pwa_service_worker_js();

				return;
			}
		} else {
			if ( is_user_logged_in() ) {
				wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			} else {
				wp_safe_redirect( add_query_arg( 'redirect_to', home_url( '/' . $pos_endpoint ), wc_get_page_permalink( 'myaccount' ) ) );
			}
			exit();
		}
		if ( ! empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			$old_cookie = wp_parse_auth_cookie( wc_clean( wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) ), 'logged_in' );
			$expiration = $old_cookie['expiration'] ?? '';
			if ( $expiration && $expiration < strtotime( '+1 days' ) ) {
				wp_set_auth_cookie( get_current_user_id() );
			}
		}
		$request            = $wp->request ?? $page_name;
		self::$page_request = explode( '/', $request );
		$rtl                = is_rtl();
		?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>"/>
            <meta name="robots" content="noindex,nofollow"/>
            <meta name="mobile-web-app-capable" content="yes">
            <title><?php esc_html_e( 'WebPOS', 'webpos-point-of-sale-for-woocommerce' ); ?></title>
			<?php wp_site_icon(); ?>
			<?php $this->viwebpos_wp_enqueue_scripts(); ?>
        </head>
        <body>
        <div class="viwebpos-wrap viwebpos-wrap-loading<?php echo esc_attr( $rtl ? ' rtl' : '' ); ?>">
            <div class="viwebpos-header-wrap">
				<?php
				$header_arg = array(
					'settings'     => self::$settings,
					'page_request' => self::$page_request,
					'keyboard'     => apply_filters( 'viwebpos_get_keyboard_shortcuts', array(
						'add-custom-product' => array(
							'key'   => 'F2',
							'label' => esc_html__( 'Add custom product', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'add-new-customer'   => array(
							'key'   => 'F3',
							'label' => esc_html__( 'Add new customer', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'add-discount'       => array(
							'key'   => 'F4',
							'label' => esc_html__( 'Add coupon', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'search-product'     => array(
							'key'   => 'F6',
							'label' => esc_html__( 'Search product', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'search-customer'    => array(
							'key'   => 'F7',
							'label' => esc_html__( 'Search customer', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'paid-value'         => array(
							'key'   => 'F8',
							'label' => esc_html__( 'Focus customer paid', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'payment-method'     => array(
							'key'   => 'F9',
							'label' => esc_html__( 'Choose payment method', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'checkout'           => array(
							'key'   => 'F10',
							'label' => esc_html__( 'Checkout and Print', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'close-anything'     => array(
							'key'   => 'ESC',
							'label' => esc_html__( 'Close anything', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'increase-qty'       => array(
							'icon'  => 'arrow up',
							'label' => esc_html__( 'Increase the number of products', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'decrease-qty'       => array(
							'icon'  => 'arrow down',
							'label' => esc_html__( 'Decrease the number of products', 'webpos-point-of-sale-for-woocommerce' ),
						),
					) ),
					'menu_items'   => apply_filters( 'viwebpos_header_get_menu_items', array(
						'bill-of-sale'     => array(
							'url'   => home_url( '/' . $pos_endpoint, 'relative' ),
							'icon'  => 'shopping cart',
							'label' => esc_html__( 'Point of sale', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'orders'           => array(
							'url'   => home_url( '/' . $pos_endpoint . '/orders', 'relative' ),
							'icon'  => 'shopping bag',
							'label' => esc_html__( 'Orders', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'transactions'     => array(
							'url'   => home_url( '/' . $pos_endpoint . '/transactions', 'relative' ),
							'icon'  => 'money bill alternate outline',
							'label' => esc_html__( 'Today transactions', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'customers'        => array(
							'url'   => home_url( '/' . $pos_endpoint . '/customers', 'relative' ),
							'icon'  => 'users',
							'label' => esc_html__( 'Customers', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'settings'         => array(
							'icon'  => 'cog',
							'label' => esc_html__( 'Settings layout', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'refresh_database' => array(
							'url'   => '',
							'icon'  => 'redo',
							'label' => esc_html__( 'Refresh POS data', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'my-account'       => array(
							'url'         => wc_get_page_permalink( 'myaccount' ),
							'icon'        => 'user',
							'label'       => esc_html__( 'My account', 'webpos-point-of-sale-for-woocommerce' ),
							'custom-attr' => array( 'target' => 'blank' ),
						),
						'logout'           => array(
							'url'   => wp_logout_url( wc_get_page_permalink( 'myaccount' ) ),
							'icon'  => 'sign-out',
							'label' => esc_html__( 'Logout', 'webpos-point-of-sale-for-woocommerce' ),
						),
					) ),
					'action_icons' => apply_filters( 'viwebpos_header_get_action_icons', array(
						'auto_print' => array(
							'url'      => '#',
							'icon'     => 'print',
							'position' => $rtl ? 'right center' : 'left center',
							'tooltip'  => esc_html__( 'Auto-print receipt after checkout', 'webpos-point-of-sale-for-woocommerce' ),
						),
						'keyboard'   => array(
							'url'      => '#',
							'icon'     => 'info',
							'position' => $rtl ? 'right center' : 'left center',
							'tooltip'  => esc_html__( 'Keyboard shortcuts', 'webpos-point-of-sale-for-woocommerce' ),
						),
					) ),
				);
				wc_get_template( 'viwebpos-header.php', $header_arg,
					'webpos-woocommerce-pos-point-of-sale' . DIRECTORY_SEPARATOR,
					VIWEBPOS_TEMPLATES . DIRECTORY_SEPARATOR );
				?>
            </div>
            <div class="viwebpos-container-wrap"></div>
        </div>
        </body>
        </html>
		<?php
		exit();
	}

	public function allow() {
		$allow = current_user_can( apply_filters( 'viwebpos_frontend_role', 'cashier' ) );

		return apply_filters( 'viwebpos_frontend_get_access', $allow );
	}

	public function define_ajax() {
		// phpcs:disable
		if ( ! empty( $_GET['viwebpos-ajax'] ) ) {
			wc_maybe_define_constant( 'DOING_AJAX', true );
			wc_maybe_define_constant( 'VIWEBPOS_DOING_AJAX', true );
			if ( ! WP_DEBUG || ( WP_DEBUG && ! WP_DEBUG_DISPLAY ) ) {
				@ini_set( 'display_errors', 0 ); // Turn off display_errors during AJAX events to prevent malformed JSON.
			}
			$GLOBALS['wpdb']->hide_errors();
		}
		// phpcs:enable
	}

	/**
	 * Check for Ajax request and fire action.
	 */
	public function viwebpos_ajax() {
		global $wp_query;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['viwebpos-ajax'] ) ) {
			$wp_query->set( 'viwebpos-ajax', sanitize_text_field( wp_unslash( $_GET['viwebpos-ajax'] ) ) );
		}
		$action = $wp_query->get( 'viwebpos-ajax' );
		if ( $action ) {
			$this->viwebpos_ajax_headers();
			$action = sanitize_text_field( $action );
			do_action( 'viwebpos_ajax_' . $action );
			wp_die();
		}
		// phpcs:enable
	}

	public function viwebpos_ajax_headers() {
		if ( ! headers_sent() ) {
			send_origin_headers();
			send_nosniff_header();
			wc_nocache_headers();
			header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
			header( 'X-Robots-Tag: noindex' );
			status_header( 200 );
		} elseif ( class_exists( 'Constants' ) && Constants::is_true( 'WP_DEBUG' ) ) {
			headers_sent( $file, $line );
			trigger_error( "wc_ajax_headers cannot set headers - headers already sent by {$file} on line {$line}", E_USER_NOTICE ); // @codingStandardsIgnoreLine
		}
	}

	public function add_ajax_events() {
		$ajax_events = self::ajax_events();
		foreach ( $ajax_events as $ajax_event => $params ) {
			$this->add_ajax_event( $ajax_event, $params['class'] ?? '', $params['nopriv'] ?? '', $params['viwebpos_ajax'] ?? true );
		}
	}

	public static function ajax_events() {
		if ( self::$ajax_events ) {
			return self::$ajax_events;
		}
		self::$ajax_events = apply_filters( 'viwebpos_set_ajax_events', array(
			'viwebpos_product_search_data'   => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Bill_Of_Sale' ),
			'viwebpos_get_products_data'     => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Bill_Of_Sale' ),
			'viwebpos_coupon_search_data'    => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Bill_Of_Sale' ),
			'viwebpos_get_coupons_data'      => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Bill_Of_Sale' ),
			'viwebpos_customer_search_data'  => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Customers' ),
			'viwebpos_get_customers_data'    => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Customers' ),
			'viwebpos_create_customer'       => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Customers' ),
			'viwebpos_get_orders_data'       => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Orders' ),
			'viwebpos_create_order'          => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Orders' ),
			'viwebpos_get_transactions_data' => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Transactions' ),
			'viwebpos_create_transaction'    => array( 'nopriv' => false, 'class' => 'VIWEBPOS_Frontend_Transactions' ),
		) );

		return self::$ajax_events;
	}

	public function add_ajax_event( $ajax_event = '', $class = '', $nopriv = false, $viwebpos_ajax = true, $function = '' ) {
		if ( ! $class || ! $ajax_event ) {
			return;
		}
		add_action( 'wp_ajax_' . $ajax_event, array( $class, $function ?: $ajax_event ) );
		if ( $nopriv ) {
			add_action( 'wp_ajax_nopriv_' . $ajax_event, array( $class, $function ?: $ajax_event ) );
		}
		if ( $viwebpos_ajax ) {
			add_action( 'viwebpos_ajax_' . $ajax_event, array( $class, $function ?: $ajax_event ) );
		}
	}

	public function viwebpos_wp_enqueue_scripts() {
		do_action( 'viwebpos_before_enqueue_scripts' );
		$remove_script = [
			'\/wp-content\/plugins\/checkout-upsell-funnel-for-woo',
			'\/wp-content\/plugins\/woo-cart-all-in-one',
			'\/wp-content\/plugins\/woo-photo-reviews',
			'\/wp-content\/plugins\/woo-product-builder',
			'\/wp-content\/plugins\/woocommerce-cart-all-in-one',
			'\/wp-content\/plugins\/woocommerce-checkout-upsell-funnel',
			'\/wp-content\/plugins\/woocommerce-photo-reviews',
			'\/wp-content\/plugins\/woocommerce-product-builder',
			'\/wp-content\/plugins\/wordpress-lucky-wheel',
			'\/wp-content\/themes',
			'\/wp-content\/plugins\/woocommerce\/'
		];
		$remove_script = apply_filters( 'viwebpos_remove_script_pattern', '/(' . implode( '|', $remove_script ) . ')/', $remove_script );
		VIWEBPOS_Admin_Settings::remove_other_script( $remove_script, true );
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'semantic-ui-button', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-icon', 'semantic-ui-popup', 'semantic-ui-table', 'semantic-ui-transition' ),
			array( 'button', 'checkbox', 'dropdown', 'icon', 'popup', 'table', 'transition' ),
			array( 1, 1, 1, 1, 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_style(
			array( 'viwebpos', 'viwebpos-show-message', 'viwebpos-bill-of-sale', 'viwebpos-transaction', 'viwebpos-order', 'viwebpos-customer' ),
			array( 'frontend', 'villathem-show-message', 'frontend-bill-of-sale', 'frontend-transaction', 'frontend-order', 'frontend-customer' )
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'semantic-ui-dropdown', 'transition', 'onscan' ),
			array( 'dropdown', 'transition', 'onscan' ),
			array( 1, 1, 1 )
		);
		VIWEBPOS_Admin_Settings::enqueue_script(
			array( 'viwebpos-pos-data', 'viwebpos-price', 'viwebpos-cart', 'viwebpos', 'viwebpos-show-message', 'viwebpos-bill-of-sale', 'viwebpos-transaction', 'viwebpos-orders', 'viwebpos-customer' ),
			array( 'frontend-pos-data', 'frontend-price', 'frontend-cart', 'frontend', 'villathem-show-message', 'frontend-bill-of-sale', 'frontend-transaction', 'frontend-order', 'frontend-customer' )
		);
		$transactions = array(
			'transaction_table_title_id'     => esc_html__( 'Transaction ID', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_order'  => esc_html__( 'Order', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_in'     => esc_html__( 'In', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_out'    => esc_html__( 'Out', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_method' => esc_html__( 'Method', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_note'   => esc_html__( 'Note', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_table_title_date'   => esc_html__( 'Create at', 'webpos-point-of-sale-for-woocommerce' ),
			'search_transaction'             => esc_html__( 'Search transaction', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_title'          => esc_html__( 'Add new transaction', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_type_title'     => esc_html__( 'Type', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_type_in'        => esc_html__( 'Cash in', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_type_out'       => esc_html__( 'Cash out', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_price_title'    => esc_html__( 'Amount', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_price_value'    => wc_get_template_html( 'viwebpos-price-input.php', array( 'input_name' => 'add_transaction_value' ),
				'webpos-woocommerce-pos-point-of-sale' . DIRECTORY_SEPARATOR,
				VIWEBPOS_TEMPLATES . DIRECTORY_SEPARATOR ),
			'transaction_add_reason_title'   => esc_html__( 'Reason', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_price_empty'    => esc_html__( 'Please enter the transaction amount.', 'webpos-point-of-sale-for-woocommerce' ),
			'transaction_add_bt_title'       => esc_html__( 'Save', 'webpos-point-of-sale-for-woocommerce' ),
			'search_transaction_empty'       => esc_html__( 'No today transaction found', 'webpos-point-of-sale-for-woocommerce' ),
		);
		$customers    = array(
			'customer_first_name'          => esc_html__( 'First name', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_last_name'           => esc_html__( 'Last name', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_email'               => esc_html__( 'Email', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_phone'               => esc_html__( 'Phone', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_id'                  => esc_html__( 'ID', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_address1'            => esc_html__( 'Address', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_country'             => esc_html__( 'Country', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_country_select'      => esc_html__( 'Select an option', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_state'               => esc_html__( 'State', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_city'                => esc_html__( 'City', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_postcode'            => esc_html__( 'Postcode/ ZIP code', 'webpos-point-of-sale-for-woocommerce' ),
			'add_new_customer'             => esc_html__( 'Add new customer', 'webpos-point-of-sale-for-woocommerce' ),
			'add_new_customer_bt'          => esc_html__( 'Add customer', 'webpos-point-of-sale-for-woocommerce' ),
			'search_customer'              => esc_html__( 'Search customer (F7)', 'webpos-point-of-sale-for-woocommerce' ),
			'search_customer_empty'        => esc_html__( 'No customer found', 'webpos-point-of-sale-for-woocommerce' ),
			'error_customer_name_empty'    => esc_html__( 'Please enter the customer name.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_customer_invalid_email' => esc_html__( 'Please provide a valid email address.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_customer_invalid_phone' => esc_html__( 'Please provide a valid phone number.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_email_exists'           => esc_html__( 'An account is already registered with this email address.', 'webpos-point-of-sale-for-woocommerce' ),
			'wc_countries'                 => WC()->countries->get_countries(),
			'wc_states'                    => WC()->countries->get_states(),
		);
		$coupons      = array(
			'wc_coupons_enabled'       => wc_coupons_enabled() ?: '',
			'wc_get_cart_coupon_types' => wc_get_cart_coupon_types(),
			'wc_product_coupon_types'  => wc_get_product_coupon_types(),
			'coupon_maximum_applied'   => self::$settings->get_params( 'maximum_applied_coupons' ) ?: 0,
			'coupon_title'             => esc_html__( 'Coupon', 'webpos-point-of-sale-for-woocommerce' ),
			'coupon_input_placeholder' => esc_html__( 'Coupon code', 'webpos-point-of-sale-for-woocommerce' ),
			'coupon_bt_title'          => esc_html__( 'APPLY COUPON', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %s: coupon code */
			'coupon_not_exist'         => sprintf( esc_html__( 'Coupon "%s" does not exist!', 'webpos-point-of-sale-for-woocommerce' ), '{coupon_code}' ),
			'coupon_please_enter'      => esc_html__( 'Please enter a coupon code.', 'webpos-point-of-sale-for-woocommerce' ),
		);
		$cart         = array(
			'update_variation_on_cart'           => self::$settings->get_params( 'update_variation_on_cart' ) ?: '',
			'product_stock_title'                => esc_html__( 'Stock', 'webpos-point-of-sale-for-woocommerce' ),
			'search_product_empty'               => esc_html__( 'No product found', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %s: product name */
			'make_a_selection_text'              => sprintf( esc_html__( 'Please select some product options before adding "%s" to your cart.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			'cannot_be_purchased_message'        => esc_html__( 'Sorry, this product cannot be purchased.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %s: product name */
			'cannot_add_another_message'         => sprintf( esc_html__( 'You cannot add another "%s" to your cart.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			'no_matching_variations_text'        => esc_html__( 'Sorry, no products matched your selection. Please choose a different combination.', 'webpos-point-of-sale-for-woocommerce' ),
			'maximum_atc_message'                => sprintf( esc_html__( 'You cannot add over 5 items to your cart.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			/* translators: %s: product name */
			'out_of_stock_message'               => sprintf( esc_html__( 'You cannot add "%s" to the cart because the product is out of stock.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			/* translators: %s: product name, %s: product quantity */
			'not_enough_stock_message'           => sprintf( esc_html__( 'You cannot add that amount of "%s" to the cart because there is not enough stock (%s remaining).', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}', '{product_quantity}' ),// phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText
			/* translators: %s: product name */
			'add_to_cart_message'                => sprintf( esc_html__( '%s has been added to your cart.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			/* translators: %s: product name */
			'cart_item_removed_message'          => sprintf( esc_html__( '%s has been removed from your cart because it can no longer be purchased. Please contact us if you need assistance.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			/* translators: %s: product name */
			'cart_item_removed_message1'         => sprintf( esc_html__( '%s has been removed from your cart because it has since been modified.', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}' ),
			/* translators: %1s: product name, %2$s: stock number */
			'cart_item_not_enough_stock_message' => sprintf( esc_html__( '%1s is not enough stock to fulfill this order (%2$s available).', 'webpos-point-of-sale-for-woocommerce' ), '{product_name}', '{product_quantity}' ),// phpcs:ignore WordPress.WP.I18n.MixedOrderedPlaceholdersText
			'cart_item_invalid'                  => esc_html__( 'An item which is no longer available was removed from your cart.', 'webpos-point-of-sale-for-woocommerce' ),
			'cart_customer_invalid'              => esc_html__( 'The current customer is no longer available.', 'webpos-point-of-sale-for-woocommerce' ),
			'remove_cart_item'                   => esc_html__( 'Remove this item', 'webpos-point-of-sale-for-woocommerce' ),
			'add_cart_item_note'                 => esc_html__( 'Add product note', 'webpos-point-of-sale-for-woocommerce' ),
			'add_order_note'                     => esc_html__( 'Add order note', 'webpos-point-of-sale-for-woocommerce' ),
			'remove_all_items'                   => esc_html__( 'Remove all items', 'webpos-point-of-sale-for-woocommerce' ),
		);
		if ( self::$settings->get_params( 'atc_custom_pd' ) ) {
			$cart['custom_product_tooltip']          = esc_html__( 'Add a custom product', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_title']            = esc_html__( 'Custom product', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_name_title']       = esc_html__( 'Name', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_name_placeholder'] = esc_html__( 'Product name', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_price_title']      = esc_html__( 'Price', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_price_value']      = wc_get_template_html( 'viwebpos-price-input.php', array( 'input_name' => 'custom_product_price' ),
				'webpos-woocommerce-pos-point-of-sale' . DIRECTORY_SEPARATOR,
				VIWEBPOS_TEMPLATES . DIRECTORY_SEPARATOR );
			$cart['custom_product_qty_title']        = esc_html__( 'Quantity', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_atc_title']        = esc_html__( 'ADD TO CART', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_name_empty']       = esc_html__( 'Please enter the product name', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_price_empty']      = esc_html__( 'Please enter the product price', 'webpos-point-of-sale-for-woocommerce' );
			$cart['custom_product_qty']              = esc_html__( 'The product quantity must be greater than 0', 'webpos-point-of-sale-for-woocommerce' );
		}
		$checkout          = array(
			'place_order_title'           => esc_html__( 'CHECKOUT', 'webpos-point-of-sale-for-woocommerce' ),
			'total_title'                 => esc_html__( 'Total', 'webpos-point-of-sale-for-woocommerce' ),
			'subtotal_title'              => esc_html__( 'Subtotal', 'webpos-point-of-sale-for-woocommerce' ),
			'tax_title'                   => esc_html__( 'Tax', 'webpos-point-of-sale-for-woocommerce' ),
			'ship_title'                  => esc_html__( 'Shipping', 'webpos-point-of-sale-for-woocommerce' ),
			'discount_title'              => esc_html__( 'Discount', 'webpos-point-of-sale-for-woocommerce' ),
			'need_to_pay_title'           => esc_html__( 'Need to pay', 'webpos-point-of-sale-for-woocommerce' ),
			'paid_title'                  => esc_html__( 'Paid', 'webpos-point-of-sale-for-woocommerce' ),
			'change_title'                => esc_html__( 'Change', 'webpos-point-of-sale-for-woocommerce' ),
			'payment_method_title'        => esc_html__( 'Payment method', 'webpos-point-of-sale-for-woocommerce' ),
			'pay_amount_title'            => esc_html__( 'Amount', 'webpos-point-of-sale-for-woocommerce' ),
			'back_to_bill_title'          => esc_html__( 'Back To Bill', 'webpos-point-of-sale-for-woocommerce' ),
			'checkout_cart_empty_message' => esc_html__( 'The cart is empty. Please add some products to the cart!', 'webpos-point-of-sale-for-woocommerce' ),
			'checkout_low_paid'           => esc_html__( 'The total paid is low than the order total!', 'webpos-point-of-sale-for-woocommerce' ),
			'not_found_order_to_print'    => esc_html__( 'No order exist to print receipt order id : {order_id}!', 'webpos-point-of-sale-for-woocommerce' ),
			'print_title'                 => esc_html__( 'Print Receipt', 'webpos-point-of-sale-for-woocommerce' ),
			'print_button_title'          => esc_html__( 'Print', 'webpos-point-of-sale-for-woocommerce' ),
		);
		$order             = array(
			'order_title'            => esc_html__( 'Order', 'webpos-point-of-sale-for-woocommerce' ),
			'guest_title'            => esc_html__( 'Guest', 'webpos-point-of-sale-for-woocommerce' ),
			'search_order_empty'     => esc_html__( 'No order found', 'webpos-point-of-sale-for-woocommerce' ),
			'wc_product_placeholder' => wc_placeholder_img_src( 'woocommerce_single' ),
		);
		$payments          = self::$settings->get_params( 'payments' );
		$viwebpos_payments = array();
		if ( is_array( $payments ) && count( $payments ) ) {
			array_unshift( $payments, 'cash' );
			$woo_payments = WC()->payment_gateways->payment_gateways();
			foreach ( $payments as $key ) {
				if ( empty( $woo_payments[ $key ] ) ) {
					continue;
				}
				$viwebpos_payments[ $key ] = array(
					'id'    => $key,
					'title' => $woo_payments[ $key ]->get_title(),
				);
			}
		}
		$viwebpos_payments = apply_filters( 'viwebpos_get_payments', $viwebpos_payments );
		if ( count( $viwebpos_payments ) ) {
			if ( ! empty( $woo_payments['cash'] ) ) {
				$viwebpos_payments['cash'] = array(
					'id'    => 'cash',
					'title' => $woo_payments['cash']->get_title(),
				);
			}
			$checkout['viwebpos_payments'] = $viwebpos_payments;
		}
		$default                   = array(
			'data_version'                 => self::$data_version,
			'page_request'                 => self::$page_request,
			'pos_pathname'                 => home_url( '/' . self::$pos_endpoint, 'relative' ),
			'viwebpos_ajax_url'            => add_query_arg( 'viwebpos-ajax', '%%endpoint%%', home_url( '/', 'relative' ) ),
			'admin_ajax_url'               => admin_url( 'admin-ajax.php' ),
			'my_account_url'               => wc_get_page_permalink( 'myaccount' ),
			'cashier_id'                   => get_current_user_id(),
			'today'                        => date( 'Y-m-d' ),// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
			'search_product'               => esc_html__( 'Search product (F6)', 'webpos-point-of-sale-for-woocommerce' ),
			'search_order'                 => esc_html__( 'Search order', 'webpos-point-of-sale-for-woocommerce' ),
			'load_more_title'              => esc_html__( 'Load more', 'webpos-point-of-sale-for-woocommerce' ),
			'logout_message'               => esc_html__( 'Are you sure to log out?', 'webpos-point-of-sale-for-woocommerce' ),
			'refreshed_data_message'       => esc_html__( 'Loaded data successfully', 'webpos-point-of-sale-for-woocommerce' ),
			'error_title'                  => esc_html__( 'Error!', 'webpos-point-of-sale-for-woocommerce' ),
			'wc_tax_enabled'               => wc_tax_enabled(),
			'wc_currency'                  => get_woocommerce_currency(),
			'inc_tax_or_vat'               => WC()->countries->inc_tax_or_vat(),
			'ex_tax_or_vat'                => WC()->countries->ex_tax_or_vat(),
			'display_prices_including_tax' => 'incl' === get_option( 'woocommerce_tax_display_cart' ) ? 1 : '',
			'data_prefix'                  => array(),
			'nonce'                        => wp_create_nonce( 'viwebpos_nonce' ),
		);
		$viwebpos_param            = array_merge( $transactions, $customers, $coupons, $cart, $checkout, $order, $default );
		$prefix_today_transactions = 'viwebpos_today_transactions' . date( "Ymd" );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
		if ( ! self::$settings::data_prefix_exist( 'today_transactions' ) || ( self::$settings::get_data_prefix( 'today_transactions' ) !== $prefix_today_transactions ) ) {
			self::$settings::set_data_prefix( 'today_transactions', date( "Ymd" ) );// phpcs:ignore 	WordPress.DateTime.RestrictedFunctions.date_date
			self::$settings::set_data_prefix( 'transactions' );
		}
		$prefix = array(
			'products'     => esc_html__( 'products', 'webpos-point-of-sale-for-woocommerce' ),
			'orders'       => esc_html__( 'orders', 'webpos-point-of-sale-for-woocommerce' ),
			'customers'    => esc_html__( 'customers', 'webpos-point-of-sale-for-woocommerce' ),
			'transactions' => esc_html__( 'transactions', 'webpos-point-of-sale-for-woocommerce' ),
			'coupons'      => esc_html__( 'coupons', 'webpos-point-of-sale-for-woocommerce' ),
		);
		foreach ( $prefix as $type => $v ) {
			$viwebpos_param['data_prefix'][ $type ]                 = self::$settings::get_data_prefix( $type );
			/* translators: %1s: error message */
			$viwebpos_param[ 'refresh_' . $type . '_data_message' ] = sprintf( esc_html__( 'Loading %s data in POS', 'webpos-point-of-sale-for-woocommerce' ), $v );
		}
		$viwebpos_text = array(
			'settings_title'                => esc_html__( 'Settings layout', 'webpos-point-of-sale-for-woocommerce' ),
			'settings_default'              => array(
				'auto_atc'      => esc_html__( 'Auto add to cart if only one product found', 'webpos-point-of-sale-for-woocommerce' ),
				'cart_item'     => array(
					'multi' => 1,
					'title' => esc_html__( 'Hide cart item information', 'webpos-point-of-sale-for-woocommerce' ),
					'info'  => array(
						'cart_item_number'   => esc_html__( 'Order number of cart items', 'webpos-point-of-sale-for-woocommerce' ),
						'cart_item_price'    => esc_html__( 'Cart item price', 'webpos-point-of-sale-for-woocommerce' ),
						'cart_item_subtotal' => esc_html__( 'Cart item subtotal', 'webpos-point-of-sale-for-woocommerce' ),
					),
				),
				'checkout_form' => array(
					'multi' => 1,
					'title' => esc_html__( 'Hide checkout form information', 'webpos-point-of-sale-for-woocommerce' ),
					'info'  => array(
						'checkout_subtotal' => esc_html__( 'Cart subtotal field', 'webpos-point-of-sale-for-woocommerce' ),
						'checkout_tax'      => esc_html__( 'Tax field', 'webpos-point-of-sale-for-woocommerce' ),
						'suggested_amount'  => esc_html__( 'Suggested amount', 'webpos-point-of-sale-for-woocommerce' ),
					),
				),
			),
			'order_types'                   => array(
				'online_pos'  => esc_html__( 'POS', 'webpos-point-of-sale-for-woocommerce' ),
				'online_shop' => esc_html__( 'Online orders', 'webpos-point-of-sale-for-woocommerce' ),
				'all'         => esc_html__( 'All', 'webpos-point-of-sale-for-woocommerce' ),
			),
			/* translators: %1$s: discount fixed number */
			'discount_fixed_title'          => esc_html__( 'Fixed (%1$s)', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %s: discount percentage */
			'discount_percentage_title'     => esc_html__( 'Percentage (%)', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_address1'             => esc_html__( 'Address line 1', 'webpos-point-of-sale-for-woocommerce' ),
			'customer_address2'             => esc_html__( 'Address line 2', 'webpos-point-of-sale-for-woocommerce' ),
			'save_title'                    => esc_html__( 'Save', 'webpos-point-of-sale-for-woocommerce' ),
			'refunded_title'                => esc_html__( 'Refunded', 'webpos-point-of-sale-for-woocommerce' ),
			'net_payment_title'             => esc_html__( 'Net Payment', 'webpos-point-of-sale-for-woocommerce' ),
			'error_print_receipt'           => esc_html__( 'Can not print receipt', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: coupon code */
			'error_order_invalid_coupon1'   => esc_html__( 'Sorry, it seems the coupon %1$s is invalid - it has now been removed from your order.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: coupon code */
			'error_order_invalid_coupon2'   => esc_html__( 'Sorry, it seems the coupon %1$s is not yours - it has now been removed from your order.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: coupon code */
			'error_invalid_coupon_for_user' => esc_html__( 'Sorry, it seems the coupon %1$s is not your.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_coupon_usage_limit'      => esc_html__( 'Coupon usage limit has been reached.', 'webpos-point-of-sale-for-woocommerce' ),
			'success_apply_coupon'          => esc_html__( 'Coupon code applied successfully!', 'webpos-point-of-sale-for-woocommerce' ),
			'success_remove_coupon'         => esc_html__( 'Coupon has been removed', 'webpos-point-of-sale-for-woocommerce' ),
			'error_invalid_coupon'          => esc_html__( 'Coupon is not valid.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_applied_coupon'          => esc_html__( 'Coupon code already applied!', 'webpos-point-of-sale-for-woocommerce' ),
			'error_invalid_coupon_for_sale' => esc_html__( 'Sorry, this coupon is not valid for sale items.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: coupon code */
			'error_individual_use_coupon'   => esc_html__( 'Sorry, coupon %1$s has already been applied and cannot be used in conjunction with other coupons.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: maximum number */
			'error_coupon_maximum_applied'  => esc_html__( 'Sorry, the maximum number of coupons to be applied must not be greater than %1$s.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_change_variation'        => esc_html__( 'Can not change this variation', 'webpos-point-of-sale-for-woocommerce' ),
			'error_coupon_expired'          => esc_html__( 'This coupon has expired.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: minimum value */
			'error_coupon_minimum'          => esc_html__( 'The minimum spend for this coupon is %1$s.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: maximum value */
			'error_coupon_maximum'          => esc_html__( 'The maximum spend for this coupon is %1$s.', 'webpos-point-of-sale-for-woocommerce' ),
			'error_coupon_not_applicable1'  => esc_html__( 'Sorry, this coupon is not applicable to selected products.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: product name */
			'error_coupon_not_applicable2'  => esc_html__( 'Sorry, this coupon is not applicable to the products: %1$s.', 'webpos-point-of-sale-for-woocommerce' ),
			/* translators: %1$s: categories name */
			'error_coupon_not_applicable3'  => esc_html__( 'Sorry, this coupon is not applicable to the categories: %1$s.', 'webpos-point-of-sale-for-woocommerce' ),
		);
		wp_localize_script( 'viwebpos', 'viwebpos', apply_filters( 'viwebpos_frontend_params', $viwebpos_param ) );
		wp_localize_script( 'viwebpos', 'viwebpos_text', apply_filters( 'viwebpos_frontend_text', $viwebpos_text ) );
		wp_localize_script( 'viwebpos', 'viwebpos_price', apply_filters( 'viwebpos_frontend_price', self::viwebpos_price() ) );
		do_action( 'viwebpos_after_enqueue_scripts' );
		wp_print_styles();
		wp_print_scripts();
	}

	public static function viwebpos_price() {
		$args           = [
			'shop_address'                            => array(
				'country'  => $shop_base_country = WC()->countries->get_base_country(),
				'state'    => WC()->countries->get_base_state(),
				'postcode' => wc_get_wildcard_postcodes( WC()->countries->get_base_postcode(), $shop_base_country ),
				'city'     => WC()->countries->get_base_city(),
			),
			'woocommerce_calc_discounts_sequentially' => 'yes' === get_option( 'woocommerce_calc_discounts_sequentially', 'no' ) ? 1 : '',
			'product_price_includes_tax'              => wc_prices_include_tax(),
			'wc_tax_round_at_subtotal'                => 'yes' === get_option( 'woocommerce_tax_round_at_subtotal' ) ? 1 : '',
			'pd_display_prices_including_tax'         => 'incl' === get_option( 'woocommerce_tax_display_shop' ) ? 1 : '',
			'wc_currency_symbol'                      => get_woocommerce_currency_symbol(),
			'wc_get_price_decimals'                   => wc_get_price_decimals(),
			'wc_price_format'                         => get_woocommerce_price_format(),
			'wc_price_decimal_separator'              => wc_get_price_decimal_separator(),
			'wc_price_thousand_separator'             => wc_get_price_thousand_separator(),
			'wc_get_rounding_precision'               => wc_get_rounding_precision(),
		];
		$wc_tax_classes = WC_Tax::get_tax_classes();
		if ( ! in_array( '', $wc_tax_classes ) ) { // Make sure "Standard rate" (empty class name) is present.
			array_unshift( $wc_tax_classes, '' );
		}
		$args['wc_tax_classes'] = $wc_tax_classes;
		$wc_tax_rates           = array();
		foreach ( $wc_tax_classes as $tax_class ) { // For each tax class, get all rates.
			$wc_tax_rates[ sanitize_title( $tax_class ) ] = WC_Tax::get_rates_for_tax_class( $tax_class );
		}
		$args['wc_tax_rates'] = $wc_tax_rates;

		return $args;
	}
}