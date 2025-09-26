<?php
declare(strict_types=1);

namespace Services;

use DateInterval;
use DateTimeImmutable;
use PDO;

class SolPayoutManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate payout schedule when group is created
     */
    public function generateSchedule(string $groupId, string $frequency): void
    {
        $participants = $this->getParticipants($groupId);
        if (empty($participants)) {
            return;
        }

        $interval = $this->getInterval($frequency);
        $startDate = new DateTimeImmutable();
        $position = 1;

        foreach ($participants as $participant) {
            $payoutDate = $startDate->add(new DateInterval($interval));

            $stmt = $this->db->prepare("
                INSERT INTO sol_payouts (id, sol_group_id, participant_id, payout_order, payout_date, created_at, updated_at)
                VALUES (UUID(), :group_id, :participant_id, :payout_position, :payout_date, NOW(), NOW())
            ");
            $stmt->execute([
                ':group_id'      => $groupId,
                ':participant_id'=> $participant['id'],
                ':payout_position'  => $position,
                ':payout_date'   => $payoutDate->format('Y-m-d')
            ]);

            $startDate = $payoutDate;
            $position++;
        }
    }

    /**
     * Regenerate payout schedule for a SOL group.
     * @param string $groupId
     * @return void
     */
    public function regeneratePayoutSchedule(string $groupId): void
    {
        // Implement payout schedule regeneration logic here.
        $stmt = $this->db->prepare("SELECT frequency FROM sol_groups WHERE id = :group_id");
        $stmt->execute([':group_id' => $groupId]);
        $frequency = $stmt->fetch(PDO::FETCH_ASSOC)['frequency'];

        $this->generateSchedule($groupId, $frequency);
    }

    /**
     * Update payout schedule when participants change or frequency is updated
     * @param string $groupId
     * @param string $frequency
     * @return void
     */
    public function updateSchedule(string $groupId, string $frequency): void
    {
        $participants = $this->getParticipants($groupId);
        if (empty($participants)) {
            return;
        }

        // Delete old schedule
        $this->db->prepare("DELETE FROM sol_payouts WHERE sol_group_id = :group_id")
                 ->execute([':group_id' => $groupId]);

        // Re-generate with updated list
        $this->generateSchedule($groupId, $frequency);
    }

    /**
     * Fetch participants of a SOL group
     * @param string $groupId
     * @return array
     */
    private function getParticipants(string $groupId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM sol_participants WHERE sol_group_id = :group_id ORDER BY created_at ASC");
        $stmt->execute([':group_id' => $groupId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get DateInterval string based on frequency
     * @param string $frequency
     * @return string
     */
    private function getInterval(string $frequency): string
    {
        switch ($frequency) {
            case 'daily':
                return 'P1D';
            case 'weekly':
                return 'P1W';
            case 'monthly':
                return 'P1M';
            default:
                return 'P1W'; // Default to weekly if frequency is not recognized.
        }
    }
}
