[Signifyd Extension for Magento 2](../README.md) > Reroute

# Reroute

This endpoint should be called anytime Delivery Address on an Order needs to be changed.
Orders can be updated a maximum of 100 times for a new decision.

The reroute will be automatically created in Magento whenever an order's shipping address changes. 
The first attempt is made when saving the address, however, if an error occurs, the reroute will be done via cron.
Orders that do not exist on Signifyd or that already have a shipment created will not be sent.