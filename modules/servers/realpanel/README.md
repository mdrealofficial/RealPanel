# RealPanel WHMCS Server Module

This module connects [WHMCS](https://www.whmcs.com/) with **RealPanel** for automated provisioning and **One-Click Single Sign-On (SSO)** into the client control panel.

---

## Features

- **Test Connection**: Instant connectivity and API key validation.
- **Account Provisioning**: Automatically create hosting accounts upon payment.
- **One-Click SSO**: Clients can click **"Log in to RealPanel"** directly inside WHMCS without typing a password.
- **Account Management**:
  - Suspend / Unsuspend (e.g. on invoice overdue/payment).
  - Terminate (delete).
  - Change Password.
  - Upgrade / Downgrade package.
- **Usage Metrics**: Syncs disk space and bandwidth usage to WHMCS automatically via WHMCS cron.

---

## Installation

1. Copy the `realpanel` directory into your WHMCS server modules folder:
   ```bash
   /path/to/whmcs/modules/servers/realpanel/
   ├── lib/
   │   └── RealPanelAPI.php
   └── realpanel.php
   ```

2. In WHMCS Admin:
   - Go to **Configuration > System Settings > Servers**.
   - Click **Add New Server**.
   - Set **Module** to **RealPanel**.
   - Enter your RealPanel **Hostname** (or IP).
   - In the **Access Hash** field, paste your RealPanel **API Key** (created under *Admin > Integrations > API Keys* in RealPanel).
   - Check **Secure** (HTTPS).
   - Click **Test Connection**.

3. In your WHMCS Products/Services:
   - Go to **Configuration > System Settings > Products/Services**.
   - Edit your Hosting Product -> **Module Settings**.
   - Select **RealPanel** as the module.
   - Enter the exact **Package Name** matching your RealPanel package (e.g. `Starter`, `Standard`, `Unlimited`).
   - Choose your preferred automated provisioning setting (e.g. *Automatically setup the product as soon as the first payment is received*).

---

## One-Click SSO Setup

No extra configuration is required. WHMCS will query RealPanel's `/api/v1/accounts/sso` endpoint using the configured server API key and redirect clients directly to their dashboard with an ephemeral, single-use token.
