jQuery(document).ready(function ($) {
    'use strict';
    let search_order, search_order_result = {};

    $(document.body).on('viwebpos-orders-load', function () {
        if (!$('.viwebpos-header-search-order').length) {
            let html = '<div class="viwebpos-header-search viwebpos-header-search-order">';
            html += '<div class="vi-ui right action labeled input">';
            html += '<div class="viwebpos-header-search-icon"><i class="icon search"></i></div>';
            html += '<input type="text" class="viwebpos-search-input viwebpos-search-order" placeholder="' + viwebpos.search_order + '">';
            html += '<select class="vi-ui dropdown viwebpos-order-type">';
            $.each(viwebpos_text.order_types, function (k, v) {
                html += `<option value="${k}">${v}</option>`;
            });
            html += '</select>';
            html += '</div>';
            $('.viwebpos-header-search-wrap').html(html);
            $('.viwebpos-order-type').dropdown('set selected', $('.viwebpos-header-action-icons-wifi-offline').length ? 'offline' : 'online_pos');
            $('.viwebpos-order-type').dropdown({
                onChange: function (val) {
                    $('.viwebpos-search-order').val(null);
                    $(document.body).trigger('viwebpos_order_refresh_html');
                }
            });
        }
        if (!$('.viwebpos-orders-container').length) {
            let html = '<div class="viwebpos-container-element viwebpos-orders-container"><div class="viwebpos-orders-list-wrap">';
            html += '<div class="viwebpos-orders-list"></div></div><div class="viwebpos-order-details-wrap">';
            html += '<div class="viwebpos-order-detail-header"></div>';
            html += '<div class="viwebpos-order-detail-products"></div>';
            html += '<div class="viwebpos-order-detail-totals"></div></div>';
            html += '</div>';
            $('.viwebpos-container-wrap').append(html)
        }
        setTimeout(function () {
            $(document.body).trigger('viwebpos-frontend-loaded', ['orders']);
            $(document.body).trigger('viwebpos_order_refresh_html');
        }, 100);
    });

    $(document).on('click keyup', '.viwebpos-search-order', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let $this = $(this);
        let val = $this.val(),
            old_key = $this.data('old_key') || '';
        if (val === old_key) {
            return false;
        }
        $this.data('page', 1);
        $this.data('old_key', val);
        $(document.body).trigger('viwebpos_order_refresh_html', [val]);
    });

    $(document).on('click', '.viwebpos-search-order-row', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let $this = $(this);
        let order_id = $(this).data('id'),
            old_order_id = $('.viwebpos-order-details-wrap').data('id') ? $('.viwebpos-order-details-wrap').data('id') : '';
        if (old_order_id && old_order_id == order_id) {
            return false;
        }

        if (!order_id) {
            return false;
        }
        let order = $(document.body).triggerHandler('viwebpos_search_data', ['order', order_id, 5, 1]);
        if (typeof order.then === 'function') {
            order.then(function (result) {
                $(document.body).trigger('viwebpos_order_details', [result]);
            })
        } else {
            $(document.body).trigger('viwebpos_order_details', [order]);
        }
        $('.viwebpos-order-active').removeClass('viwebpos-order-active');
        $(this).addClass('viwebpos-order-active');
    });

    $(document).on('click', '.viwebpos-search-order-more', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        let search_wrap = $('.viwebpos-search-input.viwebpos-search-order'),
            to_page = search_wrap.data('page') ? search_wrap.data('page') : '',
            key = search_wrap.data('old_key') || '';
        if (!to_page) {
            return false;
        }
        $(document.body).trigger('viwebpos_order_refresh_html', [key, to_page, undefined, true]);
    });

    $(document.body).on('viwebpos_order_details', function (e, order) {
        let html = '', wrap;
        wrap = $('.viwebpos-order-details-wrap');
        wrap.html('');
        wrap.data('id', order.id);
        if (!order) {
            wrap.html('<div class="viwebpos-order-details-empty">' + (viwebpos.search_order_empty || 'No order found') + '</div>');
        }
        let user = order.billing_address.first_name + ' ' + order.billing_address.last_name;
        if (!order.billing_address.first_name && !order.billing_address.last_name) {
            user = order.billing_address.phone;
            if (!order.billing_address.phone) {
                user = order.email;
                if (!order.email) user = viwebpos.guest_title;
            }
        }
        let is_discount = parseFloat(order.total_discount) > 0 ? '- ' : '';
        html += '<div class="viwebpos-order-detail-header"><span class="viwebpos-order-detail-header-order-id">' + viwebpos.order_title + ' #' + order.id + '';
        html += '</span><span class="viwebpos-order-detail-header-customer"><i class="user outline icon"></i>' + user + '</span></div>';

        html += '<div class="viwebpos-order-detail-products">';
        $.each(order.line_items, function (key, val) {
            let item_image = val.image || '<img src="' + viwebpos.wc_product_placeholder + '">';
            html += `<div class="viwebpos-order-detail-product-wrap" title="${val.name}">`;
            html += '<div class="viwebpos-order-detail-product-image">';
            html += '<div class="viwebpos-product-image">' + item_image + '</div>';
            html += '</div>';
            html += '<div class="viwebpos-order-detail-product">';
            html += '<div class="viwebpos-order-detail-product1">';
            html += '<div class="viwebpos-order-detail-product-name">' + val.name + '</div>';
            let subtotal = viwebpos_get_price_html(val.subtotal, null, null, null, null, order.currency_symbol);
            if (val.refund_total && 0 !== parseFloat(val.refund_total)) {
                let refund_total = parseFloat(val.refund_total), current_subtotal = parseFloat(val.subtotal);
                current_subtotal = refund_total > 0 ? current_subtotal - refund_total : current_subtotal + refund_total;
                current_subtotal = viwebpos_get_price_html(current_subtotal, null, null, null, null, order.currency_symbol);
                subtotal = `<del><span class="amount">${subtotal}</span></del><ins><span class="amount">${current_subtotal}</span></ins>`;
            }
            html += '<div class="viwebpos-order-detail-product-price">' + subtotal + '</div>';
            html += '</div>';
            html += '<div class="viwebpos-order-detail-product2">';
            html += '<div class="viwebpos-order-detail-product-qty"><span>' + viwebpos_get_price_html(val.price, null, null, null, null, order.currency_symbol) + '</span> x <span>' + val.quantity + '</span></div>';
            if (val.note) {
                html += '<div class="viwebpos-order-detail-product-note">' + viwebpos.transaction_table_title_note + ': ' + val.note + '</div>';
            }
            html += '</div></div>';
            html += '</div>';
        });
        html += '</div>';

        html += '<div class="viwebpos-order-detail-totals">';
        if (order.note) {
            html += '<div class="viwebpos-order-detail-notes">' + viwebpos.transaction_table_title_note + ': ' + order.note + '</div>';
        }
        html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + viwebpos.subtotal_title + '</div>';
        html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(order.subtotal, null, null, null, null, order.currency_symbol) + '</div></div>';
        html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + viwebpos.tax_title + '</div>';
        let total_tax = viwebpos_get_price_html(order.total_tax, null, null, null, null, order.currency_symbol);
        if (order.refund_total_tax && 0 !== parseFloat(order.refund_total_tax)) {
            let refund_total_tax = parseFloat(order.refund_total_tax), current_total_tax = parseFloat(order.total_tax);
            current_total_tax = refund_total_tax > 0 ? current_total_tax - refund_total_tax : current_total_tax + refund_total_tax;
            current_total_tax = viwebpos_get_price_html(current_total_tax, null, null, null, null, order.currency_symbol);
            total_tax = `<del><span class="amount">${total_tax}</span></del><ins><span class="amount">${current_total_tax}</span></ins>`;
        }
        html += '<div class="viwebpos-order-total-line-value">' + total_tax + '</div></div>';
        if (0 !== parseFloat(order.total_shipping)) {
            let total_shipping = viwebpos_get_price_html(order.total_shipping, null, null, null, null, order.currency_symbol);
            if (order.refund_total_shipping && 0 !== parseFloat(order.refund_total_shipping)) {
                let refund_total_shipping = parseFloat(order.refund_total_shipping), current_total_shipping = parseFloat(order.total_shipping);
                current_total_shipping = refund_total_shipping > 0 ? current_total_shipping - refund_total_shipping : current_total_shipping + refund_total_shipping;
                current_total_shipping = viwebpos_get_price_html(current_total_shipping, null, null, null, null, order.currency_symbol);
                total_shipping = `<del><span class="amount">${total_shipping}</span></del><ins><span class="amount">${current_total_shipping}</span></ins>`;
            }
            html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + viwebpos.ship_title + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + total_shipping + '</div></div>';
        }
        if (is_discount !== '') {
            html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + viwebpos.discount_title + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + is_discount + viwebpos_get_price_html(order.total_discount, null, null, null, null, order.currency_symbol) + '</div></div>';
        }
        if (order.fee_lines && order.fee_lines.length) {
            $.each(order.fee_lines, function (k, v) {
                html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + v.title + '</div>';
                html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(parseFloat(v.total) + parseFloat(v.total_tax), null, null, null, null, order.currency_symbol) + '</div></div>';
            });
        }
        html += '<div class="viwebpos-order-total-line viwebpos-order-total-line1"><div class="viwebpos-order-total-line-title">' + viwebpos.total_title + '</div>';
        html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(order.total, null, null, null, null, order.currency_symbol) + '</div></div>';
        if (order.pos_payment_details && Object.keys(order.pos_payment_details).length) {
            let payment_html = '';
            $.each(order.pos_payment_details, function (k, v) {
                let order_method = '';
                if (order.payment_details.method_id === 'multi') {
                    order_method = viwebpos.viwebpos_payments && viwebpos.viwebpos_payments[k] ? viwebpos.viwebpos_payments[k]['title'] || viwebpos.paid_title : k || viwebpos.paid_title;
                } else {
                    order_method = viwebpos.viwebpos_payments && viwebpos.viwebpos_payments[order.payment_details.method_id] ? viwebpos.viwebpos_payments[k]['title'] : (k ?? viwebpos.paid_title);
                }
                payment_html += '<div class="viwebpos-order-total-line viwebpos-payment-label"><div class="viwebpos-order-total-line-title">' + order_method + '</div>';
                payment_html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(v.paid || 0, null, null, null, null, order.currency_symbol) + '</div></div>';
            });
            html += '<div class="viwebpos-order-total-line viwebpos-order-total-line1"><div class="viwebpos-order-total-line-title">' + viwebpos.paid_title + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(order.pos_total_paid || 0, null, null, null, null, order.currency_symbol) + '</div></div>';
            html += payment_html;
        } else {
            let order_method = order.payment_details.method_title;
            if (!order_method && order.payment_details.method_id && viwebpos.viwebpos_payments && typeof viwebpos.viwebpos_payments[order.payment_details.method_id] != "undefined") {
                order_method = viwebpos.viwebpos_payments[order.payment_details.method_id]['title'];
            }
            if (!order_method) {
                order_method = order.payment_details.method_id;
            }
            if (!order_method) {
                order_method = viwebpos.paid_title;
            }
            html += '<div class="viwebpos-order-total-line viwebpos-order-total-line1"><div class="viwebpos-order-total-line-title">' + order_method + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(parseFloat(order.pos_total_paid), null, null, null, null, order.currency_symbol) + '</div></div>';
        }
        html += '<div class="viwebpos-order-total-line"><div class="viwebpos-order-total-line-title">' + viwebpos.change_title + '</div>';
        html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(parseFloat(order.pos_change), null, null, null, null, order.currency_symbol) + '</div></div>';
        if (order.refund_total && 0 !== parseFloat(order.refund_total)) {
            let refund_total = parseFloat(order.refund_total);
            html += '<div class="viwebpos-order-total-line viwebpos-order-total-refunded"><div class="viwebpos-order-total-line-title">' + viwebpos_text.refunded_title + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(-refund_total, null, null, null, null, order.currency_symbol) + '</div></div>';
            html += '<div class="viwebpos-order-total-line viwebpos-order-total-line1"><div class="viwebpos-order-total-line-title">' + viwebpos_text.net_payment_title + '</div>';
            html += '<div class="viwebpos-order-total-line-value">' + viwebpos_get_price_html(order.total - refund_total, null, null, null, null, order.currency_symbol) + '</div></div>';
        }
        html += '<div class="viwebpos-order-buttons">';
        html += '<button type="button" class="vi-ui button teal viwebpos-order-print viwebpos-order-button" data-order_id="' + order.id + '"><span><i class="print icon"></i></span>' + viwebpos.print_title + '</button>';
        html += '</div>';
        html += '</div>';
        wrap.html(html);
        if (order.customer_id) {
            wrap.find('.viwebpos-order-detail-header-customer').addClass('viwebpos-url').data({href: viwebpos.pos_pathname + '/customers?customer=' + order.customer_id});
        }
    });

    $(document.body).on('viwebpos_order_refresh_html', function (e, key = '', page = 1, limit = 20, change_page = false, network = null) {
        if (search_order) {
            clearTimeout(search_order);
        }
        search_order = setTimeout(function (key) {
            let orders = $(document.body).triggerHandler('viwebpos_search_data', ['orders', key, limit, page, network ?? $('.viwebpos-order-type').dropdown('get value')]);
            $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
            if (typeof orders.then === 'function') {
                orders.then(function (result) {
                    viwebpos_orders_get_html(result, page, key, change_page);
                });
            } else {
                viwebpos_orders_get_html(orders, page, key, change_page);
            }
        }, 100, key);
    });

    function viwebpos_orders_get_html(orders, current_page = 1, key = '', change_page = false) {
        $('.viwebpos-wrap').removeClass('viwebpos-wrap-loading');
        let wrap = $('.viwebpos-orders-container'),
            list_wrap = $('.viwebpos-orders-list'),
            search_wrap = $('.viwebpos-search-input.viwebpos-search-order'),
            html = '',
            next_page = false;
        if (!list_wrap.length) {
            $('.viwebpos-orders-list-wrap').prepend('<div class="viwebpos-orders-list"></div>');
            list_wrap = $('.viwebpos-orders-list');
        }
        if (!orders) {
            list_wrap.html('<div class="viwebpos-search-order-empty">' + (viwebpos.search_order_empty || 'No order found') + '</div>');
            return;
        }
        wrap.find('.viwebpos-search-order-more').remove();
        if (current_page === 1 && !change_page) {
            list_wrap.html('');
            wrap.find('.viwebpos-order-details-wrap').html('');
        }
        if (orders.length && orders.at(-1)['next_page']) {
            next_page = orders.at(-1)['next_page'];
            search_wrap.data('page', next_page);
            orders.pop();
        }
        let show_all_type = $('.viwebpos-order-type').dropdown('get value') === 'all';
        $.each(orders, function (k, v) {
            if (!change_page) html = '';
            html += '<div class="viwebpos-search-order-row" data-id="' + v.id + '">';
            html += '<div class="viwebpos-search-order-details" >';
            html += '<div class="viwebpos-search-order-name">';
            html += '<div class="viwebpos-search-order-id">' + viwebpos.order_title + ' #<span>' + v.id;
            if (show_all_type) {
                html += `<span class="viwebpos-search-order-type viwebpos-search-order-type-${v.type}">${viwebpos_text.order_types[v.type]}</span>`;
            }
            html += '</span></div>';
            html += '<div class="viwebpos-search-order-date"><span><i class="clock outline icon"></i></span>' + v.created_at + '</div>';
            if (!v.email && !v.customer_id) {
                html += '<div class="viwebpos-search-order-user"><i class="user outline icon"></i>' + viwebpos.guest_title + '</div>';
            } else {
                if (v.billing_address.last_name || v.billing_address.first_name) {
                    html += '<div class="viwebpos-search-order-user"><i class="user outline icon"></i>' + v.billing_address.first_name + ' ' + v.billing_address.last_name + '</div>';
                }
                if (v.email || v.billing_address.email) {
                    html += '<div class="viwebpos-search-order-user"><i class="envelope outline icon"></i>' + (v.email || v.billing_address.email) + '</div>';
                }
            }
            html += '</div>';
            html += '<div class="viwebpos-search-order-count">';
            html += '<div class="viwebpos-search-order-price">' + viwebpos_get_price_html(v.total, null, null, null, null, v.currency_symbol) + '</div>';
            html += '<div class="viwebpos-search-order-status">' + v.status + '</div>';
            if (v.line_items.length === 1) {
                html += '<div class="viwebpos-search-order-items">' + v.line_items.length + ' item</div>';
            } else {
                html += '<div class="viwebpos-search-order-items">' + v.line_items.length + ' items</div>';
            }
            html += '</div>';
            html += '<div class="viwebpos-order-arrow"><i class="angle right icon"></i></div>';
            html += '</div>';
            html += '</div>';
            if (!change_page) list_wrap.append(html);
        });
        if (change_page) list_wrap.append(html);
        if (next_page) {
            let more_btn = '<div class="viwebpos-search-order-more"><span class="vi-ui teal button">' + (viwebpos.load_more_title || 'Load more') + '</span></div>';
            list_wrap.append(more_btn);
        }
        if (!change_page) {
            setTimeout(function () {
                viwebpos_order_detail_init(orders[0]);
            }, 200);
        }
        if (!html) {
            list_wrap.html('<div class="viwebpos-search-order-empty">' + (viwebpos.search_order_empty || 'No order found') + '</div>');
        }
    }

    function viwebpos_order_detail_init(order_data) {
        let $this = $('.viwebpos-orders-list .viwebpos-search-order-row:first-child');
        if (!order_data) {
            $('.viwebpos-order-details-wrap').html('');
            return false;
        }
        if (typeof order_data.then === 'function') {
            order_data.then(function (result) {
                $(document.body).trigger('viwebpos_order_details', [result]);
            })
        } else {
            $(document.body).trigger('viwebpos_order_details', [order_data]);
        }
        $('.viwebpos-order-active').removeClass('viwebpos-order-active');
        $this.addClass('viwebpos-order-active');
    }

    $(document).on('click', '.viwebpos-order-print:not(.loading)', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        $(this).addClass('loading');
        $(document.body).trigger('viwebpos_print_receipt', [$(this).data('order_id')]);
    });
    $(document).on('click', '.viwebpos-popup-bt-print-receipt:not(.viwebpos-popup-bt-loading)', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let form = $(this).closest('.viwebpos-popup-wrap-print-receipt'), button = $(this);
        $(document.body).trigger('villatheme_show_message_timeout', [$('.villatheme-show-message-message-print-receipt'), 1]);
        form.addClass('viwebpos-popup-wrap-loading');
        button.addClass('viwebpos-popup-bt-loading');
        let input = form.find('.viwebpos-print-receipt-order');
        $(document.body).trigger('viwebpos_print_receipt', [input.val(), input.data('order_data')]);
    });

});