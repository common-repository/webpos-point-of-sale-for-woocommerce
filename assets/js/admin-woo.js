jQuery(document).ready(function () {
    'use strict';
    jQuery(document).on('change','.viwebpos_is_pos', function (e) {
        if (jQuery(this).val()==='yes'){
            jQuery('.viwebpos-cashier-search').removeClass('hidden');
        }else {
            jQuery('.viwebpos-cashier-search').addClass('hidden').val('');
        }
    });
    if (typeof viwebpos_admin_product !== "undefined") {
        jQuery(document.body).on('woocommerce-product-type-change', function (e, select_val) {
            if (jQuery.inArray(select_val, ['grouped', 'external']) !== -1) {
                jQuery('.viwebpos_barcode_field').addClass('hidden');
            } else {
                jQuery('.viwebpos_barcode_field').removeClass('hidden');
            }
        });
        if (viwebpos_admin_product.auto_create_barcode_by_sku) {
            jQuery(document).on('keydown change', 'input[name=_sku], input[name^=variable_sku]', function () {
                let sku = jQuery(this);
                let val = sku.val();
                if (sku.attr('name').indexOf('variable') !== -1) {
                    sku.closest('.woocommerce_variable_attributes').find('.viwebpos-barcode-dynamic').val(val);
                } else {
                    sku.closest('#inventory_product_data').find('.viwebpos-barcode-dynamic').val(val);
                }
            });
        }
        jQuery(document).on('woocommerce_variations_loaded', '#woocommerce-product-data', function () {
            jQuery('.viwebpos-barcode-dynamic-wrap').each(function (k,v) {
               let temp = jQuery(v),
                   wrap = jQuery(v).closest('.woocommerce_variable_attributes'),
                   loop = jQuery(v).find('.viwebpos-barcode-dynamic').data('loop');
               jQuery(this).remove();
               if (wrap.find('.variable_sku'+loop+'_field').length){
                   temp.insertAfter(wrap.find('.variable_sku'+loop+'_field'));
               }else {
                   temp.insertAfter(wrap.find('.upload_image'));
               }
            });
        });
    }
});