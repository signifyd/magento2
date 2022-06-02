# Transaction risk analysis pre-auth (TRA_PRE_AUTH)

Transaction risk analysis pre-auth (TRA_PRE_AUTH) allows that Signifyd can check if the transaction qualifies for SCA exemption. A Merchant will use this recommendation to request exemptions from the PSP to minimize SCA based friction.

The Signifyd pre-auth response would contain an additional scaEvaluation object (see the Checkout API response in [docs.signifyd.com](https://docs.signifyd.com/#operation/Checkout))

the scaEvaluation object has three fields as listed below
- outcome (Outcomes for Signifyd's SCA Evaluation product)
- exclusionDetails (Details on Signifyd's evaluation that the order is out of SCA regulatory scope. Only present if outcome = REQUEST_EXCLUSION)
- exemptionDetails (Details on Signifyd's evaluation that the transaction should be sent to the merchant's bank for SCA exemption. Only present if outcome = REQUEST_EXEMPTION)

### Implementation

When Signifyd's recommendation is received, the payment auth request to payment method should be modified according to the documentation provided by them

The extension already has the mapping for Adyen. For the other  payment methods it is necessary to create a customization.

To retrieve the signifyd recommendation in the request class it is necessary to instantiate the Signifyd\Connect\Model\TRAPreAuth\ScaEvaluation class and use the getScaEvaluation method passing the quote id as a parameter. If there is a recommendation for tra_pre_auth the response will be a Signifyd\Models\ScaEvaluation class.