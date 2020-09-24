[Signifyd Extension for Magento 2](../README.md) > Pass payment gateway details **using payment gateways APIs**

# Pass payment details - using payment gateways APIs

## Overview

Use integrations directly with payment gateway APIs to fetch most of these information:

- AVS Response Code
- CVV Response Code
- Bin
- Credit card - Last 4 digits
- Expiry Month
- Expiry Year
- Transaction ID

## Basic Structure

In order to fetch information, a gateway class must implement \Signifyd\Models\Payment\GatewayInterface. It must return a class implementing \Signifyd\Models\Payment\Response\ResponseInterface as response.

The Signifyd SDK also provides an abstract gateway class \Signifyd\Models\Payment\AbstractGateway, which can be extended by the final gateway class implementation and a default response class \Signifyd\Models\Payment\Response\DefaultResponse It is strongly recommended that the default response class to be used as either the final response class or as parent for the final response class.

Before starting building your own classes, check if they are not already implemented under \Signifyd\Models\Payment namespace.

## Use existing payment gateway classes

If the payment gateway class already exists for the desired payment gateway, you only need to provide a configuration to get it working. Each payment method extension should have its own settings. Some payment methods extensions have this configuration built into the Signifyd extension, as you can see for AuthorizeNet official extension on etc/config.xml:

```xml
<config>
    <default>
        <signifyd>
            <gateway_integration>
                <anet_creditcard>{"gateway":"\\Signifyd\\Models\\Payment\\Authorizenet","params":{"name":{"type":"path","path":"authorize_net/anet_core/login_id"},"transactionKey":{"type":"path","path":"authorize_net/anet_core/trans_key"}}}</anet_creditcard>
            </gateway_integration>
        </signifyd>
    </default>
</config>
```

The settings for a gateway must be set on the core_config_data table within the Magento database using the string below as path.

```
signifyd/gateway_integration/{payment_method_code}
```

The value must be a JSON string which needs to include two properties: gateway and params.

The "gateway" property must include the gateway class name with duplicated slashes. E.g. if the class' name is \Signifyd\Models\Payment\Authorizenet, then it must be provided as \\Signifyd\\Models\\Payment\\Authorizenet.

The "params" property should include the information needed by the payment gateway class to comunicate with the gateway API. E.g. for \Signifyd\Models\Payment\Authorizenet - the class parameters "name" and "transactionKey".

Each parameter on "params" property can be provided as a direct value or as a path for some existing Magento setting.

To provide a parameter as a direct value, the properties "type" and "value" must be provided. For the "type" property, the string "direct" must be set and the "value" property must contain the parameter's actual value. Below example shows how to provide the AuthorizeNet transaction key.

```json
{"params":{"transactionKey":{"type":"direct","value":"XSS983HDN3"}}}
```

To provide a parameter as a reference to an existing Magento setting, the properties "type" and "path" must me provided. For the "type" property, the string "path" must be set and the "path" property must contain the path for the desired setting. Below example shows how to get the AuthorizeNet transaction key from AuthorizeNet's official extension: this way re-typing the transaction key directly in settings is not necessary. The extension will get it from the provided path. Also, if for some reason the transaction key is changed on AuthorizeNet's extension settings, changing it on the gateway integration settings will not be necessary.

```json
{"params":{"transactionKey":{"type":"path","path":"authorize_net/anet_core/trans_key"}}}
```

Finally, after the gateway class and parameters are defined, perform the insert SQL command on the database.

```sql
INSERT INTO core_config_data(path, value) VALUES(
    'signifyd/gateway_integration/{payment_method_code}',
    '{"gateway":"\\\\Gateway\\\\Class","params":{...}}}'
);
```

**Note:** the gateway class name must include extra slash scapes on the SQL command.

It is possible to use multiscope (multi store) settings on core_config_data if needed.

## Build custom payment gateway

The new gateway class must implement \Signifyd\Models\Payment\GatewayInterface and return a class implementing \Signifyd\Models\Payment\Response\ResponseInterface as response.

The Signifyd SDK also provides an abstract gateway class \Signifyd\Models\Payment\AbstractGateway, which can be extended by the final gateway class implementation and a default response class \Signifyd\Models\Payment\Response\DefaultResponse. It is strongly recommended that the default response class to be used as either the final response class or as parent for the final response class.

If the \Signifyd\Models\Payment\AbstractGateway class is used as a parent class for the new custom class, the parameters set on core_config_data will be set on the $params property.

Below is an example of a gateway class implementation.

```php
<?php

namespace Vendor\Module;

use Signifyd\Models\Payment\Response\DefaultResponse;

class CustomGateway extends AbstractGateway
{
    /**
     * @param $transactionId
     * @return DefaultResponse|Response\ResponseInterface
     */
    public function fetchData($transactionId)
    {
        $apiKey = $this->params['api_key'];
        
        // Fetch data from gateway API. E.g. using curl request
        $response = curl_exec(...);
        $responseJson = json_decode($response);

        // Instantiate and populate 
        $response = new \Signifyd\Models\Payment\Response\Authorizenet();
        $response->setCardholder($responseJson->cardholder);
        $response->setLast4($responseJson->last4);
        $response->setBin($responseJson->bin);
        $response->setAvs($responseJson->avsResponse);
        $response->setCvv($responseJson->cvvResponse);
        $response->setExpiryMonth($responseJson->expMonth);
        $response->setExpiryYear($responseJson->expYear);

        return $response;
    }
}
```

And finally, provide settings to the Signifyd extension on Magento database by adding settings on the core_config_data table as explained in the *Use existing payment gateway classes* section.

```sql
INSERT INTO core_config_data(path, value) VALUES(
    'signifyd/gateway_integration/custom_payment_method_code',
    '{"gateway":"\\\\Vendor\\\\Module\\\\CustomGateway","params":{"api_key":{"type":"direct","value":"APIKEY"}}}}'
);
```
