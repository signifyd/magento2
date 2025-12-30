# **Pass Pre-Auth Data – Using GraphQL APIs**

## **Overview**

The Signifyd pre-authorization (pre_auth) process is described in detail in the document
[PASS-PAYMENT-PRE-AUTH.md](/PASS-PAYMENT-PRE-AUTH.md)

This document focuses specifically on **making the pre_auth process compatible with Adobe Commerce GraphQL APIs**.

To achieve this, it is necessary to **extend the Magento GraphQL schema** by adding new fields to the `PaymentMethodInput` used in **Step 9 (set payment method)** of the Adobe Commerce checkout flow:

* [https://developer.adobe.com/commerce/webapi/graphql/tutorials/checkout/set-payment-method/](https://developer.adobe.com/commerce/webapi/graphql/tutorials/checkout/set-payment-method/)

The pre_auth data sent during this step is **stored in the database**, and later consumed during **Step 10 (place order)**:

* [https://developer.adobe.com/commerce/webapi/graphql/tutorials/checkout/place-order/](https://developer.adobe.com/commerce/webapi/graphql/tutorials/checkout/place-order/)

During the *place order* step, Signifyd will automatically perform the pre-authorization, sending the captured values to the Signifyd service.

---

## **Pre-Auth Data Structure**

To enable GraphQL compatibility, the Signifyd extension adds a new object inside the `payment_method` input:

```
signifyd_preauth_data
```

This object accepts the following fields:

* `cardBin`
* `cardExpiryMonth`
* `cardExpiryYear`
* `cardLast4`
* `holderName`

### **Example GraphQL Usage**

```graphql
mutation {
  setPaymentMethodOnCart(input: {
      cart_id: "{ CART_ID }"
      payment_method: {
          code: "{ Method_Code }"
          signifyd_preauth_data: {
            cardBin: "444444"
            cardExpiryMonth: "05"
            cardExpiryYear: "2039"
            cardLast4: "8888"
            holderName: "John Doe"
          }
      }
  }) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

**Note:**
At this point, Signifyd does **not** yet receive the pre_auth data.
The data is stored in the quote and will be transmitted **during the placeOrder step**.

# **Practical Checkout Flow Using GraphQL**

Below is a complete demonstration of a working flow, showing how pre_auth fields fit into the full checkout process.

---

## **1. Create a customer**

```graphql
mutation {
  createCustomerV2(
    input: {
      firstname: "John"
      lastname: "Doe"
      email: "john.doe@example.com"
      password: "b1b2b3l@w+"
      is_subscribed: true
    }
  ) {
    customer {
      firstname
      lastname
      email
      is_subscribed
    }
  }
}
```

---

## **2. Generate a customer authentication token**

```graphql
mutation {
  generateCustomerToken(email: "john.doe@example.com", password: "b1b2b3l@w+") {
    token
  }
}
```

The token must be added to the `Authorization: Bearer {TOKEN}` header for the next steps.

---

## **3. Create a customer cart**

```graphql
{
  customerCart {
    id
  }
}
```

---

## **4. Add a simple product to the cart**

```graphql
mutation {
  addSimpleProductsToCart(
    input: {
      cart_id: "{ CART_ID }"
      cart_items: [
        {
          data: {
            quantity: 1
            sku: "24-MG04"
          }
        }
      ]
    }
  ) {
    cart {
      itemsV2 {
        items {
          id
          product {
            sku
            stock_status
          }
          quantity
        }
      }
    }
  }
}
```

---

## **5. Set shipping address**

```graphql
mutation {
  setShippingAddressesOnCart(
    input: {
      cart_id: "{ CART_ID }"
      shipping_addresses: [
        {
          address: {
            firstname: "John"
            lastname: "Doe"
            company: "Company Name"
            street: ["3320 N Crescent Dr", "Beverly Hills"]
            city: "Los Angeles"
            region: "CA"
            region_id: 12
            postcode: "90210"
            country_code: "US"
            telephone: "123-456-0000"
            save_in_address_book: false
          }
        }
      ]
    }
  ) {
    cart {
      shipping_addresses {
        firstname
        lastname
        company
        street
        city
        region {
          code
          label
        }
        postcode
        telephone
        country {
          code
          label
        }
        available_shipping_methods {
          carrier_code
          carrier_title
          method_code
          method_title
        }
      }
    }
  }
}
```

---

## **6. Set billing address**

```graphql
mutation {
  setBillingAddressOnCart(
    input: {
      cart_id: "{ CART_ID }"
      billing_address: {
        address: {
          firstname: "John"
          lastname: "Doe"
          company: "Company Name"
          street: ["64 Strawberry Dr", "Beverly Hills"]
          city: "Los Angeles"
          region: "CA"
          region_id: 12
          postcode: "90210"
          country_code: "US"
          telephone: "123-456-0000"
          save_in_address_book: true
        }
      }
    }
  ) {
    cart {
      billing_address {
        firstname
        lastname
        company
        street
        city
        region {
          code
          label
        }
        postcode
        telephone
        country {
          code
          label
        }
      }
    }
  }
}
```

---

## **7. Set delivery method**

```graphql
mutation {
  setShippingMethodsOnCart(input: {
    cart_id: "{ CART_ID }"
    shipping_methods: [
      {
        carrier_code: "tablerate"
        method_code: "bestway"
      }
    ]
  }) {
    cart {
      shipping_addresses {
        selected_shipping_method {
          carrier_code
          method_code
          carrier_title
          method_title
        }
      }
    }
  }
}
```

---

## **8. Set payment method (with Signifyd pre_auth data)**

This is where the pre-auth data must be added to the `payment_method` object.
The fields will be stored in the database and consumed later during the placeOrder step.

```graphql
mutation {
  setPaymentMethodOnCart(
    input: {
      cart_id: "{ CART_ID }"
      payment_method: {
        code: "{ Method_Code }"
        signifyd_preauth_data: {
          cardBin: "444444"
          cardExpiryMonth: "05"
          cardExpiryYear: "2039"
          cardLast4: "8888"
          holderName: "Jonathan Test"
        }
      }
    }
  ) {
    cart {
      selected_payment_method {
        code
      }
    }
  }
}
```

---

## **9. Place the order (pre_auth performed here)**

During this call, Signifyd receives the pre_auth data stored previously and performs the pre-authorization request.

```graphql
mutation {
  placeOrder(input: { cart_id: "{ CART_ID }" }) {
    orderV2 {
      number
    }
    errors {
      message
      code
    }
  }
}
```

---

# **Conclusion**

By extending the Magento GraphQL schema and injecting the `signifyd_preauth_data` object into the `PaymentMethodInput`, the Signifyd pre-authorization process becomes fully compatible with headless and GraphQL-based checkout flows.
