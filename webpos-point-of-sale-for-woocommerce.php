<?php
/**
 * Plugin Name: WebPOS – Point of Sale for WooCommerce
 * Plugin URI: https://villatheme.com/extensions/webpos-woocommerce-pos-point-of-sale/
 * Description: WebPOS – Point of Sale for WooCommerce allows you to sell in your physical retail store and sync with data online
 * Version: 1.1.2
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webpos-point-of-sale-for-woocommerce
 * Domain Path: /languages
 * Copyright 2022-2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires PHP: 7.0
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 7.0
 * WC tested up to: 8.9
 **/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

/**
 * Class VIWEBPOS_POINT_OF_SALE_FOR_WOO
 */
class VIWEBPOS_POINT_OF_SALE_FOR_WOO {

	public function __construct() {
		//compatible with 'High-Performance order storage (COT)'
		add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
		if ( is_plugin_active( 'webpos-woocommerce-pos-point-of-sale/webpos-woocommerce-pos-point-of-sale.php' ) ) {
			return;
		}
		$this->define();
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'activated_plugin', array( $this, 'activated_plugin' ) ,10,2);
		add_filter( 'woocommerce_payment_gateways', 'viwebpos_add_gateway_class' );
	}
	public function init() {
		$include_dir = plugin_dir_path( __FILE__ ) . 'includes/';
		if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
			include_once $include_dir . 'support.php';
		}

		$environment = new VillaTheme_Require_Environment( [
				'plugin_name'     => 'WebPOS – Point of Sale for WooCommerce',
				'php_version'     => '7.0',
				'wp_version'      => '5.0',
				'require_plugins' => [
					[
						'slug' => 'woocommerce',
						'name' => 'WooCommerce' ,
						'required_version' => '7.0',
					]
				]
			]
		);

		if ( $environment->has_error() ) {
			return;
		}
		$this->includes();
		add_action( 'admin_init', array( $this, 'update_database' ) );
	}

	public function before_woocommerce_init() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
	protected function define() {
		define( 'VIWEBPOS_VERSION', '1.1.2' );
		define( 'VIWEBPOS_DIR', plugin_dir_path( __FILE__ ) );
		define( 'VIWEBPOS_LANGUAGES', VIWEBPOS_DIR . "languages" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_INCLUDES', VIWEBPOS_DIR . "includes" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_ADMIN', VIWEBPOS_INCLUDES . "admin" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_FRONTEND', VIWEBPOS_INCLUDES . "frontend" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_TEMPLATES', VIWEBPOS_INCLUDES . "templates" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_PLUGINS', VIWEBPOS_INCLUDES . "plugins" . DIRECTORY_SEPARATOR );
		define( 'VIWEBPOS_URL', plugins_url( '', __FILE__ ) );
		$plugin_url = plugins_url( 'assets/', __FILE__ );
		define( 'VIWEBPOS_CSS', $plugin_url . "css/" );
		define( 'VIWEBPOS_JS', $plugin_url . "js/" );
		define( 'VIWEBPOS_IMAGES', $plugin_url . "images/" );
	}


	protected function includes() {
		$files = array(
			VIWEBPOS_INCLUDES . 'data.php',
			VIWEBPOS_INCLUDES . 'functions.php',
			VIWEBPOS_INCLUDES . 'support.php',
		);
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
		villatheme_include_folder( VIWEBPOS_INCLUDES . "class" . DIRECTORY_SEPARATOR, 'just_require' );
		villatheme_include_folder( VIWEBPOS_ADMIN, 'VIWEBPOS_Admin_' );
		villatheme_include_folder( VIWEBPOS_PLUGINS, 'VIWEBPOS_Plugins_' );
		if ( ! is_admin() || wp_doing_ajax() ) {
			villatheme_include_folder( VIWEBPOS_FRONTEND, 'VIWEBPOS_Frontend_' );
		}
	}


	public function activated_plugin( $plugin,$network_wide ) {
		if ( is_plugin_active( 'webpos-woocommerce-pos-point-of-sale/webpos-woocommerce-pos-point-of-sale.php' ) ) {
			return;
		}
		if ( $plugin !== 'webpos-point-of-sale-for-woocommerce/webpos-point-of-sale-for-woocommerce.php' ) {
			return;
		}
		if ( ! class_exists( 'VIWEBPOS_Transactions_Table' ) ) {
			require_once VIWEBPOS_INCLUDES  . "class" . DIRECTORY_SEPARATOR. 'class-transaction-table.php';
		}
		global $wpdb;
		if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
			$current_blog = $wpdb->blogid;
			$blogs        = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			//Multi site activate action
			foreach ( $blogs as $blog ) {
				switch_to_blog( $blog );
				VIWEBPOS_Transactions_Table::create_table();
			}
			switch_to_blog( $current_blog );
		} else {
			//Single site activate action
			VIWEBPOS_Transactions_Table::create_table();
		}
		global $wp_roles;
		if ( ! $wp_roles ) {
			$wp_roles = new WP_Roles();
		}
		if ( ! in_array( 'cashier', $wp_roles->get_names() ) ) {
			add_role( 'cashier', 'Cashier', array( 'read' => true, 'level_0' => true ) );
		}
		if ( (! $wp_roles->get_role( 'administrator' )->has_cap( 'cashier' )) ||
		     (! $wp_roles->get_role( 'shop_manager' )->has_cap( 'cashier' )) ||
		     ((current_user_can('shop_manager') || current_user_can('administrator')) && !current_user_can('cashier')) ) {
			$wp_roles->add_cap( 'shop_manager', 'cashier' );
			$wp_roles->add_cap( 'administrator', 'cashier' );
		}
	}
	public function update_database(){
		$this->update_column( 'viwebpos_transactions', array('currency','currency_symbol'));
	}
	public function update_column( $table, $cols=array(), $after=array(), $formats=array() ) {
		global $wpdb;
		$updates = get_option('viwebpos_update_database',array());
		$is_update = false;
		if (empty($updates[$table])){
			$updates[$table] = array();
		}
		foreach ($cols as $k => $col){
			if (!empty($updates[$table][$col])){
				continue;
			}
			$sql = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$wpdb->prefix}{$table}' AND COLUMN_NAME = '{$col}'";
			$check_exist = $wpdb->query( $sql );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $check_exist ) {
				$format = $formats[$k] ?? 'TEXT';
				$sql_add_col = !empty($after[$k]) ? " ALTER TABLE {$wpdb->prefix}{$table} ADD $col {$format}  AFTER {$after[$k]}" : " ALTER TABLE {$wpdb->prefix}{$table} ADD $col {$format}" ;
				$result      = $wpdb->query( $sql_add_col );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				if ( $result ) {
					$is_update = true;
					$updates[$table][$col] = 1;
				}
			} else {
				$is_update = true;
				$updates[$table][$col] = 1;
			}
		}
		if ($is_update){
			update_option('viwebpos_update_database', $updates);
		}
	}
}

new VIWEBPOS_POINT_OF_SALE_FOR_WOO();