# Billing

Billing resources are central records keyed by UUID. The superadmin includes operational views for plans, subscriptions, payments, invoices, refunds, coupons, taxes, currencies, and gateways.

Gateway secrets must be stored as encrypted settings or encrypted provider payloads and masked in UI/audit output. Webhook processing should use idempotency keys and queue-backed jobs before enabling live providers.
