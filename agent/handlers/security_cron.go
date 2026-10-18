package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"regexp"
	"strconv"
	"strings"

	"opterius-agent-oss/system"
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
