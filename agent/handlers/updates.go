package handlers

import (
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	"realpanel-agent-oss/system"
)

// VersionHandler returns version information for RealPanel agent and services
func VersionHandler(w http.ResponseWriter, r *http.Request) {
	// Read panel version if available
	panelDir := "/opt/realpanel"
	if _, err := os.Stat(panelDir); os.IsNotExist(err) {
		panelDir = "/opt/opterius"
	}
	versionFile := filepath.Join(panelDir, "version.txt")
	if data, err := os.ReadFile(versionFile); err == nil {
		panelVer = strings.TrimSpace(string(data))
	}

	mailInstalled := false
	if _, err := os.Stat("/opt/realpanel-mail"); err == nil {
		mailInstalled = true
	} else if _, err := os.Stat("/opt/opterius-mail"); err == nil {
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
	panelDir := "/opt/realpanel"
	if _, err := os.Stat(panelDir); os.IsNotExist(err) {
		panelDir = "/opt/opterius"
	}
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
	artisanCmd := fmt.Sprintf("cd %s && php artisan migrate --force && php artisan view:clear && php artisan route:clear && php artisan config:clear && php artisan cache:clear 2>&1", panelDir)
	artOut, artErr := system.ExecCommand("bash", "-c", artisanCmd)
	if artErr != nil {
		writeLog("Artisan Warning/Error: " + artOut + "\n")
	} else {
		writeLog("Artisan Output:\n" + artOut + "\n")
	}

	// 4. Automatic System & Service Repairs (PowerDNS, DNSStubListener, ttyd, etc.)
	writeLog("Checking & applying automatic service maintenance fixes...\n")
	sysFixCmd := `
# Ensure symlinks between legacy and new directory structures
if [ -d /opt/opterius ] && [ ! -e /opt/realpanel ]; then
    ln -sfn /opt/opterius /opt/realpanel 2>/dev/null || true
fi
if [ -d /opt/realpanel ] && [ ! -e /opt/opterius ]; then
    ln -sfn /opt/realpanel /opt/opterius 2>/dev/null || true
fi
if [ -d /etc/opterius ] && [ ! -d /etc/realpanel ]; then
    cp -rn /etc/opterius /etc/realpanel 2>/dev/null || true
    ln -sfn /etc/realpanel /etc/opterius 2>/dev/null || true
fi

# --- PowerDNS Database Schema & Fixes ---
if command -v mysql >/dev/null 2>&1 && mysql -u root -e "USE pdns;" >/dev/null 2>&1; then
    mysql -u root <<'EOSQL' >/dev/null 2>&1 || true
USE pdns;
ALTER TABLE domains ADD COLUMN IF NOT EXISTS master VARCHAR(128) DEFAULT NULL AFTER name;
ALTER TABLE domains ADD COLUMN IF NOT EXISTS last_check INT DEFAULT NULL AFTER master;
ALTER TABLE domains ADD COLUMN IF NOT EXISTS options VARCHAR(64000) DEFAULT NULL AFTER account;
ALTER TABLE domains ADD COLUMN IF NOT EXISTS catalog VARCHAR(255) DEFAULT NULL AFTER options;
ALTER TABLE records ADD COLUMN IF NOT EXISTS ordername VARCHAR(255) BINARY DEFAULT NULL AFTER disabled;
ALTER TABLE records ADD COLUMN IF NOT EXISTS auth TINYINT(1) DEFAULT 1 AFTER ordername;

CREATE TABLE IF NOT EXISTS comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(10) NOT NULL,
  modified_at INT NOT NULL,
  account VARCHAR(40) CHARACTER SET 'utf8mb4' DEFAULT NULL,
  comment TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS domainmetadata (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  kind VARCHAR(32),
  content TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cryptokeys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  domain_id INT NOT NULL,
  flags INT NOT NULL,
  active BOOL,
  published BOOL DEFAULT 1,
  content TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tsigkeys (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255),
  algorithm VARCHAR(50),
  secret VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
EOSQL

    # Clean up duplicate package default configs in pdns.d that conflict with pdns.conf
    rm -f /etc/powerdns/pdns.d/bind.conf /etc/powerdns/pdns.d/*.conf 2>/dev/null || true
    chown -R pdns:pdns /etc/powerdns 2>/dev/null || true

    # Make sure systemd-resolved is not blocking port 53
    if systemctl is-active --quiet systemd-resolved 2>/dev/null || [ -d /etc/systemd/resolved.conf.d ]; then
        mkdir -p /etc/systemd/resolved.conf.d
        cat > /etc/systemd/resolved.conf.d/10-powerdns.conf <<'EORESOLV'
[Resolve]
DNSStubListener=no
EORESOLV
        systemctl restart systemd-resolved 2>/dev/null || true
    fi

    # Restart PowerDNS
    systemctl restart pdns 2>/dev/null || true
fi

# --- OpenDKIM Setup & Fixes ---
if ! command -v opendkim >/dev/null 2>&1; then
    if command -v apt-get >/dev/null 2>&1; then
        DEBIAN_FRONTEND=noninteractive apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq opendkim opendkim-tools
    elif command -v dnf >/dev/null 2>&1; then
        dnf install -y -q opendkim opendkim-tools
    fi
fi

if [ -f /etc/opendkim.conf ] || command -v opendkim >/dev/null 2>&1; then
    mkdir -p /etc/opendkim/keys
    touch /etc/opendkim/signing_table
    touch /etc/opendkim/key_table
    if [ ! -f /etc/opendkim/trusted_hosts ]; then
        cat > /etc/opendkim/trusted_hosts <<'EODKIMHOSTS'
127.0.0.1
localhost
::1
EODKIMHOSTS
    fi

    cat > /etc/opendkim.conf <<'EODKIM'
AutoRestart             Yes
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
EODKIM

    chown -R opendkim:opendkim /etc/opendkim 2>/dev/null || true
    systemctl restart opendkim 2>/dev/null || systemctl start opendkim 2>/dev/null || true

    if command -v postconf >/dev/null 2>&1; then
        postconf -e "milter_default_action = accept"
        postconf -e "milter_protocol = 6"
        postconf -e "smtpd_milters = inet:127.0.0.1:8891"
        postconf -e "non_smtpd_milters = inet:127.0.0.1:8891"
        systemctl reload postfix 2>/dev/null || true
    fi
fi

# --- ttyd Web Terminal Ensure ---
if [ ! -f /usr/local/bin/ttyd ]; then
    ARCH=$(uname -m)
    curl -fsSL "https://github.com/tsl0922/ttyd/releases/download/1.7.7/ttyd.${ARCH}" -o /usr/local/bin/ttyd 2>/dev/null || \
    curl -fsSL "https://github.com/tsl0922/ttyd/releases/latest/download/ttyd.${ARCH}" -o /usr/local/bin/ttyd 2>/dev/null
    chmod +x /usr/local/bin/ttyd 2>/dev/null || true
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
    systemctl daemon-reload 2>/dev/null || true
    systemctl enable --now ttyd.service 2>/dev/null || true
fi
`
	sysFixOut, _ := system.ExecCommand("bash", "-c", sysFixCmd)
	if sysFixOut != "" {
		writeLog("System Maintenance Output:\n" + sysFixOut + "\n")
	}

	// 5. Rebuild and restart the Go agent in the background so this request finishes cleanly
	go func() {
		time.Sleep(1 * time.Second)
		agentBuildCmd := fmt.Sprintf(`cd %s/agent && go build -o /usr/local/bin/realpanel-agent . && chmod +x /usr/local/bin/realpanel-agent && ln -sf /usr/local/bin/realpanel-agent /usr/local/bin/opterius-agent
if [ -f /etc/systemd/system/opterius-agent.service ] && [ ! -f /etc/systemd/system/realpanel-agent.service ]; then
    cp /etc/systemd/system/opterius-agent.service /etc/systemd/system/realpanel-agent.service
    sed -i 's|Description=.*|Description=RealPanel Agent|' /etc/systemd/system/realpanel-agent.service
    sed -i 's|/opterius-agent|/realpanel-agent|g' /etc/systemd/system/realpanel-agent.service
    systemctl daemon-reload 2>/dev/null || true
    systemctl enable realpanel-agent 2>/dev/null || true
fi
systemctl restart realpanel-agent 2>/dev/null || systemctl restart opterius-agent 2>/dev/null || true
`, panelDir)
		system.ExecCommand("bash", "-c", agentBuildCmd)
	}()

	writeLog("=== RealPanel Update Finished Successfully ===\n")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Update completed successfully",
		"output":  pullOut + "\n" + artOut,
	})
}
