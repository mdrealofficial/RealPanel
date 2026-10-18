package config

import (
	"bufio"
	"os"
	"strconv"
	"strings"
)

type Config struct {
	Secret          string
	ListenAddr      string
	Port            int
	MailURL         string
	MailSyncSecret  string
	PmaSignonSecret string
}

func Load(path string) (*Config, error) {
	cfg := &Config{
		ListenAddr: "127.0.0.1",
		Port:       7443,
	}

	file, err := os.Open(path)
	if err != nil {
		return cfg, err
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		parts := strings.SplitN(line, "=", 2)
		if len(parts) != 2 {
			continue
		}
		key := strings.TrimSpace(parts[0])
		val := strings.TrimSpace(parts[1])

		switch key {
		case "secret":
			cfg.Secret = val
		case "listen_addr":
			cfg.ListenAddr = val
		case "port":
			if p, err := strconv.Atoi(val); err == nil {
				cfg.Port = p
			}
		case "mail_url":
			cfg.MailURL = val
		case "mail_sync_secret":
			cfg.MailSyncSecret = val
		case "pma_signon_secret":
			cfg.PmaSignonSecret = val
		}
	}

	return cfg, scanner.Err()
}
