define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'jquery'
], function (Component, customerData, $) {
    'use strict';

    return Component.extend({
        /** @inheritdoc */
        initialize: function () {
            var me = this;
            me._super();

            if (jQuery('.checkout-onepage-success').length > 0) {
                customerData.reload(['signifyd-fingerprint']);

            } else {
                me.data = customerData.get('signifyd-fingerprint');
                me.sent = false;

                me.sent = me.checkSessionId(me.data().dataOrderSessionId);

                if (me.sent === false) {
                    me.observing = me.data.subscribe(function (newData) {
                        me.sent = me.checkSessionId(newData.dataOrderSessionId);
                    });
                }
            }
        },

        checkSessionId: function(dataOrderSessionId) {
            var me = this;

            if (typeof dataOrderSessionId !== 'undefined' && dataOrderSessionId.length > 0) {
                console.log('Sending fingerprint...');

                me.callScript(dataOrderSessionId);

                if (typeof me.observing != "undefined") {
                    me.observing.dispose();
                }

                return true;
            } else {
                console.log('Will not send fingerprint');
                console.log(dataOrderSessionId);

                return false;
            }
        },

        callScript: function(dataOrderSessionId) {
            var script = document.createElement('script');
            script.setAttribute('async', true);
            script.setAttribute('type', 'text/javascript');
            script.setAttribute('id', 'sig-api');
            script.setAttribute('data-order-session-id', dataOrderSessionId);
            script.setAttribute('src', 'https://cdn-scripts.signifyd.com/api/script-tag.js');

            $("body").append(script);
        }
    });
});
