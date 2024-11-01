<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$page = $page_request[1] ?? '';
$rtl = is_rtl();
?>
<div class="viwebpos-header-left-wrap">
    <div class="viwebpos-header-menu-wrap">
        <div class="vi-ui <?php echo esc_attr($rtl ? 'right' : 'left'); ?> top pointing dropdown">
            <i class="bars icon"></i>
            <div class="menu viwebpos-menu-items">
	            <?php
	            if ( is_array( $menu_items ) ) {
		            foreach ( $menu_items as $key => $item ) {
			            if ( $key === 'keyboard' && ! empty( $keyboard ) ) {
				            printf( '<div class="item viwebpos-menu-item viwebpos-menu-item-%s"><i class="icon %s"></i><span class="viwebpos-menu-item-label">%s</span>',
					            esc_attr( $key ), esc_attr( $item['icon'] ?? '' ), wp_kses_post( $item['label'] ?? '' ) );
				            printf( '<div class="right menu viwebpos-keyboard-wrap">' );
				            foreach ( $keyboard as $k => $v ) {
					            printf( '<div class="item viwebpos-menu-item viwebpos-keyboard-%s">', esc_attr( $k ) );
					            if ( ! empty( $v['icon'] ) ) {
						            printf( '<i class="icon %s"></i>', esc_attr( $v['icon'] ) );
					            }
					            if ( ! empty( $v['key'] ) ) {
						            printf( '<strong class="viwebpos-menu-item-key">%s</strong>', esc_attr( $v['key'] ) );
					            }
					            printf( '<span class="viwebpos-menu-item-label">%s</span></div>', wp_kses_post( $v['label'] ?? '' ) );
				            }
				            printf( '</div></div>' );
				            continue;
			            }
			            $custom_attr = array();
			            if ( ! empty( $item['custom-attr'] ) ) {
				            foreach ( $item['custom-attr'] as $k => $v ) {
					            $custom_attr[] = esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
				            }
			            }
			            printf( '<a class="item viwebpos-menu-item viwebpos-menu-item-%s" href="%s" %s><i class="icon %s"></i><span class="viwebpos-menu-item-label">%s</span></a>',
				            esc_attr( $key ), esc_attr( $item['url'] ?? '#' ), esc_attr( implode( ' ', $custom_attr ) ), esc_attr( $item['icon'] ?? '' ), wp_kses_post( $item['label'] ?? '' ) );
		            }
	            }
	            ?>
            </div>
        </div>
    </div>
    <div class="viwebpos-header-search-wrap"></div>
</div>
<div class="viwebpos-header-right-wrap">
	<?php
	if ( is_array( $action_icons ) && ! empty( $action_icons ) ) {
		printf( '<ul class="viwebpos-header-action-icons-wrap">' );
		foreach ( $action_icons as $type => $icon ) {
			if ( $type === 'keyboard' && ! empty( $keyboard ) ) {
				printf( '<li class="vi-ui %1s top pointing dropdown viwebpos-header-action-icons">', esc_attr($rtl ? 'left' : 'right') );
				printf( '<span class="viwebpos-header-action-icons-%s" data-position="%s" data-tooltip="%s"><i class="icon %s"></i></span>',
					esc_attr( $type ), esc_attr( $icon['position'] ?? 'left center' ), wp_kses_post( $icon['tooltip'] ), esc_attr( $icon['icon'] ?? '' ) );
				printf( '<div class="menu viwebpos-keyboard-wrap">' );
				foreach ( $keyboard as $k => $v ) {
					printf( '<div class="item viwebpos-menu-item viwebpos-keyboard-%s">', esc_attr( $k ) );
					if ( ! empty( $v['icon'] ) ) {
						printf( '<i class="icon %s"></i>', esc_attr( $v['icon'] ) );
					}
					if ( ! empty( $v['key'] ) ) {
						printf( '<strong class="viwebpos-menu-item-key">%s</strong>', esc_attr( $v['key'] ) );
					}
					printf( '<span class="viwebpos-menu-item-label">%s</span></div>', wp_kses_post( $v['label'] ?? '' ) );
				}
				printf( '</div></li>' );
				continue;
			}
			if ( ! empty( $icon['tooltip'] ) ) {
				printf( '<li class="viwebpos-header-action-icons"><a href="%s"><span class="viwebpos-header-action-icons-%s" data-position="%s" data-tooltip="%s"><i class="icon %s"></i></span></a></li>',
					esc_attr( $icon['url'] ?? '#' ), esc_attr( $type ), esc_attr( $icon['position'] ?? 'left center' ), wp_kses_post( $icon['tooltip'] ), esc_attr( $icon['icon'] ?? '' ) );
			} else {
				printf( '<li class="viwebpos-header-action-icons"><a href="%s"><span class="viwebpos-header-action-icons-%s" data-position="%s"><i class="icon %s"></i></span></a></li>',
					esc_attr( $icon['url'] ?? '#' ), esc_attr( $type ), esc_attr( $icon['position'] ?? 'left center' ), esc_attr( $icon['icon'] ?? '' ) );
			}
		}
		printf( '</ul>' );
	}
	?>
</div>
