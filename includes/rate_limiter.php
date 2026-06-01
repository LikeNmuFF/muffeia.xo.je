<?php
class RateLimiter {
    private $conn;
    private $ip;
    private $maxRequests = 120;
    private $timeWindow = 60;
    private $max404Requests = 20;
    private $blockDuration = 300;
    private $maxConcurrent = 10;

    public function __construct($db_connection) {
        $this->conn = $db_connection;
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $this->cleanStale();
    }

    public function checkRateLimit() {
        $now = time();
        $windowStart = $now - $this->timeWindow;

        $stmt = $this->conn->prepare("SELECT id, request_count, window_start, is_blocked, blocked_until FROM rate_limits WHERE ip_address = ?");
        $stmt->bind_param("s", $this->ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();

        if ($record) {
            if ($record['is_blocked']) {
                if ($now < $record['blocked_until']) {
                    header('HTTP/1.1 429 Too Many Requests');
                    header('Retry-After: ' . ($record['blocked_until'] - $now));
                    echo json_encode(['error' => 'Too many requests. Please try again later.']);
                    exit();
                } else {
                    $reset = $this->conn->prepare("UPDATE rate_limits SET is_blocked = 0, blocked_until = 0, request_count = 1, window_start = ? WHERE ip_address = ?");
                    $reset->bind_param("is", $now, $this->ip);
                    $reset->execute();
                    return;
                }
            }

            if ($record['window_start'] < $windowStart) {
                $update = $this->conn->prepare("UPDATE rate_limits SET request_count = 1, window_start = ? WHERE ip_address = ?");
                $update->bind_param("is", $now, $this->ip);
                $update->execute();
            } else {
                if ($record['request_count'] >= $this->maxRequests) {
                    $block = $this->conn->prepare("UPDATE rate_limits SET is_blocked = 1, blocked_until = ? WHERE ip_address = ?");
                    $blockUntil = $now + $this->blockDuration;
                    $block->bind_param("is", $blockUntil, $this->ip);
                    $block->execute();

                    header('HTTP/1.1 429 Too Many Requests');
                    header('Retry-After: ' . $this->blockDuration);
                    echo json_encode(['error' => 'Too many requests. You have been temporarily blocked.']);
                    exit();
                } else {
                    $update = $this->conn->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE ip_address = ?");
                    $update->bind_param("s", $this->ip);
                    $update->execute();
                }
            }
        } else {
            $insert = $this->conn->prepare("INSERT INTO rate_limits (ip_address, request_count, window_start) VALUES (?, 1, ?)");
            $insert->bind_param("si", $this->ip, $now);
            $insert->execute();
        }
    }

    public function track404($requestPath) {
        $now = time();
        $windowStart = $now - $this->timeWindow;

        $stmt = $this->conn->prepare("SELECT id, hit_count, first_hit FROM rate_limit_404 WHERE ip_address = ? AND first_hit > ?");
        $stmt->bind_param("si", $this->ip, $windowStart);
        $stmt->execute();
        $result = $stmt->get_result();
        $record = $result->fetch_assoc();

        if ($record) {
            $newCount = $record['hit_count'] + 1;
            if ($newCount >= $this->max404Requests) {
                $block = $this->conn->prepare("UPDATE rate_limits SET is_blocked = 1, blocked_until = ? WHERE ip_address = ?");
                $blockUntil = $now + $this->blockDuration;
                $block->bind_param("is", $blockUntil, $this->ip);
                $block->execute();
            }

            $update = $this->conn->prepare("UPDATE rate_limit_404 SET hit_count = ?, last_hit = ?, request_path = ? WHERE id = ?");
            $update->bind_param("iiss", $newCount, $now, $requestPath, $record['id']);
            $update->execute();
        } else {
            $insert = $this->conn->prepare("INSERT INTO rate_limit_404 (ip_address, request_path, hit_count, first_hit, last_hit) VALUES (?, ?, 1, ?, ?)");
            $insert->bind_param("ssii", $this->ip, $requestPath, $now, $now);
            $insert->execute();
        }

        if (isset($record) && $record['hit_count'] >= $this->max404Requests) {
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode(['error' => 'Too many invalid requests. Blocked.']);
            exit();
        }
    }

    private function cleanStale() {
        $cutoff = time() - 86400;
        $clean = $this->conn->prepare("DELETE FROM rate_limits WHERE window_start < ? AND is_blocked = 0");
        $clean->bind_param("i", $cutoff);
        $clean->execute();

        $clean404 = $this->conn->prepare("DELETE FROM rate_limit_404 WHERE last_hit < ?");
        $clean404->bind_param("i", $cutoff);
        $clean404->execute();
    }

    public static function checkPrerequisites() {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($agent)) {
            header('HTTP/1.1 403 Forbidden');
            exit();
        }
    }
}
