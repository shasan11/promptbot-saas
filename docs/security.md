# Security

Central administrator registration is disabled. Administrators are created by explicit owner seeding or invitation. Invitations store only token hashes and expire automatically.

Central routes use the central guard and central password broker. Login blocks inactive, suspended, locked, and password-expired administrators. Platform Owner accounts require 2FA.

Never log passwords, API keys, tokens, database passwords, authorization headers, card details, TOTP secrets, or recovery codes.
