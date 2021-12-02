define(function () {
    'use strict';
    var mixin = {
        getData: function (key) {
            var returnInformation = this._super();

            if (
                typeof this.creditCardExpYear() !== 'undefined' &&
                typeof this.creditCardExpMonth() !== 'undefined' &&
                typeof this.creditCardNumber() !== 'undefined'
            ) {
                var last4 = this.creditCardNumber().substr(-4);
                var bin = this.creditCardNumber().substr(0,6);

                returnInformation.additional_data.cardExpiryMonth = this.creditCardExpMonth();
                returnInformation.additional_data.cardExpiryYear = this.creditCardExpYear();
                returnInformation.additional_data.cardLast4 = last4;
                returnInformation.additional_data.cardBin = bin;
            }

            return returnInformation;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
