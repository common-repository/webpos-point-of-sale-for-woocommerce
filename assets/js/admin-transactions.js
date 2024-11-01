jQuery(document).ready(function () {
    'use strict';
    jQuery(document).on('click','.column-id .delete a.submitdelete', function (e) {
        if (!confirm('Are you sure to delete this transaction?')){
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
    jQuery(document).on('click','.bulkactions #doaction', function (e) {
        if (!confirm('Are you sure to delete these transactions?')){
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });
});