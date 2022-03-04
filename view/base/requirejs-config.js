var config = {
    config: {
        mixins: {
            'Magento_Braintree/js/view/payment/method-renderer/cc-form': {
                'Signifyd_Connect/js/model/braintree-cc-mixin': true
            },
            'PayPal_Braintree/js/view/payment/method-renderer/cc-form': {
                'Signifyd_Connect/js/model/paypal-braintree-cc-mixin': true
            },
            'Adyen_Payment/js/view/payment/method-renderer/adyen-cc-method': {
                'Signifyd_Connect/js/model/adyen-cc-method-mixin': true
            },
            'Openpay_Cards/js/view/payment/method-renderer/cc-form': {
                'Signifyd_Connect/js/model/openpay-cc-method-mixin': true
            },
            'Adyen_Payment/js/view/payment/method-renderer/adyen-oneclick-method': {
                'Signifyd_Connect/js/model/adyen-oneclick-method-mixin': true
            }
        }
    }
};
