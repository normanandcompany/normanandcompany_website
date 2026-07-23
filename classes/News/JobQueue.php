<?php
declare(strict_types=1);

final class NewsJobQueue
{
    public function __construct(private PDO $pdo) {}
    public function enqueue(string $type, array $payload, int $priority = 50): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO news_jobs(job_type,payload_json,priority) VALUES(:type,:payload,:priority)');
        $stmt->execute([':type' => $type, ':payload' => json_encode($payload, JSON_THROW_ON_ERROR), ':priority' => $priority]);
        return (int) $this->pdo->lastInsertId();
    }
    public function claim(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $row = $this->pdo->query("SELECT * FROM news_jobs WHERE status='pending' AND available_at <= NOW() ORDER BY priority DESC,id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $this->pdo->commit(); return null; }
            $stmt = $this->pdo->prepare("UPDATE news_jobs SET status='processing',started_at=NOW(),attempt_count=attempt_count+1 WHERE id=:id");
            $stmt->execute([':id' => $row['id']]); $this->pdo->commit(); return $row;
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
    public function finish(int $id): void { $this->pdo->prepare("UPDATE news_jobs SET status='completed',completed_at=NOW() WHERE id=:id")->execute([':id'=>$id]); }
    public function fail(int $id, string $error): void { $this->pdo->prepare("UPDATE news_jobs SET status=IF(attempt_count>=max_attempts,'failed','pending'),failed_at=IF(attempt_count>=max_attempts,NOW(),NULL),available_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE),last_error=:error WHERE id=:id")->execute([':id'=>$id,':error'=>mb_substr($error,0,2000)]); }
}
