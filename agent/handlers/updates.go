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

// UpdateRunHandler executes the panel update script and agent recompile synchronously
func UpdateRunHandler(w http.ResponseWriter, r *http.Request) {
	panelDir := "/opt/opterius"
	logPath := "/var/log/realpanel-update.log"

	os.MkdirAll(filepath.Dir(logPath), 0755)
	f, _ := os.OpenFile(logPath, os.O_CREATE|os.O_WRONLY|os.O_APPEND, 0644)
	if f != nil {
		defer f.Close()
	}

	writeLog := func(msg string) {
		if f != nil {
			fmt.Fprint(f, msg)
		}
	}

	writeLog("\n=== Starting RealPanel Update ===\n")

	// 1. Ensure git safe.directory is configured to avoid dubious ownership errors
	system.ExecCommand("git", "config", "--global", "--add", "safe.directory", panelDir)

	// 2. Perform git pull
	pullCmd := fmt.Sprintf("cd %s && git config --global --add safe.directory %s && git pull origin main 2>&1", panelDir, panelDir)
	pullOut, pullErr := system.ExecCommand("bash", "-c", pullCmd)
	if pullErr != nil {
		errStr := fmt.Sprintf("Git pull failed: %v\nOutput: %s\n", pullErr, pullOut)
		writeLog("Update Error: " + errStr)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"error":  "Git pull failed",
			"output": pullOut,
		})
		return
	}
	writeLog("Git pull output:\n" + pullOut + "\n")

	// 3. Run Laravel migrations and cache clearing
	artisanCmd := fmt.Sprintf("cd %s && php artisan migrate --force && php artisan view:clear && php artisan config:clear && php artisan cache:clear 2>&1", panelDir)
	artOut, artErr := system.ExecCommand("bash", "-c", artisanCmd)
	if artErr != nil {
		writeLog("Artisan Warning/Error: " + artOut + "\n")
	} else {
		writeLog("Artisan Output:\n" + artOut + "\n")
	}

	// 4. Ensure ttyd Web Terminal is installed and running
	go func() {
		ttydSetupCmd := `
if [ ! -f /usr/local/bin/ttyd ]; then
    ARCH=$(uname -m)
    curl -fsSL "https://github.com/tsl0922/ttyd/releases/download/1.7.7/ttyd.${ARCH}" -o /usr/local/bin/ttyd 2>/dev/null || \
    curl -fsSL "https://github.com/tsl0922/ttyd/releases/latest/download/ttyd.${ARCH}" -o /usr/local/bin/ttyd 2>/dev/null
    chmod +x /usr/local/bin/ttyd
fi

if [ ! -f /etc/systemd/system/ttyd.service ]; then
    cat > /etc/systemd/system/ttyd.service << 'EOSERVICE_TTYD'
[Unit]
Description=RealPanel Root Web Terminal (ttyd)
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/root
ExecStart=/usr/local/bin/ttyd -i 127.0.0.1 -p 7681 -b /terminal-root -W -t fontSize=14 -t cursorBlink=true -t theme={"background":"#111827"} bash
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOSERVICE_TTYD
    systemctl daemon-reload
    systemctl enable --now ttyd.service 2>/dev/null || true
fi
`
		system.ExecCommand("bash", "-c", ttydSetupCmd)

		agentBuildCmd := fmt.Sprintf("cd %s/agent && go build -o /usr/local/bin/opterius-agent . && systemctl restart opterius-agent", panelDir)
		system.ExecCommand("bash", "-c", agentBuildCmd)
	}()

	writeLog("=== RealPanel Update Finished Successfully ===\n")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Update completed successfully",
		"output":  pullOut + "\n" + artOut,
	})
}
