# Detailed Overview of Implementing Async Payment methods
## 1. AsyncCheckerInterface
### Purpose 
This interface defines a contract for classes that check the completion status of asynchronous payments.
Method: The __invoke(Order $order, Casedata $case) method is the key method that takes an Order and Casedata object and returns a boolean indicating the payment completion status.
## 2. AsyncChecker Classes
### AdyenPayByLink/AsyncChecker:
#### Functionality: 
Checks if an order made with 'adyen_pay_by_link' has a transaction ID (ccTransId). If not, it's assumed that the payment hasn't been completed.
#### Implementation: 
Extends from BaseAsyncChecker, utilizes the parent class's functionality after performing its specific checks.
### Stripe/Payments/AsyncChecker:
#### Functionality: 
For Stripe payments, it verifies if the stripe_status is 'approved'. If not, the payment is considered incomplete.
#### Implementation: 
Also extends BaseAsyncChecker, and includes additional logic specific to Stripe.
## 3. PaymentVerificationFactory Modifications
### Updates 
Now includes support for AsyncCheckerInterface, allowing the system to handle different asynchronous payment methods dynamically.
Method Creation: Added a method createPaymentAsyncChecker() which dynamically creates an appropriate async checker based on the payment method.
## 4. ProcessCron Updates
### Integration of Async Checkers
Cron classes are updated to use async checkers, thereby ensuring that asynchronous payments are fully processed before any further action is taken.
## 5. Configuration Updates in config.xml
### Async Payment Methods 
Added entries for async payment methods such as 'adyen_pay_by_link', allowing these methods to be recognized and processed correctly by the system.
Method Specific Configuration: Each async payment method has specific configurations like async checkers and other related settings.
## 6. Purchase Observer Logic for Phone Orders
### Order Channel Detection 
The observer sets the orderChannel to "PHONE" for orders placed through the admin area. This is critical for correctly identifying the context of the order.
#### Implementation
In Model/Api/Purchase.php, checks for the originStoreCode and sets the orderChannel accordingly.


# Detailed Documentation for Implementing New Async Payment Methods
## Developing a New AsyncChecker Class:

### Class Creation 

Create a new class in the appropriate namespace, implementing AsyncCheckerInterface.
#### Invoke Method 
Implement the __invoke() method to include specific logic for determining if the payment is complete for the new method.

### Integrating with PaymentVerificationFactory:
#### Factory Update
Add a new case for the async checker in PaymentVerificationFactory.
#### Factory Method 
Utilize createPaymentAsyncChecker() for creating instances of the new async checker.

### Configuring New Payment Methods:
#### XML Configuration
Update config.xml to include the new payment method in async_payment_methods.
Method-Specific Settings: Add necessary method-specific configurations in config.xml, similar to existing methods.

### Registering the New Payment Method:
#### DI Configuration
Ensure the new async checker class is correctly configured in etc/di.xml for dependency injection.

## Understanding Existing Async Payment Methods

### Adyen Pay by Link

Uses the AdyenPayByLink/AsyncChecker class for checking if the transaction ID is set post-payment.
Stripe Payments:

Utilizes the Stripe/Payments/AsyncChecker class to verify the stripe_status of the payment.
### Common Logic:

Both methods use the base logic from BaseAsyncChecker and extend it for specific payment method checks.