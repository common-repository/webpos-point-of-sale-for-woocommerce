jQuery(document).ready(function () {
    'use strict';
    jQuery(document.body).on('villatheme_show_messages', function (e, messages) {
        if (!messages || !messages.length) {
            return false;
        }
        let show_message = setInterval(function (messages) {
            let message = messages[0] || {};
            if (!message.title && !message.message) {
                clearInterval(show_message);
                return false;
            }
            if (message.title) {
                jQuery(document.body).trigger('villatheme_show_message', [message.title, message.status, message.message, message.is_central_title || false, message.time || 3000])
            } else {
                jQuery(document.body).trigger('villatheme_show_message', [message.message, message.status, '', message.is_central_title || false, message.time || 3000])
            }
            messages.shift();
            if (!messages.length) {
                clearInterval(show_message);
                return false;
            }
        }, 500, messages);
    });
    jQuery(document.body).on('villatheme_show_message', function (e, message, status = '', message_content = '', is_central_title = false, time_out = 0) {
        if (status.indexOf('refresh-data') === -1) {
            let arg = [
                'viwebpos-popup-wrap-loading',
                'viwebpos-popup-bt-loading',
                'viwebpos-wrap-loading',
                'viwebpos-checkout-form-content-paid-input-checkout_after_change',
                'loading',
            ];
            jQuery.each(arg,function (k,v) {
                jQuery('.'+v).removeClass(v);
            });
        }
        if (!jQuery('.villatheme-show-message-container').length) {
            jQuery('body').append('<div class="villatheme-show-message-container"></div>');
        }
        let $message_field = jQuery('.villatheme-show-message-container'),
            titleClass = 'villatheme-show-message-title', $new_message;
        if (message_content) {
            titleClass += ' villatheme-show-message-title1';
            message_content = '<div class="villatheme-show-message-content">' + message_content + '</div>';
        }
        if (is_central_title) {
            titleClass += ' villatheme-show-message-title-central';
        }
        $new_message = '<div class="villatheme-show-message-item villatheme-show-message-new-added-item">';
        $new_message += '<div class="' + titleClass + '">' + message + '</div>';
        $new_message += '<i class="villatheme-show-message-item-close close icon"></i>' + message_content;
        $new_message += '</div></div>';
        $new_message = jQuery($new_message);
        $message_field.prepend($new_message);
        if (typeof status === "string") {
            $new_message.addClass('villatheme-show-message-message-' + status);
        } else {
            jQuery.each(status, function (k, v) {
                $new_message.addClass('villatheme-show-message-message-' + v);
            })
        }
        let timeOut = jQuery(document.body).triggerHandler('villatheme_show_message_timeout', [$new_message, time_out]);
        if (timeOut.length > 0) {
            $new_message.on('mouseenter', function () {
                for (let i in timeOut) {
                    clearTimeout(timeOut[i]);
                }
            });
            $new_message.on('mouseleave', function () {
                for (let i in timeOut) {
                    clearTimeout(timeOut[i]);
                }
                timeOut = jQuery(document.body).triggerHandler('villatheme_show_message_timeout', [$new_message, time_out]);
            });
        }
        $new_message.on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        });
        $new_message.on('click', '.villatheme-show-message-item-close', function (e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            if (timeOut.length > 0) {
                for (let i in timeOut) {
                    clearTimeout(timeOut[i]);
                }
            }
            $new_message.addClass('villatheme-show-message-new-added-item');
            setTimeout(function () {
                $new_message.remove();
            }, 300);
        });
    });
    jQuery(document.body).on('villatheme_show_message_timeout', function (e, $new_message, time_out) {
        let timeOut = [];
        setTimeout(function () {
            $new_message.removeClass('villatheme-show-message-new-added-item');
            if (time_out > 0) {
                timeOut.push(setTimeout(function () {
                    $new_message.addClass('villatheme-show-message-new-added-item');
                }, time_out));
                timeOut.push(setTimeout(function () {
                    $new_message.remove();
                }, (time_out + 300)));
            }
        }, 10);
        return timeOut;
    });
});