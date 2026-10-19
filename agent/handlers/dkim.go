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

type DkimReq struct {
	Domain string `json:"domain"`
}

// EnsureOpenDKIMConfig sets up the basic configuration and directories for OpenDKIM
func EnsureOpenDKIMConfig() error {
	os.MkdirAll("/etc/opendkim/keys", 0755)

	// Ensure tables exist
	if _, err := os.Stat("/etc/opendkim/signing_table"); os.IsNotExist(err) {
		os.WriteFile("/etc/opendkim/signing_table", []byte(""), 0644)
	}
	if _, err := os.Stat("/etc/opendkim/key_table"); os.IsNotExist(err) {
		os.WriteFile("/etc/opendkim/key_table", []byte(""), 0644)
	}
	if _, err := os.Stat("/etc/opendkim/trusted_hosts"); os.IsNotExist(err) {
		trusted := "127.0.0.1\nlocalhost\n::1\n"
		os.WriteFile("/etc/opendkim/trusted_hosts", []byte(trusted), 0644)
	}

	// Ensure /etc/opendkim.conf exists
	confPath := "/etc/opendkim.conf"
	if _, err := os.Stat(confPath); os.IsNotExist(err) {
		conf := `AutoRestart             Yes
AutoRestartRate         10/1h
Syslog                  Yes
SyslogSuccess           Yes
LogWhy                  Yes
UserID                  opendkim:opendkim
Socket                  inet:8891@127.0.0.1
Umask                   002
Canonicalization        relaxed/simple
Mode                    sv
SubDomains              no
KeyTable                /etc/opendkim/key_table
SigningTable            refile:/etc/opendkim/signing_table
ExternalIgnoreList      refile:/etc/opendkim/trusted_hosts
InternalHosts           refile:/etc/opendkim/trusted_hosts
`
		os.WriteFile(confPath, []byte(conf), 0644)
	}

	system.ExecCommand("chown", "-R", "opendkim:opendkim", "/etc/opendkim")

	// Ensure Postfix is wired to OpenDKIM
	if _, err := system.ExecCommand("postconf", "-h", "smtpd_milters"); err == nil {
		system.ExecCommand("postconf", "-e", "milter_default_action = accept")
		system.ExecCommand("postconf", "-e", "milter_protocol = 6")
		system.ExecCommand("postconf", "-e", "smtpd_milters = inet:127.0.0.1:8891")
		system.ExecCommand("postconf", "-e", "non_smtpd_milters = inet:127.0.0.1:8891")
	}

	return nil
}

// GenerateDkimKey generates a 2048-bit RSA key for the domain, registers it with OpenDKIM,
// inserts the TXT record into PowerDNS, and returns the record name and formatted TXT content.
func GenerateDkimKey(domain string) (recordName string, txtContent string, err error) {
	domain = strings.ToLower(strings.TrimSpace(domain))
	if domain == "" {
		return "", "", fmt.Errorf("domain cannot be empty")
	}

	EnsureOpenDKIMConfig()

	keyDir := fmt.Sprintf("/etc/opendkim/keys/%s", domain)
	os.MkdirAll(keyDir, 0750)

	privKeyPath := filepath.Join(keyDir, "default.private")

	// 1. Generate 2048-bit private key if not already existing
	if _, statErr := os.Stat(privKeyPath); os.IsNotExist(statErr) {
		// Prefer opendkim-genkey if available
		if _, lookErr := system.ExecCommand("which", "opendkim-genkey"); lookErr == nil {
			system.ExecCommand("opendkim-genkey", "-b", "2048", "-d", domain, "-s", "default", "-D", keyDir)
		} else {
			system.ExecCommand("openssl", "genrsa", "-out", privKeyPath, "2048")
		}
	}

	system.ExecCommand("chmod", "600", privKeyPath)
	system.ExecCommand("chown", "-R", "opendkim:opendkim", "/etc/opendkim")

	// 2. Extract public key in standard base64 DER format
	cmd := fmt.Sprintf("openssl rsa -in %s -pubout -outform DER 2>/dev/null | base64 | tr -d '\\r\\n'", privKeyPath)
	out, _ := system.ExecCommand("bash", "-c", cmd)
	pubKey := strings.TrimSpace(out)

	if pubKey == "" {
		// Fallback check if default.txt exists from opendkim-genkey
		txtFile := filepath.Join(keyDir, "default.txt")
		if data, err := os.ReadFile(txtFile); err == nil {
			s := string(data)
			start := strings.Index(s, "p=")
			if start != -1 {
				rem := s[start+2:]
				rem = strings.ReplaceAll(rem, "\"", "")
				rem = strings.ReplaceAll(rem, "\t", "")
				rem = strings.ReplaceAll(rem, "\n", "")
				rem = strings.ReplaceAll(rem, " ", "")
				rem = strings.ReplaceAll(rem, ")", "")
				rem = strings.ReplaceAll(rem, ";", "")
				pubKey = strings.TrimSpace(rem)
			}
		}
	}

	if pubKey == "" {
		return "", "", fmt.Errorf("failed to extract public key for domain %s", domain)
	}

	txtContent = fmt.Sprintf("v=DKIM1; k=rsa; p=%s", pubKey)
	recordName = fmt.Sprintf("default._domainkey.%s", domain)

	// 3. Register in OpenDKIM tables
	signingEntry := fmt.Sprintf("*@%s default._domainkey.%s\n", domain, domain)
	removeLineFromFile("/etc/opendkim/signing_table", "*@"+domain)
	appendToFile("/etc/opendkim/signing_table", signingEntry)

	keyEntry := fmt.Sprintf("default._domainkey.%s %s:default:%s\n", domain, domain, privKeyPath)
	removeLineFromFile("/etc/opendkim/key_table", "default._domainkey."+domain)
	appendToFile("/etc/opendkim/key_table", keyEntry)

	if !fileContains("/etc/opendkim/trusted_hosts", domain) {
		appendToFile("/etc/opendkim/trusted_hosts", domain+"\n*."+domain+"\n")
	}

	system.ExecCommand("chown", "-R", "opendkim:opendkim", "/etc/opendkim")
	system.ExecCommand("systemctl", "restart", "opendkim")
	system.ExecCommand("systemctl", "reload", "postfix")

	// 4. Upsert DKIM TXT record in PowerDNS
	getIdQuery := fmt.Sprintf("SELECT id FROM pdns.domains WHERE name = '%s' LIMIT 1;", domain)
	idOut, _ := system.ExecCommand("mysql", "-sN", "-e", getIdQuery)
	domainIdStr := strings.TrimSpace(idOut)

	if domainIdStr != "" {
		// Remove any existing DKIM TXT record to avoid duplicates
		delQuery := fmt.Sprintf("DELETE FROM pdns.records WHERE domain_id = %s AND name = '%s' AND type = 'TXT';", domainIdStr, recordName)
		system.ExecCommand("mysql", "-e", delQuery)

		// Insert the new DKIM TXT record with escaped quotes
		escapedTxt := strings.ReplaceAll(txtContent, "'", "\\'")
		recQuery := fmt.Sprintf("INSERT INTO pdns.records (domain_id, name, type, content, ttl, prio) VALUES (%s, '%s', 'TXT', '\"%s\"', 3600, 0);",
			domainIdStr, recordName, escapedTxt)
		system.ExecCommand("mysql", "-e", recQuery)
	}

	return recordName, txtContent, nil
}

func EmailDkimGenerateHandler(w http.ResponseWriter, r *http.Request) {
	var req DkimReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	recordName, txtContent, err := GenerateDkimKey(req.Domain)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":         "ok",
		"domain":         req.Domain,
		"selector":       "default",
		"record_name":    recordName,
		"record_type":    "TXT",
		"record_content": txtContent,
	})
}

func EmailDkimGetHandler(w http.ResponseWriter, r *http.Request) {
	domain := strings.ToLower(strings.TrimSpace(r.URL.Query().Get("domain")))
	if domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	recordName, txtContent, err := GenerateDkimKey(domain)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":         "ok",
		"domain":         domain,
		"selector":       "default",
		"record_name":    recordName,
		"record_type":    "TXT",
		"record_content": txtContent,
	})
}
