package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"

	"realpanel-agent-oss/system"
)

type FirewallRule struct {
	Number int    `json:"number"`
	To     string `json:"to"`
	Action string `json:"action"`
	From   string `json:"from"`
}

type FirewallAddReq struct {
	Port   string `json:"port"`
	Proto  string `json:"proto"`
	Action string `json:"action"`
	From   string `json:"from"`
}

type FirewallRemoveReq struct {
	RuleNumber int    `json:"rule_number"`
	Port       string `json:"port"`
}

type CronReq struct {
	Username string `json:"username"`
	Schedule string `json:"schedule"`
	Command  string `json:"command"`
	Status   string `json:"status,omitempty"`
}

func FirewallListHandler(w http.ResponseWriter, r *http.Request) {
	out, _ := system.ExecCommand("ufw", "status", "numbered")
	var rules []FirewallRule
	status := "inactive"

	if strings.Contains(out, "Status: active") {
		status = "active"
	}

	re := regexp.MustCompile(`\[\s*(\d+)\]\s+([^\s]+(?:\s+\(v6\))?)\s+([A-Z]+(?:\s+IN)?)\s+([^\s]+(?:\s+\(v6\))?)`)
	matches := re.FindAllStringSubmatch(out, -1)
	for _, m := range matches {
		if len(m) >= 5 {
			num, _ := strconv.Atoi(m[1])
			rules = append(rules, FirewallRule{
				Number: num,
				To:     m[2],
				Action: m[3],
				From:   m[4],
			})
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":          "ok",
		"firewall_status": status,
		"rules":           rules,
	})
}

func FirewallAddHandler(w http.ResponseWriter, r *http.Request) {
	var req FirewallAddReq
	json.NewDecoder(r.Body).Decode(&req)

	if req.Port != "" {
		if req.Proto != "" {
			system.ExecCommand("ufw", "allow", fmt.Sprintf("%s/%s", req.Port, req.Proto))
		} else {
			system.ExecCommand("ufw", "allow", req.Port)
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FirewallRemoveHandler(w http.ResponseWriter, r *http.Request) {
	var req FirewallRemoveReq
	json.NewDecoder(r.Body).Decode(&req)

	if req.RuleNumber > 0 {
		system.ExecCommand("bash", "-c", fmt.Sprintf("echo y | ufw delete %d", req.RuleNumber))
	} else if req.Port != "" {
		system.ExecCommand("ufw", "delete", "allow", req.Port)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func Fail2banListHandler(w http.ResponseWriter, r *http.Request) {
	out, _ := system.ExecCommand("fail2ban-client", "status")
	var jails []string
	if strings.Contains(out, "Jail list:") {
		parts := strings.Split(out, "Jail list:")
		if len(parts) > 1 {
			rawList := strings.TrimSpace(parts[1])
			for _, j := range strings.Split(rawList, ",") {
				clean := strings.TrimSpace(j)
				if clean != "" {
					jails = append(jails, clean)
				}
			}
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"jails":  jails,
		"banned": []interface{}{},
	})
}

func Fail2banUnbanHandler(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	json.NewDecoder(r.Body).Decode(&req)

	ip := req["ip"]
	jail := req["jail"]
	if jail == "" {
		jail = "sshd"
	}
	if ip != "" {
		system.ExecCommand("fail2ban-client", "set", jail, "unbanip", ip)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func CronCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req CronReq
	json.NewDecoder(r.Body).Decode(&req)

	line := fmt.Sprintf("%s %s", req.Schedule, req.Command)
	system.ExecCommand("bash", "-c", fmt.Sprintf("(crontab -u %s -l 2>/dev/null; echo '%s') | crontab -u %s -", req.Username, line, req.Username))

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func CronDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req CronReq
	json.NewDecoder(r.Body).Decode(&req)

	system.ExecCommand("bash", "-c", fmt.Sprintf("crontab -u %s -l 2>/dev/null | grep -Fv '%s' | crontab -u %s -", req.Username, req.Command, req.Username))

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

type IpBlockSyncReq struct {
	Domain   string   `json:"domain"`
	Blocked  []string `json:"blocked"`
	Username string   `json:"username"`
}

// IpBlockSyncHandler generates Nginx and .htaccess deny rules for a specific domain or server-wide
func IpBlockSyncHandler(w http.ResponseWriter, r *http.Request) {
	var req IpBlockSyncReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid request"})
		return
	}

	includeDir := "/etc/nginx/realpanel_includes"
	os.MkdirAll(includeDir, 0755)

	confName := "blocked_ips.conf"
	if req.Domain != "" {
		confName = fmt.Sprintf("%s_blocked_ips.conf", req.Domain)
	}
	confPath := filepath.Join(includeDir, confName)

	var lines []string
	lines = append(lines, "# Managed by RealPanel - IP Deny Rules")
	for _, ip := range req.Blocked {
		trimmed := strings.TrimSpace(ip)
		if trimmed != "" {
			lines = append(lines, fmt.Sprintf("deny %s;", trimmed))
		}
	}
	lines = append(lines, "")

	if err := os.WriteFile(confPath, []byte(strings.Join(lines, "\n")), 0644); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	// Also if domain is specified, ensure Nginx site configuration includes it
	if req.Domain != "" {
		vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
		if content, err := os.ReadFile(vhostPath); err == nil {
			incDirective := fmt.Sprintf("    include %s;", confPath)
			if !strings.Contains(string(content), incDirective) {
				// Inject inside server block
				updated := strings.Replace(string(content), "root ", incDirective+"\n    root ", 1)
				os.WriteFile(vhostPath, []byte(updated), 0644)
			}
		}
	}

	system.ExecCommand("nginx", "-t")
	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Blocked IP rules synchronized successfully",
		"count":   len(req.Blocked),
	})
}

type ModSecStatusReq struct {
	Domain string `json:"domain"`
}

type ModSecToggleReq struct {
	Domain  string `json:"domain"`
	Enabled bool   `json:"enabled"`
}

type ModSecWhitelistReq struct {
	Domain string   `json:"domain"`
	RuleID string   `json:"rule_id"`
	Action string   `json:"action"` // "add" or "remove"
}

// ModSecStatusHandler checks if ModSecurity is active on the server / domain
func ModSecStatusHandler(w http.ResponseWriter, r *http.Request) {
	var req ModSecStatusReq
	json.NewDecoder(r.Body).Decode(&req)

	// Check if modsecurity is installed on system
	installed := false
	if _, err := os.Stat("/etc/modsecurity"); err == nil {
		installed = true
	} else if _, err := os.Stat("/etc/nginx/modsecurity.conf"); err == nil {
		installed = true
	}

	enabled := false
	var disabledRules []string

	modsecDir := "/etc/nginx/realpanel_modsec"
	if req.Domain != "" {
		domainConf := filepath.Join(modsecDir, fmt.Sprintf("%s_rules.conf", req.Domain))
		if data, err := os.ReadFile(domainConf); err == nil {
			content := string(data)
			if strings.Contains(content, "SecRuleEngine On") {
				enabled = true
			}
			re := regexp.MustCompile(`SecRuleRemoveById\s+(\d+)`)
			matches := re.FindAllStringSubmatch(content, -1)
			for _, m := range matches {
				if len(m) > 1 {
					disabledRules = append(disabledRules, m[1])
				}
			}
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":          "ok",
		"installed":       installed,
		"enabled":         enabled,
		"disabled_rules":  disabledRules,
	})
}

// ModSecToggleHandler toggles ModSecurity on or off for a domain
func ModSecToggleHandler(w http.ResponseWriter, r *http.Request) {
	var req ModSecToggleReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	modsecDir := "/etc/nginx/realpanel_modsec"
	os.MkdirAll(modsecDir, 0755)

	domainConf := filepath.Join(modsecDir, fmt.Sprintf("%s_rules.conf", req.Domain))

	engineVal := "Off"
	if req.Enabled {
		engineVal = "On"
	}

	var existingExclusions []string
	if data, err := os.ReadFile(domainConf); err == nil {
		re := regexp.MustCompile(`SecRuleRemoveById\s+(\d+)`)
		matches := re.FindAllStringSubmatch(string(data), -1)
		for _, m := range matches {
			if len(m) > 1 {
				existingExclusions = append(existingExclusions, m[1])
			}
		}
	}

	var lines []string
	lines = append(lines, fmt.Sprintf("# ModSecurity rules for %s", req.Domain))
	lines = append(lines, fmt.Sprintf("SecRuleEngine %s", engineVal))
	for _, rule := range existingExclusions {
		lines = append(lines, fmt.Sprintf("SecRuleRemoveById %s", rule))
	}

	os.WriteFile(domainConf, []byte(strings.Join(lines, "\n")+"\n"), 0644)

	// Ensure inclusion in Nginx site config
	vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	if content, err := os.ReadFile(vhostPath); err == nil {
		inc := fmt.Sprintf("    modsecurity on;\n    modsecurity_rules_file %s;", domainConf)
		if !strings.Contains(string(content), domainConf) && req.Enabled {
			updated := strings.Replace(string(content), "root ", inc+"\n    root ", 1)
			os.WriteFile(vhostPath, []byte(updated), 0644)
		}
	}

	system.ExecCommand("nginx", "-t")
	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"enabled": req.Enabled,
		"message": fmt.Sprintf("ModSecurity %s for %s", engineVal, req.Domain),
	})
}

// ModSecWhitelistHandler adds or removes a rule exception (SecRuleRemoveById)
func ModSecWhitelistHandler(w http.ResponseWriter, r *http.Request) {
	var req ModSecWhitelistReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" || req.RuleID == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain and rule_id required"})
		return
	}

	modsecDir := "/etc/nginx/realpanel_modsec"
	os.MkdirAll(modsecDir, 0755)

	domainConf := filepath.Join(modsecDir, fmt.Sprintf("%s_rules.conf", req.Domain))
	content := ""
	if data, err := os.ReadFile(domainConf); err == nil {
		content = string(data)
	}

	re := regexp.MustCompile(`SecRuleRemoveById\s+(\d+)`)
	existingMatches := re.FindAllStringSubmatch(content, -1)
	rulesMap := make(map[string]bool)
	for _, m := range existingMatches {
		if len(m) > 1 {
			rulesMap[m[1]] = true
		}
	}

	if req.Action == "remove" {
		delete(rulesMap, req.RuleID)
	} else {
		rulesMap[req.RuleID] = true
	}

	engineState := "On"
	if strings.Contains(content, "SecRuleEngine Off") {
		engineState = "Off"
	}

	var lines []string
	lines = append(lines, fmt.Sprintf("# ModSecurity rules for %s", req.Domain))
	lines = append(lines, fmt.Sprintf("SecRuleEngine %s", engineState))
	for id := range rulesMap {
		lines = append(lines, fmt.Sprintf("SecRuleRemoveById %s", id))
	}

	os.WriteFile(domainConf, []byte(strings.Join(lines, "\n")+"\n"), 0644)

	system.ExecCommand("nginx", "-t")
	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"rule_id": req.RuleID,
		"action":  req.Action,
		"message": "ModSecurity rule list updated",
	})
}

