# SIGNIFYD Connect extension for Magento2

## v1.0.0RC1 

2015-01-01 - This is Release Candidate 1 of the new SIGNIFYD Connect extension for Magento2. It is largely based on the original Magento SIGNIFYD plugin with identical or similar functionality and configuration where possible:

* Given an SIGNIFYD API key, the extension will automatically submit all new Orders to SIGNIFYD for risk evaluation.
* Via webhooks, SIGNIFYD will send back scoring information within seconds which is displayed on the Magento2 Order Grid.
* The user may configure a score threshold to automatically  place high risk orders **On Hold** for further review.
* Customers of SIGNIFYD's Guarantee product can also receive guarantee results via webhooks which are also displayed on the Order Grid.
* Customers can configure certain actions to occur on Guarantee events such as unholding **On Hold** orders upon approval.

There are a few differences from the original plugin:

* Display of SIGNIFYD columns in the Order Grid is now controlled with Magento2's own column controls rather than from the SIGNIFYD Connect config. This applies to both scores and guarantee dispositions.
* Automatically canceling **On Hold** orders when a guarantee is declined is no longer allowed.
* The order resend functionality is temporarily unavailable, but should be online again shortly.

Contact: [Brannon Smith](mailto:brannon@signifyd.com),

SIGNIFYD Software Engineer

