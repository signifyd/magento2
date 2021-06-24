define(function () {
    'use strict';
    var mixin = {
        /**
         *
         * @param {Column} elem
         */
        handleNonce: function (data) {
            if (
                typeof data !== 'undefined' &&
                typeof data.details !== 'undefined' &&
                typeof data.details.bin !== 'undefined' &&
                typeof data.details.lastFour !== 'undefined'
            ) {
                this.additionalData['signifyd-bin'] = data.details.bin;
                this.additionalData['signifyd-lastFour'] = data.details.lastFour;
            }

            this._super();
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
