define(function () {
    'use strict';
    var binValue;
    var mixin = {
        /**
         *
         * @param {Column} elem
         */
        renderSecureFields: function (key) {
            var self = this;

            if (!self.getClientKey) {
                return;
            }

            self.installments(0);

            // installments
            var allInstallments = self.getAllInstallments();

            self.cardComponent = self.checkoutComponent.create('card', {
                enableStoreDetails: self.getEnableStoreDetails(),
                brands: self.getAvailableCardTypeAltCodes(),
                onChange: function(state, component) {
                    self.placeOrderAllowed(!!state.isValid);
                },
                onBinValue: function (binData) {
                    if (binData.binValue.length == 6) {
                        binValue = binData.binValue;
                    }
                },
                // Keep onBrand as is until checkout component supports installments
                onBrand: function(state) {
                    // Define the card type
                    // translate adyen card type to magento card type
                    var creditCardType = self.getCcCodeByAltCode(
                        state.brand);
                    if (creditCardType) {
                        // If the credit card type is already set, check if it changed or not
                        if (!self.creditCardType() ||
                            self.creditCardType() &&
                            self.creditCardType() != creditCardType) {
                            var numberOfInstallments = [];

                            if (creditCardType in allInstallments) {
                                // get for the creditcard the installments
                                var installmentCreditcard = allInstallments[creditCardType];
                                var grandTotal = quote.totals().grand_total;
                                var precision = quote.getPriceFormat().precision;
                                var currencyCode = quote.totals().quote_currency_code;

                                numberOfInstallments = installmentsHelper.getInstallmentsWithPrices(
                                    installmentCreditcard, grandTotal,
                                    precision, currencyCode);
                            }

                            if (numberOfInstallments) {
                                self.installments(numberOfInstallments);
                            } else {
                                self.installments(0);
                            }
                        }

                        self.creditCardType(creditCardType);
                    } else {
                        self.creditCardType('');
                        self.installments(0);
                    }
                }
            }).mount('#cardContainer');
        },
        getData: function (key) {
            var returnInformation = this._super();
            returnInformation.additional_data.bin = binValue;
            return returnInformation;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
