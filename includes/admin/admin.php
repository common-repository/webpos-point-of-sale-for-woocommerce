<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWEBPOS_Admin_Admin {
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter(
			'plugin_action_links_webpos-point-of-sale-for-woocommerce/webpos-point-of-sale-for-woocommerce.php', array(
				$this,
				'settings_link'
			)
		);
	}

	public function settings_link( $links ) {
		$settings_link = sprintf( '<a href="%s?page=viwebpos" title="%s">%s</a>', esc_url( admin_url( 'admin.php' ) ),
			esc_attr__( 'Settings', 'webpos-point-of-sale-for-woocommerce' ),
			esc_html__( 'Settings', 'webpos-point-of-sale-for-woocommerce' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function init() {
		$this->load_plugin_textdomain();
		if ( class_exists( 'VillaTheme_Support' ) ) {
			new VillaTheme_Support(
				array(
					'support'   => 'https://wordpress.org/support/plugin/webpos-point-of-sale-for-woocommerce/',
					'docs'      => 'http://docs.villatheme.com/?item=webpos',
					'review'    => 'https://wordpress.org/support/plugin/webpos-point-of-sale-for-woocommerce/reviews/?rate=5#rate-response',
					'pro_url'   => 'https://1.envato.market/7m7Jmd',
					'css'       => VIWEBPOS_CSS,
					'image'     => VIWEBPOS_IMAGES,
					'slug'      => 'webpos-point-of-sale-for-woocommerce',
					'menu_slug' => 'viwebpos',
					'survey_url' => 'https://script.google.com/macros/s/AKfycbxsCRNgW63LqljPCg7GT4nDdXbSNwT4tMCkzxPw7R3-TiwGy-gdAlc6ZjFNDeHvZPbJ/exec',
					'version'   => VIWEBPOS_VERSION
				)
			);
		}
	}

	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'webpos-point-of-sale-for-woocommerce' );
		load_textdomain( 'webpos-point-of-sale-for-woocommerce', VIWEBPOS_LANGUAGES . "webpos-point-of-sale-for-woocommerce-$locale.mo" );
		load_plugin_textdomain( 'webpos-point-of-sale-for-woocommerce', false, VIWEBPOS_LANGUAGES );
	}
}