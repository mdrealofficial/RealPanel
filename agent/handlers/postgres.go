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

// PostgresCreateReq represents payload to create a PostgreSQL database
type PostgresCreateReq struct {
	Name string `json:"name"`
}

// PostgresUserCreateReq represents payload to create a PostgreSQL user
type PostgresUserCreateReq struct {
	PgUsername string `json:"pg_username"`
	Password   string `json:"password"`
	Database   string `json:"database"`
}

// PostgresUserPassReq represents payload to change a PostgreSQL user password
type PostgresUserPassReq struct {
	PgUsername string `json:"pg_username"`
	Password   string `json:"password"`
}

// PostgresUserDeleteReq represents payload to delete a PostgreSQL user
type PostgresUserDeleteReq struct {
	PgUsername string `json:"pg_username"`
}

// PostgresInfoReq represents payload to query PostgreSQL database stats
type PostgresInfoReq struct {
	Name string `json:"name"`
}

// isSafePgIdentifier checks whether a database/username is alphanumeric + underscore
func isSafePgIdentifier(name string) bool {
	if len(name) == 0 || len(name) > 63 {
		return false
	}
	for _, c := range name {
		if !((c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z') || (c >= '0' && c <= '9') || c == '_') {
			return false
		}
	}
	return true
}

func execPsql(sql string) (string, error) {
	// Execute via postgres system user
	return system.ExecCommand("su", "-", "postgres", "-c", fmt.Sprintf("psql -t -A -c %q", sql))
}

// PostgresCreateHandler handles /postgres/create
func PostgresCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.Name) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name is required"})
		return
	}

	sql := fmt.Sprintf("CREATE DATABASE \"%s\" ENCODING 'UTF8';", req.Name)
	if out, err := execPsql(sql); err != nil {
		// Ignore if already exists
		if !strings.Contains(out, "already exists") && !strings.Contains(err.Error(), "already exists") {
			system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
			return
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("PostgreSQL database %s created", req.Name),
	})
}

// PostgresDeleteHandler handles /postgres/delete
func PostgresDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.Name) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name is required"})
		return
	}

	// Terminate active connections first
	termSql := fmt.Sprintf("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '%s';", req.Name)
	execPsql(termSql)

	sql := fmt.Sprintf("DROP DATABASE IF EXISTS \"%s\";", req.Name)
	if _, err := execPsql(sql); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{"status": "ok"})
}

// PostgresUserCreateHandler handles /postgres/user-create
func PostgresUserCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresUserCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.PgUsername) || req.Password == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid pg_username and password are required"})
		return
	}

	cleanPassword := strings.ReplaceAll(req.Password, "'", "''")
	sql := fmt.Sprintf("DO $$ BEGIN IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '%s') THEN CREATE ROLE \"%s\" WITH LOGIN PASSWORD '%s'; ELSE ALTER ROLE \"%s\" WITH PASSWORD '%s'; END IF; END $$;",
		req.PgUsername, req.PgUsername, cleanPassword, req.PgUsername, cleanPassword)

	if _, err := execPsql(sql); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	if req.Database != "" && isSafePgIdentifier(req.Database) {
		grantSql := fmt.Sprintf("GRANT ALL PRIVILEGES ON DATABASE \"%s\" TO \"%s\"; ALTER DATABASE \"%s\" OWNER TO \"%s\";",
			req.Database, req.PgUsername, req.Database, req.PgUsername)
		execPsql(grantSql)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{"status": "ok"})
}

// PostgresUserPasswordHandler handles /postgres/user-password
func PostgresUserPasswordHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresUserPassReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.PgUsername) || req.Password == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid pg_username and password are required"})
		return
	}

	cleanPassword := strings.ReplaceAll(req.Password, "'", "''")
	sql := fmt.Sprintf("ALTER ROLE \"%s\" WITH PASSWORD '%s';", req.PgUsername, cleanPassword)
	if _, err := execPsql(sql); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{"status": "ok"})
}

// PostgresUserDeleteHandler handles /postgres/user-delete
func PostgresUserDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresUserDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.PgUsername) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid pg_username is required"})
		return
	}

	sql := fmt.Sprintf("DROP ROLE IF EXISTS \"%s\";", req.PgUsername)
	execPsql(sql)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{"status": "ok"})
}

// PostgresInfoHandler handles /postgres/info
func PostgresInfoHandler(w http.ResponseWriter, r *http.Request) {
	var req PostgresInfoReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.Name) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name is required"})
		return
	}

	sql := fmt.Sprintf("SELECT pg_size_pretty(pg_database_size('%s'));", req.Name)
	sizePretty, _ := execPsql(sql)

	sqlBytes := fmt.Sprintf("SELECT pg_database_size('%s');", req.Name)
	sizeBytesStr, _ := execPsql(sqlBytes)
	sizeMB := 0.0
	if bytesVal, err := strconv.ParseFloat(strings.TrimSpace(sizeBytesStr), 64); err == nil {
		sizeMB = bytesVal / (1024 * 1024)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"size":    strings.TrimSpace(sizePretty),
		"size_mb": sizeMB,
	})
}

// PostgresExportHandler dumps a PostgreSQL database using pg_dump | gzip
func PostgresExportHandler(w http.ResponseWriter, r *http.Request) {
	var req DbExportReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.Name) {
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

	dumpCmd := fmt.Sprintf("su - postgres -c \"pg_dump -F p '%s'\" | gzip > %s", req.Name, targetFile)
	if _, err := system.ExecCommand("bash", "-c", dumpCmd); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("pg_dump failed: %v", err)})
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

// PostgresImportHandler imports a .sql or .sql.gz dump into PostgreSQL using psql
func PostgresImportHandler(w http.ResponseWriter, r *http.Request) {
	var req DbImportReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || !isSafePgIdentifier(req.Name) || req.FilePath == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Valid database name and file path are required"})
		return
	}

	if _, err := os.Stat(req.FilePath); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Dump file not found on server"})
		return
	}

	var importCmd string
	if strings.HasSuffix(req.FilePath, ".gz") {
		importCmd = fmt.Sprintf("gunzip -c %s | su - postgres -c \"psql -d '%s'\"", req.FilePath, req.Name)
	} else {
		importCmd = fmt.Sprintf("su - postgres -c \"psql -d '%s' < %s\"", req.Name, req.FilePath)
	}

	if _, err := system.ExecCommand("bash", "-c", importCmd); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("Postgres import failed: %v", err)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Successfully imported SQL dump into %s", req.Name),
	})
}
