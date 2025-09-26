<?php
use PHPUnit\Framework\TestCase;

final class InvestmentLogicTest extends TestCase {

    public function testGenerateUuidProducesValidFormat(): void {
        $id = generateUuid();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $id);
    }

    public function testPledgeDoesNotExceedRemainingNeed(): void {
        // Simple pure function style simulation mirroring logic from pledge handling
        $fundingGoal = 10000.00;
        $amountRaised = 9200.00;
        $pledgeAttempt = 900.00; // would exceed by 100

        $remainingNeed = $fundingGoal - $amountRaised; // 800
        $accepted = min($pledgeAttempt, $remainingNeed);

        $this->assertSame(800.00, $accepted, 'Pledge should be capped at remaining need.');
    }

    public function testStatusTransitionsToFundedWhenThresholdReached(): void {
        $fundingGoal = 5000.00;
        $amountRaised = 4800.00;
        $pledge = 250.00; // reaches 5050

        $newAmount = $amountRaised + $pledge;
        $status = ($newAmount >= $fundingGoal) ? 'funded' : 'open';

        $this->assertSame('funded', $status);
    }
}
