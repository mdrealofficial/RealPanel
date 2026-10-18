package auth

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"io"
	"net/http"
	"strings"
	"time"
)

// Middleware verifies HMAC-SHA256 signatures for incoming requests.
func Middleware(secret string, next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Whitelist unauthenticated public endpoints
		if r.URL.Path == "/health" || r.URL.Path == "/metrics/realtime" {
			next.ServeHTTP(w, r)
			return
		}

		if secret == "" {
			// No secret configured: allow for local dev if needed, or reject
			http.Error(w, `{"error": "Agent secret not configured"}`, http.StatusUnauthorized)
			return
		}

		sigHeader := r.Header.Get("X-Signature")
		tsHeader := r.Header.Get("X-Timestamp")

		if sigHeader == "" || tsHeader == "" {
			http.Error(w, `{"error": "Missing signature or timestamp header"}`, http.StatusUnauthorized)
			return
		}

		// Optional: Timestamp drift check (allow up to 10 minutes clock drift)
		if parsedTs, err := time.Parse(time.RFC3339, tsHeader); err == nil {
			drift := time.Since(parsedTs)
			if drift < -10*time.Minute || drift > 10*time.Minute {
				http.Error(w, `{"error": "Request timestamp expired or skewed"}`, http.StatusUnauthorized)
				return
			}
		}

		var bodyBytes []byte
		if r.URL.Path == "/files/upload" {
			// Special case in AgentService: upload multipart skips body hashing
			bodyBytes = []byte("")
		} else if r.Body != nil {
			var err error
			bodyBytes, err = io.ReadAll(r.Body)
			if err != nil {
				http.Error(w, `{"error": "Failed to read request body"}`, http.StatusBadRequest)
				return
			}
			// Restore request body for subsequent handlers
			r.Body = io.NopCloser(bytes.NewBuffer(bodyBytes))
		}

		// Payload: timestamp + method + pathOnly + body
		pathOnly := strings.Split(r.URL.Path, "?")[0]
		payload := tsHeader + r.Method + pathOnly + string(bodyBytes)

		mac := hmac.New(sha256.New, []byte(secret))
		mac.Write([]byte(payload))
		expectedSig := hex.EncodeToString(mac.Sum(nil))

		if !hmac.Equal([]byte(strings.ToLower(sigHeader)), []byte(strings.ToLower(expectedSig))) {
			http.Error(w, `{"error": "Invalid HMAC signature"}`, http.StatusUnauthorized)
			return
		}

		next.ServeHTTP(w, r)
	})
}
