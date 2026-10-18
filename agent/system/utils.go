package system

import (
	"bytes"
	"encoding/json"
	"fmt"
	"net/http"
	"os"
	"os/exec"
	"strconv"
	"strings"
	"syscall"
	"time"
)

var startTime = time.Now()

func RespondJSON(w http.ResponseWriter, status int, data interface{}) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	json.NewEncoder(w).Encode(data)
}

func ExecCommand(name string, args ...string) (string, error) {
	cmd := exec.Command(name, args...)
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &stdout
	cmd.Stderr = &stderr
	err := cmd.Run()
	if err != nil {
		return stdout.String(), fmt.Errorf("%v: %s", err, stderr.String())
	}
	return strings.TrimSpace(stdout.String()), nil
}

type MetricsSnapshot struct {
	Timestamp    int64   `json:"timestamp"`
	CPUPercent   float64 `json:"cpu_percent"`
	MemUsedMB    int64   `json:"mem_used_mb"`
	MemTotalMB   int64   `json:"mem_total_mb"`
	MemPercent   float64 `json:"mem_percent"`
	DiskUsedGB   int64   `json:"disk_used_gb"`
	DiskTotalGB  int64   `json:"disk_total_gb"`
	DiskPercent  int64   `json:"disk_percent"`
	LoadAvg1     float64 `json:"load_avg_1"`
	LoadAvg5     float64 `json:"load_avg_5"`
	LoadAvg15    float64 `json:"load_avg_15"`
	NetworkInKB  int64   `json:"network_in_kb"`
	NetworkOutKB int64   `json:"network_out_kb"`
}

func GetCurrentMetrics() MetricsSnapshot {
	m := MetricsSnapshot{
		Timestamp: time.Now().UnixMilli(),
	}

	// Memory from /proc/meminfo
	if memTotal, memFree, memAvail, err := readMemInfo(); err == nil && memTotal > 0 {
		m.MemTotalMB = memTotal / 1024
		used := memTotal - memAvail
		if used < 0 {
			used = memTotal - memFree
		}
		m.MemUsedMB = used / 1024
		m.MemPercent = float64(used) / float64(memTotal) * 100.0
	}

	// Disk from Statfs
	var stat syscall.Statfs_t
	if err := syscall.Statfs("/", &stat); err == nil {
		total := stat.Blocks * uint64(stat.Bsize)
		free := stat.Bavail * uint64(stat.Bsize)
		used := total - free
		m.DiskTotalGB = int64(total / (1024 * 1024 * 1024))
		m.DiskUsedGB = int64(used / (1024 * 1024 * 1024))
		if total > 0 {
			m.DiskPercent = int64((float64(used) / float64(total)) * 100)
		}
	}

	// Load Average from /proc/loadavg
	if data, err := os.ReadFile("/proc/loadavg"); err == nil {
		fields := strings.Fields(string(data))
		if len(fields) >= 3 {
			m.LoadAvg1, _ = strconv.ParseFloat(fields[0], 64)
			m.LoadAvg5, _ = strconv.ParseFloat(fields[1], 64)
			m.LoadAvg15, _ = strconv.ParseFloat(fields[2], 64)
			m.CPUPercent = m.LoadAvg1 * 10.0
			if m.CPUPercent > 100.0 {
				m.CPUPercent = 100.0
			}
		}
	}

	return m
}

func GetUptimeString() string {
	d := time.Since(startTime)
	hours := int(d.Hours())
	mins := int(d.Minutes()) % 60
	if hours > 0 {
		return fmt.Sprintf("%dh %dm", hours, mins)
	}
	return fmt.Sprintf("%dm", mins)
}

func readMemInfo() (total, free, avail int64, err error) {
	data, err := os.ReadFile("/proc/meminfo")
	if err != nil {
		return 0, 0, 0, err
	}
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		parts := strings.Fields(line)
		if len(parts) >= 2 {
			val, _ := strconv.ParseInt(parts[1], 10, 64)
			switch parts[0] {
			case "MemTotal:":
				total = val
			case "MemFree:":
				free = val
			case "MemAvailable:":
				avail = val
			}
		}
	}
	if avail == 0 {
		avail = free
	}
	return total, free, avail, nil
}
