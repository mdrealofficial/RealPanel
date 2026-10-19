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
