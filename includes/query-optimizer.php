<?php
/**
 * Query Optimizer — Performance Monitoring
 *
 * Wraps PDO to measure query execution times, log slow queries and
 * provide basic index-suggestion heuristics.
 *
 * SQL migration (run once):
 * ------------------------------------------------------------
 * CREATE TABLE IF NOT EXISTS `query_log` (
 *   `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 *   `query_hash`        CHAR(64)     NOT NULL,
 *   `query_text`        TEXT         NOT NULL,
 *   `execution_time_ms` FLOAT        NOT NULL,
 *   `row_count`         INT UNSIGNED NOT NULL DEFAULT 0,
 *   `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   INDEX `idx_query_hash`       (`query_hash`),
 *   INDEX `idx_execution_time`   (`execution_time_ms`),
 *   INDEX `idx_created_at`       (`created_at`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 * ------------------------------------------------------------
 */

class QueryOptimizer
{
    private PDO   $db;
    private array $log          = [];
    private bool  $loggingEnabled;

    /** Auto-cleanup after this many days */
    private const LOG_RETENTION_DAYS = 7;

    /**
     * @param PDO  $db              The PDO connection to wrap.
     * @param bool $loggingEnabled  Set false to disable DB logging (e.g. in tests).
     */
    public function __construct(PDO $db, bool $loggingEnabled = true)
    {
        $this->db             = $db;
        $this->loggingEnabled = $loggingEnabled;
    }

    // -----------------------------------------------------------------------
    // Query execution
    // -----------------------------------------------------------------------

    /**
     * Execute a prepared statement with timing and optional slow-query logging.
     *
     * @param string  $sql    The SQL query string (use ? or :name placeholders).
     * @param array   $params Parameters to bind.
     * @param int     $slowThresholdMs  Queries exceeding this (ms) are logged to DB.
     */
    public function query(string $sql, array $params = [], int $slowThresholdMs = 100): PDOStatement
    {
        $start = microtime(true);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $elapsed  = (microtime(true) - $start) * 1000; // ms
        $rowCount = $stmt->rowCount();

        $this->log[] = [
            'sql'          => $sql,
            'time_ms'      => round($elapsed, 3),
            'row_count'    => $rowCount,
        ];

        if ($this->loggingEnabled && $elapsed >= $slowThresholdMs) {
            $this->logQuery($sql, $elapsed, $rowCount);
        }

        return $stmt;
    }

    // -----------------------------------------------------------------------
    // Slow-query reporting
    // -----------------------------------------------------------------------

    /**
     * Return in-memory queries exceeding the given threshold (ms).
     */
    public function getSlowQueries(int $thresholdMs = 100): array
    {
        return array_values(
            array_filter($this->log, fn($q) => $q['time_ms'] >= $thresholdMs)
        );
    }

    /**
     * Log a slow query to the `query_log` table.
     */
    public function logQuery(string $sql, float $executionTime, int $rowCount): void
    {
        try {
            $hash = hash('sha256', $sql);
            $stmt = $this->db->prepare(
                "INSERT INTO query_log (query_hash, query_text, execution_time_ms, row_count)
                 VALUES (:hash, :sql, :time, :rows)"
            );
            $stmt->execute([
                ':hash' => $hash,
                ':sql'  => $sql,
                ':time' => round($executionTime, 3),
                ':rows' => $rowCount,
            ]);
            $this->cleanOldLogs();
        } catch (PDOException) {
            // Swallow — monitoring should never break the application
        }
    }

    // -----------------------------------------------------------------------
    // Statistics
    // -----------------------------------------------------------------------

    /**
     * Return aggregate stats for queries executed this request.
     *
     * @return array{total: int, avg_time_ms: float, max_time_ms: float, slowest_sql: string}
     */
    public function getQueryStats(): array
    {
        if (empty($this->log)) {
            return ['total' => 0, 'avg_time_ms' => 0.0, 'max_time_ms' => 0.0, 'slowest_sql' => ''];
        }

        $times   = array_column($this->log, 'time_ms');
        $maxTime = max($times);
        $slowest = array_filter($this->log, fn($q) => $q['time_ms'] === $maxTime);

        return [
            'total'       => count($this->log),
            'avg_time_ms' => round(array_sum($times) / count($times), 3),
            'max_time_ms' => $maxTime,
            'slowest_sql' => array_values($slowest)[0]['sql'] ?? '',
        ];
    }

    // -----------------------------------------------------------------------
    // EXPLAIN & index suggestions
    // -----------------------------------------------------------------------

    /**
     * Run EXPLAIN on a SELECT query and return the result rows.
     */
    public function explain(string $sql): array
    {
        try {
            $stmt = $this->db->query("EXPLAIN {$sql}");
            return $stmt->fetchAll();
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Suggest indexes based on slow query patterns stored in `query_log`.
     * Returns a list of human-readable suggestion strings.
     */
    public function suggestIndexes(): array
    {
        $suggestions = [];

        try {
            $stmt = $this->db->query(
                "SELECT query_text, COUNT(*) AS occurrences, AVG(execution_time_ms) AS avg_ms
                   FROM query_log
                  WHERE execution_time_ms >= 100
                    AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::LOG_RETENTION_DAYS . " DAY)
                  GROUP BY query_hash
                  ORDER BY avg_ms DESC
                  LIMIT 20"
            );
            $rows = $stmt->fetchAll();
        } catch (PDOException) {
            return $suggestions;
        }

        foreach ($rows as $row) {
            $sql = strtolower($row['query_text']);

            // Heuristic: extract table name after FROM/JOIN
            preg_match_all('/\b(?:from|join)\s+`?(\w+)`?/i', $sql, $matches);
            $tables = $matches[1] ?? [];

            // Heuristic: extract columns after WHERE
            preg_match_all('/\bwhere\b.*?\b(\w+)\s*=/i', $sql, $wMatches);
            $whereCols = $wMatches[1] ?? [];

            foreach ($tables as $table) {
                foreach ($whereCols as $col) {
                    $suggestions[] = sprintf(
                        'Consider adding INDEX on `%s`.`%s` — query ran %.1f ms avg (%d occurrences): %s',
                        $table,
                        $col,
                        $row['avg_ms'],
                        $row['occurrences'],
                        substr($row['query_text'], 0, 120)
                    );
                }
            }
        }

        return $suggestions;
    }

    // -----------------------------------------------------------------------
    // Maintenance
    // -----------------------------------------------------------------------

    /**
     * Delete query_log entries older than LOG_RETENTION_DAYS.
     */
    private function cleanOldLogs(): void
    {
        try {
            $this->db->exec(
                "DELETE FROM query_log
                  WHERE created_at < DATE_SUB(NOW(), INTERVAL " . self::LOG_RETENTION_DAYS . " DAY)"
            );
        } catch (PDOException) {
            // Swallow
        }
    }
}
