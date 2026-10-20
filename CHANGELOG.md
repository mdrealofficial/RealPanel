## [2.5.0] - 2026-04-26

Add force webmail update button and webmail version display in Updates page

# Changelog

All notable changes to RealPanel (panel + agent) are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/) and
[Semantic Versioning](https://semver.org/).

Each release ships panel and agent together under the same version number.

## [2.9.50] - 2026-10-20

### Added — cPanel Parity: Email Ecosystem (Routing, Catch-All & Disk Usage Cleaner)
- **Email Routing & MX Controls (Local vs Remote Mail Exchanger)**:
  - Added [EmailRoutingController.php](file:///Users/mdreal/Panel/app/Http/Controllers/EmailRoutingController.php) and interactive view [routing.blade.php](file:///Users/mdreal/Panel/resources/views/emails/routing.blade.php).
  - Automatically queries external DNS MX records to detect whether mail is handled locally or remotely (Google Workspace, Microsoft 365, Zoho).
  - Added Go agent endpoint `/email/routing-set` to manage `/etc/postfix/vdomains`, run `postmap`, and reload Postfix so remote setups deliver outbound directly instead of failing locally.
- **Default / Catch-All Email Address**:
  - Added [CatchallEmailController.php](file:///Users/mdreal/Panel/app/Http/Controllers/CatchallEmailController.php) and view [catchall.blade.php](file:///Users/mdreal/Panel/resources/views/emails/catchall.blade.php).
  - Configures `@domain` wildcard alias in `/etc/postfix/virtual_aliases` via Go agent endpoint `/email/catchall-set` to forward unmatched emails, bounce with custom failure message, or `:discard:`.
- **Email Disk Usage & Cleaner**:
  - Added [EmailDiskUsageController.php](file:///Users/mdreal/Panel/app/Http/Controllers/EmailDiskUsageController.php) and view [disk_usage.blade.php](file:///Users/mdreal/Panel/resources/views/emails/disk_usage.blade.php).
  - Added Go agent endpoint `/email/disk-usage` to inspect mailbox maildirs (`INBOX`, `Sent`, `Trash`, `Junk`, `Drafts`, `Archive`) with live MB calculations.
  - Added Go agent endpoint `/email/clean-messages` executing safe `doveadm expunge` by age threshold (`all`, `30d`, `60d`, `1y`) or message size without corrupting Dovecot index files.
- **Navigation**:
  - Added **Email Routing**, **Default Address**, and **Email Disk Usage** to user sidebar under `EMAIL`.

---

## [2.9.49] - 2026-10-20

### Added — MongoDB NoSQL Database Support
- **Go Agent MongoDB Handlers**: Added `agent/handlers/mongodb.go` supporting `/mongodb/create`, `/mongodb/delete`, `/mongodb/user-create`, `/mongodb/user-password`, `/mongodb/user-delete`, and `/mongodb/info` using `mongosh` / `mongo` execution.
- **Database Model & Migration**: Added `mongo_databases` table migration (`2026_05_14_000001_create_mongo_databases_table.php`) and [MongoDatabase](file:///Users/mdreal/Panel/app/Models/MongoDatabase.php) Eloquent model.
- **User Interface & Operations**:
  - Added **MongoDB** to user sidebar under `DATABASES`.
  - Created full management views: list databases (`mongodb/index.blade.php`), create database (`mongodb/create.blade.php`), and manage details (`mongodb/show.blade.php`).
  - Added connection string generators for Mongoose, Node.js, Python, PHP, and mongosh CLI.
  - Added password change and secure database deletion.
- **Service Monitoring & Installer**: Added `mongod` to monitored services list in `agent/handlers/services.go` and added automatic MongoDB repository setup & installation in `installer/install.sh`.

---

## [2.9.48] - 2026-10-20

### Improved & Added — User Experience Streamlining & Add Domain Option
- **Account & Server Selection Elimination**: In user hosting mode, automatically scope and default File Manager (`/file-manager`), FTP accounts (`/ftp`), MySQL creation (`/databases/create`), PostgreSQL creation (`/postgres/create`), Cron jobs (`/cronjobs/create`), and Domain creation (`/domains/create`) to the active account session. Eliminated redundant "Select Account" and server/IP dropdown screens when a user is already working within their active account.
- **Add Domain Option & Navigation**: Restored the full "Add Domain" capability:
  - Added **Domains** (`user.domains.index`) directly to the user sidebar under the **DOMAIN** section.
  - Added an **Add Domain** action button to the Domains index page header and empty state linking to `user.domains.create`.
  - Implemented `create()` and `store()` methods in `DomainController.php` with validation, agent Nginx vhost provisioning, automated DNS zone setup with default template / nameservers, and auto SSL issuance.

---

## [2.9.47] - 2026-10-20

### Fixed & Added — Complete RealPanel Backup Subsystem & Safeguards
- **Agent Backup Handlers**: Implemented missing Go agent endpoints for `/backup/create`, `/backup/list`, `/backup/restore`, `/backup/delete`, `/backup/download`, `/backup/browse`, and `/backup/restore-files` in `agent/handlers/backups.go`.
- **Archive Architecture**: Backups compress `/home/{username}`, database `.sql` dumps via `mysqldump`, email mailboxes, and Nginx vhost configs into timestamped `.tar.gz` archives in `/var/backups/realpanel/` (with backward-compatible symlinks).
- **Controller Null-Safety**: Fixed `Undefined array key "size_mb"` in `BackupController.php` with robust null coalescing and type casting for `size_mb`, `duration`, `filename`, and `restored`.
- **Extended Timeout**: Switched backup creation, restoration, and file restoration to `postLong()` with 600-second timeouts to support large accounts without client timeouts.
- **Granular File Browsing & Restores**: Added streaming archive inspection via Go's `archive/tar` for zero-extraction browsing and selective single-file/folder restorations.
- **Whitelisted Direct Downloads**: Added `/backup/download` to agent whitelist with path traversal sanitization for fast browser downloads.

---

## [2.9.46] - 2026-10-20

### Fixed — Live Preview Modal Sizing & Experience
- **Expansive Modal Dimensions**: Replaced purged arbitrary height classes with guaranteed 90vh viewport sizing and flexible layout so the preview iframe never collapses.
- **Simulated Browser Chrome**: Added realistic browser address bar with SSL indicator, traffic light controls, and page title.
- **Multi-Device Emulation**: Seamless desktop (100%), tablet (768px), and mobile (375px) responsive testing.
- **Open in New Tab**: Added one-click action to view the rendered template full screen in a new browser tab.

---

## [2.9.45] - 2026-10-20

### Added — Customizable Server Pages & Automatic Default Server Routing
- **Unbound Domain Page**:
  - Automatically served via Nginx `00-default.conf` (`default_server` on port 80 and 443) when any domain points its DNS to the server IP address without being bound to an active hosting account.
  - Built with modern glassmorphism dark aesthetic, status badges, and clear explanatory steps.
- **Connected Domain Welcome Page**:
  - Automatically replaces the legacy plain text index page with a gorgeous, high-polish SaaS landing card when domains are created.
  - Dynamic token support for `{domain}` and `{panel_name}`.
  - Automatically migrates existing legacy placeholders (e.g. `digi5.cloud`) during update maintenance.
- **Server 404 Error Page**:
  - Custom, beautiful server-wide 404 error page with modern typography and quick return navigation.
- **Stop / Suspended Account Page**:
  - Displayed whenever an account or domain is suspended or stopped.
  - Agent `/account/suspend` endpoint locks Linux user login and rewrites virtual hosts to safely route to `/var/www/default/suspended.html` (preserving SSL certificates), and restores original configs seamlessly upon unsuspend.
- **Administrator Management in Admin Panel**:
  - New **Default Pages** tab (`System Settings → Default Pages`) with sub-tabs for Unbound, Welcome, 404, and Suspended pages.
  - Built-in Code Editor with instant Live Preview modal supporting Desktop, Tablet, and Mobile viewport emulation.
  - One-click "Reset to Default" button for each page.
  - Instant synchronization of all 4 templates across all connected servers via agent `/server/pages-sync`.

---

## [2.9.44] - 2026-10-20

### Added — Automated DKIM & Multi-Subdomain SSL
- **Automated OpenDKIM Integration**:
  - Automatically installs and configures OpenDKIM and connects it with Postfix via milters.
  - Automatically generates 2048-bit RSA keys (`default` selector) for domains and creates the `default._domainkey.{domain}` TXT record in PowerDNS.
  - Added agent endpoints `/email/dkim-generate` and `/email/dkim-get`.
- **Multi-Subdomain SAN SSL**:
  - Certbot now automatically requests certificates covering the primary domain, `www`, and core subdomains: `cpanel.{domain}`, `webmail.{domain}`, `mail.{domain}`, and `ftp.{domain}`.
  - Seamless fallback to apex + `www` if any individual subdomain is not yet pointed in external DNS.
  - Nginx vhosts automatically configure ACME challenge locations, and friendly HTTPS redirects for `cpanel` (to panel port 8443) and `webmail` (to port 8090).
  - Dovecot SNI automatically updated with the issued certificate for `mail.{domain}`.
- **Enhanced Standard DNS Template**:
  - `Standard (Web & Mail)` template updated with `default._domainkey.{domain}` TXT (`{dkim}`), `autoconfig.{domain}`, and `autodiscover.{domain}` records.
  - Added database migration `2026_05_13_000002_add_dkim_to_dns_templates.php` to update existing installations automatically.

---

## [2.9.43] - 2026-10-20

### Added — Configurable Default Nameservers
- **System Settings Option**: Added administrator controls under **System Settings → Domains & DNS** for setting panel-wide default nameservers:
  - **Primary Nameserver (NS1)** (e.g. `ns1.yourdomain.com`)
  - **Secondary Nameserver (NS2)** (e.g. `ns2.yourdomain.com`)
- **Dynamic Template Resolution**: `DnsTemplate::resolveRecords()` dynamically resolves `{ns1}` and `{ns2}` using administrator settings, with automatic fallback to `config('realpanel.ns1')` / `config('realpanel.ns2')` or domain-relative nameservers.
- **PowerDNS Integration**: Account provisioning, alias domain creation, and DNS zone setup automatically configure SOA and NS records with the administrator-configured nameservers.

---

## [2.9.41] - 2026-10-20

### Added — Pre-Built DNS Templates
- **Built-in DNS Templates**: Six pre-configured, production-ready DNS templates automatically seeded on clean installs and database migrations:
  1. **Standard (Web & Mail)** (Default): Full web hosting with mail, webmail, cpanel, FTP, SPF, and DMARC.
  2. **Google Workspace (Gmail)**: Optimized for sites utilizing Google Workspace with standard MX records, SPF, and DMARC.
  3. **Microsoft 365 (Office 365)**: Complete Office 365 MX, Autodiscover CNAME, and SPF records.
  4. **Cloudflare & CDN Ready**: Direct origin host bypass (`direct.domain`) for SSH/FTP and DNS-only mail handling.
  5. **Web Only (No Local Mail)**: Lightweight profile with restrictive SPF (`-all`) for web-only / API workloads.
  6. **Zoho Mail**: Pre-configured Zoho MX, SPF, and DMARC.
- **Package Integration**: Hosting packages can now optionally specify a default DNS template for all provisioned domains.
- **Agent Zone Population**: The Go agent `/dns/create-zone` handler now supports bulk population of custom DNS template records into PowerDNS.

---

## [2.9.40] - 2026-10-20

### Added & Changed
- **Full Brand Rebrand**: Complete rebranding to RealPanel across all interfaces, configs (`config/realpanel.php`), agent binary (`realpanel-agent`), and installers.
- **Root Web Terminal**: Direct interactive root bash shell (`ttyd`) accessible in the browser for administrators.
- **Fixed Terminal Hostname Badge**: Correctly renders server hostname in the web terminal header.
- **Top Quick Actions**: One-click "Clear Cache" and "Migrate DB" buttons directly in the top navigation bar.
- **Self-Healing Updates**: Auto-updater automatically applies PowerDNS 4.8 schema migrations, resolves systemd-resolved port 53 conflicts, installs `ttyd`, and recompiles the Go agent.
- **Force Update**: Allows triggering an immediate full panel and agent update from the Updates page anytime.

---

## [2.2.5] - 2026-04-21

### Added — Analytics
- **Real-time visitors** — live counter at the top of the analytics page, auto-refreshes every 30 seconds
- **Last 30-minute visit sparkline** — see traffic trends in real time
- **Hour × Day heatmap** — visualize when visitors arrive (GitHub-style grid)
- **Bandwidth over time chart** — separate from visits for clearer bandwidth tracking
- **Traffic source breakdown** — Direct / Search / Social / Referral donut chart
- **Device type breakdown** — Desktop / Mobile / Tablet donut chart
- **Top 404 broken links** — helps catch SEO-damaging dead URLs
- **Top IPs table** — spot abusive clients or misconfigured scrapers
- **Compare with previous period** — overlay dashed line on visits chart
- **Exact HTTP status codes** — individual codes like 200, 301, 404 (previously only 2xx/3xx/4xx summaries)
- **Full-width analytics layout** — makes better use of wide screens

### Added — Dashboard
- SSL + status badges next to the primary domain in the Account Info card
- New `.htaccess` management page under Advanced (previously buried in the domains list)

### Added — Branding
- RealPanel logo + favicon everywhere (welcome page, auth pages, sidebars, browser tab)
- Dark-themed login / password / register pages matching the welcome page
- Email account password fields now have **Generate random password**, **Show/Hide**, and **Copy** buttons (cryptographically secure, avoids confusing characters)

### Changed
- **Brand color everywhere is now #ff6900 (RealPanel orange)** — all previously indigo buttons, badges, and highlights now use the brand color
- Create-account page redesigned as a two-column layout (left: account info/domain/owner; right: package selection + summary)
- Removed redundant Domain icon from user dashboard (sidebar Domains link replaces it)
- User sidebar groups are now collapsible with auto-expand for the active page
- Account switcher: alphabetical sort, search box, scrollable list — for users with many accounts
- phpMyAdmin: multi-server accounts see a server dropdown (top button previously only reached the first server)
- phpMyAdmin top button now uses cookie auth (`?server=2`) — fixes "Missing token" error
- Single "Update RealPanel" button updates panel and agent together
- Force agent re-download moved to an Advanced section for troubleshooting
- Server details page: shows "Agent connected" state with collapsible reinstall instructions when the agent is online, or a prominent install card when it's offline; agent token is now displayed as a masked copyable field

### Fixed
- Updates page shows current version in release notes title
- Update log viewer has a proper scrollable box
- Panel auto-update logs each step individually for better visibility

---

## [2.2.3] - 2026-04-21

### Added
- Automatic panel auto-update — runs daily at 03:00, no manual SSH required
- Unified release script (`release.sh`) — one command bumps both panel and agent
- Changelog is now sourced from `CHANGELOG.md` in the panel repo

### Fixed
- Nginx now redirects plain HTTP to HTTPS on the panel port instead of returning 400
- Browser auto-fill no longer overwrites the admin panel password when editing an account owner
- Admin users bypass the per-license account cap (enforced only for regular users)
- Subdomain document root default path uses the account home directory correctly

### Removed
- Agent IP-based account-limit bypass (security: bypass now enforced only at the panel level for admins)

---

## [2.2.2] - 2026-04-15

### Added
- Force agent update button + live update log viewer in the admin Updates page
- phpMyAdmin SSO — one-click login directly into the selected database

### Fixed
- Return 404 instead of 403 for unauthorized email account access (reduces information disclosure)

---

## [2.1.1] - 2026-04-01

### Added
- First public release, open-source under AGPL-3.0
- cPanel backup import (files, databases, email with original passwords, subdomains, DNS, SSL, cron)
- Customer self-service cPanel import
- Visual cron builder with run history
- Real-time error log streaming
- Point-in-time file restore from backups
- One-click staging environments
- CDN integration (BunnyCDN)
- Visitor analytics (privacy-friendly, log-based, no cookies)
- Directory password protection + hotlink protection
- Server monitoring with historical metrics (1h/6h/24h/7d/30d)
- Agent auto-update (self-updates every 12 hours)
- SSL certificate delete + re-issue
- PHP version switching per domain
- Apache/.htaccess support (per-domain toggle)
- Reseller accounts with custom packages
