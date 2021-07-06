var config = {
    config: {
        mixins: {
            'Magento_Braintree/js/view/payment/method-renderer/cc-form': {
                'Signifyd_Connect/js/model/braintree-cc-mixin': true
            },
            'PayPal_Braintree/js/view/payment/method-renderer/cc-form': {
                'Signifyd_Connect/js/model/paypal-braintree-cc-mixin': true
            }
        }
    }
};
