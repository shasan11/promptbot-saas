# Customer Portal

The customer portal is the central, account-level product at `/account`. It is deliberately separate from both the `/superadmin` control plane and users stored inside tenant databases.

## Identity and account model

- `portal_users` contains customer identities and uses the `portal` authentication guard.
- `customer_accounts` is the commercial owner of services, subscriptions, invoices, payments, support tickets, and billing details.
- `customer_account_users` is a many-to-many membership table. A person can belong to several accounts and switch the active account without signing in again.
- The membership role (`owner`, `admin`, `billing`, `member`, `viewer`) and capability flags are checked from the database for every protected operation.
- Tenant users remain tenant-local and are not silently copied from portal identities. Creating a tenant owner is an explicit provisioning option.

Registration creates a portal identity, an owned customer account, its billing profile, an activity record, and an ownership membership in one transaction. Email verification is enforced when the registration policy requires it and can be disabled without leaving users trapped behind verification middleware. Password-reset tokens and notifications use the portal broker and portal routes.

## Google customer authentication

Google authentication is optional and applies only to the `portal` guard. It is never shown on Superadmin or tenant-staff login. Password authentication remains available.

Existing customers are resolved by a unique provider identity or safely linked once to the same verified Google email. Inactive portal users are rejected before a link or session is created. Google authentication does not bypass PromptBot two-factor authentication: users with local 2FA continue through the existing portal challenge. Successful and failed attempts use `PortalLoginActivity`, identify `google` as the provider, regenerate the session after authentication, and continue through normal `PortalUserSession` tracking. Provider access and refresh tokens are not stored.

New Google identities are accepted only when public registration policy is `enabled`. Disabled and invitation-only policies refuse account creation. The customer confirms an account name after Google returns; PromptBot then creates the portal user, customer account, owner membership, billing profile, and account activity through the existing account service. A verified Google email is marked verified only for the newly created identity. Plan slug and monthly/yearly interval selected on public pricing are held server-side through OAuth and restored before workspace creation.

### Google OAuth setup

1. Create a Google OAuth web client for the deployment domain.
2. Configure the authorized JavaScript origin if required by the Google project.
3. Add the exact callback URL: `https://your-domain.example/account/oauth/google/callback`.
4. Set the following environment variables (never store real secrets in documentation or CMS content):

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://your-domain.example/account/oauth/google/callback
```

5. In Superadmin settings, open Customer Portal and enable **Google customer login**.
6. Run `php artisan optimize:clear` after environment or cached configuration changes.

If the toggle is enabled but any credential is missing, customer pages remain usable and the Google button stays hidden. Secrets remain environment-only; the Superadmin toggle controls product policy, not credentials.

## Main areas

- Overview: live workspace, subscription, balance, ticket, and activity summaries.
- Workspaces: list, detail, launch links, and idempotent additional-workspace purchase/provisioning.
- Billing: recurring totals, service subscriptions, plan changes, cancellation/resume, invoices, payments, payment-method visibility, and billing-profile editing.
- Members: invitations, role/capability changes, removal safeguards, and password-confirmed ownership transfer.
- Support: account-scoped cases and replies; internal superadmin notes are never returned.
- Profile, security, notifications, and multi-account switching.

## Security invariants

All object policies re-query account membership. Route-model binding alone never grants access. Attempts to open another account's workspace, invoice, payment, subscription, or support ticket return 403. Suspended portal users are rejected before account resolution. Portal authentication never authenticates a superadmin or tenant guard.

Ownership transfer requires the current owner's password, a verified destination user, and retains at least one owner. Invitation tokens are random, stored only as hashes, expire, and are bound to the invited email and account.

## Configuration and operations

Seeded `registration` and `customer_portal` settings provide safe defaults. `registration.mode` supports enabled, disabled, and invitation-only signup; legacy `registration.enabled` remains a fallback for upgraded installations. Eligible-plan IDs, verification, payment-before-provisioning, trial-without-payment, and workspace-count policy are enforced server-side.

Workspace creation uses a cache lock plus `workspace_purchase_requests` to make retries safe. When payment is required, an immutable invoice is created first and provisioning starts only after settlement. A completed idempotency key returns the original tenant; failures record a sanitized reason and can be retried by policy. Self-service requires the MySQL-admin or cPanel automatic provisioning mode; manual mode stays available to Superadmins who supply database credentials.

Limits are scope-aware. Plan user, storage, and resource limits apply to one service. Account-wide overrides live in `customer_account_limits`; workspace and member limits are enforced before creation or invitation. Usage screens query only counters backed by an existing tenant table and report unavailable counters instead of inventing values.

## Demo flow

1. Visit `/account/register`, create an account, and verify the email.
2. Create the first workspace, then create a second with a different slug.
3. Switch between two customer accounts from the portal header if the user has multiple memberships.
4. Open Billing, inspect each service subscription, schedule a plan change, and inspect invoice history.
5. Invite a billing-only member and verify that member cannot manage workspaces or membership.
6. Submit a support case, reply, and verify superadmin internal notes are absent from the customer view.
