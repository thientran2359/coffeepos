=== CoffeePOS ===
Contributors: coffeepos
Tags: woocommerce, point of sale, pos, restaurant, coffee shop
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WooCommerce-powered point of sale with cashier, customer display, kitchen, queue, shifts, reports, and receipts.

== Description ==

CoffeePOS provides a browser-based point-of-sale workflow for coffee shops that
use WooCommerce as the authoritative catalog, customer, stock, coupon, and order
system.

Features include:

* Cashier catalog, product variations, item notes, coupons, and cart management.
* Dine-in tables and takeaway service.
* Guest and WooCommerce customer/member identification.
* Cash and manually confirmed bank-transfer checkout.
* Paired Customer Display with payment amount and VietQR presentation.
* Kitchen Display and Order Queue workflows.
* Shift management, order history, refunds, reorders, reports, and receipts.
* WordPress-account staff access with granular CoffeePOS capabilities.
* Frontend CoffeePOS settings independent from WordPress site identity.

CoffeePOS never treats browser-provided totals, stock, permissions, or payment
state as authoritative. WooCommerce remains the source of truth for commerce
data and order creation.

== Requirements ==

* WordPress 6.4 or later.
* PHP 7.4 or later.
* WooCommerce installed and active.
* HTTPS is recommended for production use.
* A browser that supports BroadcastChannel is required for paired Customer Display synchronization.

== Installation ==

1. Upload the `coffeepos` directory to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add New.
2. Activate CoffeePOS while WooCommerce is active.
3. Open `/pos/` and sign in with an authorized WordPress account.
4. Configure store identity, tables, payments, receipt, membership, and operations at `/pos/settings/`.
5. Open Customer Display from the active Cashier session so both screens share the same POS session identifier.

CoffeePOS adds dedicated role bundles during activation. Administrators and Shop
Managers receive all CoffeePOS capabilities; other staff should receive only the
capabilities required for their work.

== External services ==

= VietQR image service =

When Bank Transfer is enabled and the cashier selects it during checkout,
CoffeePOS creates an image URL hosted by `https://vietqr.app/img`. The Customer
Display browser requests that image so the customer can scan a payment QR code.

The request may send the configured bank identifier, bank account number,
account holder, store name, authoritative order amount, and generated payment
reference to VietQR. No request is made when Bank Transfer is disabled or the
VietQR beneficiary is not configured.

Service information: https://vietqr.app/

VietQR only presents payment instructions. CoffeePOS does not consider the QR
image proof of payment. The cashier must independently verify receipt of funds
and explicitly confirm payment before CoffeePOS creates the WooCommerce order.
Use of the external service is subject to the service provider's published
terms and privacy practices.

== Frequently Asked Questions ==

= Does CoffeePOS replace WooCommerce products or orders? =

No. WooCommerce remains authoritative for products, variations, stock,
customers, coupons, orders, order items, refunds, and commerce totals.

= Does the VietQR image automatically confirm payment? =

No. The cashier verifies the bank receipt and confirms payment manually.

= Does CoffeePOS use a separate employee password or PIN? =

No. Staff authenticate with normal WordPress accounts and CoffeePOS capabilities.

= Can the Customer Display be opened on another device? =

The current synchronization uses a session-scoped BroadcastChannel and is
intended for paired browser contexts on the same browser profile/device.

== Privacy ==

CoffeePOS stores POS transaction data through WooCommerce and its documented
private order metadata. Customer Display receives a privacy-limited projection
and masks member phone numbers. Private order notes and staff capabilities are
not broadcast to Customer Display.

Administrators are responsible for configuring appropriate WordPress privacy
notices, retention practices, staff access, and the disclosed VietQR service for
their jurisdiction.

== Changelog ==

= 1.0.0 =

* First WordPress.org release candidate.
* Added complete Cashier, Customer Display, KDS, Order Queue, Shift, History, Reports, Settings, and receipt workflows.
* Added WordPress-account staff roles and granular capabilities.
* Added GPL-compatible release metadata and internationalization foundation.
* Documented VietQR external-service behavior and transmitted fields.

== Upgrade Notice ==

= 1.0.0 =

Review staff capabilities and payment settings after upgrading from a development build.
