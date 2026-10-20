package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"

	"realpanel-agent-oss/system"
)

type DbCreateReq struct {
	Name string `json:"name"`
}

type DbUserCreateReq struct {
	Username string `json:"username"`
	Password string `json:"password"`
	Database string `json:"database"`
	Host     string `json:"host"`
}

type DbUserDeleteReq struct {
	Username string `json:"username"`
	Host     string `json:"host"`
}

type DbUserPassReq struct {
	Username string `json:"username"`
	Password string `json:"password"`
}

type DbInfoReq struct {
	Name string `json:"name"`
}

func DbCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req DbCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Name == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Database name is required"})
		return
	}

	query := fmt.Sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;", req.Name)
	if _, err := system.ExecCommand("mysql", "-e", query); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Database %s created", req.Name),
	})
}

func DbDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req DbCreateReq
	json.NewDecoder(r.Body).Decode(&req)

	query := fmt.Sprintf("DROP DATABASE IF EXISTS `%s`;", req.Name)
	system.ExecCommand("mysql", "-e", query)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DbUserCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req DbUserCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" || req.Password == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "username and password are required"})
		return
	}

	host := req.Host
	if host == "" {
		host = "localhost"
	}

	// Escape single quotes in password
	cleanPassword := strings.ReplaceAll(req.Password, "'", "\\'")

	var queries string
	if req.Database != "" {
		queries = fmt.Sprintf("CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s'; GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%s'; FLUSH PRIVILEGES;",
			req.Username, host, cleanPassword, req.Database, req.Username, host)
	} else {
		queries = fmt.Sprintf("CREATE USER IF NOT EXISTS '%s'@'%s' IDENTIFIED BY '%s'; FLUSH PRIVILEGES;",
			req.Username, host, cleanPassword)
	}

	if _, err := system.ExecCommand("mysql", "-e", queries); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DbUserDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req DbUserDeleteReq
	json.NewDecoder(r.Body).Decode(&req)

	host := req.Host
	if host == "" {
		host = "localhost"
	}

	query := fmt.Sprintf("DROP USER IF EXISTS '%s'@'%s'; FLUSH PRIVILEGES;", req.Username, host)
	system.ExecCommand("mysql", "-e", query)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DbUserPasswordHandler(w http.ResponseWriter, r *http.Request) {
	var req DbUserPassReq
	json.NewDecoder(r.Body).Decode(&req)

	cleanPassword := strings.ReplaceAll(req.Password, "'", "\\'")
	query := fmt.Sprintf("ALTER USER '%s'@'localhost' IDENTIFIED BY '%s'; ALTER USER '%s'@'%%' IDENTIFIED BY '%s'; FLUSH PRIVILEGES;",
		req.Username, cleanPassword, req.Username, cleanPassword)

	system.ExecCommand("mysql", "-e", query)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DbInfoHandler(w http.ResponseWriter, r *http.Request) {
	var req DbInfoReq
	json.NewDecoder(r.Body).Decode(&req)

	query := fmt.Sprintf("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '%s' GROUP BY table_schema;", req.Name)
	out, err := system.ExecCommand("mysql", "-sN", "-e", query)

	sizeMB := 0.0
	if err == nil && strings.TrimSpace(out) != "" {
		sizeMB, _ = strconv.ParseFloat(strings.TrimSpace(out), 64)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"size_mb": sizeMB,
	})
}

func DbRepairHandler(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	json.NewDecoder(r.Body).Decode(&req)

	dbName := req["name"]
	action := req["action"]
	if action == "" {
		action = "check"
	}

	system.ExecCommand("mysqlcheck", fmt.Sprintf("--%s", action), dbName)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Database %s %sed", dbName, action),
	})
}

// DbExportReq represents payload to export a MySQL database
type DbExportReq struct {
	Name     string `json:"name"`
	Username string `json:"username"`
}

// DbImportReq represents payload to import a SQL dump into a MySQL database
type DbImportReq struct {
	Name     string `json:"name"`
	Username string `json:"username"`
	FilePath string `json:"file_path"`
}

// DbUserPermsReq represents payload to update specific MySQL privileges for a user on a database
type DbUserPermsReq struct {
	Username   string   `json:"username"`
	Database   string   `json:"database"`
	Host       string   `json:"host"`
	Privileges []string `json:"privileges"` // SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, etc.
}

func isSafeDbIdentifier(name string) bool {
	if len(name) == 0 || len(name) > 64 {
		return false
	}
	for _, c := range name {
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == '_') {
			return false
		}
	}
	return true
}

// DbExportHandler exports a MySQL database to /home/{user}/backups/{name}_{timestamp}.sql.gz
func DbExportHandler(w http.ResponseWriter, r *http.Request) {
	var req DbExportReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafeDbIdentifier(req.Name) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name is required"})
		return
	}

	backupDir := fmt.Sprintf("/var/realpanel/backups/%s", req.Username)
	if req.Username != "" {
		userHome := fmt.Sprintf("/home/%s/backups", req.Username)
		if _, err := os.Stat(fmt.Sprintf("/home/%s", req.Username)); err == nil {
			backupDir = userHome
		}
	}
	os.MkdirAll(backupDir, 0755)

	dumpFileName := fmt.Sprintf("%s_%d.sql.gz", req.Name, time.Now().Unix())
	targetFile := filepath.Join(backupDir, dumpFileName)

	dumpCmd := fmt.Sprintf("mysqldump --single-transaction --quick --routines --triggers %s | gzip > %s", req.Name, targetFile)
	if _, err := system.ExecCommand("bash", "-c", dumpCmd); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("mysqldump failed: %v", err)})
		return
	}

	if req.Username != "" {
		system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), targetFile)
	}

	fi, _ := os.Stat(targetFile)
	var sizeBytes int64
	if fi != nil {
		sizeBytes = fi.Size()
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":     "ok",
		"file_name":  dumpFileName,
		"file_path":  targetFile,
		"size_bytes": sizeBytes,
		"size_mb":    fmt.Sprintf("%.2f MB", float64(sizeBytes)/(1024*1024)),
	})
}

// DbImportHandler imports a .sql or .sql.gz dump into the target database
func DbImportHandler(w http.ResponseWriter, r *http.Request) {
	var req DbImportReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafeDbIdentifier(req.Name) || req.FilePath == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name and file path are required"})
		return
	}

	if _, err := os.Stat(req.FilePath); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Dump file not found on server"})
		return
	}

	var importCmd string
	if strings.HasSuffix(req.FilePath, ".gz") {
		importCmd = fmt.Sprintf("gunzip -c %s | mysql %s", req.FilePath, req.Name)
	} else {
		importCmd = fmt.Sprintf("mysql %s < %s", req.Name, req.FilePath)
	}

	if _, err := system.ExecCommand("bash", "-c", importCmd); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("Import failed: %v", err)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Successfully imported SQL dump into %s", req.Name),
	})
}

// DbUserPermsHandler sets granular privileges for a MySQL user on a database
func DbUserPermsHandler(w http.ResponseWriter, r *http.Request) {
	var req DbUserPermsReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafeDbIdentifier(req.Username) || !isSafeDbIdentifier(req.Database) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid username and database are required"})
		return
	}

	host := req.Host
	if host == "" {
		host = "localhost"
	}

	allowedPerms := map[string]bool{
		"SELECT":          true,
		"INSERT":          true,
		"UPDATE":          true,
		"DELETE":          true,
		"CREATE":          true,
		"DROP":            true,
		"ALTER":           true,
		"INDEX":           true,
		"CREATE TEMPORARY TABLES": true,
		"LOCK TABLES":     true,
		"EXECUTE":         true,
		"CREATE VIEW":     true,
		"SHOW VIEW":       true,
		"CREATE ROUTINE":  true,
		"ALTER ROUTINE":   true,
		"EVENT":           true,
		"TRIGGER":         true,
		"ALL PRIVILEGES":  true,
	}

	var validPrivs []string
	for _, p := range req.Privileges {
		up := strings.ToUpper(strings.TrimSpace(p))
		if allowedPerms[up] {
			validPrivs = append(validPrivs, up)
		}
	}

	if len(validPrivs) == 0 {
		validPrivs = []string{"ALL PRIVILEGES"}
	}

	// Revoke all then grant selected
	revokeQuery := fmt.Sprintf("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '%s'@'%s'; FLUSH PRIVILEGES;", req.Username, host)
	system.ExecCommand("mysql", "-e", revokeQuery)

	grantQuery := fmt.Sprintf("GRANT %s ON `%s`.* TO '%s'@'%s'; FLUSH PRIVILEGES;",
		strings.Join(validPrivs, ", "), req.Database, req.Username, host)

	if _, err := system.ExecCommand("mysql", "-e", grantQuery); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":     "ok",
		"privileges": validPrivs,
		"message":    fmt.Sprintf("Privileges updated for %s@%s on %s", req.Username, host, req.Database),
	})
}
