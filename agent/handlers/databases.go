package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"opterius-agent-oss/system"
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
