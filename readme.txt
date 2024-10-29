=== Bayarcash WooCommerce ===
Contributors: webimpian
Tags: FPX, DuitNow, Direct Debit, DuitNow QR
Requires at least: 5.6
Tested up to: 6.6.1
Requires PHP: 7.4
Stable tag: 4.2.5
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Accept online payment & QR from Malaysia. Currently, Bayarcash support FPX, Direct Debit and DuitNow payment channels.

== Description ==

Bayarcash is a Malaysia online payment platform that support FPX, Direct Debit & DuitNow payment channels.

Fully supports WooCommerce Subscription products with Direct Debit functionality. [See more](https://woocommerce.com/products/woocommerce-subscriptions/)

== How it works ==

This plugin will connect to Bayarcash endpoint to secure payment processing between bank & ewallet in Malaysia.

Please visit our website [https://bayarcash.com/](https://bayarcash.com/) for terms of use and privacy policy, or email to hai@bayarcash.com for any inquiries.

== Features ==

- One-off payment via FPX (CASA & credit card account)
- Payment via DuitNow Online Banking/Wallets
- Payment via DuitNow QR
- Support cross-border payment via DuitNow QR
- Weekly & monthly recurring payment via Direct Debit. Deduction happen automatic directly via bank account (flat rate fees).
- Support multiple Bayarcash account per website
- Shariah-compliance payment gateway

Register as [**Bayarcash merchant here**](https://bayarcash.com/register/)

== Requirements ==

To use Bayarcash WooCommerce requires minimum:

- PHP 7.4
- WordPress 5.6
- WooCommerce Plugin

== Installation ==

= Demo =

[Test with WordPress](https://tastewp.com/new/?pre-installed-plugin-slug=bayarcash-wc&pre-installed-plugin-slug=woocommerce&redirect=admin.php%3Fpage%3Dwc-settings%26tab%3Dcheckout%26section%3Dbayarcash-wc%26ni%3Dtrue)

Make sure that you already have WooCommerce plugin installed and activated.

1. Login to your **WordPress Dashboard**
2. Go to **Plugins > Add New**
3. Search **Bayarcash WC** and click **Install**
4. **Activate** the plugin through the **Plugins** screen in WordPress

= Updating =

While our plugin supports seamless automatic updates, we strongly advise creating a full backup of your site before any update process. This precautionary measure ensures the safety of your data and allows for easy restoration if needed.

== Screenshots ==
* Fill up the Personal Access Token (PAT) and Save changes to activate.
* Checkout and pay with Bayarcash
* WooCommerce order note

== Frequently Asked Questions ==

= Where can I register as Bayarcash merchant? =
You can register as merchant [here](https://bayarcash.com/register/). We accept organisation that has active SSM certificate, ROS for non-governmental organization (NGO), state-certified for madrasah & sekolah tahfiz and yayasan.

= What does it mean by shariah-compliance payment gateway? =
Please note that in order for us to comply with our shariah-compliance policy, we do not support organisation involved in:

- The production or sale of pork, alcohol and alcohol-related activities, non-halal food and beverages, tobacco product (including e-cigarettes), drug paraphernalia, pornography, guns, and other arms
- Gaming and betting
- Shariah non-compliant entertainment
- Conventional insurance
- Jihadist or terrorist activities
- Fraud and corruption organization

[Click here](https://bayarcash.com/wp-content/uploads/sites/2/2022/09/elzar-bayarcash.jpeg) to view shariah-certificate endorsement by our official advisor Dr. Zaharuddin Abd Rahman from Elzar Shariah Solutions & Advisory.

== Changelog ==

= 4.2.5 =
* Gateway Fees: Added option to combine flat rate and percentage
* Buy Now, Pay Later (BNPL): New promotional label on catalog and product pages
* SPayLater: Added warning for orders over RM 1,000

= 4.2.4 =
* Fixed error when changing order status
* Enhanced retrieve portal list display all list available

= 4.2.3 =
* Fixed compatibility issues with PHP 7.4 and added full support for this version

= 4.2.2 =
* Improve payment option logo image

= 4.2.1 =
* Improve order note

= 4.2.0 =
* Added support for DuitNow QR, SPayLater,Boost PayFlex & QRIS payment methods
* Added fallback email option to ensure transaction processing when customer email is disabled or unavailable at checkout
* Implemented configurable gateway fees for different payment methods
* Introduced payment gateway restriction options for each payment method
* Added option to customize checkout logo display for improved brand visibility

= 4.1.2 =
* Iteration on payment channel logos

= 4.1.1 =
* Fix small bugs

= 4.1.0 =
* Integrated support for WooCommerce Subscriptions, enabling Direct Debit payments for subscription-based products
* Enhanced phone number processing for improved data transmission to Bayarcash
* Add custom field for id verification in Funnelkit checkout for subscription-based products
* Refined error messaging to provide more user-friendly and informative notifications

= 4.0.0 =
* Added support for DuitNow and Line of Credit payment methods
* Implemented new Bayarcash SDK for improved API interactions
* Streamlined token verification with Vue.js, reducing admin page bloat
* Enhanced admin settings page with dynamic portal key selection
* Upgraded cron requery function for better performance
* Added checksum verification for increased security
* Improved error handling and logging for individual payment methods
* Refactored code for better structure and maintainability

= 3.0.0 =
* Refactoring and code improvements.

= 2.0.19 =
* Prevent the plugin from accidentally changing the order status that has already been paid (like on hold, processing, completed, etc) back to failed after the requery process to Bayarcash Console.
* Add parameter raw_website containing order data to the transaction request form. 

= 2.0.18 =
* Add security measure to ensure request received from server is not tampered

= 2.0.17 =
* Comment out cron status logger to reduce WC log verbosity
* Add prefix BC_WooCommerce_FPX to file names and class names
* Change cron implementation to execute in actionable class instance instead of relying on http request triggers
* Update plugin identifier as bayarcash instead of generic fpx
* Optimize cron re-query by only only querying orders that have pending status, payment_method of bayarcash/fpx with return result limit capped at 30 orders
* Fix access non-existent method get_transaction_order_no() to get_order_no()

= 2.0.16 =
* Fix order note for normal callback return, mapped buyer name correctly 

= 2.0.15 =
* Fix re-query order status update respond mapping from console.bayar.cash 

= 2.0.14 =
* Handle other payment response that is obtain when user complete the purchase-payment cycle correctly
* Split database handler for different responses

= 2.0.13 =
* Fix missing variable argument on method and invocation

= 2.0.12 =
* Add if WC Order Status Manager Plugin active use plugin order status def.
* Refactor check_exchange_no_can_be_add into separate operations
* Remove JS limit checkout button clicks code

= 2.0.11 =
* Prevent abnormal fpx_transaction_exchange_number from being to stored
* Add js implementation to prevent user from multiple requests to server
* If order status already pending don't add transaction exchange number

= 2.0.10 = 
*  Fix unpaid transaction redirect back to success thank you page

= 2.0.9 = 
*  Fix Transaction ID not saved to wp_postmeta, add wc logger

= 2.0.8 = 
*  Add comments and DocBlock

= 2.0.7 = 
*  Filter server order info, only updated previous selected order statuses

  The order statuses are :
  - pending
  - canceled

= 2.0.6 = 
* Add FPX Response Sanitizer,Validator,better order no duplicate handling

= 2.0.5 =
* Remove hardcode set order to processing status when success pay

= 2.0.4 =
* Change object property mapping to get correct order no

= 2.0.3 =
* Change cron interval to 5 minutes
* Fix add duplicated order notes
* Update DocBlock
* Add GPL-3.0-or-later license reference

= 2.0.2 =
* Fix variable fpx_output_data_primary to use the latest output returned from the payment portal.
* Remove trailing comma at Authorization: Bearer array.
* Remove payment type description at both back-end and front-end.
* Fix duplicate missing data detection, this->portal_key at the back-end. The 1st should detect missing bearer token, and the 2nd should detect missing Payment Portal key.
* Remove Cancel button at the checkout page after buyer confirm choosing FPX, add auto-click features to the submit button, and immediately display the page loader while contacting the Payment Portal.
* Add customizable payment channel title and payment channel description.

= 2.0.1 =
* Replace parameter s3a with RefNo for more user friendly submission request.
* Add parameter payment_gateway = 1 to the transaction request form.
* Re-order parameters for order payment transaction data comparison between the payment portal and shopping cart.

= 2.0.0 =
* Replace combination of FPX Payment Portal Auth User and Auth Password with Bearer Token for Payment Portal user authentication.
* Update API_client_version to v2.0.0
* Add 'Accept: application/json' and 'Authorization: Bearer ' .$bearer_token to cURL request for communication to the payment portal.

= 1.0 =
* Initial release.
