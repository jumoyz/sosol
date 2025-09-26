<?php 
/**
 *  SOL Group Manager 
 * 
 */
require_once __DIR__ . '/SolPayoutManager.php';

// Remove or adjust the 'use' statement if SolPayoutManager is not namespaced
// If SolPayoutManager is in a namespace, use the correct namespace, e.g.:
use Services\SolPayoutManager;

class SolGroupManager
{
    private PDO $db;
    private SolPayoutManager $payoutManager;

    public function __construct(PDO $db, SolPayoutManager $payoutManager)
    {
        $this->db = $db;
        $this->payoutManager = $payoutManager;
    }

    /**
     * Create a new SOL group
     * @param string $groupName
     * @param string $creatorUserId
     * @param string $frequency 'weekly' | 'monthly'
     * @param DateTimeImmutable|null $startDate
     * @return string|false Group ID on success, false on failure
     */
    public function createSolGroup(
        string $groupName,
        string $creatorUserId,
        string $frequency = 'weekly',
        ?DateTimeImmutable $startDate = null
    ) {
        $groupId = $this->uuid();
        $startDate = $startDate ?? new DateTimeImmutable();

        try {
            $this->db->beginTransaction();

            // Insert group
            $stmt = $this->db->prepare("
                INSERT INTO sol_groups (id, name, frequency, start_date, created_at, updated_at)
                VALUES (:id, :name, :frequency, :start_date, NOW(), NOW())
            ");
            $stmt->execute([
                'id'        => $groupId,
                'name'      => $groupName,
                'frequency' => $frequency,
                'start_date'=> $startDate->format('Y-m-d'),
            ]);

            // Add creator as first participant
            $participantId = $this->uuid();
            $stmt = $this->db->prepare("
                INSERT INTO sol_participants (id, group_id, user_id, payout_position, created_at, updated_at)
                VALUES (:id, :group_id, :user_id, 1, NOW(), NOW())
            ");
            $stmt->execute([
                'id'       => $participantId,
                'group_id' => $groupId,
                'user_id'  => $creatorUserId,
            ]);

            // Generate initial payout schedule
            $this->payoutManager->regeneratePayoutSchedule($groupId);

            $this->db->commit();
            return $groupId;

        } catch (Throwable $e) {
            $this->db->rollBack();
            error_log("SOL group creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a UUID v4
     * @return string
     */
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

    // Additional methods for managing SOL groups can be added here
    // ...
}
