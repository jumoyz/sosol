<?php
declare(strict_types=1);

namespace Services;

use PDO; use Throwable; use DateTimeImmutable;

class SolFinanceManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Record a contribution. Returns contribution id or false on failure.
     * @return string|false
     */
    public function recordContribution(string $groupId, string $participantId, string $userId, float $amount, string $method, ?string $reference, int $cycle)
    {
        $id = $this->uuid();
        $status = $method === 'wallet' ? 'paid' : 'pending';
        try {
            $stmt = $this->db->prepare("INSERT INTO sol_contributions (id, sol_group_id, participant_id, user_id, amount, currency, status, cycle_number, created_at, updated_at, payment_method, payment_reference) VALUES (?,?,?,?,?,'HTG',?,?,NOW(),NOW(),?,?)");
            $stmt->execute([$id,$groupId,$participantId,$userId,$amount,$status,$cycle,$method,$reference]);
            return $id;
        } catch (Throwable $e) {
            error_log('recordContribution failed: '.$e->getMessage());
            return false;
        }
    }

    /** Approve a pending contribution */
    public function approveContribution(string $contributionId, string $adminId): bool
    {
        try {
            $this->db->beginTransaction();
            $lock = $this->db->prepare("SELECT id,status FROM sol_contributions WHERE id=? FOR UPDATE");
            $lock->execute([$contributionId]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'pending') { $this->db->rollBack(); return false; }
            $up = $this->db->prepare("UPDATE sol_contributions SET status='paid', approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?");
            $up->execute([$adminId,$contributionId]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('approveContribution failed: '.$e->getMessage());
            return false;
        }
    }

    /** Determine if all contributions for a cycle are paid */
    public function isCycleReady(string $groupId, int $cycle): bool
    {
        $stmt = $this->db->prepare("SELECT 
              SUM(CASE WHEN sc.status='paid' THEN 1 ELSE 0 END) paid_count,
              COUNT(DISTINCT sp.id) participant_count
            FROM sol_participants sp
            LEFT JOIN sol_contributions sc ON sc.participant_id = sp.id AND sc.cycle_number = ?
            WHERE sp.sol_group_id = ?");
        $stmt->execute([$cycle,$groupId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row && (int)$row['participant_count'] > 0 && (int)$row['paid_count'] >= (int)$row['participant_count'];
    }

    /** Create payout event. Returns event id or false on failure.
     * @return string|false
     */
    public function createPayoutEvent(string $groupId, string $participantId, int $cycle, float $amount, string $method, ?string $reference, string $actorId)
    {
        $id = $this->uuid();
        try {
            $stmt = $this->db->prepare("INSERT INTO sol_payout_events (id, sol_group_id, participant_id, cycle_number, amount, payout_method, payout_reference, status, processed_by, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())");
            $stmt->execute([$id,$groupId,$participantId,$cycle,$amount,$method,$reference,'initiated',$actorId]);
            return $id;
        } catch (Throwable $e) {
            error_log('createPayoutEvent failed: '.$e->getMessage());
            return false;
        }
    }

    public function completePayoutEvent(string $eventId, string $actorId): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE sol_payout_events SET status='completed', processed_by=?, processed_at=NOW(), updated_at=NOW() WHERE id=? AND status IN ('initiated','processing')");
            $stmt->execute([$actorId,$eventId]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            error_log('completePayoutEvent failed: '.$e->getMessage());
            return false;
        }
    }

    public function getPendingContributions(string $groupId): array
    {
        $stmt = $this->db->prepare("SELECT sc.id, sc.amount, sc.cycle_number, sc.created_at, u.full_name FROM sol_contributions sc INNER JOIN sol_participants sp ON sc.participant_id=sp.id INNER JOIN users u ON sp.user_id=u.id WHERE sp.sol_group_id = ? AND sc.status='pending' ORDER BY sc.created_at ASC");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
