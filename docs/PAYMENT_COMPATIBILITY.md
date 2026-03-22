# Payment Compatibility

Last reviewed: 2026-03-22

This note explains how Pressplay LMS stays compatible with the broader WooCommerce payment ecosystem without coupling the plugin to one specific gateway.

## Strategy

Pressplay LMS treats WooCommerce as the source of truth for payment state.

Instead of listening for gateway-specific callbacks from PayPal, Mercado Pago, PagBank/PagSeguro, or another single provider, the LMS reacts to the order lifecycle that WooCommerce exposes:

- `woocommerce_checkout_order_processed`
- `woocommerce_payment_complete`
- `woocommerce_order_status_changed`
- `wc_get_is_paid_statuses()`

This makes the plugin compatible with gateways that integrate correctly with WooCommerce order statuses and payment confirmation flows.

## What the LMS does

1. Creates or refreshes a pending enrollment when the order is created.
2. Activates enrollment access when WooCommerce confirms payment.
3. Also activates access when the order moves into a paid status recognized by WooCommerce.
4. Revokes access when the order moves into invalid states such as:
   - `cancelled`
   - `failed`
   - `refunded`
5. Stores the actual WooCommerce payment method ID on the enrollment when available.

## Why this is more universal

Different gateways confirm payments differently:

- some confirm immediately and move the order to `processing` or `completed`
- some confirm later through a webhook and only then update the WooCommerce order
- some extensions add custom paid statuses through WooCommerce filters or status extensions

By following WooCommerce's own payment lifecycle instead of a gateway-specific API, the LMS remains compatible with:

- PayPal gateways for WooCommerce
- Mercado Pago for WooCommerce
- PagBank / PagSeguro gateways for WooCommerce
- Stripe gateways for WooCommerce
- other gateways that correctly synchronize payment events back to WooCommerce

## Compatibility expectations for any gateway

A gateway is considered compatible when it does at least one of these correctly:

- calls the WooCommerce payment-complete flow
- updates the order to a paid status recognized by WooCommerce
- updates the order to an invalid status on cancellation, failure, or refund

If a gateway does not update WooCommerce order state correctly, the LMS should not attempt to guess payment status directly from the gateway API.

## Notes for future development

- Prefer WooCommerce hooks over custom gateway hooks.
- Avoid hardcoding support for one provider unless there is a verified bug in that provider.
- If a gateway needs a fallback integration, keep it additive and do not replace the WooCommerce-native flow.
- When adding support for custom order statuses, rely on `wc_get_is_paid_statuses()` whenever possible.

## Reference sources

- WooCommerce code reference showing stock and payment flows tied to `woocommerce_payment_complete` and order status hooks:
  - https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-stock-functions.html
- WooCommerce code reference showing paid-status handling via `wc_get_is_paid_statuses()`:
  - https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-user-functions.html
- WooCommerce Order Status Manager extension documentation showing that stores can define custom paid statuses:
  - https://woocommerce.com/document/woocommerce-order-status-manager/
- WooCommerce PayPal Payments plugin page:
  - https://wordpress.org/plugins/woocommerce-paypal-payments/
- Mercado Pago for WooCommerce plugin page:
  - https://wordpress.org/plugins/woocommerce-mercadopago/
- PagBank / PagSeguro plugin ecosystem example with automatic notification handling:
  - https://wordpress.org/plugins/woo-pagseguro-rm/
