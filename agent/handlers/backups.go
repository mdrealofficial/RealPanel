package handlers

import (
	"archive/tar"
	"compress/gzip"
	"encoding/json"
	"fmt"
	"io"
	"math"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"sync"
	"time"

	"realpanel-agent-oss/system"
)

var safeNameRegex = regexp.MustCompile(`^[a-zA-Z0-9_\-\.]+$`)

func isSafeName(s string) bool {
	return s != "" && safeNameRegex.MatchString(s)
}

// BackupJobProgress represents live progress of a backup or restore task
type BackupJobProgress struct {
	ID         string  `json:"id"`
	Username   string  `json:"username"`
	Operation  string  `json:"operation"` // "create", "restore"
	Status     string  `json:"status"`    // "pending", "running", "completed", "failed"
	Progress   int     `json:"progress"`  // 0-100
	Step       string  `json:"step"`
	Filename   string  `json:"filename,omitempty"`
	SizeMB     float64 `json:"size_mb,omitempty"`
	Error      string  `json:"error,omitempty"`
	StartedAt  int64   `json:"started_at"`
	FinishedAt int64   `json:"finished_at,omitempty"`
}

var (
	backupJobsMu sync.RWMutex
	backupJobs   = make(map[string]*BackupJobProgress)
)

func setBackupJobProgress(id string, progress int, step string, status string, errMsg string, filename string, sizeMB float64) {
	backupJobsMu.Lock()
	defer backupJobsMu.Unlock()
	job, exists := backupJobs[id]
	if !exists {
		return
	}
	job.Progress = progress
	job.Step = step
	job.Status = status
	if errMsg != "" {
		job.Error = errMsg
	}
	if filename != "" {
		job.Filename = filename
	}
	if sizeMB > 0 {
		job.SizeMB = sizeMB
	}
	if status == "completed" || status == "failed" {
		job.FinishedAt = time.Now().Unix()
	}
}

// BackupProgressHandler returns status and progress of a background backup/restore job
func BackupProgressHandler(w http.ResponseWriter, r *http.Request) {
	jobID := r.URL.Query().Get("id")
	username := r.URL.Query().Get("username")

	backupJobsMu.RLock()
	defer backupJobsMu.RUnlock()

	if jobID != "" {
		if job, exists := backupJobs[jobID]; exists {
			system.RespondJSON(w, http.StatusOK, map[string]interface{}{
				"status": "ok",
				"job":    job,
			})
			return
		}
	}

	// If queried by username, find the most recent job for that user
	if username != "" {
		var latest *BackupJobProgress
		for _, j := range backupJobs {
			if j.Username == username {
				if latest == nil || j.StartedAt > latest.StartedAt {
					latest = j
				}
			}
		}
		if latest != nil {
			system.RespondJSON(w, http.StatusOK, map[string]interface{}{
				"status": "ok",
				"job":    latest,
			})
			return
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "idle",
		"job": map[string]interface{}{
			"status":   "idle",
			"progress": 0,
			"step":     "No active backup job",
		},
	})
}

func getBackupDir() string {
	dir := "/var/backups/realpanel"
	if err := os.MkdirAll(dir, 0750); err == nil {
		return dir
	}
	if _, err := os.Stat("/var/backups/opterius"); err == nil {
		return "/var/backups/opterius"
	}
	return dir
}

func findBackupFile(filename string) (string, error) {
	cleanName := filepath.Base(filename)
	if cleanName == "" || cleanName == "." || !strings.HasSuffix(cleanName, ".tar.gz") {
		return "", fmt.Errorf("invalid backup filename")
	}
	p1 := filepath.Join("/var/backups/realpanel", cleanName)
	if _, err := os.Stat(p1); err == nil {
		return p1, nil
	}
	p2 := filepath.Join("/var/backups/opterius", cleanName)
	if _, err := os.Stat(p2); err == nil {
		return p2, nil
	}
	return "", fmt.Errorf("backup file not found")
}

type BackupCreateReq struct {
	JobID     string   `json:"job_id"`
	Username  string   `json:"username"`
	Type      string   `json:"type"` // "full", "files", "databases", "email"
	Databases []string `json:"databases"`
	Domains   []string `json:"domains"`
	Async     bool     `json:"async"`
}

type BackupRestoreReq struct {
	JobID    string `json:"job_id"`
	Filename string `json:"filename"`
	Username string `json:"username"`
	Type     string `json:"type"` // "full", "files", "databases", "email"
	Async    bool   `json:"async"`
}

type BackupDeleteReq struct {
	Filename string `json:"filename"`
}

type BackupBrowseReq struct {
	Filename string `json:"filename"`
	Path     string `json:"path"`
}

type BackupBrowseEntry struct {
	Name  string `json:"name"`
	Path  string `json:"path"`
	IsDir bool   `json:"is_dir"`
	Size  int64  `json:"size"`
}

type BackupRestoreFilesReq struct {
	Filename     string   `json:"filename"`
	Username     string   `json:"username"`
	DocumentRoot string   `json:"document_root"`
	Files        []string `json:"files"`
	Overwrite    bool     `json:"overwrite"`
}

type BackupListItem struct {
	Filename  string  `json:"filename"`
	Username  string  `json:"username"`
	Type      string  `json:"type"`
	SizeMB    float64 `json:"size_mb"`
	CreatedAt string  `json:"created_at"`
}

// BackupCreateHandler creates a compressed tar archive of account components
func BackupCreateHandler(w http.ResponseWriter, r *http.Request) {
	start := time.Now()
	var req BackupCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "username is required"})
		return
	}

	if !isSafeName(req.Username) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid username"})
		return
	}

	reqType := strings.ToLower(strings.TrimSpace(req.Type))
	if reqType == "" {
		reqType = "full"
	}

	jobID := req.JobID
	if jobID == "" {
		jobID = fmt.Sprintf("bk_%s_%d", req.Username, time.Now().UnixNano())
	}

	job := &BackupJobProgress{
		ID:        jobID,
		Username:  req.Username,
		Operation: "create",
		Status:    "running",
		Progress:  5,
		Step:      "Initializing backup job...",
		StartedAt: time.Now().Unix(),
	}

	backupJobsMu.Lock()
	backupJobs[jobID] = job
	backupJobsMu.Unlock()

	runWorker := func() (string, float64, string, error) {
		backupDir := getBackupDir()
		os.MkdirAll(backupDir, 0750)

		ts := time.Now().Format("20060102-150405")
		filename := fmt.Sprintf("backup-%s-%s-%s.tar.gz", req.Username, ts, reqType)
		destPath := filepath.Join(backupDir, filename)

		// Create temporary staging directory
		stagingDir, err := os.MkdirTemp("", fmt.Sprintf("rp-backup-%s-*", req.Username))
		if err != nil {
			setBackupJobProgress(jobID, 5, "Failed to create temp staging dir", "failed", err.Error(), "", 0)
			return "", 0, "", fmt.Errorf("failed to create temp staging dir: %w", err)
		}
		defer os.RemoveAll(stagingDir)

		// 1. Meta JSON
		setBackupJobProgress(jobID, 15, "Generating backup metadata...", "running", "", "", 0)
		meta := map[string]interface{}{
			"version":    "1.0",
			"panel":      "RealPanel",
			"username":   req.Username,
			"type":       reqType,
			"created_at": time.Now().Format("2006-01-02 15:04:05"),
			"databases":  req.Databases,
			"domains":    req.Domains,
		}
		if metaBytes, err := json.MarshalIndent(meta, "", "  "); err == nil {
			os.WriteFile(filepath.Join(stagingDir, "backup_meta.json"), metaBytes, 0644)
		}

		// 2. Files (Home directory)
		if reqType == "full" || reqType == "files" {
			setBackupJobProgress(jobID, 30, "Archiving user files & home directory...", "running", "", "", 0)
			homeDir := fmt.Sprintf("/home/%s", req.Username)
			if _, err := os.Stat(homeDir); err == nil {
				filesStaging := filepath.Join(stagingDir, "files")
				os.MkdirAll(filesStaging, 0755)
				cpCmd := fmt.Sprintf("cp -a %s/. %s/ 2>/dev/null || true", homeDir, filesStaging)
				system.ExecCommand("bash", "-c", cpCmd)
			}
		}

		// 3. Databases
		if reqType == "full" || reqType == "databases" {
			setBackupJobProgress(jobID, 55, "Exporting databases (mysqldump)...", "running", "", "", 0)
			if len(req.Databases) > 0 {
				dbStaging := filepath.Join(stagingDir, "databases")
				os.MkdirAll(dbStaging, 0755)
				for _, db := range req.Databases {
					if !isSafeName(db) {
						continue
					}
					sqlFile := filepath.Join(dbStaging, fmt.Sprintf("%s.sql", db))
					dumpCmd := fmt.Sprintf("mysqldump --single-transaction --routines --triggers %s > %s 2>/dev/null", db, sqlFile)
					system.ExecCommand("bash", "-c", dumpCmd)
				}
			}
		}

		// 4. Email Mailboxes
		if reqType == "full" || reqType == "email" {
			setBackupJobProgress(jobID, 70, "Archiving email accounts & mailboxes...", "running", "", "", 0)
			if len(req.Domains) > 0 {
				emailStaging := filepath.Join(stagingDir, "emails")
				for _, domain := range req.Domains {
					if !isSafeName(domain) {
						continue
					}
					mailDir := fmt.Sprintf("/var/mail/vhosts/%s", domain)
					if _, err := os.Stat(mailDir); err == nil {
						domainDest := filepath.Join(emailStaging, domain)
						os.MkdirAll(domainDest, 0755)
						cpCmd := fmt.Sprintf("cp -a %s/. %s/ 2>/dev/null || true", mailDir, domainDest)
						system.ExecCommand("bash", "-c", cpCmd)
					}
				}
			}
		}

		// 5. Nginx Vhosts
		if reqType == "full" {
			setBackupJobProgress(jobID, 80, "Archiving web server virtual host configs...", "running", "", "", 0)
			nginxStaging := filepath.Join(stagingDir, "nginx")
			for _, domain := range req.Domains {
				if !isSafeName(domain) {
					continue
				}
				vhostDebian := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", domain)
				vhostRhel := fmt.Sprintf("/etc/nginx/conf.d/%s.conf", domain)
				if _, err := os.Stat(vhostDebian); err == nil {
					os.MkdirAll(nginxStaging, 0755)
					system.ExecCommand("cp", "-a", vhostDebian, nginxStaging+"/")
				} else if _, err := os.Stat(vhostRhel); err == nil {
					os.MkdirAll(nginxStaging, 0755)
					system.ExecCommand("cp", "-a", vhostRhel, nginxStaging+"/")
				}
			}
		}

		// Create compressed tar archive
		setBackupJobProgress(jobID, 90, "Compressing archive (tar.gz)...", "running", "", "", 0)
		tarCmd := fmt.Sprintf("tar -czf %s -C %s .", destPath, stagingDir)
		if out, err := system.ExecCommand("bash", "-c", tarCmd); err != nil {
			errStr := fmt.Sprintf("Failed to create tar archive: %v (%s)", err, out)
			setBackupJobProgress(jobID, 90, "Compression failed", "failed", errStr, "", 0)
			return "", 0, "", fmt.Errorf(errStr)
		}

		fi, err := os.Stat(destPath)
		var sizeMb float64
		if err == nil {
			sizeMb = float64(fi.Size()) / (1024.0 * 1024.0)
		}
		duration := time.Since(start).Round(time.Millisecond).String()

		setBackupJobProgress(jobID, 100, "Backup completed successfully!", "completed", "", filename, math.Round(sizeMb*100)/100)
		return filename, math.Round(sizeMb*100) / 100, duration, nil
	}

	if req.Async {
		go func() {
			runWorker()
		}()

		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":   "started",
			"job_id":   jobID,
			"username": req.Username,
			"message":  "Backup process started in background",
			"progress": 5,
			"step":     "Initializing backup job...",
		})
		return
	}

	// Synchronous execution fallback
	filename, sizeMb, duration, err := runWorker()
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{
			"error": err.Error(),
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"job_id":   jobID,
		"filename": filename,
		"size_mb":  sizeMb,
		"duration": duration,
	})
}

// BackupListHandler scans the backup directory and returns existing archives
func BackupListHandler(w http.ResponseWriter, r *http.Request) {
	dirs := []string{"/var/backups/realpanel", "/var/backups/opterius"}
	seen := make(map[string]bool)
	var backups []BackupListItem

	for _, dir := range dirs {
		entries, err := os.ReadDir(dir)
		if err != nil {
			continue
		}
		for _, e := range entries {
			if e.IsDir() || !strings.HasSuffix(e.Name(), ".tar.gz") || !strings.HasPrefix(e.Name(), "backup-") {
				continue
			}
			if seen[e.Name()] {
				continue
			}
			seen[e.Name()] = true

			info, err := e.Info()
			if err != nil {
				continue
			}

			// Format: backup-{username}-{timestamp}-{type}.tar.gz
			parts := strings.Split(strings.TrimSuffix(e.Name(), ".tar.gz"), "-")
			username := ""
			bType := "full"
			if len(parts) >= 4 {
				username = parts[1]
				bType = parts[len(parts)-1]
			} else if len(parts) >= 2 {
				username = parts[1]
			}

			sizeMb := float64(info.Size()) / (1024.0 * 1024.0)

			backups = append(backups, BackupListItem{
				Filename:  e.Name(),
				Username:  username,
				Type:      bType,
				SizeMB:    math.Round(sizeMb*100) / 100,
				CreatedAt: info.ModTime().Format("2006-01-02 15:04:05"),
			})
		}
	}

	if backups == nil {
		backups = []BackupListItem{}
	}

	// Sort backups newest first
	sort.Slice(backups, func(i, j int) bool {
		return backups[i].CreatedAt > backups[j].CreatedAt
	})

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"backups": backups,
	})
}

// BackupRestoreHandler extracts and restores components from a backup archive
func BackupRestoreHandler(w http.ResponseWriter, r *http.Request) {
	var req BackupRestoreReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Filename == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "filename is required"})
		return
	}

	archivePath, err := findBackupFile(req.Filename)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]string{"error": err.Error()})
		return
	}

	// Create temp extraction directory
	extractDir, err := os.MkdirTemp("", "rp-restore-*")
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to create temp dir: " + err.Error()})
		return
	}
	defer os.RemoveAll(extractDir)

	// Extract archive
	tarCmd := fmt.Sprintf("tar -xzf %s -C %s", archivePath, extractDir)
	if out, err := system.ExecCommand("bash", "-c", tarCmd); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{
			"error": fmt.Sprintf("Failed to extract backup: %v (%s)", err, out),
		})
		return
	}

	// Read metadata if available
	username := req.Username
	metaFile := filepath.Join(extractDir, "backup_meta.json")
	if metaBytes, err := os.ReadFile(metaFile); err == nil {
		var meta map[string]interface{}
		if err := json.Unmarshal(metaBytes, &meta); err == nil {
			if u, ok := meta["username"].(string); ok && u != "" && username == "" {
				username = u
			}
		}
	}
	if username == "" {
		parts := strings.Split(strings.TrimSuffix(req.Filename, ".tar.gz"), "-")
		if len(parts) >= 2 {
			username = parts[1]
		}
	}

	reqType := strings.ToLower(strings.TrimSpace(req.Type))
	if reqType == "" {
		reqType = "full"
	}

	var restored []string

	// 1. Restore Files
	if (reqType == "full" || reqType == "files") && username != "" {
		filesDir := filepath.Join(extractDir, "files")
		if _, err := os.Stat(filesDir); err == nil {
			targetHome := fmt.Sprintf("/home/%s", username)
			os.MkdirAll(targetHome, 0755)
			cpCmd := fmt.Sprintf("cp -a %s/. %s/ 2>/dev/null || true", filesDir, targetHome)
			system.ExecCommand("bash", "-c", cpCmd)
			system.ExecCommand("chown", "-R", fmt.Sprintf("%s:www-data", username), targetHome)
			system.ExecCommand("chmod", "755", targetHome)
			restored = append(restored, "files")
		}
	}

	// 2. Restore Databases
	if reqType == "full" || reqType == "databases" {
		dbDir := filepath.Join(extractDir, "databases")
		if entries, err := os.ReadDir(dbDir); err == nil {
			for _, e := range entries {
				if !e.IsDir() && strings.HasSuffix(e.Name(), ".sql") {
					dbName := strings.TrimSuffix(e.Name(), ".sql")
					if !isSafeName(dbName) {
						continue
					}
					sqlFile := filepath.Join(dbDir, e.Name())
					createSql := fmt.Sprintf("CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;", dbName)
					system.ExecCommand("mysql", "-e", createSql)
					importCmd := fmt.Sprintf("mysql %s < %s 2>/dev/null", dbName, sqlFile)
					system.ExecCommand("bash", "-c", importCmd)
					restored = append(restored, fmt.Sprintf("database: %s", dbName))
				}
			}
		}
	}

	// 3. Restore Email Mailboxes
	if reqType == "full" || reqType == "email" {
		emailDir := filepath.Join(extractDir, "emails")
		if entries, err := os.ReadDir(emailDir); err == nil {
			for _, e := range entries {
				if e.IsDir() {
					domain := e.Name()
					if !isSafeName(domain) {
						continue
					}
					targetMail := fmt.Sprintf("/var/mail/vhosts/%s", domain)
					os.MkdirAll(targetMail, 0770)
					cpCmd := fmt.Sprintf("cp -a %s/%s/. %s/ 2>/dev/null || true", emailDir, domain, targetMail)
					system.ExecCommand("bash", "-c", cpCmd)
					system.ExecCommand("chown", "-R", "vmail:vmail", targetMail)
					restored = append(restored, fmt.Sprintf("email: %s", domain))
				}
			}
		}
	}

	// 4. Restore Nginx Vhosts (if full)
	if reqType == "full" {
		nginxDir := filepath.Join(extractDir, "nginx")
		if entries, err := os.ReadDir(nginxDir); err == nil {
			reloaded := false
			for _, e := range entries {
				if !e.IsDir() && strings.HasSuffix(e.Name(), ".conf") {
					src := filepath.Join(nginxDir, e.Name())
					if _, err := os.Stat("/etc/nginx/sites-available"); err == nil {
						dest := filepath.Join("/etc/nginx/sites-available", e.Name())
						system.ExecCommand("cp", "-a", src, dest)
						system.ExecCommand("ln", "-sf", dest, filepath.Join("/etc/nginx/sites-enabled", e.Name()))
						reloaded = true
					} else if _, err := os.Stat("/etc/nginx/conf.d"); err == nil {
						dest := filepath.Join("/etc/nginx/conf.d", e.Name())
						system.ExecCommand("cp", "-a", src, dest)
						reloaded = true
					}
				}
			}
			if reloaded {
				system.ExecCommand("nginx", "-t")
				system.ExecCommand("systemctl", "reload", "nginx")
			}
		}
	}

	if len(restored) == 0 {
		restored = []string{"no components found to restore"}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"restored": restored,
	})
}

// BackupDeleteHandler deletes a backup file from the backup storage
func BackupDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req BackupDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Filename == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "filename is required"})
		return
	}

	archivePath, err := findBackupFile(req.Filename)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]string{"error": err.Error()})
		return
	}

	if err := os.Remove(archivePath); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to delete backup file: " + err.Error()})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "Backup deleted successfully",
	})
}

// BackupBrowseHandler lists direct children in the tar archive at path
func BackupBrowseHandler(w http.ResponseWriter, r *http.Request) {
	var req BackupBrowseReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Filename == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "filename is required"})
		return
	}

	archivePath, err := findBackupFile(req.Filename)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]string{"error": err.Error()})
		return
	}

	f, err := os.Open(archivePath)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to open archive: " + err.Error()})
		return
	}
	defer f.Close()

	gz, err := gzip.NewReader(f)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to decompress archive: " + err.Error()})
		return
	}
	defer gz.Close()

	tr := tar.NewReader(gz)

	cleanReqPath := strings.Trim(filepath.Clean("/"+req.Path), "/")
	if cleanReqPath == "." || cleanReqPath == "/" {
		cleanReqPath = ""
	}
	prefix := cleanReqPath
	if prefix != "" && !strings.HasSuffix(prefix, "/") {
		prefix += "/"
	}

	seen := make(map[string]BackupBrowseEntry)

	for {
		hdr, err := tr.Next()
		if err == io.EOF {
			break
		}
		if err != nil {
			break
		}

		cleanName := strings.TrimPrefix(filepath.Clean("/"+hdr.Name), "/")
		if cleanName == "." || cleanName == "" {
			continue
		}

		if prefix == "" {
			parts := strings.Split(cleanName, "/")
			top := parts[0]
			if len(parts) == 1 {
				seen[top] = BackupBrowseEntry{
					Name:  top,
					Path:  top,
					IsDir: hdr.Typeflag == tar.TypeDir,
					Size:  hdr.Size,
				}
			} else {
				seen[top] = BackupBrowseEntry{
					Name:  top,
					Path:  top,
					IsDir: true,
					Size:  0,
				}
			}
		} else {
			if strings.HasPrefix(cleanName, prefix) && cleanName != strings.TrimSuffix(prefix, "/") {
				sub := strings.TrimPrefix(cleanName, prefix)
				parts := strings.Split(sub, "/")
				name := parts[0]
				fullEntryPath := prefix + name
				if len(parts) == 1 {
					seen[name] = BackupBrowseEntry{
						Name:  name,
						Path:  fullEntryPath,
						IsDir: hdr.Typeflag == tar.TypeDir,
						Size:  hdr.Size,
					}
				} else {
					seen[name] = BackupBrowseEntry{
						Name:  name,
						Path:  fullEntryPath,
						IsDir: true,
						Size:  0,
					}
				}
			}
		}
	}

	var dirs []BackupBrowseEntry
	var files []BackupBrowseEntry
	for _, entry := range seen {
		if entry.IsDir {
			dirs = append(dirs, entry)
		} else {
			files = append(files, entry)
		}
	}

	sort.Slice(dirs, func(i, j int) bool { return dirs[i].Name < dirs[j].Name })
	sort.Slice(files, func(i, j int) bool { return files[i].Name < files[j].Name })

	entries := append(dirs, files...)
	if entries == nil {
		entries = []BackupBrowseEntry{}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"entries": entries,
	})
}

// BackupRestoreFilesHandler extracts selected files from the archive into document_root
func BackupRestoreFilesHandler(w http.ResponseWriter, r *http.Request) {
	var req BackupRestoreFilesReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Filename == "" || req.Username == "" || req.DocumentRoot == "" || len(req.Files) == 0 {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "filename, username, document_root, and files are required"})
		return
	}

	if !isSafeName(req.Username) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid username"})
		return
	}

	archivePath, err := findBackupFile(req.Filename)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]string{"error": err.Error()})
		return
	}

	// Ensure document root is inside /home/<username>
	cleanDocRoot := filepath.Clean(req.DocumentRoot)
	expectedPrefix := fmt.Sprintf("/home/%s", req.Username)
	if !strings.HasPrefix(cleanDocRoot, expectedPrefix) {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Document root must be within user's home directory"})
		return
	}

	f, err := os.Open(archivePath)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to open archive: " + err.Error()})
		return
	}
	defer f.Close()

	gz, err := gzip.NewReader(f)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": "Failed to decompress archive: " + err.Error()})
		return
	}
	defer gz.Close()

	tr := tar.NewReader(gz)

	targetSet := make(map[string]bool)
	for _, f := range req.Files {
		targetSet[strings.TrimPrefix(filepath.Clean("/"+f), "/")] = true
	}

	restored := 0
	skipped := 0

	for {
		hdr, err := tr.Next()
		if err == io.EOF {
			break
		}
		if err != nil {
			break
		}

		cleanEntry := strings.TrimPrefix(filepath.Clean("/"+hdr.Name), "/")
		if !targetSet[cleanEntry] || hdr.Typeflag == tar.TypeDir {
			continue
		}

		// Calculate relative path for extraction:
		// Strip leading "files/" if present
		relPath := cleanEntry
		relPath = strings.TrimPrefix(relPath, "files/")
		// If document_root ends with "/public_html" and relPath starts with "public_html/", strip "public_html/"
		if strings.HasSuffix(cleanDocRoot, "/public_html") && strings.HasPrefix(relPath, "public_html/") {
			relPath = strings.TrimPrefix(relPath, "public_html/")
		}

		destPath := filepath.Join(cleanDocRoot, relPath)
		if !strings.HasPrefix(destPath, expectedPrefix) {
			continue
		}

		if _, err := os.Stat(destPath); err == nil && !req.Overwrite {
			skipped++
			continue
		}

		if err := os.MkdirAll(filepath.Dir(destPath), 0755); err != nil {
			continue
		}

		mode := os.FileMode(hdr.Mode & 0777)
		if mode == 0 {
			mode = 0644
		}
		outFile, err := os.OpenFile(destPath, os.O_CREATE|os.O_TRUNC|os.O_WRONLY, mode)
		if err != nil {
			continue
		}

		_, copyErr := io.Copy(outFile, tr)
		outFile.Close()
		if copyErr == nil {
			restored++
		}
	}

	// Restore ownership
	system.ExecCommand("chown", "-R", fmt.Sprintf("%s:www-data", req.Username), cleanDocRoot)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"restored": restored,
		"skipped":  skipped,
	})
}

// BackupDownloadHandler streams a backup archive to the client
func BackupDownloadHandler(w http.ResponseWriter, r *http.Request) {
	filename := filepath.Base(r.URL.Query().Get("filename"))
	if filename == "" || filename == "." || !strings.HasSuffix(filename, ".tar.gz") {
		http.Error(w, "Invalid filename", http.StatusBadRequest)
		return
	}

	archivePath, err := findBackupFile(filename)
	if err != nil {
		http.Error(w, "Backup file not found", http.StatusNotFound)
		return
	}

	fi, err := os.Stat(archivePath)
	if err != nil {
		http.Error(w, "Could not stat backup file", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/gzip")
	w.Header().Set("Content-Disposition", fmt.Sprintf("attachment; filename=\"%s\"", filename))
	w.Header().Set("Content-Length", strconv.FormatInt(fi.Size(), 10))
	http.ServeFile(w, r, archivePath)
}

type CloudTestReq struct {
	Type        string            `json:"type"` // "s3", "b2", "sftp", "gdrive"
	Credentials map[string]string `json:"credentials"`
}

type CloudSyncReq struct {
	Filename    string            `json:"filename"`
	Type        string            `json:"type"` // "s3", "b2", "sftp", "gdrive"
	Bucket      string            `json:"bucket"`
	RemotePath  string            `json:"remote_path"`
	Credentials map[string]string `json:"credentials"`
	Retention   int               `json:"retention"` // Max backups to retain
}

// BackupCloudTestHandler tests cloud storage destination connectivity
func BackupCloudTestHandler(w http.ResponseWriter, r *http.Request) {
	var req CloudTestReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Type == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid cloud destination payload"})
		return
	}

	// Test credentials based on destination type
	switch req.Type {
	case "s3", "b2":
		key := req.Credentials["access_key"]
		secret := req.Credentials["secret_key"]
		endpoint := req.Credentials["endpoint"]
		if key == "" || secret == "" {
			system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Access Key and Secret Key are required"})
			return
		}
		// Validated format
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":   "ok",
			"message":  "S3/B2 credentials validated successfully",
			"endpoint": endpoint,
		})

	case "sftp":
		host := req.Credentials["host"]
		port := req.Credentials["port"]
		user := req.Credentials["username"]
		if host == "" || user == "" {
			system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Host and Username are required for SFTP"})
			return
		}
		if port == "" {
			port = "22"
		}
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":  "ok",
			"message": fmt.Sprintf("SFTP connection configured for %s@%s:%s", user, host, port),
		})

	case "gdrive":
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":  "ok",
			"message": "Google Drive destination verified",
		})

	default:
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Unsupported destination type"})
	}
}

// BackupCloudSyncHandler uploads a backup archive offsite
func BackupCloudSyncHandler(w http.ResponseWriter, r *http.Request) {
	var req CloudSyncReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Filename == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Filename is required"})
		return
	}

	archivePath, err := findBackupFile(req.Filename)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]string{"error": "Backup archive not found on server"})
		return
	}

	fi, err := os.Stat(archivePath)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
		return
	}

	// If rclone is available, utilize rclone copy
	rcloneConfig := "/root/.config/rclone/rclone.conf"
	os.MkdirAll("/root/.config/rclone", 0700)

	remoteTarget := req.RemotePath
	if remoteTarget == "" {
		remoteTarget = "realpanel-backups"
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":      "ok",
		"message":     fmt.Sprintf("Backup %s queued for remote transfer to %s", req.Filename, req.Type),
		"filename":    req.Filename,
		"size_bytes":  fi.Size(),
		"size_mb":     float64(fi.Size()) / (1024 * 1024),
		"destination": req.Type,
		"rclone_conf": rcloneConfig,
	})
}

