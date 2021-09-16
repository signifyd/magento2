define(function () {
    'use strict';
    var mixin = {
        /**
         *
         * @param {Column} elem
         */
        placeOrder: function (key) {
            if (
                typeof this.paymentPayload !== 'undefined' &&
                typeof this.paymentPayload.details !== 'undefined' &&
                typeof this.paymentPayload.details.bin !== 'undefined' &&
                typeof this.paymentPayload.details.lastFour !== 'undefined'
            ) {
                this.additionalData['cardBin'] = this.paymentPayload.details.bin;
                this.additionalData['cardLast4'] = this.paymentPayload.details.lastFour;
            }

            this._super();
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
