package handlers

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"realpanel-agent-oss/system"
)

type ServerPagesSyncReq struct {
	UnboundHTML   string `json:"unbound_html"`
	WelcomeHTML   string `json:"welcome_html"`
	Page404HTML   string `json:"404_html"`
	SuspendedHTML string `json:"suspended_html"`
}

type AccountSuspendReq struct {
	Username string   `json:"username"`
	Domains  []string `json:"domains"`
	Action   string   `json:"action"` // "suspend" or "unsuspend"
}

// ServerPagesSyncHandler synchronizes customized or default HTML pages to /var/www/default
func ServerPagesSyncHandler(w http.ResponseWriter, r *http.Request) {
	var req ServerPagesSyncReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload: " + err.Error()})
		return
	}

	defaultDir := "/var/www/default"
	if err := os.MkdirAll(defaultDir, 0755); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to create /var/www/default: " + err.Error()})
		return
	}

	// Write unbound domain page (index.html)
	if req.UnboundHTML != "" {
		os.WriteFile(filepath.Join(defaultDir, "index.html"), []byte(req.UnboundHTML), 0644)
	}

	// Write connected domain welcome page template (welcome.html)
	if req.WelcomeHTML != "" {
		os.WriteFile(filepath.Join(defaultDir, "welcome.html"), []byte(req.WelcomeHTML), 0644)
	}

	// Write 404 error page (404.html)
	if req.Page404HTML != "" {
		os.WriteFile(filepath.Join(defaultDir, "404.html"), []byte(req.Page404HTML), 0644)
	}

	// Write stop / suspended page (suspended.html)
	if req.SuspendedHTML != "" {
		os.WriteFile(filepath.Join(defaultDir, "suspended.html"), []byte(req.SuspendedHTML), 0644)
	}

	system.ExecCommand("chown", "-R", "www-data:www-data", defaultDir)

	// Ensure 00-default.conf Nginx configuration is deployed and active
	EnsureDefaultNginxVhost()

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Default server pages and Nginx default server synchronized successfully",
	})
}

// AccountSuspendHandler suspends or unsuspends an account and rewrites its virtual hosts
func AccountSuspendHandler(w http.ResponseWriter, r *http.Request) {
	var req AccountSuspendReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload: " + err.Error()})
		return
	}

	action := strings.ToLower(strings.TrimSpace(req.Action))
	if action != "suspend" && action != "unsuspend" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid action. Must be 'suspend' or 'unsuspend'"})
		return
	}

	// Ensure /var/www/default/suspended.html exists
	suspendedFile := "/var/www/default/suspended.html"
	if _, err := os.Stat(suspendedFile); os.IsNotExist(err) {
		os.MkdirAll("/var/www/default", 0755)
		os.WriteFile(suspendedFile, []byte(DefaultSuspendedPageTemplate()), 0644)
	}

	if action == "suspend" {
		// 1. Lock user account
		if req.Username != "" {
			system.ExecCommand("usermod", "-L", req.Username)
		}

		// 2. Rewrite each domain's vhost to point to suspended page
		for _, domain := range req.Domains {
			domain = strings.TrimSpace(domain)
			if domain == "" {
				continue
			}
			vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", domain)
			liveBackup := fmt.Sprintf("/etc/nginx/sites-available/%s.conf.live", domain)

			contentBytes, err := os.ReadFile(vhostPath)
			if err != nil {
				continue
			}
			content := string(contentBytes)

			// Save active backup only if not already saved
			if _, err := os.Stat(liveBackup); os.IsNotExist(err) {
				os.WriteFile(liveBackup, contentBytes, 0644)
			}

			// Check if SSL certificate is present in existing config
			hasSSL := strings.Contains(content, "ssl_certificate")
			sslListen := ""
			sslDirectives := ""
			if hasSSL {
				sslListen = "listen 443 ssl;\n    listen [::]:443 ssl;"
				lines := strings.Split(content, "\n")
				for _, line := range lines {
					trimmed := strings.TrimSpace(line)
					if strings.HasPrefix(trimmed, "ssl_certificate") ||
						strings.HasPrefix(trimmed, "ssl_certificate_key") ||
						strings.HasPrefix(trimmed, "ssl_protocols") ||
						strings.HasPrefix(trimmed, "ssl_ciphers") {
						sslDirectives += "    " + trimmed + "\n"
					}
				}
			}

			serverNames := fmt.Sprintf("%s www.%s cpanel.%s webmail.%s mail.%s ftp.%s", domain, domain, domain, domain, domain, domain)

			suspendedVhost := fmt.Sprintf(`server {
    listen 80;
    listen [::]:80;
    %s

    server_name %s;
%s
    root /var/www/default;
    index suspended.html;

    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }

    location / {
        try_files /suspended.html =503;
    }
}
`, sslListen, serverNames, sslDirectives)

			os.WriteFile(vhostPath, []byte(suspendedVhost), 0644)
		}

		system.ExecCommand("systemctl", "reload", "nginx")

		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":  "ok",
			"message": fmt.Sprintf("Account %s suspended and domains routed to suspended page", req.Username),
		})
		return
	}

	// Action == "unsuspend"
	if req.Username != "" {
		system.ExecCommand("usermod", "-U", req.Username)
	}

	for _, domain := range req.Domains {
		domain = strings.TrimSpace(domain)
		if domain == "" {
			continue
		}
		vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", domain)
		liveBackup := fmt.Sprintf("/etc/nginx/sites-available/%s.conf.live", domain)

		if _, err := os.Stat(liveBackup); err == nil {
			if backupBytes, err := os.ReadFile(liveBackup); err == nil {
				os.WriteFile(vhostPath, backupBytes, 0644)
			}
			os.Remove(liveBackup)
		}
	}

	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Account %s unsuspended and domains restored", req.Username),
	})
}

// EnsureDefaultNginxVhost deploys 00-default.conf for unbound domains and server 404s
func EnsureDefaultNginxVhost() {
	// 1. Ensure self-signed fallback cert exists
	fallbackKey := "/etc/ssl/realpanel/fallback.key"
	fallbackCrt := "/etc/ssl/realpanel/fallback.crt"
	if _, err := os.Stat(fallbackCrt); os.IsNotExist(err) {
		os.MkdirAll("/etc/ssl/realpanel", 0755)
		system.ExecCommand("openssl", "req", "-x509", "-nodes", "-days", "3650",
			"-newkey", "rsa:2048",
			"-keyout", fallbackKey,
			"-out", fallbackCrt,
			"-subj", "/CN=realpanel-default")
	}

	defaultConf := fmt.Sprintf(`server {
    listen 80 default_server;
    listen [::]:80 default_server;
    listen 443 ssl default_server;
    listen [::]:443 ssl default_server;

    ssl_certificate %s;
    ssl_certificate_key %s;

    server_name _;
    root /var/www/default;
    index index.html index.htm;

    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }

    error_page 404 /404.html;
    location = /404.html {
        internal;
    }

    location / {
        try_files $uri $uri/ /index.html;
    }
}
`, fallbackCrt, fallbackKey)

	// Sites-available (Debian/Ubuntu) or conf.d (RHEL)
	if _, err := os.Stat("/etc/nginx/sites-available"); err == nil {
		availablePath := "/etc/nginx/sites-available/00-default.conf"
		enabledPath := "/etc/nginx/sites-enabled/00-default.conf"
		os.WriteFile(availablePath, []byte(defaultConf), 0644)
		os.Remove("/etc/nginx/sites-enabled/default") // Remove Debian default package welcome
		os.Symlink(availablePath, enabledPath)
	} else if _, err := os.Stat("/etc/nginx/conf.d"); err == nil {
		confPath := "/etc/nginx/conf.d/00-default.conf"
		os.WriteFile(confPath, []byte(defaultConf), 0644)
	}

	// Test and reload Nginx
	out, err := system.ExecCommand("nginx", "-t")
	if err == nil {
		system.ExecCommand("systemctl", "reload", "nginx")
	} else {
		log.Printf("[WARN] nginx -t failed after updating default vhost: %s", out)
	}
}

func DefaultSuspendedPageTemplate() string {
	return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Website Suspended</title>
<style>
body { font-family: system-ui, -apple-system, sans-serif; background: #0d1117; color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
.card { max-width: 540px; background: #161b22; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; padding: 40px 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
h1 { font-size: 24px; color: #f87171; margin-bottom: 12px; }
p { color: #94a3b8; font-size: 15px; line-height: 1.5; }
</style>
</head>
<body>
<div class="card">
<h1>This Website is Currently Suspended</h1>
<p>The hosting service for this website has been temporarily suspended. If you are the website owner, please contact technical support.</p>
</div>
</body>
</html>`
}
