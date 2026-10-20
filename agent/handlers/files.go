package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"

	"realpanel-agent-oss/system"
)

type FileItem struct {
	Name        string `json:"name"`
	Path        string `json:"path"`
	IsDirectory bool   `json:"is_directory"`
	Size        int64  `json:"size"`
	Permissions string `json:"permissions"`
	Modified    string `json:"modified"`
}

type FileReq struct {
	Username    string `json:"username"`
	Path        string `json:"path"`
	Content     string `json:"content,omitempty"`
	NewPath     string `json:"new_path,omitempty"`
	Mode        string `json:"mode,omitempty"`
	Destination string `json:"destination,omitempty"`
}

func sanitizeUserPath(username, relPath string) string {
	base := fmt.Sprintf("/home/%s", username)
	cleanRel := filepath.Clean("/" + strings.TrimPrefix(relPath, "/"))
	full := filepath.Join(base, cleanRel)
	// Prevent path traversal outside /home/<username>
	if !strings.HasPrefix(full, base) {
		return base
	}
	return full
}

func FileListHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	targetDir := sanitizeUserPath(req.Username, req.Path)
	entries, err := os.ReadDir(targetDir)
	if err != nil {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status": "ok",
			"files":  []FileItem{},
		})
		return
	}

	var files []FileItem
	for _, entry := range entries {
		info, err := entry.Info()
		if err != nil {
			continue
		}
		rel := filepath.Join(req.Path, entry.Name())
		files = append(files, FileItem{
			Name:        entry.Name(),
			Path:        rel,
			IsDirectory: entry.IsDir(),
			Size:        info.Size(),
			Permissions: info.Mode().String(),
			Modified:    info.ModTime().Format("2006-01-02 15:04:05"),
		})
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"files":  files,
	})
}

func FileReadHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	fullPath := sanitizeUserPath(req.Username, req.Path)
	data, err := os.ReadFile(fullPath)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"content": string(data),
	})
}

func FileWriteHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	fullPath := sanitizeUserPath(req.Username, req.Path)
	err := os.WriteFile(fullPath, []byte(req.Content), 0644)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}
	system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), fullPath)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FileDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	fullPath := sanitizeUserPath(req.Username, req.Path)
	base := fmt.Sprintf("/home/%s", req.Username)
	// Do not allow deleting home directory root
	if fullPath == base {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Cannot delete root home directory"})
		return
	}

	os.RemoveAll(fullPath)
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FileMkdirHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	fullPath := sanitizeUserPath(req.Username, req.Path)
	os.MkdirAll(fullPath, 0755)
	system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), fullPath)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FileRenameHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	src := sanitizeUserPath(req.Username, req.Path)
	dst := sanitizeUserPath(req.Username, req.NewPath)
	os.Rename(src, dst)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FileChmodHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	fullPath := sanitizeUserPath(req.Username, req.Path)
	if mode, err := strconv.ParseUint(req.Mode, 8, 32); err == nil {
		os.Chmod(fullPath, os.FileMode(mode))
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func FileArchiveHandler(w http.ResponseWriter, r *http.Request) {
	var req FileReq
	json.NewDecoder(r.Body).Decode(&req)

	src := sanitizeUserPath(req.Username, req.Path)
	dst := sanitizeUserPath(req.Username, req.Destination)

	system.ExecCommand("tar", "-czf", dst, "-C", filepath.Dir(src), filepath.Base(src))
	system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), dst)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

type DiskBreakdownReq struct {
	Username string   `json:"username"`
	Domains  []string `json:"domains"`
}

type DiskFolderItem struct {
	Name      string  `json:"name"`
	Path      string  `json:"path"`
	SizeBytes int64   `json:"size_bytes"`
	SizeMB    float64 `json:"size_mb"`
	Category  string  `json:"category"`
}

func getPathSizeBytes(path string) int64 {
	if _, err := os.Stat(path); err != nil {
		return 0
	}
	out, err := system.ExecCommand("du", "-sb", path)
	if err == nil {
		parts := strings.Fields(out)
		if len(parts) > 0 {
			if b, errConv := strconv.ParseInt(parts[0], 10, 64); errConv == nil {
				return b
			}
		}
	}
	// Fallback to Walk
	var total int64
	filepath.Walk(path, func(_ string, info os.FileInfo, err error) error {
		if err == nil && !info.IsDir() {
			total += info.Size()
		}
		return nil
	})
	return total
}

// DiskBreakdownHandler analyzes disk consumption across public_html, mail, databases, logs, and trash
func DiskBreakdownHandler(w http.ResponseWriter, r *http.Request) {
	var req DiskBreakdownReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Username is required"})
		return
	}

	homeDir := fmt.Sprintf("/home/%s", req.Username)
	var items []DiskFolderItem

	// 1. Home root top-level directories
	entries, err := os.ReadDir(homeDir)
	var homeTotalBytes int64
	if err == nil {
		for _, entry := range entries {
			fullPath := filepath.Join(homeDir, entry.Name())
			sz := getPathSizeBytes(fullPath)
			homeTotalBytes += sz
			cat := "other"
			if entry.Name() == "public_html" || strings.HasSuffix(entry.Name(), ".com") || strings.HasSuffix(entry.Name(), ".net") || strings.HasSuffix(entry.Name(), ".org") {
				cat = "web"
			} else if entry.Name() == ".trash" || entry.Name() == "trash" {
				cat = "trash"
			} else if entry.Name() == "logs" || entry.Name() == ".logs" {
				cat = "logs"
			} else if entry.Name() == "backups" {
				cat = "backups"
			} else if entry.Name() == "mail" {
				cat = "mail"
			}

			items = append(items, DiskFolderItem{
				Name:      entry.Name(),
				Path:      fullPath,
				SizeBytes: sz,
				SizeMB:    float64(sz) / (1024 * 1024),
				Category:  cat,
			})
		}
	}

	// 2. Email storage in /var/mail/vdomains/{domain}
	var mailTotalBytes int64
	for _, domain := range req.Domains {
		domainMailPath := filepath.Join("/var/mail/vdomains", domain)
		if sz := getPathSizeBytes(domainMailPath); sz > 0 {
			mailTotalBytes += sz
			items = append(items, DiskFolderItem{
				Name:      fmt.Sprintf("Mail: %s", domain),
				Path:      domainMailPath,
				SizeBytes: sz,
				SizeMB:    float64(sz) / (1024 * 1024),
				Category:  "mail",
			})
		}
	}

	// 3. MySQL Database storage for databases prefixed with username_
	var dbTotalBytes int64
	dbQuery := fmt.Sprintf("SELECT table_schema, SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema LIKE '%s\\_%%' OR table_schema = '%s' GROUP BY table_schema;", req.Username, req.Username)
	if out, err := system.ExecCommand("mysql", "-sN", "-e", dbQuery); err == nil && strings.TrimSpace(out) != "" {
		for _, line := range strings.Split(strings.TrimSpace(out), "\n") {
			parts := strings.Fields(line)
			if len(parts) >= 2 {
				dbName := parts[0]
				if b, errConv := strconv.ParseInt(parts[1], 10, 64); errConv == nil {
					dbTotalBytes += b
					items = append(items, DiskFolderItem{
						Name:      fmt.Sprintf("MySQL: %s", dbName),
						Path:      fmt.Sprintf("/var/lib/mysql/%s", dbName),
						SizeBytes: b,
						SizeMB:    float64(b) / (1024 * 1024),
						Category:  "database",
					})
				}
			}
		}
	}

	totalUsedBytes := homeTotalBytes + mailTotalBytes + dbTotalBytes

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":           "ok",
		"total_bytes":      totalUsedBytes,
		"total_mb":         float64(totalUsedBytes) / (1024 * 1024),
		"home_bytes":       homeTotalBytes,
		"mail_bytes":       mailTotalBytes,
		"database_bytes":   dbTotalBytes,
		"breakdown":        items,
	})
}

type DiskCleanReq struct {
	Username string `json:"username"`
	Action   string `json:"action"` // empty_trash, clear_logs, clear_tmp
}

// DiskCleanHandler executes fast maintenance cleanups
func DiskCleanHandler(w http.ResponseWriter, r *http.Request) {
	var req DiskCleanReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Username is required"})
		return
	}

	homeDir := fmt.Sprintf("/home/%s", req.Username)
	var msg string

	switch req.Action {
	case "empty_trash":
		trashDir := filepath.Join(homeDir, ".trash")
		os.RemoveAll(trashDir)
		os.MkdirAll(trashDir, 0755)
		system.ExecCommand("chown", fmt.Sprintf("%s:www-data", req.Username), trashDir)
		msg = "Trash emptied successfully."

	case "clear_logs":
		logsDir := filepath.Join(homeDir, "logs")
		if entries, err := os.ReadDir(logsDir); err == nil {
			for _, e := range entries {
				if strings.HasSuffix(e.Name(), ".log") || strings.HasSuffix(e.Name(), ".gz") {
					os.Remove(filepath.Join(logsDir, e.Name()))
				}
			}
		}
		msg = "Log archives cleared successfully."

	case "clear_tmp":
		tmpDir := filepath.Join(homeDir, "tmp")
		if entries, err := os.ReadDir(tmpDir); err == nil {
			for _, e := range entries {
				os.RemoveAll(filepath.Join(tmpDir, e.Name()))
			}
		}
		msg = "Temporary files cleared successfully."

	default:
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid cleanup action"})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": msg,
	})
}

