define(
    [
        'Adyen_Payment/js/model/adyen-configuration',
        'Adyen_Payment/js/model/adyen-checkout',
        'Magento_Checkout/js/model/quote',
        'Adyen_Payment/js/model/installments',
    ],
    function (adyenConfiguration, adyenCheckout, quote, installmentsHelper) {
    'use strict';
    var binValue;
    var cardLast4;
    var mixin = {
        //Mixin for adyen extension 7.x.x version
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
                onFieldValid: function (onFieldValid) {
                    if (onFieldValid.fieldType === 'encryptedCardNumber') {
                        cardLast4 = onFieldValid.endDigits;
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
        //Mixin for adyen extension 8.x.x version
        renderCCPaymentMethod: function () {
            var self = this;
            if (!self.getClientKey) {
                return false;
            }

            self.installments(0);

            // installments
            let allInstallments = self.getAllInstallments();

            let componentConfig = {
                enableStoreDetails: self.getEnableStoreDetails(),
                brands: self.getAvailableCardTypeAltCodes(),
                hasHolderName: adyenConfiguration.getHasHolderName(),
                holderNameRequired: adyenConfiguration.getHasHolderName() &&
                    adyenConfiguration.getHolderNameRequired(),
                onChange: function(state, component) {
                    self.placeOrderAllowed(!!state.isValid);
                    self.storeCc = !!state.data.storePaymentMethod;
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
                                var grandTotal = self.grandTotal();
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
                },
                onBinValue: function (binData) {
                    if (binData.binValue.length == 6) {
                        binValue = binData.binValue;
                    }
                },
                onFieldValid: function (onFieldValid) {
                    if (onFieldValid.fieldType === 'encryptedCardNumber') {
                        cardLast4 = onFieldValid.endDigits;
                    }
                }
            }

            self.cardComponent = adyenCheckout.mountPaymentMethodComponent(
                this.checkoutComponent,
                'card',
                componentConfig,
                '#cardContainer'
            )

            return true
        },
        getData: function (key) {
            var returnInformation = this._super();
            returnInformation.additional_data.cardBin = binValue;
            returnInformation.additional_data.cardLast4 = cardLast4;
            return returnInformation;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
