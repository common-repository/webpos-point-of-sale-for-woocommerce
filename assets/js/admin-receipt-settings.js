jQuery(document).ready(function ($) {
    'use strict';
    if (typeof viwebpos_receipts === "undefined" || !viwebpos_receipts.action) {
        return;
    }
    $(document).on('viwebpos-frame-print', function (e, button) {
        if (!$('#viwebpos-frame-print').length) {
            return false;
        }
        $('#viwebpos-frame-print').css('left', '0');
        window.frames['viwebpos-frame-print'].onload = function () {
            window.frames['viwebpos-frame-print'].focus();
            window.frames['viwebpos-frame-print'].print();
            $('#viwebpos-frame-print').css('left', '100%');
            button.removeClass('loading');
            $('.viwebpos-save').attr('type', 'submit');
        };
    });
    if ($('.vi-ui.vi-ui-main.tabular.menu .item').length) {
        $('.vi-ui.vi-ui-main.tabular.menu .item').vi_tab({
            history: true,
            historyType: 'hash'
        });
    }
    $('.vi-ui.checkbox').off().checkbox();
    $('.vi-ui.dropdown').off().dropdown();
    $('input[type="checkbox"]').off().on('change', function () {
        if ($(this).prop('checked')) {
            $(this).parent().find('input[type="hidden"]').val(1);
            switch ($(this).attr('id')) {
                case 'viwebpos-receipts-logo-checkbox':
                    $('.viwebpos-receipt_logo-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-logo').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-header-wrap').removeClass('viwebpos-bill-header-max');
                    break;
                case 'viwebpos-receipts-date_create-checkbox':
                    $('.viwebpos-receipts-date_create-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-date-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-order');
                    break;
                case 'viwebpos-receipts-order_id-checkbox':
                    $('.viwebpos-receipts-order_id-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-id-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-order');
                    break;
                case 'viwebpos-receipts-cashier-checkbox':
                    $('.viwebpos-receipts-cashier-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-cashier-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-order');
                    break;
                case 'viwebpos-receipts-customer-checkbox':
                    $('.viwebpos-receipts-customer-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-style');
                    break;
                case 'viwebpos-receipts-customer_phone-checkbox':
                    $('.viwebpos-receipts-customer_phone-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-phone-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-style');
                    break;
                case 'viwebpos-receipts-customer_address-checkbox':
                    $('.viwebpos-receipts-customer_address-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-address-wrap').removeClass('viwebpos-hidden');
                    $('.viwebpos-receipt-preview .viwebpos-bill-top').addClass('viwebpos-header-style');
                    break;
                case 'viwebpos-receipts-product_id-checkbox':
                    $('.viwebpos-receipts-product_id-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-id-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-id').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-id-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-id').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_sku-checkbox':
                    $('.viwebpos-receipts-product_sku-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-sku-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-sku').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-sku-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-sku').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_price-checkbox':
                    $('.viwebpos-receipts-product_price-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-price-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-price').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-price-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-price').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_quantity-checkbox':
                    $('.viwebpos-receipts-product_quantity-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-quantity-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-quantity').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-quantity-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-quantity').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_subtotal-checkbox':
                    $('.viwebpos-receipts-product_subtotal-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-subtotal-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-subtotal').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-subtotal-label').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-subtotal').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_total-checkbox':
                    $('.viwebpos-receipts-order_total-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-total-wrap').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_tax-checkbox':
                    $('.viwebpos-receipts-order_tax-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-tax-wrap').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_discount-checkbox':
                    $('.viwebpos-receipts-order_discount-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-discount-wrap').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_paid-checkbox':
                    $('.viwebpos-receipts-order_paid-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-paid-wrap').removeClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_change-checkbox':
                    $('.viwebpos-receipts-order_change-enable').removeClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-change-wrap').removeClass('viwebpos-hidden');
                    break;
            }
        } else {
            $(this).parent().find('input[type="hidden"]').val(0);
            switch ($(this).attr('id')) {
                case 'viwebpos-receipts-logo-checkbox':
                    $('.viwebpos-receipt_logo-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-logo').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-header-wrap').addClass('viwebpos-bill-header-max');
                    break;
                case 'viwebpos-receipts-date_create-checkbox':
                    $('.viwebpos-receipts-date_create-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-date-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-order_id-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-cashier-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-order');
                    }
                    break;
                case 'viwebpos-receipts-order_id-checkbox':
                    $('.viwebpos-receipts-order_id-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-id-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-date_create-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-cashier-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-order');
                    }
                    break;
                case 'viwebpos-receipts-cashier-checkbox':
                    $('.viwebpos-receipts-cashier-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-cashier-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-order_id-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-date_create-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-order');
                    }
                    break;
                case 'viwebpos-receipts-customer-checkbox':
                    $('.viwebpos-receipts-customer-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-customer_phone-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-customer_address-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-style');
                    }
                    break;
                case 'viwebpos-receipts-customer_phone-checkbox':
                    $('.viwebpos-receipts-customer_phone-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-phone-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-customer-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-customer_address-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-style');
                    }
                    break;
                case 'viwebpos-receipts-customer_address-checkbox':
                    $('.viwebpos-receipts-customer_address-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-customer-address-wrap').addClass('viwebpos-hidden');
                    if (!$('#viwebpos-receipts-customer_phone-checkbox').prop('checked') &&
                        !$('#viwebpos-receipts-customer-checkbox').prop('checked')) {
                        $('.viwebpos-receipt-preview .viwebpos-bill-top').removeClass('viwebpos-header-style');
                    }
                    break;
                case 'viwebpos-receipts-product_id-checkbox':
                    $('.viwebpos-receipts-product_id-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-id-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-id').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-id-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-id').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_sku-checkbox':
                    $('.viwebpos-receipts-product_sku-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-sku-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-sku').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-sku-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-sku').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_price-checkbox':
                    $('.viwebpos-receipts-product_price-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-price-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-price').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-price-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-price').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_quantity-checkbox':
                    $('.viwebpos-receipts-product_quantity-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-quantity-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-quantity').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-quantity-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-quantity').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-product_subtotal-checkbox':
                    $('.viwebpos-receipts-product_subtotal-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-subtotal-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-product-subtotal').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-subtotal-label').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-col-product-subtotal').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_total-checkbox':
                    $('.viwebpos-receipts-order_total-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-total-wrap').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_tax-checkbox':
                    $('.viwebpos-receipts-order_tax-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-tax-wrap').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_discount-checkbox':
                    $('.viwebpos-receipts-order_discount-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-discount-wrap').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_paid-checkbox':
                    $('.viwebpos-receipts-order_paid-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-paid-wrap').addClass('viwebpos-hidden');
                    break;
                case 'viwebpos-receipts-order_change-checkbox':
                    $('.viwebpos-receipts-order_change-enable').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-order-change-wrap').addClass('viwebpos-hidden');
                    break;
            }
        }
    });
    if (['edit'].includes(viwebpos_receipts.action)) {
        setTimeout(function () {
            $(document).trigger('viwebpos_receipt_render_html');
        }, 100);
    }
    $(document).on('viwebpos_receipt_render_html', function () {
        switch (viwebpos_receipts.type) {
            default:
                upload_image();
                $('.viwebpos-bill-header-wrap').addClass('viwebpos-bill-header-' + $('#viwebpos-receipts-logo_pos').val());
                if (!parseInt($('#viwebpos-receipts-logo').val())) {
                    $('.viwebpos-bill-logo').addClass('viwebpos-hidden');
                    $('.viwebpos-bill-header-wrap').addClass('viwebpos-bill-header-max');
                }
                $('.viwebpos-bill-logo-preview').html(`<img src="${$('.viwebpos-upload-logo-preview img').attr('src')}" data-src_placeholder="${$('.viwebpos-upload-logo-preview img').data('src_placeholder')}">`);
                let address_html = '', address_array = $('[name=contact_info]').val(),
                    address_replace = {
                        '{site_title}': viwebpos_receipts.site_title,
                        '{address_1}': viwebpos_receipts.primary_address,
                        '{address_2}': viwebpos_receipts.secondary_address,
                        '{city}': viwebpos_receipts.city,
                        '{state}': viwebpos_receipts.state,
                        '{country}': viwebpos_receipts.country,
                    };
                $.each(address_replace, function (k, v) {
                    address_array = address_array.replace(new RegExp(k, 'g'), v);
                });
                address_array = address_array.split('\n');
                $.each(address_array, function (k, v) {
                    address_html += `<div>${v}</div>`;
                });
                $('.viwebpos-bill-contact').data({
                    'title': viwebpos_receipts.site_title,
                    'address1': viwebpos_receipts.primary_address,
                    'address2': viwebpos_receipts.secondary_address,
                    'city': viwebpos_receipts.city,
                    'state': viwebpos_receipts.state,
                    'country': viwebpos_receipts.country,
                }).html(address_html);
                let option_enable = {
                        'date_create': '.viwebpos-bill-order-date-wrap',
                        'order_id': '.viwebpos-bill-order-id-wrap',
                        'cashier': '.viwebpos-bill-cashier-wrap',
                        'customer': '.viwebpos-bill-customer-wrap',
                        'customer_phone': '.viwebpos-bill-customer-phone-wrap',
                        'customer_address': '.viwebpos-bill-customer-address-wrap',
                        'product_id': '.viwebpos-bill-product-id, .viwebpos-bill-col-product-id-label, .viwebpos-bill-col-product-id',
                        'product_sku': '.viwebpos-bill-product-sku, .viwebpos-bill-col-product-sku-label, .viwebpos-bill-col-product-sku',
                        'product_price': '.viwebpos-bill-product-price, .viwebpos-bill-col-product-price-label, .viwebpos-bill-col-product-price',
                        'product_quantity': '.viwebpos-bill-product-quantity .viwebpos-bill-col-product-quantity-label, .viwebpos-bill-col-product-quantity',
                        'product_subtotal': '.viwebpos-bill-product-subtotal-label,.viwebpos-bill-col-product-subtotal-label, .viwebpos-bill-product-subtotal, .viwebpos-bill-col-product-subtotal',
                        'order_tax': '.viwebpos-bill-order-tax-wrap',
                        'order_discount': '.viwebpos-bill-order-discount-wrap',
                        'order_paid': '.viwebpos-bill-order-paid-wrap',
                        'order_change': '.viwebpos-bill-order-change-wrap',
                        'order_total': '.viwebpos-bill-order-total-wrap',
                    },
                    label_display = {
                        'date_create_label': '.viwebpos-bill-date-label',
                        'order_id_label': '.viwebpos-bill-id-label',
                        'cashier_label': '.viwebpos-bill-cashier-label',
                        'customer_label': '.viwebpos-bill-customer-label',
                        'customer_phone_label': '.viwebpos-bill-customer-phone-label',
                        'customer_address_label': '.viwebpos-bill-customer-address-label',
                    }
                $.each(option_enable, function (k, v) {
                    if (!parseInt($(`[name=${k}]`).val())) {
                        $(v).addClass('viwebpos-hidden');
                    }
                });
                $.each(label_display, function (k, v) {
                    if ($(`[name=${k}]`).val()) {
                        $(v).removeClass('viwebpos-hidden').html($(`[name=${k}]`).val());
                    }
                });
                if ($('[name=bill_title]').val()) {
                    $('.viwebpos-bill-title').removeClass('viwebpos-hidden').html($('[name=bill_title]').val())
                }
                $('.viwebpos-bill-customer-info .viwebpos-bill-customer-' + $('[name=customer_display]').val()).removeClass('viwebpos-hidden');
                let customer_address_html = '', customer_address_array = $('[name=customer_address_display]').val(),
                    customer_address_replace = {
                        '{address_line_1}': $('.viwebpos-bill-customer-address-info').data('address1'),
                        '{address_line_2}': $('.viwebpos-bill-customer-address-info').data('address2'),
                        '{city}': $('.viwebpos-bill-customer-address-info').data('city'),
                        '{state}': $('.viwebpos-bill-customer-address-info').data('state'),
                        '{country}': $('.viwebpos-bill-customer-address-info').data('country')
                    };
                $.each(customer_address_replace, function (k, v) {
                    customer_address_array = customer_address_array.replace(new RegExp(k, 'g'), v);
                });
                customer_address_array = customer_address_array.split('\n');
                $.each(customer_address_array, function (k, v) {
                    customer_address_html += `<div>${v}</div>`;
                });
                $('.viwebpos-bill-customer-address-info').html(customer_address_html);
                if (parseFloat($('[name=page_width]').val() || 0) <= 80) {
                    $('.viwebpos-bill-product-wrap, .viwebpos-bill-col-product-wrap').addClass('viwebpos-bill-product-block');
                }
                $('.viwebpos-bill-product-title-col-block, .viwebpos-bill-col-product-title-label').html($('[name=product_label]').val());
                $('.viwebpos-bill-product-subtotal-label, .viwebpos-bill-col-product-subtotal-label').html($('[name=product_subtotal_label]').val());
                $('.viwebpos-bill-col-product-id-label').html($('[name=product_id_label]').val());
                $('.viwebpos-bill-col-product-price-label').html($('[name=product_price_label]').val());
                $('.viwebpos-bill-col-product-quantity-label').html($('[name=product_quantity_label]').val());
                $('.viwebpos-bill-product-note-label').html($('[name=product_note_label]').val());
                $('.viwebpos-bill-col-product-sku-label').html($('[name=product_sku_label]').val());
                if (parseInt($('[name=product_character]').val())) {
                    let product_character = parseInt($('[name=product_character]').val());
                    $('.viwebpos-bill-product-title-inline').each(function (k, v) {
                        let original_title = $(v).data('title');
                        if (original_title.length > product_character) {
                            $(v).html(original_title.substring(0, product_character) + '...');
                        }
                    });
                    $('.viwebpos-bill-col-product-title').each(function (k, v) {
                        let original_title = $(v).data('title');
                        if (original_title.length > product_character) {
                            let note = $(v).find('.viwebpos-bill-product-note-wrap').clone();
                            $(v).html(original_title.substring(0, product_character) + '...');
                            $(v).append(note);
                        }
                    });
                }
                $('.viwebpos-bill-order-note-label').html($('[name=order_note_label]').val());
                $('.viwebpos-bill-order-tax-label').html($('[name=order_tax_label]').val());
                $('.viwebpos-bill-order-ship-label').html($('[name=order_ship_label]').val());
                $('.viwebpos-bill-order-discount-label').html($('[name=order_discount_label]').val());
                $('.viwebpos-bill-order-pos-discount-label').html($('[name=order_pos_discount_label]').val());
                $('.viwebpos-bill-order-paid-label').html($('[name=order_paid_label]').val());
                $('.viwebpos-bill-order-change-label').html($('[name=order_change_label]').val());
                $('.viwebpos-bill-order-total-label').html($('[name=order_total_label]').val());
                if ($('[name=footer_message]').val()) {
                    let footer_message_html = '', footer_message_array = $('[name=footer_message]').val();
                    footer_message_array = footer_message_array.split('\n');
                    $.each(footer_message_array, function (k, v) {
                        footer_message_html += `<div>${v}</div>`;
                    });
                    $('.viwebpos-bill-footer-message').html(footer_message_html);
                }
        }
    });

    $(document).on('change', '.viwebpos-receipt-settings-wrap select', function () {
        viwebpos_receipt_change($(this).attr('name'), $(this).val());
    });
    $(document).on('change keyup', '.viwebpos-receipt-settings-wrap textarea, .viwebpos-receipt-settings-wrap input[type="number"], .viwebpos-receipt-settings-wrap input[type="text"]', function () {
        let type = $(this).attr('name'), val = $(this).val();
        switch (type) {
            case 'page_width':
                $('.viwebpos-bill-top').removeClass('viwebpos-header-block');
                $('.viwebpos-bill-header-wrap').removeClass('viwebpos-bill-header-block');
                break;
        }
        viwebpos_receipt_change(type, val);
    });
    $(document).on('click', '.viwebpos-print-preview:not(.loading)', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let button = $(this);
        let print = new Promise(function (resolve) {
            button.addClass('loading');
            $('.viwebpos-save').attr('type', 'button');
            $('#viwebpos-frame-print').remove();
            $('body').append('<iframe id="viwebpos-frame-print" name="viwebpos-frame-print"></iframe>');
            let new_win = window.frames['viwebpos-frame-print'], temp = $('<div></div>');
            temp.append('<meta charset="utf-8">');
            temp.append($('head title').clone());
            temp.append($('#viwebpos-admin-settings-css').clone());
            temp.append($('#viwebpos-receipt-css').clone());
            temp.append($('#viwebpos-receipt-inline-css').clone());
            new_win.document.write('<!DOCTYPE html><html>');
            new_win.document.write('<head>' + temp.html() + '</head>');
            new_win.document.write('<body>' + $('.viwebpos-receipt-preview-wrap').html() + '</body>');
            new_win.document.write('</html>');
            new_win.document.close();
            resolve(new_win);
        });
        print.then(function () {
            $(document).trigger('viwebpos-frame-print', [button]);
        });
    });

    function viwebpos_receipt_change(element, val) {
        switch (element) {
            case 'customer_display':
                $('.viwebpos-receipt-preview .viwebpos-bill-customer-info span').addClass('viwebpos-hidden');
                $('.viwebpos-receipt-preview .viwebpos-bill-customer-info span.viwebpos-bill-customer-' + val).removeClass('viwebpos-hidden');
                break;
            case 'logo':
                $('.viwebpos-receipt-preview .viwebpos-bill-logo-preview img').attr('src', val);
                $('.viwebpos-upload-logo-wrap .viwebpos-upload-logo-preview img').attr('src', val);
                break;
            case 'page_width':
                $('.viwebpos-receipt-preview').css('width', val + 'mm');
                break;
            case 'page_margin':
                $('.viwebpos-receipt-preview .viwebpos-receipt-inner').css('padding', val + 'mm');
                break;
            case 'contact_info':
                let bill_contact = $('.viwebpos-receipt-preview .viwebpos-bill-contact');
                let chars = {
                        '{site_title}': bill_contact.data('title'),
                        '{address_1}': bill_contact.data('address1'),
                        '{address_2}': bill_contact.data('address2'),
                        '{city}': bill_contact.data('city'),
                        '{state}': bill_contact.data('state'),
                        '{country}': bill_contact.data('country')
                    },
                    retStr = val;
                for (let x in chars) {
                    retStr = retStr.replace(new RegExp(x, 'g'), chars[x]);
                }
                let address_array = retStr.split("\n"),
                    address_html = '';
                $.each(address_array, function (index, value) {
                    address_html += '<div>' + value + '</div>';
                });
                bill_contact.html(address_html);
                break;
            case 'customer_address_display':
                let customer_wrap = $('.viwebpos-receipt-preview .viwebpos-bill-customer-address-info'),
                    customer_retStr = val;
                let customer_chars = {
                    '{address_line_1}': customer_wrap.data('address1'),
                    '{address_line_2}': customer_wrap.data('address2'),
                    '{city}': customer_wrap.data('city'),
                    '{state}': customer_wrap.data('state'),
                    '{country}': customer_wrap.data('country')
                };
                for (let x in customer_chars) {
                    customer_retStr = customer_retStr.replace(new RegExp(x, 'g'), customer_chars[x]);
                }
                let customer_address_array = customer_retStr.split("\n"),
                    customer_address_html = '';
                $.each(customer_address_array, function (index, value) {
                    customer_address_html += '<div>' + value + '</div>';
                });
                customer_wrap.html(customer_address_html);
                break;
            case 'footer_message':
                if (viwebpos_receipts.type === 'order_item') {
                    if (val) {
                        $('.viwebpos-bill-footer').removeClass('viwebpos-hidden');
                    } else {
                        $('.viwebpos-bill-footer').addClass('viwebpos-hidden');
                    }
                }
                if (val) {
                    let footer_message = val,
                        footer_message_array = footer_message.split("\n"),
                        footer_message_html = '';
                    $.each(footer_message_array, function (index, value) {
                        footer_message_html += '<div>' + value + '</div>';
                    });
                    $('.viwebpos-bill-footer-message').html(footer_message_html);
                } else {
                    $('.viwebpos-bill-footer-message').html(val);
                }
                break;
            case 'bill_title':
                $('.viwebpos-bill-title').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-title').removeClass('viwebpos-hidden');
                }
                break;
            case 'date_create_label':
                $('.viwebpos-bill-date-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-date-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'order_id_label':
                $('.viwebpos-bill-id-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-id-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'cashier_label':
                $('.viwebpos-bill-cashier-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-cashier-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'customer_label':
                $('.viwebpos-bill-customer-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-customer-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'customer_phone_label':
                $('.viwebpos-bill-customer-phone-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-customer-phone-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'customer_address_label':
                $('.viwebpos-bill-customer-address-label').html(val).addClass('viwebpos-hidden');
                if (val) {
                    $('.viwebpos-bill-customer-address-label').removeClass('viwebpos-hidden');
                }
                break;
            case 'product_id_label':
                $('.viwebpos-bill-col-product-id-label').html(val);
                break;
            case 'product_sku_label':
                $('.viwebpos-bill-col-product-sku-label').html(val);
                break;
            case 'product_price_label':
                $('.viwebpos-bill-product-price-label, .viwebpos-bill-col-product-price-label').html(val);
                if (viwebpos_receipts.type === 'order_item') {
                    if (val) {
                        $('.viwebpos-bill-product-price-label').removeClass('viwebpos-hidden');
                    } else {
                        $('.viwebpos-bill-product-price-label').addClass('viwebpos-hidden');
                    }
                }
                break;
            case 'product_quantity_label':
                $('.viwebpos-bill-product-quantity-label').html(val);
                $('.viwebpos-bill-col-product-quantity-label').html(val);
                break;
            case 'product_subtotal_label':
                $('.viwebpos-bill-product-subtotal-label').html(val);
                $('.viwebpos-bill-col-product-subtotal-label').html(val);
                break;
            case 'product_label':
                $('.viwebpos-bill-product-title-col-block').html(val);
                $('.viwebpos-bill-col-product-title-label').html(val);
                break;
            case 'order_tax_label':
                $('.viwebpos-bill-order-tax-label').html(val);
                break;
            case 'order_discount_label':
                $('.viwebpos-bill-order-discount-label').html(val);
                break;
            case 'order_paid_label':
                $('.viwebpos-bill-order-paid-label').html(val);
                break;
            case 'order_change_label':
                $('.viwebpos-bill-order-change-label').html(val);
                break;
            case 'order_total_label':
                $('.viwebpos-bill-order-total-label').html(val);
                break;
        }
    }

    function upload_image() {
        var viwebpos_img_uploader;
        $(document).on('click', '.viwebpos-upload-logo-remove', function () {
            let wrap = $(this).closest('.viwebpos-upload-logo-wrap');
            let src_placeholder = wrap.find('.viwebpos-upload-logo-preview img').data('src_placeholder');
            wrap.find('.receipt_logo_id').val('');
            wrap.find('.viwebpos-upload-logo-preview img').attr('src', src_placeholder);
            viwebpos_receipt_change('logo', src_placeholder);
            $(this).addClass('viwebpos-hidden');
        });
        $(document).on('click', '.viwebpos-upload-logo-add-new', function (e) {
            e.preventDefault();
            let editing = $('.viwebpos-upload-img-editing');
            editing.removeClass('viwebpos-upload-img-editing');
            $(this).closest('.viwebpos-upload-logo-wrap').addClass('viwebpos-upload-img-editing');
            //If the uploader object has already been created, reopen the dialog
            if (viwebpos_img_uploader) {
                viwebpos_img_uploader.open();
                return false;
            }
            //Extend the wp.media object
            viwebpos_img_uploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Image',
                button: {
                    text: 'Choose Image'
                },
                multiple: true
            });

            //When a file is selected, grab the URL and set it as the text field's value
            viwebpos_img_uploader.on('select', function () {
                let attachment = viwebpos_img_uploader.state().get('selection').first().toJSON();
                editing.find('.receipt_logo_id').val(attachment.id);
                $('.viwebpos-receipt_logo-enable .receipt_logo_id').val(attachment.id);
                editing.find('.viwebpos-upload-logo-preview img').attr('src', attachment.url);
                editing.find('.viwebpos-upload-logo-remove').removeClass('viwebpos-hidden');
                viwebpos_receipt_change('logo', attachment.url);
                editing.removeClass('viwebpos-upload-img-editing');
            });

            //Open the uploader dialog
            viwebpos_img_uploader.open();
        });
    }
});