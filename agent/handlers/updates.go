package handlers

import (
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"opterius-agent-oss/system"
)

// VersionHandler returns version information for RealPanel agent and services
func VersionHandler(w http.ResponseWriter, r *http.Request) {
	// Read panel version if available
	panelVer := "2.9.20"
	versionFile := "/opt/opterius/version.txt"
	if data, err := os.ReadFile(versionFile); err == nil {
		panelVer = strings.TrimSpace(string(data))
	}

	mailInstalled := false
	if _, err := os.Stat("/opt/opterius-mail"); err == nil {
		mailInstalled = true
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":         "ok",
		"agent_version":  panelVer,
		"mail_installed": mailInstalled,
		"mail_version":   panelVer,
	})
}

// UpdateLogHandler returns the latest update log
func UpdateLogHandler(w http.ResponseWriter, r *http.Request) {
	logPath := "/var/log/realpanel-update.log"
	logContent := ""
	if data, err := os.ReadFile(logPath); err == nil {
		logContent = string(data)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"log":    logContent,
	})
}

// UpdateRunHandler executes the panel update script or git pull in background
func UpdateRunHandler(w http.ResponseWriter, r *http.Request) {
	panelDir := "/opt/opterius"
	logPath := "/var/log/realpanel-update.log"

	go func() {
		os.MkdirAll(filepath.Dir(logPath), 0755)
		f, _ := os.OpenFile(logPath, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
		defer f.Close()

		fmt.Fprintf(f, "\n=== Starting RealPanel Update ===\n")
		out, err := system.ExecCommand("bash", "-c", fmt.Sprintf("cd %s && git pull origin main && php artisan migrate --force && php artisan view:clear && php artisan config:clear && php artisan cache:clear", panelDir))
		if err != nil {
			fmt.Fprintf(f, "Update Error: %v\nOutput: %s\n", err, out)
		} else {
			fmt.Fprintf(f, "Update Succeeded:\n%s\n", out)
		}
	}()

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Update process started successfully",
	})
}
