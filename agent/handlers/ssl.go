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
		// Output format: notAfter=Jun 15 12:00:00 2026 GMT
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

		cmdArgs := []string{
			"certonly", "--webroot", "-w", docRoot,
			"-d", req.Domain, "-d", "www." + req.Domain,
			"--non-interactive", "--agree-tos", "-m", emailArg,
		}
		system.ExecCommand("certbot", cmdArgs...)

		// If successful, enable SSL in Nginx
		certFile := fmt.Sprintf("/etc/letsencrypt/live/%s/fullchain.pem", req.Domain)
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
		}
	}()

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "SSL certificate issuance started in background",
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
