# Transaction risk analysis pre-auth (SCA_PRE_AUTH)

Transaction risk analysis pre-auth (SCA_PRE_AUTH) allows that Signifyd can check if the transaction qualifies for SCA exemption. A Merchant should use this recommendation to request exemptions from the PSP to minimize SCA based friction.

The Signifyd pre-auth response would contain an additional scaEvaluation object (see the Checkout API response in [docs.signifyd.com](https://docs.signifyd.com/#operation/Checkout))

The scaEvaluation object has three fields as listed below
- outcome (Outcomes for Signifyd's SCA Evaluation product)
- exclusionDetails (Details on Signifyd's evaluation that the order is out of SCA regulatory scope. Only present if outcome = REQUEST_EXCLUSION)
- exemptionDetails (Details on Signifyd's evaluation that the transaction should be sent to the merchant's bank for SCA exemption. Only present if outcome = REQUEST_EXEMPTION)

### Implementation

When Signifyd's recommendation is received, the payment auth request to payment method should be modified according to the documentation provided by the Signifyd team.

The extension already has the mapping for Adyen. For the other  payment methods it is necessary to create a custom one.

First it is necessary to find where the payment method request is created, then create a plugin/preference and inject the class Signifyd\Connect\Model\SCAPreAuth\ScaEvaluation to use the getScaEvaluation method, passing the quote as a parameter, to retrieve the Signifyd SCA recommendation. If there is a recommendation for SCA pre auth, the response will be a [Signifyd\Models\ScaEvaluation](https://github.com/signifyd/php/blob/main/lib/Models/ScaEvaluation.php) class.

The final plugin/preference class should look like the bellow one:

```php
<?php

namespace Vendor\Module\Model\Custom;

use Signifyd\Connect\Model\SCAPreAuth\ScaEvaluation;

/**
 * Modify the request to the PSP according Signifyd SCA evaluation
 * Call the scaModifyPspRequest according the customization of the PSP Magento plugin
 */
class PspScaIntegration
{
    /**
     * @var ScaEvaluation
     */
    protected $scaEvaluation;

    /**
     * ScaEvaluation $scaEvaluation
     */
    public function __construct(ScaEvaluation $scaEvaluation) 
    {
        $this->scaEvaluation = $scaEvaluation;
    }

    /**
     * Process Signifyd SCA Evaluation and mofidy the request to the PSP
     * 
     * @param mixed $request Original request to the PSP
     */
    public function scaModifyPspRequest($request)
    {
        $quote = $this->getQuote();
        $scaEvaluation = $this->scaEvaluation->getScaEvaluation($quote);

        if ($scaEvaluation instanceof \Signifyd\Models\ScaEvaluation === false) {
            return $request;
        }

        // Modify the PSP request according to each outcome
        switch ($scaEvaluation->outcome) {
            case 'REQUEST_EXEMPTION':
                break;

            case 'REQUEST_EXCLUSION':
                break;

            case 'DELEGATE_TO_PSP':
                break;

            // Not on Signifyd documentation, custom value added by the Signifyd_Connect plugin
            case 'SOFT_DECLINE':
                break;
        }

        return $request;
    }

    /**
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        // Add logics to get the quote being processed by the PSP
    }
}
```

## Soft Decline

For a Signifyd approved order that is sent to the payment processor for authorization, when the processor responds back with a soft decline, the order needs to be routed via 3DS.

First it is necessary to find where the payment method response is received, then create a plugin/preference and inject the class Signifyd\Connect\Model\SCAPreAuth\ScaEvaluationConfig to use the isScaEnabled method to check if SCA it's enabled for the payment method. The class Signifyd\Connect\Model\SCAPreAuth\ScaEvaluation should also be injected and the method setIsSoftDecline must be used to indicate if the response it's a soft decline or not.

```php
<?php

namespace Vendor\Module\Model\Custom;

use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluation;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluationConfig;

class TransactionPayment
{
    /**
     * @var ScaEvaluation
     */
    protected $scaEvaluation;

    /**
     * @var ScaEvaluationConfig
     */
    protected $scaEvaluationConfig;

    /**
     * @param ScaEvaluation $scaEvaluation
     * @param ScaEvaluationConfig $scaEvaluationConfig
     */
    public function __construct(
        ScaEvaluation $scaEvaluation,
        ScaEvaluationConfig $scaEvaluationConfig
    ){
        $this->scaEvaluation = $scaEvaluation;
        $this->scaEvaluationConfig = $scaEvaluationConfig;
    }

    /**
     * Check if PSP response is a soft decline and flag Signifyd
     * 
     * @param mixed $response Original request from the PSP
     */
    public function checkSoftDecline($response)
    {
        $storeId = $this->getStoreId();
        $isScaAvailable = (bool) $this->scaEvaluationConfig->isScaEnabled($storeId, 'payment_method');

        if ($isScaAvailable === false) {
            return $response;
        }

        if ($this->isResponseSoftDecline($response)) {
            $this->scaEvaluation->setIsSoftDecline(true);
        } else {
            $this->scaEvaluation->setIsSoftDecline(false);
        }

        return $response;
    }

    /**
     * @param $response mixed
     */
    public function isResponseSoftDecline($response)
    {
        // Add logics to check if response is a soft decline
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        // Add logics to retrieve store ID
    }
}
```
If the PSP plugin implementation uses the [Magento payment provider gateway mechanism](https://devdocs.magento.com/guides/v2.4/payments-integrations/payment-gateway/payment-gateway-intro.html) then Signifyd extension will automatically re-submit the transaction to the PSP. If the PSP plugin uses other kinds of implementation, then will be necessary to customize the PSP plugin to resubmit the transaction when $scaEvaluation->getIsSoftDecline() returns true.