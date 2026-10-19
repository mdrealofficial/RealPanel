package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"

	"realpanel-agent-oss/system"
)

type PanelSetHostnameReq struct {
	Hostname string `json:"hostname"`
	Email    string `json:"email"`
}

// PanelSetHostnameHandler sets up a Let's Encrypt SSL certificate for the panel's hostname,
// reconfigures Nginx on port 8443 with SSL, updates Laravel .env APP_URL, and reloads Nginx.
func PanelSetHostnameHandler(w http.ResponseWriter, r *http.Request) {
	var req PanelSetHostnameReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Hostname == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{
			"error": "hostname is required",
		})
		return
	}

	hostname := strings.ToLower(strings.TrimSpace(req.Hostname))
	email := strings.TrimSpace(req.Email)
	if email == "" {
		email = "admin@" + hostname
	}

	panelDir := "/opt/realpanel"
	if _, err := os.Stat(panelDir); os.IsNotExist(err) {
		panelDir = "/opt/opterius"
	}
	publicDir := panelDir + "/public"

	// 1. Issue certificate with certbot using webroot against panel public directory
	// Make sure .well-known directory exists and is accessible
	os.MkdirAll(publicDir+"/.well-known/acme-challenge", 0755)

	certbotArgs := []string{
		"certonly", "--webroot", "-w", publicDir,
		"-d", hostname,
		"--non-interactive", "--agree-tos", "-m", email,
	}

	out, err := system.ExecCommand("certbot", certbotArgs...)
	certFile := fmt.Sprintf("/etc/letsencrypt/live/%s/fullchain.pem", hostname)
	keyFile := fmt.Sprintf("/etc/letsencrypt/live/%s/privkey.pem", hostname)

	// Check if certificate exists (either just issued or previously issued)
	if _, statErr := os.Stat(certFile); statErr != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"error":   fmt.Sprintf("Failed to obtain SSL certificate: %v. Output: %s", err, out),
			"details": out,
		})
		return
	}

	// 2. Detect PHP-FPM socket
	fpmSock := "/run/php/php8.4-fpm.sock"
	if _, err := os.Stat(fpmSock); os.IsNotExist(err) {
		// Check alternative versions
		for _, v := range []string{"8.3", "8.2", "8.1"} {
			s := fmt.Sprintf("/run/php/php%s-fpm.sock", v)
			if _, err := os.Stat(s); err == nil {
				fpmSock = s
				break
			}
		}
	}

	// 3. Write SSL-enabled Nginx configuration for the panel
	vhostContent := fmt.Sprintf(`server {
    listen 8443 ssl http2;
    listen [::]:8443 ssl http2;
    server_name %s;

    ssl_certificate %s;
    ssl_certificate_key %s;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root %s/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Internal subrequest endpoint to verify admin session
    location = /internal-admin-auth {
        internal;
        fastcgi_pass unix:%s;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param QUERY_STRING "";
        fastcgi_param REQUEST_URI "/admin/terminal/auth-check";
        fastcgi_param REQUEST_METHOD "GET";
    }

    # Protected Web Terminal WebSocket proxy (Administrator Only)
    location ^~ /terminal-root/ {
        auth_request /internal-admin-auth;

        proxy_pass http://127.0.0.1:7681;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
    }

    location ~ \.php$ {
        fastcgi_pass unix:%s;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
`, hostname, certFile, keyFile, panelDir, fpmSock, fpmSock)

	vhostPath := "/etc/nginx/sites-available/realpanel.conf"
	if _, err := os.Stat("/etc/nginx/sites-available"); os.IsNotExist(err) {
		vhostPath = "/etc/nginx/conf.d/realpanel.conf"
	}

	if err := os.WriteFile(vhostPath, []byte(vhostContent), 0644); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{
			"error": fmt.Sprintf("Failed to write nginx config: %v", err),
		})
		return
	}

	// Symlink if on Debian/Ubuntu
	if _, err := os.Stat("/etc/nginx/sites-enabled"); err == nil {
		system.ExecCommand("ln", "-sf", vhostPath, "/etc/nginx/sites-enabled/realpanel.conf")
		// Clean up legacy vhost symlinks
		os.Remove("/etc/nginx/sites-enabled/opterius-panel.conf")
	}
	os.Remove("/etc/nginx/conf.d/opterius-panel.conf")

	// Configure SSL for RealPanel Mail webmail on port 8090
	mailDir := "/opt/realpanel-mail"
	if _, err := os.Stat(mailDir); os.IsNotExist(err) {
		mailDir = "/opt/opterius-mail"
	}

	mailVhost := fmt.Sprintf(`server {
    listen 8090 ssl http2;
    listen [::]:8090 ssl http2;
    server_name _;

    ssl_certificate %s;
    ssl_certificate_key %s;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root %s/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:%s;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(ht|git|svn) {
        deny all;
    }
}
`, certFile, keyFile, mailDir, fpmSock)
	mailConfPath := "/etc/nginx/sites-available/realpanel-mail.conf"
	if _, err := os.Stat("/etc/nginx/sites-available"); os.IsNotExist(err) {
		mailConfPath = "/etc/nginx/conf.d/realpanel-mail.conf"
	}
	os.WriteFile(mailConfPath, []byte(mailVhost), 0644)
	if _, err := os.Stat("/etc/nginx/sites-enabled"); err == nil {
		system.ExecCommand("ln", "-sf", mailConfPath, "/etc/nginx/sites-enabled/realpanel-mail.conf")
		os.Remove("/etc/nginx/sites-enabled/opterius-mail.conf")
	}
	os.Remove("/etc/nginx/conf.d/opterius-mail.conf")

	// Configure SSL for phpMyAdmin on port 8081
	pmaVhost := fmt.Sprintf(`server {
    listen 8081 ssl http2;
    listen [::]:8081 ssl http2;
    server_name _;

    ssl_certificate %s;
    ssl_certificate_key %s;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /usr/share/phpmyadmin;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:%s;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
`, certFile, keyFile, fpmSock)
	pmaConfPath := "/etc/nginx/sites-available/phpmyadmin.conf"
	if _, err := os.Stat("/etc/nginx/sites-available"); os.IsNotExist(err) {
		pmaConfPath = "/etc/nginx/conf.d/phpmyadmin.conf"
	}
	os.WriteFile(pmaConfPath, []byte(pmaVhost), 0644)

	// Test and reload nginx
	if testOut, testErr := system.ExecCommand("nginx", "-t"); testErr != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{
			"error": fmt.Sprintf("Nginx test failed: %s", testOut),
		})
		return
	}
	system.ExecCommand("systemctl", "reload", "nginx")

	// 4. Update APP_URL in Laravel .env and clear config cache
	newUrl := fmt.Sprintf("https://%s:8443", hostname)
	envPath := panelDir + "/.env"
	if envData, err := os.ReadFile(envPath); err == nil {
		lines := strings.Split(string(envData), "\n")
		var newLines []string
		for _, l := range lines {
			if strings.HasPrefix(l, "APP_URL=") {
				newLines = append(newLines, fmt.Sprintf("APP_URL=%s", newUrl))
			} else {
				newLines = append(newLines, l)
			}
		}
		os.WriteFile(envPath, []byte(strings.Join(newLines, "\n")), 0644)
	}

	// Clear caches
	system.ExecCommand("bash", "-c", fmt.Sprintf("cd %s && php artisan config:clear && php artisan cache:clear", panelDir))

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"url":     newUrl,
		"message": "Panel hostname configured successfully with SSL",
	})
}
