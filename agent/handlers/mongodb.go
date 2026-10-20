package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"os/exec"
	"strconv"
	"strings"

	"realpanel-agent-oss/system"
)

// MongoCreateReq represents the payload to create a MongoDB database.
type MongoCreateReq struct {
	Name string `json:"name"`
}

// MongoUserCreateReq represents the payload to create a user for a MongoDB database.
type MongoUserCreateReq struct {
	Username string `json:"username"`
	Password string `json:"password"`
	Database string `json:"database"`
}

// MongoUserPassReq represents the payload to update a MongoDB user's password.
type MongoUserPassReq struct {
	Username string `json:"username"`
	Password string `json:"password"`
	Database string `json:"database"`
}

// MongoUserDeleteReq represents the payload to delete a MongoDB user.
type MongoUserDeleteReq struct {
	Username string `json:"username"`
	Database string `json:"database"`
}

// MongoInfoReq represents the payload to query MongoDB database stats.
type MongoInfoReq struct {
	Name string `json:"name"`
}

// runMongoScript executes JavaScript commands using mongosh (or mongo CLI fallback).
func runMongoScript(jsCode string) (string, error) {
	// Prefer mongosh (modern MongoDB shell), fallback to legacy mongo CLI
	cli := "mongosh"
	if _, err := exec.LookPath("mongosh"); err != nil {
		if _, errLegacy := exec.LookPath("mongo"); errLegacy == nil {
			cli = "mongo"
		} else {
			return "", fmt.Errorf("neither mongosh nor mongo shell binary is installed on server")
		}
	}

	return system.ExecCommand(cli, "--quiet", "--eval", jsCode)
}

// MongoCreateHandler handles /mongodb/create
func MongoCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Name == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Database name is required"})
		return
	}

	// MongoDB automatically creates databases when collections/data exist.
	// We initialize an initial metadata collection to ensure the DB is materialized.
	script := fmt.Sprintf(`db = db.getSiblingDB(%q); db.createCollection("_realpanel_init");`, req.Name)
	if out, err := runMongoScript(script); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("%v: %s", err, out)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("MongoDB database %s initialized", req.Name),
	})
}

// MongoUserCreateHandler handles /mongodb/user-create
func MongoUserCreateHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoUserCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" || req.Password == "" || req.Database == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Username, password, and database are required"})
		return
	}

	// Create user directly on the target database with readWrite role
	script := fmt.Sprintf(`
		db = db.getSiblingDB(%q);
		try { db.dropUser(%q); } catch(e) {}
		db.createUser({
			user: %q,
			pwd: %q,
			roles: [{ role: "readWrite", db: %q }]
		});
	`, req.Database, req.Username, req.Username, req.Password, req.Database)

	if out, err := runMongoScript(script); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("%v: %s", err, out)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("MongoDB user %s created on %s", req.Username, req.Database),
	})
}

// MongoUserPasswordHandler handles /mongodb/user-password
func MongoUserPasswordHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoUserPassReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" || req.Password == "" || req.Database == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Username, password, and database are required"})
		return
	}

	script := fmt.Sprintf(`
		db = db.getSiblingDB(%q);
		db.updateUser(%q, { pwd: %q });
	`, req.Database, req.Username, req.Password)

	if out, err := runMongoScript(script); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("%v: %s", err, out)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

// MongoUserDeleteHandler handles /mongodb/user-delete
func MongoUserDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoUserDeleteReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Username == "" || req.Database == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Username and database are required"})
		return
	}

	script := fmt.Sprintf(`
		db = db.getSiblingDB(%q);
		db.dropUser(%q);
	`, req.Database, req.Username)

	runMongoScript(script)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

// MongoDeleteHandler handles /mongodb/delete
func MongoDeleteHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoCreateReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Name == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Database name is required"})
		return
	}

	// Drop all database users and then drop database
	script := fmt.Sprintf(`
		db = db.getSiblingDB(%q);
		try { db.dropAllUsers(); } catch(e) {}
		db.dropDatabase();
	`, req.Name)

	if out, err := runMongoScript(script); err != nil {
		system.RespondJSON(w, http.StatusInternalServerError, map[string]string{"error": fmt.Sprintf("%v: %s", err, out)})
		return
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"message": fmt.Sprintf("MongoDB database %s dropped", req.Name),
	})
}

// MongoInfoHandler handles /mongodb/info
func MongoInfoHandler(w http.ResponseWriter, r *http.Request) {
	var req MongoInfoReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Name == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Database name is required"})
		return
	}

	// Fetch database stats as JSON
	script := fmt.Sprintf(`
		db = db.getSiblingDB(%q);
		const stats = db.stats();
		print(JSON.stringify({
			collections: stats.collections || 0,
			objects: stats.objects || 0,
			avgObjSize: stats.avgObjSize || 0,
			dataSize: stats.dataSize || 0,
			storageSize: stats.storageSize || 0,
			indexes: stats.indexes || 0,
			indexSize: stats.indexSize || 0
		}));
	`, req.Name)

	out, err := runMongoScript(script)
	if err != nil {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":      "ok",
			"size":        "0 MB",
			"size_mb":     0.0,
			"collections": 0,
			"documents":   0,
		})
		return
	}

	lines := strings.Split(strings.TrimSpace(out), "\n")
	lastLine := lines[len(lines)-1]

	var stats struct {
		Collections int     `json:"collections"`
		Objects     int     `json:"objects"`
		DataSize    float64 `json:"dataSize"`
		StorageSize float64 `json:"storageSize"`
		Indexes     int     `json:"indexes"`
		IndexSize   float64 `json:"indexSize"`
	}

	if err := json.Unmarshal([]byte(lastLine), &stats); err != nil {
		system.RespondJSON(w, http.StatusOK, map[string]interface{}{
			"status":      "ok",
			"size":        "0 MB",
			"size_mb":     0.0,
			"collections": 0,
			"documents":   0,
		})
		return
	}

	totalBytes := stats.StorageSize + stats.IndexSize
	sizeMB := totalBytes / (1024 * 1024)

	var formattedSize string
	if sizeMB < 1 {
		formattedSize = fmt.Sprintf("%.2f KB", totalBytes/1024)
	} else if sizeMB >= 1024 {
		formattedSize = fmt.Sprintf("%.2f GB", sizeMB/1024)
	} else {
		formattedSize = fmt.Sprintf("%.2f MB", sizeMB)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":      "ok",
		"size":        formattedSize,
		"size_mb":     strconv.FormatFloat(sizeMB, 'f', 2, 64),
		"collections": stats.Collections,
		"documents":   stats.Objects,
		"indexes":     stats.Indexes,
	})
}
