package handlers

import (
	"net/http"
	"os"
	"runtime"
	"strconv"
	"strings"

	"realpanel-agent-oss/system"
)

type ProcessInfo struct {
	User       string  `json:"user"`
	PID        int     `json:"pid"`
	CPUPercent float64 `json:"cpu_percent"`
	MemPercent float64 `json:"mem_percent"`
	Command    string  `json:"command"`
}

func HealthHandler(w http.ResponseWriter, r *http.Request) {
	hostname, _ := os.Hostname()
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":   "ok",
		"os":       "ubuntu",
		"version":  "22.04",
		"arch":     runtime.GOARCH,
		"hostname": hostname,
		"uptime":   system.GetUptimeString(),
	})
}

func RealtimeMetricsHandler(w http.ResponseWriter, r *http.Request) {
	current := system.GetCurrentMetrics()
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"snapshots": []system.MetricsSnapshot{current},
	})
}

func ServerStatsHandler(w http.ResponseWriter, r *http.Request) {
	m := system.GetCurrentMetrics()
	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
		"stats": map[string]interface{}{
			"cpu_percent":     m.CPUPercent,
			"memory_total_mb": m.MemTotalMB,
			"memory_used_mb":  m.MemUsedMB,
			"memory_percent":  m.MemPercent,
			"disk_total_gb":   m.DiskTotalGB,
			"disk_used_gb":    m.DiskUsedGB,
			"disk_percent":    m.DiskPercent,
			"load_avg_1":      m.LoadAvg1,
			"load_avg_5":      m.LoadAvg5,
			"load_avg_15":     m.LoadAvg15,
			"uptime":          system.GetUptimeString(),
			"process_count":   150,
		},
	})
}

func TopProcessesHandler(w http.ResponseWriter, r *http.Request) {
	out, err := system.ExecCommand("ps", "-eo", "user,pid,%cpu,%mem,comm", "--sort=-%cpu")
	var processes []ProcessInfo

	if err == nil {
		lines := strings.Split(out, "\n")
		// Skip header line
		for i := 1; i < len(lines) && i <= 20; i++ {
			fields := strings.Fields(lines[i])
			if len(fields) >= 5 {
				pid, _ := strconv.Atoi(fields[1])
				cpu, _ := strconv.ParseFloat(fields[2], 64)
				mem, _ := strconv.ParseFloat(fields[3], 64)
				processes = append(processes, ProcessInfo{
					User:       fields[0],
					PID:        pid,
					CPUPercent: cpu,
					MemPercent: mem,
					Command:    strings.Join(fields[4:], " "),
				})
			}
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":    "ok",
		"processes": processes,
	})
}
