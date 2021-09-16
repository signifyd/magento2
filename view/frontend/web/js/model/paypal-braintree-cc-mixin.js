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
                this.additionalData['cardBin'] = data.details.bin;
                this.additionalData['cardLast4'] = data.details.lastFour;
            }

            this._super();
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
