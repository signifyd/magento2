# Decision Request

Signifyd has a mapping for the decision request. By default, the extension will automatically use guarantee decision (GUARANTEE), but it is possible to set other decision.

A list of the possible values for DECISION-REQUEST can be founded on Signifyd API docs, look for decisionRequest.paymentFraud.

[https://developer.signifyd.com/api/#/reference/cases/create-case](https://developer.signifyd.com/api/#/reference/cases/create-case)

### Setting global decision

If the setting has a string, the extension will assume this as the only decision for all cases.

To set global decision run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/decision_request', 'DECISION-REQUEST');
```

### Setting decision per payment method

It is possible to select a different decision per payment method.

If the setting stores a JSON, then it will map each payment method listed on JSON to the corresponding decision. Any payment methods not mapped will fallback to the GUARANTEE decision. Here it is an example of how the final JSON could look like:

```
{"GUARANTEE": ["braintree"], "SCORE": ["checkmo"], "DECISION": ["paypal_braintree"]}
```
To set decision per payment method run command below on your database:

```sql
INSERT INTO core_config_data (path, value) VALUES ('signifyd/general/decision_request', 'INSERT-JSON-MAPPING');
```

### Updating decision

To change the decision, run command below on your database:

```sql
 UPDATE core_config_data SET value = 'DECISION-REQUEST' WHERE path = 'signifyd/general/decision_request';
```

### Check policy

To check the current decision, run the command below on your database:

```sql
SELECT * FROM core_config_data WHERE path = 'signifyd/general/decision_request';
```

If no records are found, the extension will automatically use guarantee decision.
