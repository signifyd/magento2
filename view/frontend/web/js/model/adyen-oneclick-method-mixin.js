define(
    [],
    function() {
    'use strict';

    return function (target) {
        return target.extend({
            /**
             * List all Adyen billing agreements
             * Set up installments
             *
             * @returns {Array}
             */
            getAdyenBillingAgreements: function() {
                let paymentList = this._super();

                paymentList.forEach(function(key, payment) {
                    let expiryMonth = key.agreement_data.card.expiryMonth;
                    let expiryYear = key.agreement_data.card.expiryYear;
                    let number = key.agreement_data.card.number;

                    key.getDataOriginal = key.getData;
                    key.getData = function () {
                        let result = key.getDataOriginal();

                        result.additional_data.cardExpiryMonth = expiryMonth;
                        result.additional_data.cardExpiryYear = expiryYear;
                        result.additional_data.cardLast4 = number;
                        return result;
                    };
                });

                return paymentList;
            }
        });
    };
});
