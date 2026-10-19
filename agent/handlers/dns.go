package handlers

import (
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"

	"realpanel-agent-oss/system"
)

type DnsCreateZoneReq struct {
	Domain   string `json:"domain"`
	ServerIP string `json:"server_ip"`
	NS1      string `json:"ns1"`
	NS2      string `json:"ns2"`
}

type DnsListRecordsReq struct {
	Domain string `json:"domain"`
}

type DnsAddRecordReq struct {
	Domain   string `json:"domain"`
	Name     string `json:"name"`
	Type     string `json:"type"`
	Content  string `json:"content"`
	TTL      int    `json:"ttl"`
	Priority int    `json:"priority"`
}

type DnsDeleteRecordReq struct {
	Domain   string `json:"domain"`
	RecordID int    `json:"record_id"`
}

type DnsRecord struct {
	ID       int    `json:"id"`
	Name     string `json:"name"`
	Type     string `json:"type"`
	Content  string `json:"content"`
	TTL      int    `json:"ttl"`
	Priority int    `json:"priority"`
}

func DnsCreateZoneHandler(w http.ResponseWriter, r *http.Request) {
	var req DnsCreateZoneReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil || req.Domain == "" {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "domain is required"})
		return
	}

	if req.NS1 == "" {
		req.NS1 = "ns1." + req.Domain
	}
	if req.NS2 == "" {
		req.NS2 = "ns2." + req.Domain
	}

	// Insert domain into pdns.domains
	domainQuery := fmt.Sprintf("INSERT IGNORE INTO pdns.domains (name, type) VALUES ('%s', 'NATIVE');", req.Domain)
	system.ExecCommand("mysql", "-e", domainQuery)

	// Get domain id
	getIdQuery := fmt.Sprintf("SELECT id FROM pdns.domains WHERE name = '%s' LIMIT 1;", req.Domain)
	out, _ := system.ExecCommand("mysql", "-sN", "-e", getIdQuery)
	domainIdStr := strings.TrimSpace(out)

	if domainIdStr != "" {
		// Default SOA, NS, and A records
		soa := fmt.Sprintf("%s hostmaster.%s 1 10800 3600 604800 3600", req.NS1, req.Domain)
		recordsQuery := fmt.Sprintf(`
INSERT IGNORE INTO pdns.records (domain_id, name, type, content, ttl) VALUES (%s, '%s', 'SOA', '%s', 3600);
INSERT IGNORE INTO pdns.records (domain_id, name, type, content, ttl) VALUES (%s, '%s', 'NS', '%s', 3600);
INSERT IGNORE INTO pdns.records (domain_id, name, type, content, ttl) VALUES (%s, '%s', 'NS', '%s', 3600);
INSERT IGNORE INTO pdns.records (domain_id, name, type, content, ttl) VALUES (%s, '%s', 'A', '%s', 3600);
INSERT IGNORE INTO pdns.records (domain_id, name, type, content, ttl) VALUES (%s, 'www.%s', 'A', '%s', 3600);
`, domainIdStr, req.Domain, soa,
			domainIdStr, req.Domain, req.NS1,
			domainIdStr, req.Domain, req.NS2,
			domainIdStr, req.Domain, req.ServerIP,
			domainIdStr, req.Domain, req.ServerIP)

		system.ExecCommand("mysql", "-e", recordsQuery)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DnsListRecordsHandler(w http.ResponseWriter, r *http.Request) {
	var req DnsListRecordsReq
	json.NewDecoder(r.Body).Decode(&req)

	query := fmt.Sprintf(`
SELECT r.id, r.name, r.type, r.content, r.ttl, COALESCE(r.prio, 0)
FROM pdns.records r
JOIN pdns.domains d ON d.id = r.domain_id
WHERE d.name = '%s';`, req.Domain)

	out, err := system.ExecCommand("mysql", "-sN", "-e", query)
	var records []DnsRecord

	if err == nil {
		lines := strings.Split(strings.TrimSpace(out), "\n")
		for _, line := range lines {
			parts := strings.Split(line, "\t")
			if len(parts) >= 6 {
				id, _ := strconv.Atoi(parts[0])
				ttl, _ := strconv.Atoi(parts[4])
				prio, _ := strconv.Atoi(parts[5])
				records = append(records, DnsRecord{
					ID:       id,
					Name:     parts[1],
					Type:     parts[2],
					Content:  parts[3],
					TTL:      ttl,
					Priority: prio,
				})
			}
		}
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status":  "ok",
		"records": records,
	})
}

func DnsAddRecordHandler(w http.ResponseWriter, r *http.Request) {
	var req DnsAddRecordReq
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		system.RespondJSON(w, http.StatusBadRequest, map[string]string{"error": "Invalid payload"})
		return
	}

	if req.TTL <= 0 {
		req.TTL = 3600
	}

	getIdQuery := fmt.Sprintf("SELECT id FROM pdns.domains WHERE name = '%s' LIMIT 1;", req.Domain)
	out, _ := system.ExecCommand("mysql", "-sN", "-e", getIdQuery)
	domainId := strings.TrimSpace(out)

	if domainId != "" {
		insertQuery := fmt.Sprintf("INSERT INTO pdns.records (domain_id, name, type, content, ttl, prio) VALUES (%s, '%s', '%s', '%s', %d, %d);",
			domainId, req.Name, req.Type, req.Content, req.TTL, req.Priority)
		system.ExecCommand("mysql", "-e", insertQuery)
	}

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}

func DnsDeleteRecordHandler(w http.ResponseWriter, r *http.Request) {
	var req DnsDeleteRecordReq
	json.NewDecoder(r.Body).Decode(&req)

	query := fmt.Sprintf("DELETE FROM pdns.records WHERE id = %d;", req.RecordID)
	system.ExecCommand("mysql", "-e", query)

	system.RespondJSON(w, http.StatusOK, map[string]interface{}{
		"status": "ok",
	})
}
