package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"realpanel-agent-oss/system"
)

type GitStatusReq struct {
	WorkingDir string `json:"working_dir"`
	Username   string `json:"username"`
}

type GitCloneReq struct {
	WorkingDir  string `json:"working_dir"`
	Username    string `json:"username"`
	RepoURL     string `json:"repo_url"`
	Branch      string `json:"branch"`
	AccessToken string `json:"access_token"`
}

type GitPullReq struct {
	WorkingDir  string `json:"working_dir"`
	Username    string `json:"username"`
	AccessToken string `json:"access_token"`
}

type GitLogReq struct {
	WorkingDir string `json:"working_dir"`
	Username   string `json:"username"`
	Limit      int    `json:"limit"`
}

type GitDeployReq struct {
	WorkingDir string `json:"working_dir"`
	Username   string `json:"username"`
	Tasks      string `json:"tasks"`
}

// GitStatusHandler returns branch, clean/dirty state, remote url and last commit
func GitStatusHandler(w http.ResponseWriter, r *http.Request) {
	var req GitStatusReq
	json.NewDecoder(r.Body).Decode(&req)

	if req.WorkingDir == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "working_dir is required"})
		return
	}

	gitDir := filepath.Join(req.WorkingDir, ".git")
	if _, err := os.Stat(gitDir); os.IsNotExist(err) {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"initialized": false,
		})
		return
	}

	branchOut, _ := system.ExecCommand("git", "-C", req.WorkingDir, "rev-parse", "--abbrev-ref", "HEAD")
	remoteOut, _ := system.ExecCommand("git", "-C", req.WorkingDir, "config", "--get", "remote.origin.url")
	statusOut, _ := system.ExecCommand("git", "-C", req.WorkingDir, "status", "--short")

	// Last commit info
	logOut, _ := system.ExecCommand("git", "-C", req.WorkingDir, "log", "-1", "--format=%H|%an|%ad|%s")
	var lastCommit map[string]string
	logParts := strings.Split(strings.TrimSpace(logOut), "|")
	if len(logParts) >= 4 {
		lastCommit = map[string]string{
			"hash":    logParts[0][:7],
			"author":  logParts[1],
			"date":    logParts[2],
			"message": logParts[3],
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"initialized": true,
		"branch":      strings.TrimSpace(branchOut),
		"remote_url":  strings.TrimSpace(remoteOut),
		"clean":       strings.TrimSpace(statusOut) == "",
		"status":      strings.TrimSpace(statusOut),
		"last_commit": lastCommit,
	})
}

// GitCloneHandler clones a remote repo into working_dir
func GitCloneHandler(w http.ResponseWriter, r *http.Request) {
	var req GitCloneReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.WorkingDir == "" || req.RepoURL == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "working_dir and repo_url required"})
		return
	}

	os.MkdirAll(req.WorkingDir, 0755)

	repoURL := req.RepoURL
	if req.AccessToken != "" && strings.HasPrefix(repoURL, "https://") {
		// Embed access token into HTTPS URL
		repoURL = strings.Replace(repoURL, "https://", fmt.Sprintf("https://oauth2:%s@", req.AccessToken), 1)
	}

	args := []string{"clone"}
	if req.Branch != "" {
		args = append(args, "-b", req.Branch)
	}
	args = append(args, repoURL, req.WorkingDir)

	out, err := system.ExecCommand("git", args...)
	success := (err == nil)

	// Fix file ownership if username provided
	if req.Username != "" {
		system.ExecCommand("chown", "-R", fmt.Sprintf("%s:www-data", req.Username), req.WorkingDir)
	}

	// Trigger .realpanel.yml / .cpanel.yml deployment hook if present
	deployLogs := runPostDeployment(req.WorkingDir, req.Username)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":      "ok",
		"success":     success,
		"output":      out + "\n" + deployLogs,
		"deploy_logs": deployLogs,
	})
}

// GitPullHandler pulls latest commits from origin
func GitPullHandler(w http.ResponseWriter, r *http.Request) {
	var req GitPullReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.WorkingDir == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "working_dir is required"})
		return
	}

	// If access token is provided, update remote URL temporarily
	if req.AccessToken != "" {
		remoteOut, _ := system.ExecCommand("git", "-C", req.WorkingDir, "config", "--get", "remote.origin.url")
		currentRemote := strings.TrimSpace(remoteOut)
		if strings.HasPrefix(currentRemote, "https://") && !strings.Contains(currentRemote, "@") {
			authedRemote := strings.Replace(currentRemote, "https://", fmt.Sprintf("https://oauth2:%s@", req.AccessToken), 1)
			system.ExecCommand("git", "-C", req.WorkingDir, "remote", "set-url", "origin", authedRemote)
		}
	}

	out, err := system.ExecCommand("git", "-C", req.WorkingDir, "pull")
	success := (err == nil)

	if req.Username != "" {
		system.ExecCommand("chown", "-R", fmt.Sprintf("%s:www-data", req.Username), req.WorkingDir)
	}

	// Auto run deployment tasks if .realpanel.yml or .cpanel.yml exists
	deployLogs := runPostDeployment(req.WorkingDir, req.Username)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":      "ok",
		"success":     success,
		"output":      out + "\n" + deployLogs,
		"deploy_logs": deployLogs,
	})
}

// GitLogHandler returns recent commit list
func GitLogHandler(w http.ResponseWriter, r *http.Request) {
	var req GitLogReq
	json.NewDecoder(r.Body).Decode(&req)

	limit := req.Limit
	if limit <= 0 || limit > 50 {
		limit = 10
	}

	out, _ := system.ExecCommand("git", "-C", req.WorkingDir, "log", fmt.Sprintf("-%d", limit), "--format=%H|%an|%ad|%s")
	var commits []map[string]string

	lines := strings.Split(strings.TrimSpace(out), "\n")
	for _, line := range lines {
		parts := strings.Split(line, "|")
		if len(parts) >= 4 {
			hash := parts[0]
			if len(hash) > 7 {
				hash = hash[:7]
			}
			commits = append(commits, map[string]string{
				"hash":    hash,
				"author":  parts[1],
				"date":    parts[2],
				"message": parts[3],
			})
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"commits": commits,
	})
}

// runPostDeployment inspects .realpanel.yml or .cpanel.yml and executes defined tasks
func runPostDeployment(workingDir, username string) string {
	ymlPath := filepath.Join(workingDir, ".realpanel.yml")
	if _, err := os.Stat(ymlPath); os.IsNotExist(err) {
		ymlPath = filepath.Join(workingDir, ".cpanel.yml")
		if _, err := os.Stat(ymlPath); os.IsNotExist(err) {
			return ""
		}
	}

	content, err := os.ReadFile(ymlPath)
	if err != nil {
		return ""
	}

	lines := strings.Split(string(content), "\n")
	var tasks []string
	inTasks := false

	for _, l := range lines {
		trimmed := strings.TrimSpace(l)
		if strings.HasPrefix(trimmed, "tasks:") {
			inTasks = true
			continue
		}
		if inTasks {
			if strings.HasPrefix(trimmed, "- ") {
				cmd := strings.TrimPrefix(trimmed, "- ")
				tasks = append(tasks, strings.TrimSpace(cmd))
			} else if trimmed != "" && !strings.HasPrefix(trimmed, "#") && !strings.HasPrefix(l, " ") && !strings.HasPrefix(l, "\t") {
				// Exit task block
				inTasks = false
			}
		}
	}

	if len(tasks) == 0 {
		return ""
	}

	var results []string
	results = append(results, fmt.Sprintf("--- Executing Deployment Tasks from %s ---", filepath.Base(ymlPath)))

	for _, task := range tasks {
		script := fmt.Sprintf("cd %s && %s", workingDir, task)
		var out string
		if username != "" {
			out, _ = system.ExecCommand("su", "-", username, "-c", script)
		} else {
			out, _ = system.ExecCommand("bash", "-c", script)
		}
		results = append(results, fmt.Sprintf("$ %s\n%s", task, strings.TrimSpace(out)))
	}

	return strings.Join(results, "\n")
}
