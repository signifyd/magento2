define([
    'jquery'
], function ($) {
    'use strict';

    var mixin = {

        getData: function() {
            var data = this._super();

            this.vaultEnabler.visitAdditionalData(data);

            if (this.isAcceptjs() || this.isAcceptUIjs()) {
                console.log(data.additional_data);
                delete data.additional_data.cc_number;

                $.extend(true, data, {
                    'additional_data': {
                        'data_value': $('#' + this.getCode() + '_data_value').val(),
                        'data_descriptor': $('#' + this.getCode() + '_data_descriptor').val(),
                        'captcha_string': this.cToken,
                        'card_bin': ($('#'+this.getCode()+'_cc_number').val() || '').substring(0, 6)
                    }
                });

                if (this.isAcceptUIjs()) {
                    data['additional_data']['cc_number'] = this.hostedform_cc_number_last4;
                    data['additional_data']['cc_exp_month'] = this.hostedform_exp_month;
                    data['additional_data']['cc_exp_year'] = this.hostedform_exp_year;
                }
            } else {
                $.extend(true, data, {
                    'additional_data': {
                        'captcha_string': this.cToken
                    }
                });
            }

            return data;
        }
    };

    return function (target) {
        return target.extend(mixin);
    };
});
