[Signifyd Extension for Magento 2](../README.md) > Pass payment gateway details **using mappers**

# Pass payment details - using mappers

## Overview

The extension will try to fetch the following payment data:

- AVS Response Code
- CVV Response Code
- Bin
- Last 4
- Expiry Month
- Expiry Year
- Transaction ID

As each payment gateway has it own workflow, there is no guarantee that the extension will find the payment data. To support a variety of payment gateways and extensibility mappers can be used to pass payment data from any payment gateway.  

## Basic Structure

The solution fetches the desired information using mappers on a similar way Magento team has done on Magento Signifyd [built in functionality](http://devdocs.magento.com/guides/v2.2/payments-integrations/signifyd/signifyd.html). Mappers developed by Magento team are not compatible with this solution, because of backwards compatibility with all Magento 2 versions.

Each payment method and information needs a mapper to fetch the desired information. If it is desired to collect AVS and CVV codes for a specific payment method, then it is needed to build two different mappers, one for AVS and another one for CVV. Mappers can be reused between payment methods if data to be collected is stored on the same database location or API response.

This solution has built in mappers that work for any payment method that stores the informations on standards locations (sales_order_payment table on database). Those mappers can be found on extension folder Model/Payment/Base. These mappers also work as default mappers, so if no mapper is specified for a payment method and information combination, Base mappers will be used.

All mappers should implement Signifyd\Connect\Api\PaymentVerificationInterface. It is recommended to do that by extending Signifyd\Connect\Model\Payment\DataMapper or even better - to extend one of the Base mappers.

## Including custom payment method

### Finding the payment method code

For the inclusion of a custom payment, it is necessary to find the payment method code.

Usually it is possible to find the payment method code inside the payment method config.xml file, inside the `<default><signifyd><payment>` tag. Something like this:

```xml
<default>
    <signifyd>
        <payment>
            <payment_method_code>
            ...
            </payment_method_code>
        </payment>
    </signifyd>
</default>
```

Another way to find the payment method code is on the database. Get an increment ID of any order placed with the desired payment method and use the following script on the database to get the payment method code.

**_Replace INCREMENT_ID with the order increment ID_**

```
SELECT method FROM sales_order_payment WHERE parent_id IN (SELECT entity_id FROM sales_order WHERE increment_id='INCREMENT_ID');
```

### Configure the mapper

A custom extension will be needed to configure and implement mappers, so if it does not exists yet, create one.

On config.xml set the mapper to be used for the payment method and information as bellow:

**_Replace payment_method_code with the desired payment method code_**

**_Replace information_code with the desired information code_**

**_Replace Mapper\Class with the mapper class that will be created_**

```xml
<config>
	<default>
        <signifyd>
            <payment>
                <payment_method_code>
                    <signifyd_information_code_adapter>Mapper\Class</signifyd_information_code_adapter>
                </payment_method_code>
            </payment>
        </signifyd>
	</default>
</config>
```

List of valid information codes:
- AVS response code: avs_ems
- CVV response code: cvv_ems
- Bin: bin
- Credit card last four digits: last4
- Expiry month: exp_month
- Expiry year: exp_year

It is possible to check some examples of these configurations on etc/config.xml of Signifyd extension, that can usually be found at MAGENTO_ROOT/vendor/signifyd/module-connect folder or inside MAGENTO_ROOT/app if you installed it manually. _**Do not modify Signifyd files.**_

### Implement the mapper

The mapper should implement Signifyd\Connect\Api\PaymentVerificationInterface. It is recommended to do that by extending Signifyd\Connect\Model\Payment\DataMapper or even better, to extend one of the Base mappers, from namespace Signifyd\Connect\Model\Payment\Base.

It is also required to implement public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment).

If restricting the use of the mapper to some specific payment methods is needed, that can be done by adding a property protected $allowedMethods = array('method01', 'method02', �). This is optional, but recommended to avoid configuration mistakes.

Here is a template for mapper class implementation:

_**Replace bold parts with specific information for custom extension, payment method and information**_

_**Do not implement a validate() method inside these classes**_

```php
namespace Path\Your\Namespace;

use Signifyd\Connect\Model\Payment\Base\InformationCodeMapper as Signifyd_InformationCodeMapper;

class InformationCodeMapper extends Signifyd_InformationCodeMapper
{
	// This can be dropped if it is not desirable to restrict payment methods
	protected $allowedMethods = array('payment_method_code');

	/**
	 * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
	 * @return bool|mixed|string
	 */
	public function getPaymentData(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
	{
		// Implement the code to fetch information and return it
	}
}
```

At the end of getPaymentData method the parent::getPaymentData($orderPayment) can be called as a fallback if the information is not found or missing. That will trigger Base mapper and it will try to find the information on Magento standard location on database.

## Built in mappers

Here is a list of the payment methods that have a built in helper on the extension and will have payment data collected. If the cardholder name is not found, the billing first and last name will be used.

### Authorize.Net
- Code: authorizenet_directpost
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Transaction ID

### PayPal Express/PayPal Standard
- Code: paypal_express
- Magento built in

**Available data**
- Transaction ID

### PayPal Payments Pro/PayPal Payflow Pro
- Code: payflowpro
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year
- Transaction ID

### PayPal Payflow Link
- Code: payflow_link
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year
- Transaction ID

### PayPal Payments Advanced
- Code: payflow_advanced
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year
- Transaction ID

### Braintree
- Code: braintree
- Magento built in

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year
- Transaction ID

### Stripe Payments
- Code: stripe_payments

**Available data**
- CVV Status
- AVS Status
- Last4
- Expiry Month
- Expiry Year
- Transaction ID
