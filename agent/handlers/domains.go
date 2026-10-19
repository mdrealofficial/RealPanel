package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"realpanel-agent-oss/system"
)

type DomainCreateReq struct {
	Domain       string `json:"domain"`
	DocumentRoot string `json:"document_root"`
	Username     string `json:"username"`
	PhpVersion   string `json:"php_version"`
}

type DomainDeleteReq struct {
	Domain   string `json:"domain"`
	Username string `json:"username"`
}

type AliasReq struct {
	Domain string `json:"domain"`
	Alias  string `json:"alias"`
}

type HtaccessReq struct {
	Domain  string `json:"domain"`
	Enabled bool   `json:"enabled"`
}

func DomainCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req DomainCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	if req.Domain == "" || req.Username == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain and username are required"})
		return
	}

	if req.PhpVersion == "" {
		req.PhpVersion = "8.4"
	}
	if req.DocumentRoot == "" {
		req.DocumentRoot = fmt.Sprintf("/home/%s/%s/public_html", req.Username, req.Domain)
	}

	// 1. Ensure Linux user exists
	system.ExecCommand("useradd", "-m", "-s", "/bin/bash", req.Username)

	// 2. Ensure DocumentRoot exists with proper ownership and permissions
	os.MkdirAll(req.DocumentRoot, 0755)
	system.ExecCommand("chown", "-R", fmt.Sprintf("%s:www-data", req.Username), fmt.Sprintf("/home/%s", req.Username))
	system.ExecCommand("chmod", "755", fmt.Sprintf("/home/%s", req.Username))

	// Create a default index.html if directory is empty
	indexFile := filepath.Join(req.DocumentRoot, "index.html")
	if _, err := os.Stat(indexFile); os.IsNotExist(err) {
		var defaultHTML string
		welcomeTpl := "/var/www/default/welcome.html"
		if data, err := os.ReadFile(welcomeTpl); err == nil && len(data) > 0 {
			defaultHTML = strings.ReplaceAll(string(data), "{domain}", req.Domain)
			defaultHTML = strings.ReplaceAll(defaultHTML, "{panel_name}", "RealPanel")
		} else {
			defaultHTML = fmt.Sprintf(`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to %s</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, sans-serif; background: #0f172a; color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; text-align: center; }
        .card { max-width: 640px; width: 100%%; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 48px 36px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #6ee7b7; padding: 6px 14px; border-radius: 9999px; font-size: 13px; font-weight: 600; margin-bottom: 20px; }
        .dot { width: 8px; height: 8px; border-radius: 50%%; background-color: #10b981; }
        h1 { font-size: 28px; font-weight: 800; margin-bottom: 12px; }
        .domain { color: #38bdf8; }
        p { font-size: 15px; color: #94a3b8; line-height: 1.6; margin-bottom: 28px; }
        .tip { background: rgba(99, 102, 241, 0.08); border: 1px dashed rgba(99, 102, 241, 0.35); border-radius: 10px; padding: 14px 18px; font-size: 13px; color: #cbd5e1; margin-bottom: 24px; text-align: left; }
        .tip code { background: rgba(0, 0, 0, 0.3); padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #f1f5f9; }
        .footer { font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="card">
        <div class="badge"><span class="dot"></span> Hosting Environment Ready</div>
        <h1><span class="domain">%s</span> is successfully hosted!</h1>
        <p>Your web hosting account has been created and configured on <strong>RealPanel</strong>.</p>
        <div class="tip">To replace this landing page, upload your website files into <code>public_html</code> and delete or replace <code>index.html</code>.</div>
        <div class="footer">Managed via <strong>RealPanel</strong> • High Performance Cloud Infrastructure</div>
    </div>
</body>
</html>`, req.Domain, req.Domain)
		}
		os.WriteFile(indexFile, []byte(defaultHTML), 0644)
		system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), indexFile)
	}

	// 3. Write Nginx server block
	nginxConf := fmt.Sprintf(`server {
    listen 80;
    listen [::]:80;
    server_name %s www.%s cpanel.%s webmail.%s mail.%s ftp.%s;

    root %s;
    index index.php index.html index.htm;

    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }

    if ($host ~* ^cpanel\.) {
        return 302 https://$host:8443;
    }

    if ($host ~* ^webmail\.) {
        return 302 http://$host:8090;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php%s-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git|svn) {
        deny all;
    }
}
`, req.Domain, req.Domain, req.Domain, req.Domain, req.Domain, req.Domain, req.DocumentRoot, req.PhpVersion)

	availablePath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	enabledPath := fmt.Sprintf("/etc/nginx/sites-enabled/%s.conf", req.Domain)

	if err := os.WriteFile(availablePath, []byte(nginxConf), 0644); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to write nginx config: " + err.Error()})
		return
	}

	// Symlink to sites-enabled
	os.Remove(enabledPath)
	os.Symlink(availablePath, enabledPath)

	// Test & reload Nginx
	if _, err := system.ExecCommand("nginx", "-t"); err != nil {
		os.Remove(enabledPath)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Nginx config test failed: " + err.Error()})
		return
	}
	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Domain %s created successfully", req.Domain),
	})
}

func DomainDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req DomainDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	availablePath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	enabledPath := fmt.Sprintf("/etc/nginx/sites-enabled/%s.conf", req.Domain)

	os.Remove(enabledPath)
	os.Remove(availablePath)

	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Domain %s deleted successfully", req.Domain),
	})
}

func DomainAliasAddHandler(w http.ResponseWriter, r *http.Request) {
	var req AliasReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	availablePath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	content, err := os.ReadFile(availablePath)
	if err == nil {
		updated := strings.Replace(string(content), fmt.Sprintf("server_name %s", req.Domain), fmt.Sprintf("server_name %s %s www.%s", req.Domain, req.Alias, req.Alias), 1)
		os.WriteFile(availablePath, []byte(updated), 0644)
		system.ExecCommand("systemctl", "reload", "nginx")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DomainAliasRemoveHandler(w http.ResponseWriter, r *http.Request) {
	var req AliasReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	availablePath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	content, err := os.ReadFile(availablePath)
	if err == nil {
		updated := strings.ReplaceAll(string(content), " "+req.Alias, "")
		updated = strings.ReplaceAll(updated, " www."+req.Alias, "")
		os.WriteFile(availablePath, []byte(updated), 0644)
		system.ExecCommand("systemctl", "reload", "nginx")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DomainToggleHtaccessHandler(w http.ResponseWriter, r *http.Request) {
	var req HtaccessReq
	json.NewDecoder(r.Body).Decode(&req)

	// In dual-stack Nginx+Apache mode, requests for PHP or dynamic files proxy to 127.0.0.1:8080
	// For now, accept and acknowledge
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"enabled": req.Enabled,
	})
}
