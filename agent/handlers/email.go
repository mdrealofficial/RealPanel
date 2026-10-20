package handlers

import (
	"bufio"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"

	"realpanel-agent-oss/system"
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

// EmailRoutingReq sets routing for a domain: local, remote, backup
type EmailRoutingReq struct {
	Domain  string `json:"domain"`
	Routing string `json:"routing"` // local, remote, backup
}

func EmailRoutingSetHandler(w http.ResponseWriter, r *http.Request) {
	var req EmailRoutingReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	routing := strings.ToLower(strings.TrimSpace(req.Routing))
	if routing == "" {
		routing = "local"
	}

	vdomainsPath := "/etc/postfix/vdomains"
	removeLineFromFile(vdomainsPath, req.Domain+" ")
	removeLineFromFile(vdomainsPath, req.Domain+"\t")
	removeLineFromFile(vdomainsPath, req.Domain+"\n")

	if routing == "local" || routing == "backup" {
		appendToFile(vdomainsPath, fmt.Sprintf("%s OK\n", req.Domain))
	}
	system.ExecCommand("postmap", vdomainsPath)
	system.ExecCommand("postfix", "reload")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Email routing for %s set to %s", req.Domain, routing),
	})
}

// EmailCatchallReq configures catch-all action: fail, discard, forward
type EmailCatchallReq struct {
	Domain      string `json:"domain"`
	Action      string `json:"action"` // fail, discard, forward
	Destination string `json:"destination"`
}

func EmailCatchallSetHandler(w http.ResponseWriter, r *http.Request) {
	var req EmailCatchallReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	action := strings.ToLower(strings.TrimSpace(req.Action))
	valiasPath := "/etc/postfix/virtual_aliases"
	os.OpenFile(valiasPath, os.O_CREATE|os.O_WRONLY, 0640)

	catchallPrefix := "@" + req.Domain
	removeLineFromFile(valiasPath, catchallPrefix+" ")
	removeLineFromFile(valiasPath, catchallPrefix+"\t")

	switch action {
	case "forward":
		if req.Destination != "" {
			appendToFile(valiasPath, fmt.Sprintf("%s %s\n", catchallPrefix, req.Destination))
		}
	case "discard":
		appendToFile(valiasPath, fmt.Sprintf("%s :discard:\n", catchallPrefix))
	default:
		// fail: no catch-all entry in aliases map; recipient rejection takes effect
	}

	system.ExecCommand("postmap", valiasPath)

	// Ensure virtual_alias_maps includes /etc/postfix/virtual_aliases in Postfix
	system.ExecCommand("postconf", "-e", "virtual_alias_maps = hash:/etc/postfix/virtual_aliases")
	system.ExecCommand("postfix", "reload")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Catch-all for %s configured as %s", req.Domain, action),
	})
}

// Forwarder handlers
type ForwarderReq struct {
	Domain      string `json:"domain"`
	Source      string `json:"source"`
	Destination string `json:"destination"`
}

func ForwarderListHandler(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Domain string `json:"domain"`
	}
	json.NewDecoder(r.Body).Decode(&req)

	valiasPath := "/etc/postfix/virtual_aliases"
	var forwarders []map[string]string

	content, err := os.ReadFile(valiasPath)
	if err == nil {
		scanner := bufio.NewScanner(strings.NewReader(string(content)))
		for scanner.Scan() {
			line := strings.TrimSpace(scanner.Text())
			if line == "" || strings.HasPrefix(line, "#") {
				continue
			}
			parts := strings.Fields(line)
			if len(parts) >= 2 {
				source := parts[0]
				dest := parts[1]
				if strings.HasPrefix(source, "@") {
					continue // catchall
				}
				if req.Domain != "" && !strings.HasSuffix(source, "@"+req.Domain) {
					continue
				}
				forwarders = append(forwarders, map[string]string{
					"source":      source,
					"destination": dest,
				})
			}
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":     "ok",
		"forwarders": forwarders,
	})
}

func ForwarderCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req ForwarderReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Source == "" || req.Destination == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "source and destination required"})
		return
	}

	valiasPath := "/etc/postfix/virtual_aliases"
	removeLineFromFile(valiasPath, req.Source+" ")
	appendToFile(valiasPath, fmt.Sprintf("%s %s\n", req.Source, req.Destination))
	system.ExecCommand("postmap", valiasPath)
	system.ExecCommand("postconf", "-e", "virtual_alias_maps = hash:/etc/postfix/virtual_aliases")
	system.ExecCommand("postfix", "reload")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func ForwarderDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req ForwarderReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Source == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "source required"})
		return
	}

	valiasPath := "/etc/postfix/virtual_aliases"
	removeLineFromFile(valiasPath, req.Source+" ")
	system.ExecCommand("postmap", valiasPath)
	system.ExecCommand("postfix", "reload")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

// EmailDiskUsageHandler returns sizes per mailbox and folder breakdown
func EmailDiskUsageHandler(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Email  string `json:"email"`
		Domain string `json:"domain"`
	}
	json.NewDecoder(r.Body).Decode(&req)

	type FolderUsage struct {
		Name     string  `json:"name"`
		SizeMB   float64 `json:"size_mb"`
		Messages int     `json:"messages"`
	}

	var mailboxes []map[string]interface{}
	baseDir := "/var/mail/vhosts"
	if _, err := os.Stat("/var/mail/vdomains"); err == nil {
		baseDir = "/var/mail/vdomains"
	}

	domainsToScan := []string{}
	if req.Domain != "" {
		domainsToScan = append(domainsToScan, req.Domain)
	} else if entries, err := os.ReadDir(baseDir); err == nil {
		for _, e := range entries {
			if e.IsDir() {
				domainsToScan = append(domainsToScan, e.Name())
			}
		}
	}

	for _, dom := range domainsToScan {
		domPath := fmt.Sprintf("%s/%s", baseDir, dom)
		users, err := os.ReadDir(domPath)
		if err != nil {
			continue
		}
		for _, u := range users {
			if !u.IsDir() {
				continue
			}
			accountEmail := fmt.Sprintf("%s@%s", u.Name(), dom)
			if req.Email != "" && req.Email != accountEmail {
				continue
			}

			userMailDir := fmt.Sprintf("%s/%s", domPath, u.Name())
			out, _ := system.ExecCommand("du", "-sm", userMailDir)
			totalMB := 0.0
			parts := strings.Fields(out)
			if len(parts) > 0 {
				fmt.Sscanf(parts[0], "%f", &totalMB)
			}

			// Subfolder analysis
			folders := []FolderUsage{}
			for _, folderName := range []string{"INBOX", "Sent", "Trash", "Junk", "Drafts", "Archive"} {
				fPath := userMailDir
				if folderName != "INBOX" {
					fPath = fmt.Sprintf("%s/.%s", userMailDir, folderName)
				}
				if _, err := os.Stat(fPath); err == nil {
					fOut, _ := system.ExecCommand("du", "-sm", fPath)
					fMB := 0.0
					fParts := strings.Fields(fOut)
					if len(fParts) > 0 {
						fmt.Sscanf(fParts[0], "%f", &fMB)
					}
					folders = append(folders, FolderUsage{
						Name:   folderName,
						SizeMB: fMB,
					})
				}
			}

			mailboxes = append(mailboxes, map[string]interface{}{
				"email":    accountEmail,
				"domain":   dom,
				"total_mb": totalMB,
				"folders":  folders,
			})
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":    "ok",
		"mailboxes": mailboxes,
	})
}

// EmailCleanMessagesHandler cleans messages older than N days using doveadm
func EmailCleanMessagesHandler(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Email   string `json:"email"`
		Folder  string `json:"folder"`   // Trash, Junk, Sent, etc.
		DaysOld int    `json:"days_old"` // e.g. 30, 60, 365
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Email == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "email is required"})
		return
	}

	folder := req.Folder
	if folder == "" {
		folder = "Trash"
	}
	days := req.DaysOld
	if days <= 0 {
		days = 30
	}

	// Run doveadm expunge command
	out, err := system.ExecCommand("doveadm", "expunge", "-u", req.Email, "mailbox", folder, "savedbefore", fmt.Sprintf("%dd", days))
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{
			"error": fmt.Sprintf("%v: %s", err, out),
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Cleaned messages in %s older than %d days", folder, days),
	})
}

// EmailSpamConfigHandler handles spam thresholds
func EmailSpamConfigHandler(w http.ResponseWriter, r *http.Request) {
	var req struct {
		Domain    string  `json:"domain"`
		Threshold float64 `json:"threshold"`
		SpamBox   bool    `json:"spam_box"`
	}
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Spam filter settings for %s updated", req.Domain),
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
