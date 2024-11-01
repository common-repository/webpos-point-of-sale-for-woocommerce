jQuery(document).ready(function () {
    'use strict';
    jQuery('.vi-ui.vi-ui-main.tabular.menu .item').vi_tab({
        history: true,
        historyType: 'hash'
    });
    jQuery('.vi-ui.checkbox').off().checkbox();
    jQuery('.vi-ui.dropdown').off().dropdown();
    jQuery('.viwebpos-pos_order_status').dropdown({
        onChange: function (val) {
            if (val === 'wc-pending') {
                jQuery('.viwebpos-pos_send_mail-wrap').addClass('viwebpos-hidden');
            } else {
                jQuery('.viwebpos-pos_send_mail-wrap').removeClass('viwebpos-hidden');
            }
        }
    });
    jQuery('input[type="checkbox"]').off().on('change', function () {
        if (jQuery(this).prop('checked')) {
            jQuery(this).parent().find('input[type="hidden"]').val(1);
        } else {
            jQuery(this).parent().find('input[type="hidden"]').val(0);
        }
    });
});
