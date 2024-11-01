<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Plugin CURCY - WooCommerce Multi Currency
 * Author VillaTheme
 */
class VIWEBPOS_Plugins_Curcy {
	public static $settings,$is_pro,$enable, $cache;
	public function __construct() {
		if ( ! is_plugin_active('woocommerce-multi-currency/woocommerce-multi-currency.php') &&
		     ! is_plugin_active('woo-multi-currency/woo-multi-currency.php')) {
			return;
		}
		$set_currency_hook = array(
			'viwebpos_before_create_order',
			'viwebpos_redis_before_get_discount',
			'viwebpos_curcy_before_get_cart_item_price',
			'viwebpos_curcy_before_get_price',
		);
		foreach ($set_currency_hook as $hook){
			add_action($hook, array($this,'set_current_currency'));
		}
		$set_currency_default = array(
			'viwebpos_before_get_product_data',
		);
		foreach ($set_currency_default as $hook){
			add_action($hook, array($this,'set_currency_default'));
		}
		$set_current_payment = array(
			'viwebpos_redis_get_discount_result',
			'viwebpos_curcy_get_prices_result',
		);
		foreach ($set_current_payment as $hook){
			add_filter($hook, array($this,'set_current_payment'),10,1);
		}
		add_filter('viwebpos_update_settings_args',array($this,'viwebpos_update_settings_args'), 10, 1);
		add_filter('viwebpos_settings_fields',array($this,'viwebpos_settings_fields'), 10, 2);
		add_filter('wmc_is_change_price',array($this,'wmc_is_change_price'), 10, 1);
		add_filter('viwebpos_frontend_params',array($this,'viwebpos_frontend_params'), 10, 1);
		add_filter('viwebpos_set_ajax_events',array($this,'viwebpos_set_ajax_events'),10,1);
		add_action( 'viwebpos_before_enqueue_scripts', array( $this, 'viwebpos_before_enqueue_scripts' ) );
	}
	public function viwebpos_update_settings_args($args){
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return $args;
		}
		$args['3rd_curcy_widget_style'] = isset($_POST['3rd_curcy_widget_style']) ? sanitize_text_field(wp_unslash($_POST['3rd_curcy_widget_style'])) :'';
		return $args;
	}
	public function viwebpos_settings_fields($args, $slug){
		if (self::get_enable() && $slug ==='general'){
			if (!isset($args['fields'])){
				$args['fields'] =[];
			}
			if (isset($args['fields']['pos_endpoint'])){
				$insert = [
					'3rd_curcy_widget_style'=>[
						'type'    => 'select',
						'value'   => VIWEBPOS_DATA::get_instance()->get_params( '3rd_curcy_widget_style' ),
						'options' => self::get_list_shortcodes(),
						'title'   => esc_html__( 'Currency widget style', 'webpos-point-of-sale-for-woocommerce' ),
					]
				];
				$fields = $args['fields'];
				$pos   = array_search('pos_endpoint', array_keys($fields));
				$fields = array_merge(array_slice($fields, 0, $pos), $insert, array_slice($fields, $pos));
				$args['fields'] = $fields;
			}else {
				$args['fields']['3rd_curcy_widget_style'] = [
					'type'    => 'select',
					'value'   => VIWEBPOS_DATA::get_instance()->get_params( '3rd_curcy_widget_style' ),
					'options' => self::get_list_shortcodes(),
					'title'   => esc_html__( 'Currency widget style', 'webpos-point-of-sale-for-woocommerce' ),
				];
			}
		}
		return $args;
	}
	public function wmc_is_change_price($result){
		if (defined('VIWEBPOS_DOING_AJAX') && VIWEBPOS_DOING_AJAX){
			$result = true;
		}
		return $result;
	}
	public static function curcy_wc_price($price, $arg = array()){
		if (!self::get_enable()){
			return wc_price($price, $arg);
		}
		$currency = $arg['currency'] ?? self::$settings->get_default_currency();
		add_filter('wmc_get_list_currencies',array(__CLASS__,'wmc_get_list_currencies'),10,1);
		$currencies = self::$settings->get_list_currencies();
		$arg['currency'] = $currency;
		if (!empty($currencies[$currency])){
			$arg['decimals'] = (int)$currencies[$currency]['decimals'];
			$arg['price_format'] = $currencies[$currency]['pos'];
		}
		return wc_price($price, $arg);
	}
	public static function get_enable(){
		if (self::$enable !== null){
			return self::$enable;
		}
		$settings = self::get_settings();
		if ($settings){
			self::$enable = $settings->get_enable() && $settings->get_enable_multi_payment();
		}
		self::$enable = apply_filters('viwebpos_curcy_enable',self::$enable);
		return self::$enable;
	}
	public static function get_settings(){
		if (self::$settings !== null){
			return self::$settings;
		}
		if (class_exists('WOOMULTI_CURRENCY_Data')){
			self::$settings = WOOMULTI_CURRENCY_Data::get_ins(true);
			self::$is_pro = true;
		}elseif(class_exists('WOOMULTI_CURRENCY_F_Data')){
			self::$settings = WOOMULTI_CURRENCY_F_Data::get_ins();
		}
		return self::$settings;
	}
	public static function get_list_shortcodes(){
		if (isset(self::$cache['wmc_get_list_shortcodes'])){
			return self::$cache['wmc_get_list_shortcodes'];
		}
		add_filter('wmc_get_list_shortcodes', array(__CLASS__,'wmc_get_list_shortcodes'), 10, 1);
		self::$cache['wmc_get_list_shortcodes'] = self::$settings->get_list_shortcodes();
		remove_filter('wmc_get_list_shortcodes', array(__CLASS__,'wmc_get_list_shortcodes'), 10, 1);
		return self::$cache['wmc_get_list_shortcodes'];
	}
	public static function wmc_get_list_shortcodes($args){
		$temp=array(
			'',
			'plain_vertical_2',
			'plain_horizontal',
			'layout3',
			'layout6',
			'layout9',
			'layout10',
		);
		foreach ($temp as $k){
			unset($args[$k]);
		}
		return $args;
	}
	public static function get_woocommerce_currency_symbol($currency, $override = true){
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}
		if (!isset(self::$cache['symbols'])){
			self::$cache['symbols'] = get_woocommerce_currency_symbols();
		}
		$currency_symbol = self::$cache['symbols'][ $currency ] ?? '';
		return $override ? apply_filters( 'woocommerce_currency_symbol', $currency_symbol, $currency ) : $currency_symbol;
	}
	public static function wmc_get_list_currencies($args){
		$result = $args;
		foreach ($args as $currency => $param){
			$result[$currency]['symbol'] = !empty($param['custom'])? $param['custom'] : self::get_woocommerce_currency_symbol($currency, false);
			switch ($param['pos'] ??''){
				case 'right':
					$result[$currency]['pos'] = '%2$s%1$s';
					break;
				case 'left_space':
					$result[$currency]['pos'] = '%1$s&nbsp;%2$s';
					break;
				case 'right_space':
					$result[$currency]['pos'] = '%2$s&nbsp;%1$s';
					break;
				default:
					$result[$currency]['pos'] = '%1$s%2$s';
					break;
			}
		}
		remove_filter('wmc_get_list_currencies', array(__CLASS__,'wmc_get_list_currencies'),10,1);
		return $result;
	}
	public function set_currency_default(){
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX || !self::get_enable() ){
			return ;
		}
		self::$settings->set_current_currency( self::$settings->get_default_currency() );
	}
	public function set_current_currency(){
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return;
		}
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX || !self::get_enable() ){
			return ;
		}
		$currency =isset($_POST['current_currency']) ? villatheme_sanitize_kses($_POST['current_currency']) :'';
		if (!$currency) {
			$currency =isset($_POST['currency']) ? villatheme_sanitize_kses($_POST['currency']) :'';
		}
		if ($currency) {
			self::$settings->set_current_currency( $currency );
		}
	}
	public static function set_current_payment($result){
		if ( isset( $_REQUEST['viwebpos_admin_nonce'] ) && ! wp_verify_nonce( wc_clean( wp_unslash( $_REQUEST['viwebpos_admin_nonce'] ) ), 'viwebpos_admin_nonce' ) ) {
			return $result;
		}
		if (!defined('VIWEBPOS_DOING_AJAX') || !VIWEBPOS_DOING_AJAX || !self::get_enable() ){
			return $result;
		}
		$payments = isset($_POST['payments']) ? villatheme_sanitize_fields($_POST['payments']) : array();
		$temp = array();
		foreach ($payments as $k => $v){
			$temp[$k] = wmc_get_price($v);
		}
		if (!empty($temp)){
			$result['status'] = 'success';
			$result['payments'] = $temp;
		}
		return $result;
	}
	public static function viwebpos_cury_cart_item_price($return =false){
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		$result =array(
			'status' =>'error',
			'pd_price' =>array(),
		);
		if (!self::get_enable()){
			if ($return){
				return [];
			}else {
				wp_send_json( $result );
			}
		}
		$data = isset($_POST['data']) ? villatheme_sanitize_fields($_POST['data']) :array();
		self::$cache['viwebpos_curcy_data'] = array(
			'coupons' =>isset($_POST['coupons']) ? villatheme_sanitize_fields($_POST['coupons']) : array(),
			'customer_id' =>isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '',
			'shipping_address' =>isset($_POST['shipping_address']) ? villatheme_sanitize_kses($_POST['shipping_address']) : array(),
			'current_currency' =>isset($_POST['current_currency']) ? villatheme_sanitize_kses($_POST['current_currency']) : '',
			'cart_data' =>$data,
			'cart_id' =>isset($_POST['cart_id']) ? villatheme_sanitize_kses($_POST['cart_id']) : '',
		);
		do_action('viwebpos_curcy_before_get_cart_item_price');
		$prices = array();
		foreach ( $data as $item ) {
			$product_id = ! empty( $item['variation_id'] ) ? $item['variation_id'] : ( $item['product_id'] ?? '' );
			if ( empty( $item['cart_item_key'] ) || ! $product_id ) {
				continue;
			}
			if (!empty($item['only_convert']) && !empty($item['price'])){
				$prices[ $item['cart_item_key'] ]['price'] = wmc_get_price(sanitize_text_field($item['price']));
				$prices[ $item['cart_item_key'] ]['only_convert'] = 1;
			}elseif ($product = wc_get_product( $product_id )) {
				$prices[ $item['cart_item_key'] ]['price'] = $product->get_price();
				$prices[ $item['cart_item_key'] ]['regular_price'] = $product->get_regular_price();
				$prices[ $item['cart_item_key'] ]['only_convert'] = '';
			}

		}
		if ( ! empty( $prices ) ) {
			$result['pd_price']   = $prices;
			$result['status'] = 'success';
		}
		$result = apply_filters('viwebpos_curcy_get_prices_result', $result);
		unset(self::$cache['viwebpos_curcy_data']);
		if ($return){
			return $result['pd_price'];
		}else {
			wp_send_json( $result );
		}
	}
	public static function viwebpos_cury_get_price(){
		check_ajax_referer('viwebpos_nonce','viwebpos_nonce');
		$result =array(
			'status' =>'error',
			'pd_price' =>array(),
		);
		if (!self::get_enable()){
			wp_send_json($result);
		}
		$data = isset($_POST['data']) ? villatheme_sanitize_fields($_POST['data']) :array();
		self::$cache['viwebpos_curcy_data'] = array(
			'coupons' =>isset($_POST['coupons']) ? villatheme_sanitize_fields($_POST['coupons']) : array(),
			'customer_id' =>isset($_POST['customer_id']) ? sanitize_text_field($_POST['customer_id']) : '',
			'shipping_address' =>isset($_POST['shipping_address']) ? villatheme_sanitize_kses($_POST['shipping_address']) : array(),
			'current_currency' =>isset($_POST['current_currency']) ? villatheme_sanitize_kses($_POST['current_currency']) : '',
			'cart_data' =>isset($_POST['cart_data']) ? villatheme_sanitize_fields($_POST['cart_data']) :array(),
			'cart_id' =>isset($_POST['cart_id']) ? villatheme_sanitize_kses($_POST['cart_id']) : '',
			'search_products' =>$data,
		);
		do_action('viwebpos_curcy_before_get_price');
		$prices = array();
		foreach ( $data as $product_id ) {
			if ( ! $product_id  || ! ($product = wc_get_product( $product_id ))) {
				continue;
			}
			$prices[ $product_id ]['price'] = $product->get_price();
			$prices[ $product_id ]['regular_price'] = $product->get_regular_price();
		}
		if ( ! empty( $prices ) ) {
			$result['pd_price']   = $prices;
			$result['status'] = 'success';
		}
		unset(self::$cache['viwebpos_curcy_data']);
		wp_send_json($result);
	}
	public function viwebpos_frontend_params($args){
		if (self::$settings === null){
			self::$settings = self::get_settings();
		}
		$args['wc_currency_default'] =self::$settings->get_default_currency();
		$args['wc_currency_symbol_default'] = self::get_woocommerce_currency_symbol($args['wc_currency_default']);
		self::$settings->set_current_currency($args['wc_currency_default'], false);
		$args['wc_get_price_decimals_default'] = wc_get_price_decimals();
		$args['wc_price_format_default'] = get_woocommerce_price_format();
		if (!isset($args['filter_before_calculate_totals'])){
			$args['filter_before_calculate_totals'] = [];
		}
		$args['filter_before_calculate_totals']['curcy']=['type'=>'curcy','priority'=>1];
		return $args;
	}
	public function viwebpos_set_ajax_events($events){
		if (empty($events['viwebpos_cury_get_price'])) {
			$events['viwebpos_cury_cart_item_price'] = array( 'nopriv' => false, 'class' => 'VIWEBPOS_Plugins_Curcy' );
			$events['viwebpos_cury_get_price'] = array( 'nopriv' => false, 'class' => 'VIWEBPOS_Plugins_Curcy' );
		}
		return $events;
	}
	public function viwebpos_before_enqueue_scripts(){
		if (!self::get_enable()){
			return;
		}
		if (!did_action('wp_enqueue_scripts')){
			do_action('wp_enqueue_scripts');
		}
		$viwebpos_settings = VIWEBPOS_DATA::get_instance();
		VIWEBPOS_Admin_Settings::enqueue_script( array(  'viwebpos-curcy' ), array( 'frontend-curcy') );
		$widget_style = $viwebpos_settings->get_params('3rd_curcy_widget_style') ?: 'layout4';
		add_filter('wmc_get_list_currencies',array($this,'wmc_get_list_currencies'),10,1);
		$viwebpos_curcy = array(
			'enable' => self::get_enable() ? 1 : '',
			'fix_price_enable' => self::$settings->check_fixed_price() ?: '',
			'widget' => do_shortcode("[woo_multi_currency_{$widget_style} flag_size='0.4']"),
			'currencies' => self::$settings->get_list_currencies()
		);
		wp_localize_script( 'viwebpos-curcy', 'viwebpos_curcy', apply_filters('viwebpos_frontend_params_curcy',$viwebpos_curcy ));
	}
}