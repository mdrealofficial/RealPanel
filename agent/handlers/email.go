package handlers

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"

	"opterius-agent-oss/system"
)

type EmailCreateReq struct {
	Email    string `json:"email"`
	Password string `json:"password"`
	QuotaMB  int    `json:"quota_mb"`
}

type EmailPassReq struct {
	Email    string `json:"email"`
	Password string `json:"password"`
}

type EmailDeleteReq struct {
	Email  string `json:"email"`
	Domain string `json:"domain"`
}

func EmailCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req EmailCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Email == "" || req.Password == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "email and password are required"})
		return
	}

	parts := strings.Split(req.Email, "@")
	if len(parts) != 2 {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid email address format"})
		return
	}
	userPart := parts[0]
	domainPart := parts[1]

	// 1. Ensure /var/mail/vhosts/<domain>/<user> directory exists
	mailDir := fmt.Sprintf("/var/mail/vhosts/%s/%s", domainPart, userPart)
	os.MkdirAll(mailDir, 0770)
	system.ExecCommand("chown", "-R", "vmail:vmail", fmt.Sprintf("/var/mail/vhosts/%s", domainPart))

	// 2. Add / update /etc/dovecot/users
	// Format: email:{PLAIN}password:5000:5000::/var/mail/vhosts/domain/user
	removeLineFromFile("/etc/dovecot/users", req.Email+":")
	entry := fmt.Sprintf("%s:{PLAIN}%s:5000:5000::%s\n", req.Email, req.Password, mailDir)
	appendToFile("/etc/dovecot/users", entry)

	// 3. Add to /etc/postfix/vmailbox
	// Format: email domain/user/
	removeLineFromFile("/etc/postfix/vmailbox", req.Email+" ")
	appendToFile("/etc/postfix/vmailbox", fmt.Sprintf("%s %s/%s/\n", req.Email, domainPart, userPart))
	system.ExecCommand("postmap", "/etc/postfix/vmailbox")

	// 4. Ensure domain is in /etc/postfix/vdomains
	if !fileContains("/etc/postfix/vdomains", domainPart) {
		appendToFile("/etc/postfix/vdomains", fmt.Sprintf("%s OK\n", domainPart))
		system.ExecCommand("postmap", "/etc/postfix/vdomains")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Email %s created successfully", req.Email),
	})
}

func EmailPasswordHandler(w http.ResponseWriter, r *http.Request) {
	var req EmailPassReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	parts := strings.Split(req.Email, "@")
	if len(parts) == 2 {
		mailDir := fmt.Sprintf("/var/mail/vhosts/%s/%s", parts[1], parts[0])
		removeLineFromFile("/etc/dovecot/users", req.Email+":")
		entry := fmt.Sprintf("%s:{PLAIN}%s:5000:5000::%s\n", req.Email, req.Password, mailDir)
		appendToFile("/etc/dovecot/users", entry)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func EmailDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req EmailDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	removeLineFromFile("/etc/dovecot/users", req.Email+":")
	removeLineFromFile("/etc/postfix/vmailbox", req.Email+" ")
	system.ExecCommand("postmap", "/etc/postfix/vmailbox")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func appendToFile(path, content string) error {
	f, err := os.OpenFile(path, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0640)
	if err != nil {
		return err
	}
	defer f.Close()
	_, err = f.WriteString(content)
	return err
}

func removeLineFromFile(path, prefix string) {
	content, err := os.ReadFile(path)
	if err != nil {
		return
	}
	var newLines []string
	scanner := bufio.NewScanner(strings.NewReader(string(content)))
	for scanner.Scan() {
		line := scanner.Text()
		if !strings.HasPrefix(line, prefix) {
			newLines = append(newLines, line)
		}
	}
	os.WriteFile(path, []byte(strings.Join(newLines, "\n")+"\n"), 0640)
}

func fileContains(path, substr string) bool {
	content, err := os.ReadFile(path)
	if err != nil {
		return false
	}
	return strings.Contains(string(content), substr)
}
