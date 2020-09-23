[Signifyd Extension for Magento 2](../README.md) > Pass payment gateway details **using payment gateways APIs**

# Pass payment gateway details **using payment gateways APIs**

## Overview

Use integrations directly to payment gateway APIs to fetch most of these information:

- AVS Response Code
- CVV Response Code
- Bin
- Last 4
- Expiry Month
- Expiry Year
- Transaction ID

## Basic Structure

In order to fetch information a gateway class must implement \Signifyd\Models\Payment\GatewayInterface and return as response a class implementing \Signifyd\Models\Payment\Response\ResponseInterface.

Signifyd SDK also provide a abstract gateway class \Signifyd\Models\Payment\AbstractGateway which can be extended by the final gateway class implementation and a default response class \Signifyd\Models\Payment\Response\DefaultResponse, which it is strongly recommended to be used as either the final response class or as parent for the final response class.

Before start building your own classes, check if it is not already implemented under \Signifyd\Models\Payment namespace.

## Use existing payment gateway classes

If the payment gateway class already exists for the desired payment gateway, it is only needed to provide a configuration to get it working. Each payment method extension should have it's own settings. Some payment methods extensions have this configuration built in on Signifyd extension, as we can see for AuthorizeNet official extension on etc/config.xml:

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

Settings for a gateway must be set on core_config_data table on Magento database using below string as path.

```
signifyd/gateway_integration/{payment_method_code}
```

The value must be a JSON string which need to include two properties: gateway and params.

The "gateway" property must include the gateway class name with duplicated slashes. E.g. if class name is \Signifyd\Models\Payment\Authorizenet it must be provided as \\Signifyd\\Models\\Payment\\Authorizenet.

The "params" property should include information needed by payment gateway class to comunicate with gateway API. E.g. for \Signifyd\Models\Payment\Authorizenet class params "name" and "transactionKey".

Each param on "params" property can be provided as a direct value or as a path for some existing Magento setting.

To provide a param as a direct value properties "type" and "value" must be provided. For "type" property the string "direct" must be set and "value" property must contain the param actual value. Below example show how to provide AuthorizeNet transaction key.

```json
{"params":{"transactionKey":{"type":"direct","value":"XSS983HDN3"}}}
```

To provide a param as a reference to an existing Magento setting properties "type" and "path" must me provided. For "type" property the string "path" must be set and "path" property must contain the path for the desired setting. Below example show how to get AuthorizeNet transaction key from AuthorizeNet official extension, this way it is not necessary to re-type the transaction key directly on settings, extension will get it from the provided path. Also, if for some reason the transaction key is changed on AuthorizeNet extension settings will be not necessary to change it on gateway integration settings.

```json
{"params":{"transactionKey":{"type":"path","path":"authorize_net/anet_core/trans_key"}}}
```

Finally, after it is defined the gateway class and params, perform the insert SQL command on database.

```sql
INSERT INTO core_config_data(path, value) VALUES(
    'signifyd/gateway_integration/{payment_method_code}',
    '{"gateway":"\\\\Gateway\\\\Class","params":{...}}}'
);
```

**Note:** gateway class name must include extra slash scapes on SQL command

It is possible to use multiscope (multi store) settings on core_config_data if needed.

## Build custom payment gateway

The new gateway class must implement \Signifyd\Models\Payment\GatewayInterface and return as response a class implementing \Signifyd\Models\Payment\Response\ResponseInterface.

Signifyd SDK also provide a abstract gateway class \Signifyd\Models\Payment\AbstractGateway which can be extended by the final gateway class implementation and a default response class \Signifyd\Models\Payment\Response\DefaultResponse, which it is strongly recommended to be used either as the final response class or as parent for the final response class.

If \Signifyd\Models\Payment\AbstractGateway class is used as parent class for the new custom class, params set on core_config_data will be set on $params property.

Below a example of a gateway class implementation.

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

And finally provide settings to Signifyd extension on Magento database by adding settings on core_config_data table as explained on * Use existing payment gateway classes* section.

```sql
INSERT INTO core_config_data(path, value) VALUES(
    'signifyd/gateway_integration/custom_payment_method_code',
    '{"gateway":"\\\\Vendor\\\\Module\\\\CustomGateway","params":{"api_key":{"type":"direct","value":"APIKEY"}}}}'
);
```
