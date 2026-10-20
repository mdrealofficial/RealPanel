package main

import (
	"flag"
	"fmt"
	"log"
	"net/http"
	"os"

	"realpanel-agent-oss/auth"
	"realpanel-agent-oss/config"
	"realpanel-agent-oss/handlers"
	"realpanel-agent-oss/system"
)

func main() {
	configPath := flag.String("config", "/etc/realpanel/agent.conf", "Path to agent configuration file")
	flag.Parse()

	// Fallback to legacy path if new path doesn't exist
	activeConfPath := *configPath
	if _, err := os.Stat(activeConfPath); os.IsNotExist(err) {
		if _, legacyErr := os.Stat("/etc/opterius/agent.conf"); legacyErr == nil {
			activeConfPath = "/etc/opterius/agent.conf"
		}
	}

	cfg, err := config.Load(activeConfPath)
	if err != nil {
		log.Printf("[WARN] Could not load config file at %s: %v. Using defaults.", activeConfPath, err)
	}

	mux := http.NewServeMux()

	// 1. Health & System Metrics
	mux.HandleFunc("/health", handlers.HealthHandler)
	mux.HandleFunc("/version", handlers.VersionHandler)
	mux.HandleFunc("/update/log", handlers.UpdateLogHandler)
	mux.HandleFunc("/update/status", handlers.UpdateStatusHandler)
	mux.HandleFunc("/update/run", handlers.UpdateRunHandler)
	mux.HandleFunc("/metrics/realtime", handlers.RealtimeMetricsHandler)
	mux.HandleFunc("/stats/server", handlers.ServerStatsHandler)
	mux.HandleFunc("/metrics/top-processes", handlers.TopProcessesHandler)

	// 2. Domains & Virtual Hosts
	mux.HandleFunc("/domains/create", handlers.DomainCreateHandler)
	mux.HandleFunc("/domains/delete", handlers.DomainDeleteHandler)
	mux.HandleFunc("/domains/alias-add", handlers.DomainAliasAddHandler)
	mux.HandleFunc("/domains/alias-remove", handlers.DomainAliasRemoveHandler)
	mux.HandleFunc("/domains/toggle-htaccess", handlers.DomainToggleHtaccessHandler)
	mux.HandleFunc("/account/suspend", handlers.AccountSuspendHandler)
	mux.HandleFunc("/server/pages-sync", handlers.ServerPagesSyncHandler)

	// 3. Databases (MySQL)
	mux.HandleFunc("/databases/create", handlers.DbCreateHandler)
	mux.HandleFunc("/databases/delete", handlers.DbDeleteHandler)
	mux.HandleFunc("/databases/user-create", handlers.DbUserCreateHandler)
	mux.HandleFunc("/databases/user-delete", handlers.DbUserDeleteHandler)
	mux.HandleFunc("/databases/user-password", handlers.DbUserPasswordHandler)
	mux.HandleFunc("/databases/info", handlers.DbInfoHandler)
	mux.HandleFunc("/databases/repair", handlers.DbRepairHandler)
	mux.HandleFunc("/databases/export", handlers.DbExportHandler)
	mux.HandleFunc("/databases/import", handlers.DbImportHandler)
	mux.HandleFunc("/databases/user-permissions", handlers.DbUserPermsHandler)

	// PostgreSQL
	mux.HandleFunc("/postgres/create", handlers.PostgresCreateHandler)
	mux.HandleFunc("/postgres/delete", handlers.PostgresDeleteHandler)
	mux.HandleFunc("/postgres/user-create", handlers.PostgresUserCreateHandler)
	mux.HandleFunc("/postgres/user-delete", handlers.PostgresUserDeleteHandler)
	mux.HandleFunc("/postgres/user-password", handlers.PostgresUserPasswordHandler)
	mux.HandleFunc("/postgres/info", handlers.PostgresInfoHandler)
	mux.HandleFunc("/postgres/export", handlers.PostgresExportHandler)
	mux.HandleFunc("/postgres/import", handlers.PostgresImportHandler)

	// MongoDB
	mux.HandleFunc("/mongodb/create", handlers.MongoCreateHandler)
	mux.HandleFunc("/mongodb/delete", handlers.MongoDeleteHandler)
	mux.HandleFunc("/mongodb/user-create", handlers.MongoUserCreateHandler)
	mux.HandleFunc("/mongodb/user-delete", handlers.MongoUserDeleteHandler)
	mux.HandleFunc("/mongodb/user-password", handlers.MongoUserPasswordHandler)
	mux.HandleFunc("/mongodb/info", handlers.MongoInfoHandler)
	mux.HandleFunc("/mongodb/export", handlers.MongoExportHandler)
	mux.HandleFunc("/mongodb/import", handlers.MongoImportHandler)

	// 4. Email
	mux.HandleFunc("/email/create", handlers.EmailCreateHandler)
	mux.HandleFunc("/email/password", handlers.EmailPasswordHandler)
	mux.HandleFunc("/email/delete", handlers.EmailDeleteHandler)
	mux.HandleFunc("/email/dkim-generate", handlers.EmailDkimGenerateHandler)
	mux.HandleFunc("/email/dkim-get", handlers.EmailDkimGetHandler)
	mux.HandleFunc("/email/routing-set", handlers.EmailRoutingSetHandler)
	mux.HandleFunc("/email/catchall-set", handlers.EmailCatchallSetHandler)
	mux.HandleFunc("/email/disk-usage", handlers.EmailDiskUsageHandler)
	mux.HandleFunc("/email/clean-messages", handlers.EmailCleanMessagesHandler)
	mux.HandleFunc("/email/spam-config", handlers.EmailSpamConfigHandler)
	mux.HandleFunc("/forwarder/list", handlers.ForwarderListHandler)
	mux.HandleFunc("/forwarder/create", handlers.ForwarderCreateHandler)
	mux.HandleFunc("/forwarder/delete", handlers.ForwarderDeleteHandler)

	// 5. SSL & Certificates
	mux.HandleFunc("/ssl/status", handlers.SslStatusHandler)
	mux.HandleFunc("/ssl/issue-async", handlers.SslIssueAsyncHandler)
	mux.HandleFunc("/ssl/upload", handlers.SslUploadHandler)
	mux.HandleFunc("/ssl/renew", handlers.SslRenewHandler)
	mux.HandleFunc("/ssl/delete", handlers.SslDeleteHandler)
	mux.HandleFunc("/ssl/progress", handlers.SslProgressHandler)
	mux.HandleFunc("/panel/set-hostname", handlers.PanelSetHostnameHandler)

	// 6. DNS (PowerDNS)
	mux.HandleFunc("/dns/create-zone", handlers.DnsCreateZoneHandler)
	mux.HandleFunc("/dns/list-records", handlers.DnsListRecordsHandler)
	mux.HandleFunc("/dns/add-record", handlers.DnsAddRecordHandler)
	mux.HandleFunc("/dns/delete-record", handlers.DnsDeleteRecordHandler)

	// 7. Files
	mux.HandleFunc("/files/list", handlers.FileListHandler)
	mux.HandleFunc("/files/read", handlers.FileReadHandler)
	mux.HandleFunc("/files/write", handlers.FileWriteHandler)
	mux.HandleFunc("/files/delete", handlers.FileDeleteHandler)
	mux.HandleFunc("/files/mkdir", handlers.FileMkdirHandler)
	mux.HandleFunc("/files/rename", handlers.FileRenameHandler)
	mux.HandleFunc("/files/chmod", handlers.FileChmodHandler)
	mux.HandleFunc("/files/archive", handlers.FileArchiveHandler)
	mux.HandleFunc("/files/disk-breakdown", handlers.DiskBreakdownHandler)
	mux.HandleFunc("/files/disk-clean", handlers.DiskCleanHandler)

	// 8. Firewall & Security
	mux.HandleFunc("/security/firewall-list", handlers.FirewallListHandler)
	mux.HandleFunc("/security/firewall-add", handlers.FirewallAddHandler)
	mux.HandleFunc("/security/firewall-remove", handlers.FirewallRemoveHandler)
	mux.HandleFunc("/security/fail2ban-list", handlers.Fail2banListHandler)
	mux.HandleFunc("/security/fail2ban-unban", handlers.Fail2banUnbanHandler)
	mux.HandleFunc("/security/ip-block-sync", handlers.IpBlockSyncHandler)
	mux.HandleFunc("/security/modsec/status", handlers.ModSecStatusHandler)
	mux.HandleFunc("/security/modsec/toggle", handlers.ModSecToggleHandler)
	mux.HandleFunc("/security/modsec/whitelist", handlers.ModSecWhitelistHandler)

	// 9. Cron
	mux.HandleFunc("/cron/create", handlers.CronCreateHandler)
	mux.HandleFunc("/cron/delete", handlers.CronDeleteHandler)

	// 10. PHP Versions & Management
	mux.HandleFunc("/php/list-versions", handlers.PhpListVersionsHandler)
	mux.HandleFunc("/php/install", handlers.PhpInstallHandler)
	mux.HandleFunc("/php/switch-version", handlers.PhpSwitchVersionHandler)
	mux.HandleFunc("/php/list-extensions", handlers.PhpListExtensionsHandler)
	mux.HandleFunc("/php/install-extension", handlers.PhpInstallExtensionHandler)
	mux.HandleFunc("/php/extensions", handlers.PhpExtensionToggleHandler)
	mux.HandleFunc("/php/config", handlers.PhpConfigHandler)
	mux.HandleFunc("/php/ini", handlers.PhpRawIniHandler)

	// Node.js & PM2 Management
	mux.HandleFunc("/node/version", handlers.NodeVersionHandler)
	mux.HandleFunc("/node/list", handlers.NodeListHandler)
	mux.HandleFunc("/node/app-start", handlers.NodeAppStartHandler)
	mux.HandleFunc("/node/app-restart", handlers.NodeAppRestartHandler)
	mux.HandleFunc("/node/app-stop", handlers.NodeAppStopHandler)
	mux.HandleFunc("/node/app-delete", handlers.NodeAppDeleteHandler)
	mux.HandleFunc("/node/app-logs", handlers.NodeAppLogsHandler)

	// 11. System Services Management
	mux.HandleFunc("/services/list", handlers.ServicesListHandler)
	mux.HandleFunc("/services/action", handlers.ServiceActionHandler)

	// Git & Auto-Deployment
	mux.HandleFunc("/git/status", handlers.GitStatusHandler)
	mux.HandleFunc("/git/clone", handlers.GitCloneHandler)
	mux.HandleFunc("/git/pull", handlers.GitPullHandler)
	mux.HandleFunc("/git/log", handlers.GitLogHandler)

	// 12. Backups
	mux.HandleFunc("/backup/create", handlers.BackupCreateHandler)
	mux.HandleFunc("/backup/progress", handlers.BackupProgressHandler)
	mux.HandleFunc("/backup/list", handlers.BackupListHandler)
	mux.HandleFunc("/backup/restore", handlers.BackupRestoreHandler)
	mux.HandleFunc("/backup/delete", handlers.BackupDeleteHandler)
	mux.HandleFunc("/backup/download", handlers.BackupDownloadHandler)
	mux.HandleFunc("/backup/browse", handlers.BackupBrowseHandler)
	mux.HandleFunc("/backup/restore-files", handlers.BackupRestoreFilesHandler)
	mux.HandleFunc("/backup/cloud-test", handlers.BackupCloudTestHandler)
	mux.HandleFunc("/backup/cloud-sync", handlers.BackupCloudSyncHandler)

	// Default fallback handler for any remaining endpoints
	mux.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		log.Printf("[INFO] Agent received request: %s %s", r.Method, r.URL.Path)
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":  "ok",
			"message": "Handled by open-source agent",
		})
	})

	// Wrap with HMAC authentication middleware
	handlerWithAuth := auth.Middleware(cfg.Secret, mux)

	addr := fmt.Sprintf("%s:%d", cfg.ListenAddr, cfg.Port)
	log.Printf("[OK] Open-Source RealPanel Agent listening on %s (HMAC enabled)", addr)

	if err := http.ListenAndServe(addr, handlerWithAuth); err != nil {
		log.Fatalf("[FATAL] Agent failed to start: %v", err)
	}
}
