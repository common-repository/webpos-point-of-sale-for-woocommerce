<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$currency_position = strpos( get_woocommerce_price_format(), '%1' );
$symbol_html       = get_woocommerce_currency_symbol();
$custom_attributes = array();
if ( ! empty( $custom_attr ) ) {
	foreach ( $custom_attr as $k => $v ) {
		if ( ! $k ) {
			continue;
		}
		$custom_attributes[] = esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
}
if ( $currency_position ) {
	?>
    <div class="viwebpos-price-input-wrap viwebpos-price-input-wrap-symbol viwebpos-price-input-wrap-symbol-right">
        <input type="number" class="viwebpos-price-input-value" data-can_reset="1" data-value_default="" name="<?php echo esc_attr( $input_name ); ?>" value="" <?php echo esc_attr( implode( ' ', $custom_attributes ) ) ?> >
        <div class="viwebpos-price-input-symbol"><?php echo wp_kses_post( $symbol_html ); ?></div>
    </div>
	<?php
} else {
	?>
    <div class="viwebpos-price-input-wrap viwebpos-price-input-wrap-symbol viwebpos-price-input-wrap-symbol-left">
        <div class="viwebpos-price-input-symbol"><?php echo wp_kses_post( $symbol_html ); ?></div>
        <input type="number" class="viwebpos-price-input-value" data-can_reset="1" data-value_default="" name="<?php echo esc_attr( $input_name ); ?>" value="" <?php echo esc_attr( implode( ' ', $custom_attributes ) ) ?> >
    </div>
	<?php
}
?>