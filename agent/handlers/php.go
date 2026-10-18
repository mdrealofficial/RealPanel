package handlers

import (
	"bufio"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"

	"opterius-agent-oss/system"
)

type PhpInstallReq struct {
	Version string `json:"version"`
}

type PhpSwitchReq struct {
	Domain       string `json:"domain"`
	Username     string `json:"username"`
	OldVersion   string `json:"old_version"`
	NewVersion   string `json:"new_version"`
	ParentDomain string `json:"parent_domain,omitempty"`
}

type PhpListExtReq struct {
	Version string `json:"version"`
}

type PhpExtToggleReq struct {
	Version   string `json:"version"`
	Extension string `json:"extension"`
	Enable    bool   `json:"enable"`
}

type PhpConfigReq struct {
	Domain  string            `json:"domain"`
	Version string            `json:"version"`
	Values  map[string]string `json:"values"`
}

func PhpListVersionsHandler(w http.ResponseWriter, r *http.Request) {
	supported := []string{"8.1", "8.2", "8.3", "8.4", "8.5"}
	var versions []map[string]interface{}

	for _, v := range supported {
		// Check if PHP CLI or FPM binary or directory exists
		cliBinary := fmt.Sprintf("/usr/bin/php%s", v)
		fpmBinary := fmt.Sprintf("/usr/sbin/php-fpm%s", v)
		etcDir := fmt.Sprintf("/etc/php/%s", v)
		sock := fmt.Sprintf("/run/php/php%s-fpm.sock", v)

		installed := false
		if _, err := os.Stat(cliBinary); err == nil {
			installed = true
		} else if _, err := os.Stat(fpmBinary); err == nil {
			installed = true
		} else if _, err := os.Stat(etcDir); err == nil {
			installed = true
		} else if _, err := os.Stat(sock); err == nil {
			installed = true
		}

		active := false
		if _, err := os.Stat(sock); err == nil {
			active = true
		} else if installed {
			out, _ := system.ExecCommand("systemctl", "is-active", fmt.Sprintf("php%s-fpm", v))
			if strings.TrimSpace(out) == "active" {
				active = true
			}
		}

		versions = append(versions, map[string]interface{}{
			"version":   v,
			"installed": installed,
			"active":    active,
		})
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"versions": versions,
	})
}

func PhpInstallHandler(w http.ResponseWriter, r *http.Request) {
	var req PhpInstallReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Invalid request body",
		})
		return
	}

	v := strings.TrimSpace(req.Version)
	matched, _ := regexp.MatchString(`^8\.[0-5]$`, v)
	if !matched {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("Unsupported PHP version: %s. Allowed: 8.1 - 8.5", v),
		})
		return
	}

	log.Printf("[INFO] Installing PHP %s packages via apt-get...", v)

	// Core packages guaranteed to exist for any supported PHP version
	pkgs := []string{
		fmt.Sprintf("php%s-fpm", v),
		fmt.Sprintf("php%s-cli", v),
		fmt.Sprintf("php%s-common", v),
	}

	// Extensions to install if available in repository
	candidateExts := []string{
		"mysql", "pgsql", "sqlite3", "xml", "curl", "mbstring", "zip",
		"gd", "bcmath", "intl", "readline", "soap", "imagick", "redis", "opcache",
	}

	for _, ext := range candidateExts {
		pkg := fmt.Sprintf("php%s-%s", v, ext)
		// Only include package if it exists in the distribution repository
		if _, err := system.ExecCommand("apt-cache", "show", pkg); err == nil {
			pkgs = append(pkgs, pkg)
		}
	}

	aptArgs := append([]string{"install", "-y"}, pkgs...)
	env := []string{"DEBIAN_FRONTEND=noninteractive"}
	out, err := system.ExecCommandWithEnv(env, "apt-get", aptArgs...)
	if err != nil {
		log.Printf("[ERROR] apt-get install failed for PHP %s: %v. Output: %s", v, err, out)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("Failed to install PHP %s packages: %v", v, err),
		})
		return
	}

	// Enable and restart the newly installed PHP-FPM service
	serviceName := fmt.Sprintf("php%s-fpm", v)
	system.ExecCommand("systemctl", "enable", serviceName)
	system.ExecCommand("systemctl", "restart", serviceName)

	log.Printf("[OK] PHP %s installed and service %s started successfully", v, serviceName)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("PHP %s installed successfully", v),
	})
}

func PhpSwitchVersionHandler(w http.ResponseWriter, r *http.Request) {
	var req PhpSwitchReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Invalid request body",
		})
		return
	}

	if req.Domain == "" || req.NewVersion == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "domain and new_version are required",
		})
		return
	}

	vhostPath := fmt.Sprintf("/etc/nginx/sites-available/%s.conf", req.Domain)
	content, err := os.ReadFile(vhostPath)
	if err != nil {
		system.RespondJSON(w, http.StatusNotFound, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("Nginx configuration for domain %s not found", req.Domain),
		})
		return
	}

	re := regexp.MustCompile(`fastcgi_pass unix:/run/php/php[0-9.]+-fpm\.sock;`)
	newPass := fmt.Sprintf("fastcgi_pass unix:/run/php/php%s-fpm.sock;", req.NewVersion)
	updated := re.ReplaceAllString(string(content), newPass)

	if err := os.WriteFile(vhostPath, []byte(updated), 0644); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  "Failed to write updated Nginx config",
		})
		return
	}

	if _, err := system.ExecCommand("nginx", "-t"); err != nil {
		// Roll back
		os.WriteFile(vhostPath, content, 0644)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  "Nginx syntax test failed: " + err.Error(),
		})
		return
	}

	system.ExecCommand("systemctl", "reload", "nginx")

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Domain %s switched to PHP %s", req.Domain, req.NewVersion),
	})
}

func PhpListExtensionsHandler(w http.ResponseWriter, r *http.Request) {
	var req PhpListExtReq
	json.NewDecoder(r.Body).Decode(&req)

	v := strings.TrimSpace(req.Version)
	if v == "" {
		v = "8.4"
	}

	modsDir := fmt.Sprintf("/etc/php/%s/mods-available", v)
	confDir := fmt.Sprintf("/etc/php/%s/fpm/conf.d", v)

	var extensions []map[string]interface{}
	seen := make(map[string]bool)

	// 1. Scan actual available modules in /etc/php/{v}/mods-available/
	if files, err := os.ReadDir(modsDir); err == nil {
		for _, f := range files {
			if f.IsDir() || !strings.HasSuffix(f.Name(), ".ini") {
				continue
			}
			extName := strings.TrimSuffix(f.Name(), ".ini")
			if seen[extName] {
				continue
			}
			seen[extName] = true

			// Check if symlinked / enabled in FPM conf.d
			enabled := false
			matches, _ := filepath.Glob(filepath.Join(confDir, fmt.Sprintf("*%s.ini", extName)))
			if len(matches) > 0 {
				enabled = true
			}

			extensions = append(extensions, map[string]interface{}{
				"name":      extName,
				"installed": enabled,
			})
		}
	}

	// 2. Fallback to standard core list if directory was empty
	if len(extensions) == 0 {
		standardExts := []string{
			"bcmath", "curl", "gd", "igbinary", "imagick", "intl", "mbstring",
			"mysql", "opcache", "pgsql", "readline", "redis", "soap", "sqlite3", "xml", "zip",
		}
		for _, ext := range standardExts {
			enabled := false
			matches, _ := filepath.Glob(filepath.Join(confDir, fmt.Sprintf("*%s.ini", ext)))
			if len(matches) > 0 {
				enabled = true
			}
			extensions = append(extensions, map[string]interface{}{
				"name":      ext,
				"installed": enabled,
			})
		}
	}

	sort.Slice(extensions, func(i, j int) bool {
		return extensions[i]["name"].(string) < extensions[j]["name"].(string)
	})

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":     "ok",
		"extensions": extensions,
	})
}

type PhpInstallExtReq struct {
	Version   string `json:"version"`
	Extension string `json:"extension"`
}

func PhpInstallExtensionHandler(w http.ResponseWriter, r *http.Request) {
	var req PhpInstallExtReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Invalid request body",
		})
		return
	}

	v := strings.TrimSpace(req.Version)
	ext := strings.ToLower(strings.TrimSpace(req.Extension))
	if v == "" || ext == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Version and extension name are required",
		})
		return
	}

	// Strip "php8.3-" or "php-" prefix if user typed it
	ext = strings.TrimPrefix(ext, fmt.Sprintf("php%s-", v))
	ext = strings.TrimPrefix(ext, "php-")

	pkg := fmt.Sprintf("php%s-%s", v, ext)
	log.Printf("[INFO] Installing extension package %s via apt-get...", pkg)

	env := []string{"DEBIAN_FRONTEND=noninteractive"}
	out, err := system.ExecCommandWithEnv(env, "apt-get", "install", "-y", pkg)
	if err != nil {
		log.Printf("[ERROR] apt-get install failed for %s: %v. Output: %s", pkg, err, out)
		system.RespondJSON(w, http.StatusInternalServerError, map[string]interface{}{
			"status": "error",
			"error":  fmt.Sprintf("Failed to install package %s: %v. Make sure the package exists in the repository.", pkg, err),
		})
		return
	}

	// Reload PHP-FPM service
	system.ExecCommand("systemctl", "reload", fmt.Sprintf("php%s-fpm", v))

	log.Printf("[OK] Extension %s installed successfully for PHP %s", ext, v)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Extension %s installed successfully for PHP %s", ext, v),
	})
}

func PhpExtensionToggleHandler(w http.ResponseWriter, r *http.Request) {
	var req PhpExtToggleReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
			"status": "error",
			"error":  "Invalid request body",
		})
		return
	}

	if req.Enable {
		system.ExecCommand("phpenmod", "-v", req.Version, req.Extension)
	} else {
		system.ExecCommand("phpdismod", "-v", req.Version, req.Extension)
	}

	system.ExecCommand("systemctl", "reload", fmt.Sprintf("php%s-fpm", req.Version))

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("Extension %s updated", req.Extension),
	})
}

func PhpConfigHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method == http.MethodGet {
		domain := r.URL.Query().Get("domain")
		version := r.URL.Query().Get("version")
		if version == "" {
			version = "8.4"
		}

		defaults := map[string]string{
			"memory_limit":        "256M",
			"upload_max_filesize": "64M",
			"post_max_size":       "64M",
			"max_execution_time":  "30",
			"display_errors":      "Off",
			"allow_url_fopen":     "On",
			"disable_functions":   "proc_open,popen,exec,shell_exec,system,passthru",
		}

		if domain != "" {
			overrideFile := fmt.Sprintf("/etc/php/%s/fpm/conf.d/99-domain-%s.ini", version, domain)
			if f, err := os.Open(overrideFile); err == nil {
				defer f.Close()
				scanner := bufio.NewScanner(f)
				for scanner.Scan() {
					line := strings.TrimSpace(scanner.Text())
					if strings.HasPrefix(line, ";") || !strings.Contains(line, "=") {
						continue
					}
					parts := strings.SplitN(line, "=", 2)
					key := strings.TrimSpace(parts[0])
					val := strings.TrimSpace(parts[1])
					defaults[key] = val
				}
			}
		}

		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status": "ok",
			"values": defaults,
		})
		return
	}

	if r.Method == http.MethodPost {
		var req PhpConfigReq
		if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
			system.RespondJSON(w, http.StatusBadRequest, map[string]interface{}{
				"status": "error",
				"error":  "Invalid request body",
			})
			return
		}

		v := req.Version
		if v == "" {
			v = "8.4"
		}

		if req.Domain != "" && len(req.Values) > 0 {
			overrideFile := fmt.Sprintf("/etc/php/%s/fpm/conf.d/99-domain-%s.ini", v, req.Domain)
			var lines []string
			lines = append(lines, fmt.Sprintf("; Opterius domain override for %s", req.Domain))
			for k, val := range req.Values {
				lines = append(lines, fmt.Sprintf("%s = %s", k, val))
			}
			os.WriteFile(overrideFile, []byte(strings.Join(lines, "\n")+"\n"), 0644)
			system.ExecCommand("systemctl", "reload", fmt.Sprintf("php%s-fpm", v))
		}

		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":  "ok",
			"message": "PHP configuration updated successfully",
		})
		return
	}

	system.RespondJSON(w, http.StatusMethodNotAllowed, map[string]string{"error": "Method not allowed"})
}
