jQuery(function ($) {
    'use strict';
    if (typeof viwebpos_receipts === 'undefined') {
        return;
    }
    $(document.body).on('viwebpos-bill-of-sale-load', function () {
        if (!$('.viwebpos-popup-wrap-print-receipt').length) {
            $(document.body).trigger('viwebpos_get_popup_wrap', ['print-receipt']);
        }
    });
    $(document).on('viwebpos-frame-print', function () {
        if (!$('#viwebpos-frame-print').length) {
            $('.viwebpos-wrap').removeClass('viwebpos-wrap-loading viwebpos-wrap-loading1');
            return false;
        }
        $('#viwebpos-frame-print').css('left', '0');
        window.frames['viwebpos-frame-print'].onload = function () {
            window.frames['viwebpos-frame-print'].focus();
            window.frames['viwebpos-frame-print'].print();
            $('#viwebpos-frame-print').css('left', '100%');
            $('.viwebpos-order-print').removeClass('loading');
            $('.viwebpos-wrap').removeClass('viwebpos-wrap-loading viwebpos-wrap-loading1');
            if ($('.viwebpos-popup-wrap-show').length) {
                $(document.body).trigger('viwebpos_popup_close');
            }
        };
    });
    $(document.body).on('viwebpos_print_receipt', function (e, order_id, order = null, template = '') {
        if (!parseInt(order_id)) {
            $(document.body).trigger('villatheme_show_message', [viwebpos.not_found_order_to_print.toString().replace('{order_id}', order_id), ['error', 'print-receipt'], '', false, 4500]);
            return false;
        }
        $('.viwebpos-wrap').addClass('viwebpos-wrap-loading1');
        let print = async function () {
            let error = false;
            await new Promise(function (resolve) {
                $.ajax({
                    url: viwebpos.admin_ajax_url,
                    type: 'GET',
                    data: {
                        'action': 'viwebpos_print_order',
                        'order_ids': order_id,
                        'receipt_template': template,
                    },
                    beforeSend: function () {
                        $('.viwebpos-wrap').addClass('viwebpos-wrap-loading');
                    },
                    success: function (response) {
                        if (response.messages) {
                            $(document.body).trigger('villatheme_show_message', [response.messages, [response.status, 'print-receipt'], '', false, 4500]);
                        }
                        if (response.status === 'success' && response.html) {
                            $('#viwebpos-frame-print').remove();
                            $('body').append('<iframe id="viwebpos-frame-print" name="viwebpos-frame-print"></iframe>');
                            let new_win = window.frames['viwebpos-frame-print'];
                            new_win.document.write(response.html);
                            new_win.document.close();
                        } else {
                            error = true;
                        }
                        resolve(error);
                    },
                    error: function (err) {
                        console.log(err)
                        $(document.body).trigger('villatheme_show_message', [err.statusText, ['error', 'print-receipt'], err.responseText === '-1' ? '' : err.responseText, false, 4500]);
                        resolve(error);
                    }
                });
            });
            return error;
        }
        print().then(function (error) {
            if (error) {
                $(document.body).trigger('villatheme_show_message', [viwebpos_text.error_print_receipt, ['error', 'print-receipt'], '', false, 4500]);
                if ($('.viwebpos-popup-wrap-show').length) {
                    $(document.body).trigger('viwebpos_popup_close');
                }
            }
            $(document).trigger('viwebpos-frame-print');
        });
    });
    $(document.body).on('viwebpos_set_auto_print_receipt_after_checkout', function (e, enable = true) {
        if (typeof viwebpos_data === "undefined" || !viwebpos_data) {
            return;
        }
        let self = viwebpos_data;
        if (!self.viwebposDB) {
            self.viwebposDB = indexedDB.open('viwebposDB');
        }
        let database = self.viwebposDB.result;
        self.auto_print_receipt = enable ? 1 : '';
        self.put(database.transaction('settings', 'readwrite').objectStore('settings'), self.auto_print_receipt, 'auto_print_receipt');
        if (self.auto_print_receipt) {
            $('.viwebpos-header-action-icons-auto_print').removeClass('viwebpos-header-action-icons-auto_print-disable');
        } else {
            $('.viwebpos-header-action-icons-auto_print').addClass('viwebpos-header-action-icons-auto_print-disable');
        }
    });
});