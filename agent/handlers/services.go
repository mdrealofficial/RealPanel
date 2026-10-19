package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"strings"

	"realpanel-agent-oss/system"
)

type ServiceActionReq struct {
	Service string `json:"service"`
	Action  string `json:"action"` // start, stop, restart, reload
}

type ManagedServiceDef struct {
	Name        string
	DisplayName string
	Aliases     []string
}

// ServicesListHandler returns the list of system services with their current status and uptime
func ServicesListHandler(w http.ResponseWriter, r *http.Request) {
	// Standard web hosting services monitored on the server
	predefined := []ManagedServiceDef{
		{Name: "nginx", DisplayName: "Nginx Web Server", Aliases: []string{"nginx"}},
		{Name: "apache2", DisplayName: "Apache Web Server", Aliases: []string{"apache2", "httpd"}},
		{Name: "mariadb", DisplayName: "MariaDB Database", Aliases: []string{"mariadb", "mysql"}},
		{Name: "postgresql", DisplayName: "PostgreSQL Database", Aliases: []string{"postgresql"}},
		{Name: "powerdns", DisplayName: "PowerDNS Nameserver", Aliases: []string{"pdns", "powerdns"}},
		{Name: "postfix", DisplayName: "Postfix Mail Transfer Agent", Aliases: []string{"postfix"}},
		{Name: "dovecot", DisplayName: "Dovecot IMAP/POP3 Server", Aliases: []string{"dovecot"}},
		{Name: "redis-server", DisplayName: "Redis In-Memory Cache", Aliases: []string{"redis-server", "redis"}},
		{Name: "memcached", DisplayName: "Memcached", Aliases: []string{"memcached"}},
		{Name: "cron", DisplayName: "Cron Daemon", Aliases: []string{"cron", "crond"}},
		{Name: "fail2ban", DisplayName: "Fail2ban Intrusion Prevention", Aliases: []string{"fail2ban"}},
		{Name: "ufw", DisplayName: "UFW Firewall", Aliases: []string{"ufw"}},
		{Name: "ttyd", DisplayName: "Root Web Terminal (ttyd)", Aliases: []string{"ttyd"}},
		{Name: "realpanel-agent", DisplayName: "RealPanel Agent", Aliases: []string{"realpanel-agent", "opterius-agent"}},
	}

	// Detect installed PHP-FPM versions dynamically
	for _, v := range []string{"8.4", "8.3", "8.2", "8.1", "8.0", "7.4"} {
		sock := fmt.Sprintf("/run/php/php%s-fpm.sock", v)
		svc := fmt.Sprintf("php%s-fpm", v)
		if _, err := os.Stat(sock); err == nil || isServiceInstalled(svc) {
			predefined = append(predefined, ManagedServiceDef{
				Name:        svc,
				DisplayName: fmt.Sprintf("PHP %s FPM", v),
				Aliases:     []string{svc},
			})
		}
	}

	var results []map[string]interface{}

	for _, def := range predefined {
		actualName := ""
		for _, alias := range def.Aliases {
			if isServiceInstalled(alias) {
				actualName = alias
				break
			}
		}

		if actualName == "" {
			continue
		}

		// Check status
		statusOut, _ := system.ExecCommand("systemctl", "is-active", actualName)
		status := strings.TrimSpace(statusOut)
		if status != "active" && status != "inactive" {
			status = "failed"
		}

		// Check uptime (ActiveEnterTimestamp)
		uptime := ""
		timeOut, timeErr := system.ExecCommand("systemctl", "show", "-p", "ActiveEnterTimestamp", "--value", actualName)
		if timeErr == nil {
			uptime = strings.TrimSpace(timeOut)
		}

		results = append(results, map[string]interface{}{
			"name":         actualName,
			"display_name": def.DisplayName,
			"status":       status,
			"uptime":       uptime,
		})
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"services": results,
	})
}

// ServiceActionHandler starts, stops, restarts, or reloads a service via systemctl
func ServiceActionHandler(w http.ResponseWriter, r *http.Request) {
	var req ServiceActionReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Service == "" || req.Action == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{
			"error": "service and action are required",
		})
		return
	}

	action := strings.ToLower(strings.TrimSpace(req.Action))
	service := strings.TrimSpace(req.Service)

	// Validate allowed actions
	validActions := map[string]bool{
		"start":   true,
		"stop":    true,
		"restart": true,
		"reload":  true,
	}

	if !validActions[action] {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{
			"error": "invalid action",
		})
		return
	}

	// Prevent stopping the agent itself
	if (service == "realpanel-agent" || service == "opterius-agent") && action == "stop" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{
			"error": "Cannot stop RealPanel Agent from web UI",
		})
		return
	}

	out, err := system.ExecCommand("systemctl", action, service)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("Failed to %s %s: %v (%s)", action, service, err, out),
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Service %s %sed successfully", service, action),
	})
}

func isServiceInstalled(serviceName string) bool {
	out, err := system.ExecCommand("systemctl", "list-unit-files", serviceName+".service")
	if err == nil && strings.Contains(out, serviceName+".service") {
		return true
	}
	// Fallback check in /lib/systemd/system and /etc/systemd/system
	if _, err := os.Stat(fmt.Sprintf("/lib/systemd/system/%s.service", serviceName)); err == nil {
		return true
	}
	if _, err := os.Stat(fmt.Sprintf("/etc/systemd/system/%s.service", serviceName)); err == nil {
		return true
	}
	return false
}
