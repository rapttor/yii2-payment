# PayPal setup (one-time payments)

Docs: [Orders v2](https://developer.paypal.com/docs/api/orders/v2/) · [Payments v2](https://developer.paypal.com/docs/api/payments/v2/) · [Webhooks](https://developer.paypal.com/api/rest/webhooks/) · [Sandbox](https://developer.paypal.com/tools/sandbox/)

1. Log in to [developer.paypal.com](https://developer.paypal.com/dashboard/) → **Apps & Credentials**, mode **Sandbox**.
2. Create an app (type *Merchant*). Copy *Client ID* → `PAYPAL_CLIENT_ID` and *Secret* → `PAYPAL_CLIENT_SECRET`.
3. In the app: **Webhooks → Add webhook**, URL `https://<your host>/payments/webhook/paypal`, events `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`, `PAYMENT.CAPTURE.REFUNDED`. Copy the **Webhook ID** → `PAYPAL_WEBHOOK_ID` (PayPal verifies signatures with it).
4. Switch **PayPal** on in `/payments-admin` with mode `test` (host `api-m.sandbox.paypal.com`).
5. Pay with a sandbox *personal* account (Sandbox → Accounts). The buyer returns to `/payments/return/paypal`, where the order is **captured** server-side; the capture webhook is deduplicated against it.
6. Refund from Backoffice → Orders.

Limits: no subscriptions yet (needs PayPal Billing plans), no catalog. You are the seller of record: handle VAT yourself.
