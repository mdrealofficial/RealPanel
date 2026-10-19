package handlers

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strings"

	"realpanel-agent-oss/system"
)

type NodeAppStartReq struct {
	Name       string `json:"name"`
	Command    string `json:"command"`
	WorkingDir string `json:"working_dir"`
	Port       int    `json:"port"`
	Username   string `json:"username"`
	Domain     string `json:"domain"`
}

type NodeAppActionReq struct {
	Name     string `json:"name"`
	Username string `json:"username"`
}

type NodeAppLogsReq struct {
	Name  string `json:"name"`
	Lines int    `json:"lines"`
}

func NodeVersionHandler(w http.ResponseWriter, r *http.Request) {
	nodeVer, _ := system.ExecCommand("node", "-v")
	npmVer, _ := system.ExecCommand("npm", "-v")
	pm2Ver, _ := system.ExecCommand("pm2", "-v")

	// Clean version strings
	nodeClean := strings.TrimPrefix(strings.TrimSpace(nodeVer), "v")
	npmClean := strings.TrimSpace(npmVer)
	pm2Clean := strings.TrimSpace(pm2Ver)

	if pm2Clean == "" {
		pm2Clean = "not installed"
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"node": nodeClean,
		"npm":  npmClean,
		"pm2":  pm2Clean,
	})
}

func NodeListHandler(w http.ResponseWriter, r *http.Request) {
	var req map[string]string
	json.NewDecoder(r.Body).Decode(&req)
	username := req["username"]

	out, err := system.ExecCommand("pm2", "jlist")
	if err != nil {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"apps": []interface{}{},
		})
		return
	}

	var rawApps []map[string]interface{}
	if err := json.Unmarshal([]byte(out), &rawApps); err != nil {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"apps": []interface{}{},
		})
		return
	}

	var apps []map[string]interface{}
	prefix := ""
	if username != "" {
		prefix = username + "_"
	}

	for _, app := range rawApps {
		name, _ := app["name"].(string)
		if prefix != "" && !strings.HasPrefix(name, prefix) {
			continue
		}

		pm2Env, _ := app["pm2_env"].(map[string]interface{})
		status := "stopped"
		if pm2Env != nil {
			if s, ok := pm2Env["status"].(string); ok {
				status = s
			}
		}

		monit, _ := app["monit"].(map[string]interface{})

		apps = append(apps, map[string]interface{}{
			"name":   name,
			"status": status,
			"pm_id":  app["pm_id"],
			"monit":  monit,
		})
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"apps": apps,
	})
}

func NodeAppStartHandler(w http.ResponseWriter, r *http.Request) {
	var req NodeAppStartReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Invalid request body",
		})
		return
	}

	pm2Name := fmt.Sprintf("%s_%s", req.Username, req.Name)

	log.Printf("[INFO] Starting Node.js app %s (command: %s, port: %d, cwd: %s)", pm2Name, req.Command, req.Port, req.WorkingDir)

	// Start or restart via PM2
	env := []string{fmt.Sprintf("PORT=%d", req.Port)}
	args := []string{"start", req.Command, "--name", pm2Name}
	if req.WorkingDir != "" {
		args = append(args, "--cwd", req.WorkingDir)
	}

	out, err := system.ExecCommandWithEnv(env, "pm2", args...)
	if err != nil {
		log.Printf("[ERROR] Failed to start PM2 app %s: %v. Output: %s", pm2Name, err, out)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("PM2 start failed: %v", err),
		})
		return
	}

	system.ExecCommand("pm2", "save")

	// If domain provided, configure Nginx reverse proxy to the Node.js port
	if req.Domain != "" && req.Port > 0 {
		vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
		nginxProxy := fmt.Sprintf(`server {
    listen 80;
    listen [::]:80;
    server_name %s www.%s;

    location / {
        proxy_pass http://127.0.0.1:%d;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
`, req.Domain, req.Domain, req.Port)

		os.WriteFile(vhostPath, []byte(nginxProxy), 0644)
		system.ExecCommand("nginx", "-t")
		system.ExecCommand("systemctl", "reload", "nginx")
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("App %s started on port %d", req.Name, req.Port),
	})
}

func NodeAppRestartHandler(w http.ResponseWriter, r *http.Request) {
	var req NodeAppActionReq
	json.NewDecoder(r.Body).Decode(&req)

	pm2Name := req.Name
	if req.Username != "" && !strings.HasPrefix(req.Name, req.Username+"_") {
		pm2Name = fmt.Sprintf("%s_%s", req.Username, req.Name)
	}

	out, err := system.ExecCommand("pm2", "restart", pm2Name)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("PM2 restart failed: %v (%s)", err, out),
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "App restarted",
	})
}

func NodeAppStopHandler(w http.ResponseWriter, r *http.Request) {
	var req NodeAppActionReq
	json.NewDecoder(r.Body).Decode(&req)

	pm2Name := req.Name
	if req.Username != "" && !strings.HasPrefix(req.Name, req.Username+"_") {
		pm2Name = fmt.Sprintf("%s_%s", req.Username, req.Name)
	}

	out, err := system.ExecCommand("pm2", "stop", pm2Name)
	if err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("PM2 stop failed: %v (%s)", err, out),
		})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "App stopped",
	})
}

func NodeAppDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req NodeAppActionReq
	json.NewDecoder(r.Body).Decode(&req)

	pm2Name := req.Name
	if req.Username != "" && !strings.HasPrefix(req.Name, req.Username+"_") {
		pm2Name = fmt.Sprintf("%s_%s", req.Username, req.Name)
	}

	system.ExecCommand("pm2", "delete", pm2Name)
	system.ExecCommand("pm2", "save")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": "App deleted from PM2",
	})
}

func NodeAppLogsHandler(w http.ResponseWriter, r *http.Request) {
	var req NodeAppLogsReq
	json.NewDecoder(r.Body).Decode(&req)

	lines := req.Lines
	if lines <= 0 {
		lines = 100
	}

	out, _ := system.ExecCommand("pm2", "logs", req.Name, "--lines", fmt.Sprintf("%d", lines), "--nostream")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"logs":   out,
	})
}
