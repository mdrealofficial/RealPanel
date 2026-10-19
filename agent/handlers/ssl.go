package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"realpanel-agent-oss/system"
)

type SslStatusReq struct {
	Domain string `json:"domain"`
}

type SslIssueReq struct {
	Domain   string `json:"domain"`
	Email    string `json:"email"`
	Username string `json:"username"`
}

type SslUploadReq struct {
	Domain      string `json:"domain"`
	Username    string `json:"username"`
	Certificate string `json:"certificate"`
	PrivateKey  string `json:"private_key"`
	Chain       string `json:"chain"`
}

type SslRenewReq struct {
	Domain string `json:"domain"`
}

type SslDeleteReq struct {
	Domain   string `json:"domain"`
	Username string `json:"username"`
}

func SslStatusHandler(w http.ResponseWriter, r *http.Request) {
	var req SslStatusReq
	json.NewDecoder(r.Body).Decode(&req)

	certPath := fmt.Sprintf("/etc/letsencrypt/live/%s/fullchain.pem", req.Domain)
	if _, err := os.Stat(certPath); os.IsNotExist(err) {
		certPath = fmt.Sprintf("/etc/ssl/realpanel/%s/fullchain.pem", req.Domain)
		if _, err := os.Stat(certPath); os.IsNotExist(err) {
			certPath = fmt.Sprintf("/etc/ssl/opterius/%s/fullchain.pem", req.Domain)
		}
	}

	if _, err := os.Stat(certPath); err == nil {
		out, _ := system.ExecCommand("openssl", "x509", "-enddate", "-noout", "-in", certPath)
		expiresAt := strings.TrimPrefix(strings.TrimSpace(out), "notAfter=")
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":     "ok",
			"has_ssl":    true,
			"valid":      true,
			"issuer":     "Let's Encrypt / Custom",
			"expires_at": expiresAt,
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"has_ssl": false,
		"valid":   false,
	})
}

// ensureVhostSubdomains ensures Nginx vhost server_name includes cpanel, webmail, mail, and ftp
// and provides the .well-known/acme-challenge directory so Let's Encrypt validation succeeds.
func ensureVhostSubdomains(domain string) {
	vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", domain)
	content, err := os.ReadFile(vhostPath)
	if err != nil {
		return
	}
	s := string(content)
	modified := false

	// 1. Expand server_name to include standard subdomains if not already present
	if !strings.Contains(s, "cpanel."+domain) {
		oldServerName := fmt.Sprintf("server_name %s www.%s;", domain, domain)
		newServerName := fmt.Sprintf("server_name %s www.%s cpanel.%s webmail.%s mail.%s ftp.%s;",
			domain, domain, domain, domain, domain, domain)
		if strings.Contains(s, oldServerName) {
			s = strings.Replace(s, oldServerName, newServerName, 1)
			modified = true
		}
	}

	// 2. Ensure ACME challenge location block is present
	if !strings.Contains(s, "/.well-known/acme-challenge/") {
		acmeBlock := `
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        default_type "text/plain";
    }
`
		if strings.Contains(s, "location / {") {
			s = strings.Replace(s, "location / {", acmeBlock+"\n    location / {", 1)
			modified = true
		}
	}

	// 3. Ensure friendly webmail and cpanel redirects are in place
	if !strings.Contains(s, "^cpanel\\.") {
		redirectBlock := `
    if ($host ~* ^cpanel\.) {
        return 302 https://$host:8443;
    }
    if ($host ~* ^webmail\.) {
        return 302 http://$host:8090;
    }
`
		if strings.Contains(s, "location / {") {
			s = strings.Replace(s, "location / {", redirectBlock+"\n    location / {", 1)
			modified = true
		}
	}

	if modified {
		os.WriteFile(vhostPath, []byte(s), 0644)
		system.ExecCommand("nginx", "-t")
		system.ExecCommand("systemctl", "reload", "nginx")
	}
}

// configureMailSsl associates the issued SSL certificate with Dovecot and Postfix
// for mail.<domain> secure TLS connections.
func configureMailSsl(domain, certFile, keyFile string) {
	dovecotConf := "/etc/dovecot/conf.d/10-ssl.conf"
	if _, err := os.Stat(dovecotConf); err == nil {
		content, _ := os.ReadFile(dovecotConf)
		s := string(content)
		sniMarker := fmt.Sprintf("local_name mail.%s", domain)
		if !strings.Contains(s, sniMarker) {
			sniBlock := fmt.Sprintf("\nlocal_name mail.%s {\n  ssl_cert = <%s\n  ssl_key = <%s\n}\n",
				domain, certFile, keyFile)
			f, err := os.OpenFile(dovecotConf, os.O_APPEND|os.O_WRONLY, 0644)
			if err == nil {
				f.WriteString(sniBlock)
				f.Close()
				system.ExecCommand("systemctl", "reload", "dovecot")
			}
		}
	}
}

func SslIssueAsyncHandler(w http.ResponseWriter, r *http.Request) {
	var req SslIssueReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	go func() {
		docRoot := fmt.Sprintf("/home/%s/%s/public_html", req.Username, req.Domain)
		if _, err := os.Stat(docRoot); os.IsNotExist(err) {
			docRoot = fmt.Sprintf("/var/www/html")
		}

		// Run certbot webroot
		emailArg := req.Email
		if emailArg == "" {
			emailArg = "admin@" + req.Domain
		}

		// Ensure vhost is updated to listen for cpanel, webmail, mail, ftp subdomains
		ensureVhostSubdomains(req.Domain)

		// List of subdomains to include in SSL certificate
		allDomains := []string{
			req.Domain,
			"www." + req.Domain,
			"cpanel." + req.Domain,
			"webmail." + req.Domain,
			"mail." + req.Domain,
			"ftp." + req.Domain,
		}

		// Attempt multi-subdomain SAN certificate
		fullArgs := []string{
			"certonly", "--webroot", "-w", docRoot,
			"--non-interactive", "--agree-tos", "-m", emailArg,
			"--expand",
		}
		for _, d := range allDomains {
			fullArgs = append(fullArgs, "-d", d)
		}

		system.ExecCommand("certbot", fullArgs...)
		certFile := fmt.Sprintf("/etc/letsencrypt/live/%s/fullchain.pem", req.Domain)

		// If full multi-subdomain issuance failed (e.g. DNS not pointing yet for some subdomains),
		// fall back to primary domain + www so the main website's SSL is never blocked
		if _, statErr := os.Stat(certFile); os.IsNotExist(statErr) {
			fallbackArgs := []string{
				"certonly", "--webroot", "-w", docRoot,
				"-d", req.Domain, "-d", "www." + req.Domain,
				"--non-interactive", "--agree-tos", "-m", emailArg,
				"--expand",
			}
			system.ExecCommand("certbot", fallbackArgs...)
		}

		// If successful, enable SSL in Nginx
		keyFile := fmt.Sprintf("/etc/letsencrypt/live/%s/privkey.pem", req.Domain)

		if _, err := os.Stat(certFile); err == nil {
			vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
			content, err := os.ReadFile(vhostPath)
			if err == nil && !strings.Contains(string(content), "ssl_certificate") {
				sslBlock := fmt.Sprintf(`
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate %s;
    ssl_certificate_key %s;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
`, certFile, keyFile)
				updated := strings.Replace(string(content), "listen [::]:80;", "listen [::]:80;\n"+sslBlock, 1)
				os.WriteFile(vhostPath, []byte(updated), 0644)
				system.ExecCommand("nginx", "-t")
				system.ExecCommand("systemctl", "reload", "nginx")
			}

			// Configure Dovecot mail SSL SNI
			configureMailSsl(req.Domain, certFile, keyFile)
		}
	}()

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "SSL certificate issuance started in background for domain and subdomains (cpanel, webmail, mail, ftp)",
	})
}

func SslUploadHandler(w http.ResponseWriter, r *http.Request) {
	var req SslUploadReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	sslDir := fmt.Sprintf("/etc/ssl/realpanel/%s", req.Domain)
	os.MkdirAll(sslDir, 0700)

	certFile := filepath.Join(sslDir, "fullchain.pem")
	keyFile := filepath.Join(sslDir, "privkey.pem")

	fullCert := req.Certificate
	if req.Chain != "" {
		fullCert += "\n" + req.Chain
	}

	os.WriteFile(certFile, []byte(fullCert), 0644)
	os.WriteFile(keyFile, []byte(req.PrivateKey), 0600)

	// Inject into Nginx vhost
	vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	content, err := os.ReadFile(vhostPath)
	if err == nil && !strings.Contains(string(content), "ssl_certificate") {
		sslBlock := fmt.Sprintf(`
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    ssl_certificate %s;
    ssl_certificate_key %s;
`, certFile, keyFile)
		updated := strings.Replace(string(content), "listen [::]:80;", "listen [::]:80;\n"+sslBlock, 1)
		os.WriteFile(vhostPath, []byte(updated), 0644)
		system.ExecCommand("nginx", "-t")
		system.ExecCommand("systemctl", "reload", "nginx")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func SslRenewHandler(w http.ResponseWriter, r *http.Request) {
	var req SslRenewReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	out, err := system.ExecCommand("certbot", "renew", "--cert-name", req.Domain, "--non-interactive")
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"error":  "Renewal failed: " + err.Error(),
			"output": out,
		})
		return
	}

	system.ExecCommand("systemctl", "reload", "nginx")
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Certificate renewed successfully",
	})
}

func SslDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req SslDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	// Delete from certbot
	system.ExecCommand("certbot", "delete", "--cert-name", req.Domain, "--non-interactive")

	// Remove SSL block from nginx config
	vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	if content, err := os.ReadFile(vhostPath); err == nil {
		lines := strings.Split(string(content), "\n")
		var newLines []string
		skip := false
		for _, line := range lines {
			if strings.Contains(line, "listen 443") {
				skip = true
				continue
			}
			if skip && (strings.Contains(line, "ssl_") || strings.Contains(line, "listen [::]:443")) {
				continue
			}
			skip = false
			newLines = append(newLines, line)
		}
		os.WriteFile(vhostPath, []byte(strings.Join(newLines, "\n")), 0644)
		system.ExecCommand("nginx", "-t")
		system.ExecCommand("systemctl", "reload", "nginx")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func SslProgressHandler(w http.ResponseWriter, r *http.Request) {
	domain := r.URL.Query().Get("domain")
	certFile := fmt.Sprintf("/etc/letsencrypt/live/%s/fullchain.pem", domain)
	done := false
	if _, err := os.Stat(certFile); err == nil {
		done = true
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"done":   done,
		"stage":  "complete",
		"time":   time.Now().Unix(),
	})
}
