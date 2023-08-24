define(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'mage/url',
        'Magento_Payment/js/view/payment/iframe',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Vault/js/view/payment/vault-enabler'],
    function($, modal, urlBuilder, Component, additionalValidators, setPaymentInformationAction, fullScreenLoader, VaultEnabler) {
        'use strict';

        return function (target) {
            return target.extend({
                /**
                 * List all Adyen billing agreements
                 * Set up installments
                 *
                 * @returns {Array}
                 */
                placeOrder: function () {
                    if (!this.validateHandler() || !additionalValidators.validate()) {
                        return;
                    }
                    var isEnabled = window.checkoutConfig.cybersource_recaptcha && window.checkoutConfig.cybersource_recaptcha.enabled.cybersource;
                    var recaptcha_invisible = window.checkoutConfig.payment.chcybersource.recaptcha_invisible;
                    if(isEnabled && recaptcha_invisible != "invisible"){
                        var options = {
                            type: 'popup',
                            responsive: true,
                            innerScroll: true,
                            buttons: [{
                                text: $.mage.__('OK'),
                                class: 'mymodal1',
                                click: function () {
                                    $('body').trigger('processStart');
                                    var url = urlBuilder.build("checkout");
                                    window.location = url;
                                    this.closeModal();
                                }
                            }]
                        };

                        var popup = modal(options, $('#sa-recaptcha'));
                        var rresponse = jQuery('#g-recaptcha-response').val();
                        if(rresponse.length == 0) {
                            $("#sa-recaptcha").modal("openModal");
                            $('.action-close').css('display', 'none');
                            this.isPlaceOrderActionAllowed(false);
                            return false;
                        }
                        $('#sa-recaptcha').on('modalclosed', function() {
                            $('body').trigger('processStart');
                            var url = urlBuilder.build("checkout");
                            window.location = url;
                        });
                    }

                    fullScreenLoader.startLoader();

                    this.isPlaceOrderActionAllowed(false);

                    this.getPlaceOrderDeferredObject()
                        //fail added in case of failure on checkout
                        .fail(
                            function (response) {
                                //stop loader added
                                fullScreenLoader.stopLoader();
                                return response;
                            }
                        ).then(this.placeOrderHandler)
                        .then(this.initTimeoutHandler.bind(this))
                        .always(
                            function () {
                                this.isPlaceOrderActionAllowed(true);
                            }.bind(this)
                        )
                    ;
                }
            });
        };
    });
