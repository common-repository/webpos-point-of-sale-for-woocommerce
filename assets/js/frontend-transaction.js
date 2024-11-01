jQuery(document).ready(function () {
    'use strict';
    let search_transaction;
    jQuery(document).on('click', '.viwebpos-add-transaction-wrap', function () {
        jQuery('.viwebpos-popup-wrap-add-transaction').removeClass('viwebpos-popup-wrap-hidden').addClass('viwebpos-popup-wrap-show');
        jQuery('.viwebpos-popup-wrap-add-transaction').find('.viwebpos-popup-bt-loading').removeClass('viwebpos-popup-bt-loading');
        jQuery('.viwebpos-popup-wrap-add-transaction').find('#viwebpos-popup-content-add-transaction-type-in').prop('checked', true);
        jQuery('.viwebpos-popup-wrap-add-transaction').find('textarea').val('');
        jQuery('.viwebpos-popup-wrap-add-transaction').find('[name="add_transaction_value"]').val('').trigger('focus');
    });
    jQuery(document).on('click', '.viwebpos-popup-bt-add-transaction:not(.viwebpos-popup-bt-loading)', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let form = jQuery(this).closest('.viwebpos-popup-wrap-add-transaction'), button = jQuery(this);
        jQuery(document.body).trigger('villatheme_show_message_timeout', [jQuery('.villatheme-show-message-message-transaction-adding'), 1]);
        form.addClass('viwebpos-popup-wrap-loading').find('.viwebpos-popup-content-value-error').removeClass('viwebpos-popup-content-value-error');
        button.addClass('viwebpos-popup-bt-loading');
        if (!form.find('.viwebpos-price-input-value').val()) {
            jQuery(document.body).trigger('villatheme_show_message', [viwebpos.transaction_add_price_empty, ['error', 'transaction-adding'], '', false, 4500]);
            form.find('.viwebpos-price-input-wrap').addClass('viwebpos-popup-content-value-error');
            form.find('.viwebpos-price-input-value').trigger('focus');
            return false;
        }
        let transaction = {
            cashier_id: viwebpos.cashier_id,
            currency: viwebpos.wc_currency,
            currency_symbol: viwebpos.wc_currency_symbol,
            note: form.find('.viwebpos-popup-content-add-transaction-reason-value').val() || ''
        };
        transaction[jQuery('[name="new-transaction-type"]').filter(':checked').eq(0).val() || 'in'] = form.find('.viwebpos-price-input-value').val();
        jQuery(document.body).trigger('viwebpos_add_new_transaction', [transaction]);
    });
    jQuery(document).on('click', '.viwebpos-transactions-nav-bt:not(.loading):not(.viwebpos-hidden)', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let pages = jQuery('.viwebpos-transactions-max-page').val() ? jQuery('.viwebpos-transactions-max-page').val().split('|') : [],
            get_page = (jQuery(this).data('page') || '').toString(),
            key = jQuery(this).data('key') || '';
        if (!get_page || pages.indexOf(get_page) === -1) {
            return false;
        }
        jQuery(this).addClass('loading');
        if (pages.indexOf(get_page) < (pages.length - 1) && jQuery('.viwebpos-transaction-wrap-' + get_page).length) {
            jQuery(document.body).trigger('viwebpos_transactions_get_html', ['', get_page, key, true]);
            return false;
        }
        jQuery(document.body).trigger('viwebpos_transactions_refresh_html', [key, get_page]);
    });
    jQuery(document).on('click keyup', '.viwebpos-search-transaction', function (e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        let $this = jQuery(this);
        let val = $this.val(),
            old_key = $this.data('old_key') || '';
        if (val === old_key) {
            return false;
        }
        $this.data('old_key', val);
        jQuery(document.body).trigger('viwebpos_transactions_refresh_html', [val]);
    });
    jQuery(document.body).on('viwebpos-transactions-load', function () {
        if (!jQuery('.viwebpos-header-search-transaction').length) {
            let html = '<div class="viwebpos-header-search viwebpos-header-search-transaction">';
            if (jQuery('.viwebpos-wrap.rtl').length){
                html += `<div class="viwebpos-header-search-icon viwebpos-add-transaction-wrap" data-position="bottom right" data-tooltip="${viwebpos.transaction_add_title}">&#43;</div>`;
            }else {
                html += `<div class="viwebpos-header-search-icon viwebpos-add-transaction-wrap" data-position="bottom left" data-tooltip="${viwebpos.transaction_add_title}">&#43;</div>`;
            }
            html += '<input type="text" class="viwebpos-search-input viwebpos-search-transaction" placeholder="' + viwebpos.search_transaction + '">';
            html += '</div>';
            jQuery('.viwebpos-header-search-wrap').html(html);
        }
        if (!jQuery('.viwebpos-popup-wrap-add-transaction').length) {
            jQuery(document.body).trigger('viwebpos_get_popup_wrap', ['add-transaction']);
        }
        if (!jQuery('.viwebpos-transactions-container').length) {
            let html = '<div class="viwebpos-container-element viwebpos-transactions-container"><div class="viwebpos-transactions-contents-container">';
            html += '<table class="vi-ui striped table viwebpos-transactions-wrap">';
            html += '<thead><tr>';
            html += '<th>' + viwebpos.transaction_table_title_id + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_order + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_in + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_out + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_method + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_note + '</th>';
            html += '<th>' + viwebpos.transaction_table_title_date + '</th>';
            html += '</tr></thead>';
            html += '<tbody></tbody>';
            html += '<tfoot><tr><th colspan="7"><input type="hidden" class="viwebpos-transactions-max-page" value="">';
            html += '<span class="vi-ui small button viwebpos-transactions-nav-bt viwebpos-transactions-nav-bt-prev">Prev</span>';
            html += '<span class="vi-ui small button viwebpos-transactions-nav-bt viwebpos-transactions-nav-bt-next">Next</span></th></tr></tfoot>';
            html += '</table></div></div>';
            jQuery('.viwebpos-container-wrap').prepend(html);
        }
        jQuery(document.body).trigger('viwebpos_transactions_refresh_html');
    });
    jQuery(document.body).on('viwebpos_transactions_refresh_html', function (e, key = '', page = 1, limit = 15) {
        if (search_transaction) {
            clearTimeout(search_transaction);
        }
        if (page === 1) {
            jQuery('.viwebpos-transactions-max-page').val(null);
        }
        search_transaction = setTimeout(function (key) {
            let transactions = jQuery(document.body).triggerHandler('viwebpos_search_data', ['transactions', key, limit, page]);
            if (typeof transactions.then === 'function') {
                transactions.then(function (result) {
                    jQuery(document.body).trigger('viwebpos_transactions_get_html', [result, page, key]);
                });
            } else {
                jQuery(document.body).trigger('viwebpos_transactions_get_html', [transactions, page, key]);
            }
        }, 10, key);
    });
    jQuery(document.body).on('viwebpos_transactions_get_html', function (e, transactions, current_page = 1, key = '', change_page = false) {
        jQuery('.viwebpos-wrap').removeClass('viwebpos-wrap-loading');
        let wrap = jQuery('.viwebpos-transactions-wrap');
        current_page = current_page.toString();
        let pages = wrap.find('.viwebpos-transactions-max-page').val() ? wrap.find('.viwebpos-transactions-max-page').val().split('|') : [];
        let next_page, prev_page, transaction_count = 0;
        if (pages.indexOf(current_page) > -1) {
            prev_page = pages[pages.indexOf(current_page) - 1] || 0;
        } else {
            prev_page = pages[pages.length - 1];
        }
        if (jQuery('.viwebpos-popup-wrap-show').length) {
            jQuery(document.body).trigger('viwebpos_popup_close');
        }
        wrap.removeClass('viwebpos-transactions-wrap-empty viwebpos-transactions-wrap-one-page');
        wrap.find('.viwebpos-transactions-nav-bt, .viwebpos-transaction-wrap').addClass('viwebpos-hidden');
        wrap.find('.viwebpos-transactions-nav-bt').removeClass('loading');
        if (current_page === '1' && !change_page) {
            wrap.find('tbody').html(null);
        }
        if (current_page && change_page && !transactions && wrap.find('.viwebpos-transaction-wrap-' + current_page).length) {
            wrap.find('.viwebpos-transaction-wrap-' + current_page).removeClass('viwebpos-hidden');
            if (pages.indexOf(current_page) < (pages.length - 1)) {
                next_page = pages[pages.indexOf(current_page) + 1] || '';
            }
            if (next_page) {
                wrap.find('.viwebpos-transactions-nav-bt-next').data({'page': next_page, 'key': key}).removeClass('viwebpos-hidden');
            }
            if (prev_page) {
                wrap.find('.viwebpos-transactions-nav-bt-prev').data({'page': prev_page, 'key': key}).removeClass('viwebpos-hidden');
            }
            return false;
        }
        if (transactions.length && transactions.at(-1)['next_page']) {
            next_page = transactions.at(-1)['next_page'];
            transactions.pop();
        }
        jQuery.each(transactions, function (k, v) {
            if (!v.id || !v.cashier_id || (!v.in && !v.out)) {
                return true;
            }
            transaction_count++;
            let currency_symbol = v.currency_symbol || null;
            let html = '<tr class="viwebpos-transaction-wrap viwebpos-transaction-wrap-' + current_page + '">';
            html += '<td>#' + v.id + '</td>';
            if (v.order_id) {
                html += '<td>#' + v.order_id + '</td>';
            } else {
                html += '<td>-</td>';
            }
            html += '<td>' + viwebpos_get_price_html(v.in,null,null,null,null, currency_symbol) + '</td>';
            html += '<td>' + viwebpos_get_price_html(v.out,null,null,null,null, currency_symbol) + '</td>';
            if (v.method === 'viwebpos_manual') {
                html += '<td>Manual</td>';
            } else if (viwebpos.viwebpos_payments && viwebpos.viwebpos_payments[v.method] && viwebpos.viwebpos_payments[v.method]['title']) {
                html += '<td>' + viwebpos.viwebpos_payments[v.method]['title'] + '</td>';
            } else {
                html += '<td>'+(v.method ??'')+'</td>';
            }
            if (v.note) {
                html += '<td>' + v.note + '</td>';
            } else {
                html += '<td>-</td>';
            }
            html += '<td>' + v.create_at || '-' + '</td>';
            html += '</tr>';
            wrap.find('tbody').append(html);
        });
        if (!transaction_count) {
            wrap.addClass('viwebpos-transactions-wrap-one-page viwebpos-transactions-wrap-empty').find('tbody').html('<tr class="viwebpos-search-transaction-empty"><td colspan="7" class="viwebpos-search-transaction-empty"> ' + (viwebpos.search_transaction_empty || 'No transaction found') + '</td></tr>');
        } else if (!next_page && !prev_page) {
            wrap.addClass('viwebpos-transactions-wrap-one-page');
        }
        if (next_page) {
            if (pages.indexOf(current_page) === -1) {
                pages.push(current_page);
            }
            if (pages.indexOf(next_page) === -1) {
                pages.push(next_page.toString());
            }
            wrap.find('.viwebpos-transactions-max-page').val(pages.join('|').trim());
            wrap.find('.viwebpos-transactions-nav-bt-next').data({'page': next_page, 'key': key}).removeClass('viwebpos-hidden');
        }
        if (prev_page) {
            wrap.find('.viwebpos-transactions-nav-bt-prev').data({'page': prev_page, 'key': key}).removeClass('viwebpos-hidden');
        }
    });
});