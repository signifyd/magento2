define(function () {
    'use strict';
    var mixin = {
        /**
         *
         * @param {Column} elem
         */
        handleNonce: function (data) {
            this.additionalData['signifyd-bin'] = data.details.bin;
            this.additionalData['signifyd-lastFour'] = data.details.lastFour;

            this._super();
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
