package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"opterius-agent-oss/system"
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
		defaultHTML := fmt.Sprintf(`<!DOCTYPE html>
<html>
<head><title>Welcome to %s</title></head>
<body style="font-family: sans-serif; text-align: center; padding: 50px;">
  <h1>%s is successfully hosted!</h1>
  <p>Managed via Opterius Panel.</p>
</body>
</html>`, req.Domain, req.Domain)
		os.WriteFile(indexFile, []byte(defaultHTML), 0644)
	}

	// 3. Write Nginx server block
	nginxConf := fmt.Sprintf(`server {
    listen 80;
    listen [::]:80;
    server_name %s www.%s;

    root %s;
    index index.php index.html index.htm;

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
`, req.Domain, req.Domain, req.DocumentRoot, req.PhpVersion)

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
