/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, confirm) {
    'use strict';

    /**
     * @param {String} url
     * @returns {Object}
     */
    function getForm(url) {
        return $('<form>', {
            'action': url,
            'method': 'POST'
        }).append($('<input>', {
            'name': 'form_key',
            'value': window.FORM_KEY,
            'type': 'hidden'
        }));
    }

    //post-wrapper.js defines a click event for this button, removing it
    $('#order-view-unhold-button').unbind('click');

    $('#order-view-unhold-button').click(function () {
        var msg = $.mage.__('Signifyd has not reviewed this order, are you sure you want to unhold?'),
            url = $('#order-view-unhold-button').data('url');

        confirm({
            'content': msg,
            'actions': {
                /**
                 * 'Confirm' action handler.
                 */
                confirm: function () {
                    getForm(url).appendTo('body').submit();
                }
            }
        });

        return false;
    });
});
