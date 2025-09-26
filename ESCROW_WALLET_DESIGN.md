# Escrow & Wallet Integration Design (Draft)

Date: 2025-09-20
Status: Draft Proposal – Not Implemented Yet
Related Features: Investments MVP, Wallet subsystem (existing), Future Funding Settlement

## Goals
1. Introduce a secure escrow workflow for investment pledges.
2. Prevent double-spend / overfunding while maintaining user experience.
3. Enable partial commitments, cancellation windows, and dispute handling later.
4. Reuse / extend existing wallet tables and transaction logging patterns.

## Out of Scope (Phase 1)
- Secondary market / transferable commitments
- Multi-currency conversion logic
- Complex compliance (AML/KYC advanced rules) – only basic KYC gate
- Dispute arbitration UI

## New/Extended Domain Concepts
| Concept | Description |
|---------|-------------|
| Pledge | Initial investor intent (already implemented) |
| Commitment | Investor chooses to lock funds (moves to escrow) |
| Escrow Hold | Funds removed from available wallet balance, stored logically in hold table |
| Settlement | Transfer from escrow to investment owner when funding round closes as funded |
| Refund | Release escrow back to investor if round fails / expires |

## Proposed Tables
### investment_commitments
Tracks when an interest becomes a locked monetary commitment.
```
CREATE TABLE investment_commitments (
  id CHAR(36) PRIMARY KEY,
  interest_id CHAR(36) NOT NULL,
  investment_id CHAR(36) NOT NULL,
  investor_id CHAR(36) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('pending_lock','locked','released','settled','refunded','cancelled') NOT NULL DEFAULT 'pending_lock',
  lock_tx_id CHAR(36) NULL,       -- reference to wallet_transactions
  release_tx_id CHAR(36) NULL,    -- refund transaction id (if refunded)
  settlement_tx_id CHAR(36) NULL, -- payout to owner (if settled)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_interest (interest_id),
  KEY idx_commitment_investment (investment_id),
  KEY idx_commitment_investor (investor_id),
  CONSTRAINT fk_commit_interest FOREIGN KEY (interest_id) REFERENCES investment_interests(id) ON DELETE CASCADE,
  CONSTRAINT fk_commit_investment FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
  CONSTRAINT fk_commit_investor FOREIGN KEY (investor_id) REFERENCES users(id)
) ENGINE=InnoDB;
```

### escrow_holds
Logical ledger of reserved funds (decouples from generic wallet if needed later).
```
CREATE TABLE escrow_holds (
  id CHAR(36) PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  investment_id CHAR(36) NOT NULL,
  commitment_id CHAR(36) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'HTG',
  status ENUM('initiated','held','released','applied') NOT NULL DEFAULT 'initiated',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_hold_user (user_id),
  KEY idx_hold_investment (investment_id),
  KEY idx_hold_commitment (commitment_id),
  CONSTRAINT fk_hold_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_hold_investment FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE
);
```

(Alternative: store hold state purely in investment_commitments and skip escrow_holds until multi-asset support required.)

### wallet_transactions (Extension)
Add types: `investment_lock`, `investment_settlement`, `investment_refund`.

## State Machine – Commitment Lifecycle
```
interested -> (investor clicks "Commit Funds") -> pending_lock
pending_lock -> (balance check + debit success) -> locked
locked -> (round funded & closed) -> settled
locked -> (round expired/cancelled) -> refunded
locked -> (investor withdraws pre-lock window) -> cancelled (if allowed)
```

## Flow: Commit Funds
1. Investor visits investment details page.
2. Clicks "Commit Funds" (available only if interest exists & not yet committed & investment open & remaining need > 0 & KYC ok).
3. Server validates wallet balance ≥ commitment amount.
4. Begin DB transaction.
5. Insert `investment_commitments (pending_lock)`.
6. Insert wallet transaction (type investment_lock; negative amount to available balance).
7. Insert escrow_hold (status held) OR mark commitment status locked with lock_tx_id.
8. Update commitment status locked.
9. Recalculate total locked vs goal; if goal reached, flip investment status funded (still pending final settlement event). Commit DB.

## Flow: Settlement
Triggered by: owner action "Close & Settle" OR scheduled job at end_date if funded.
Steps:
1. Verify investment.status = funded and not already settled.
2. Sum locked commitments (locked status only).
3. Begin transaction.
4. For each locked commitment create wallet transaction crediting owner (type investment_settlement).
5. Mark commitments settled (settlement_tx_id per commitment or aggregate).
6. Optionally mark investment `closed` or `funded` + `settled_at` timestamp.
7. Commit.

## Flow: Refund (Unfunded Expiration)
1. At end_date if status != funded (e.g., still open) mark investment `closed`.
2. Iterate locked commitments.
3. For each: create wallet transaction crediting investor (investment_refund) & mark commitment refunded.
4. Mark investment `closed` & `refund_completed_at`.

## Concurrency & Integrity Controls
| Risk | Mitigation |
|------|------------|
| Double commit race | Unique constraint on interest_id; serialize commit by DB transaction |
| Overfund lock | Re-check remaining need inside transaction using SELECT ... FOR UPDATE on investment row |
| Wallet negative balance | BEFORE lock: SELECT wallet FOR UPDATE; ensure balance - sum(outstanding holds) ≥ amount |
| Partial failure in settlement | Use atomic transaction; detect and resume via idempotent job |
| Deleted investment after lock | FK ON DELETE CASCADE returns funds through cleanup job or manual recovery policy |

## Required Code Additions (Phase 1 Implementation Outline)
- Migration file for new tables & transaction type enum (if enforced).
- Service: `InvestmentEscrowService` (methods: commitFunds, settleInvestment, refundInvestment, expireInvestmentsJob).
- Extensions to existing pledge/interest controller to expose "Commit" button.
- Wallet helper additions: reserveFunds(), releaseFunds(), applySettlement().
- Cron / CLI script: `php tools/investments-cron.php --expire --settle`.

## Minimal API / UI Changes
| UI Element | Description |
|------------|-------------|
| Commit Funds button | Shown to interested investors w/out commitment |
| Commitment list (owner view) | Distinguish interested vs committed |
| Settlement banner | If funded & not settled, show actionable notice to owner |
| Refund status (investor) | Display refunded commitments after close |

## Performance Considerations
- Typical commit volume per investment expected low (human scale); simple row-level locking sufficient.
- Add index on commitments (investment_id, status) for settlement scans.

## Monitoring & Logging
- Log activity types: `investment_commit_locked`, `investment_commit_refunded`, `investment_commit_settled`.
- Alert if orphan commitments (locked > 24h past expired) – detection query.

## Backfill / Migration Strategy
- Existing `investment_interests` remain source of truth for initial amounts.
- Script to convert all existing interests to commitments (optional) if we want retroactive locking.

## Future Extensions
| Feature | Note |
|---------|------|
| Partial settlement rounds | Multi-tranche logic |
| Multi-currency escrow | Add currency conversions & hedging table |
| Dispute / arbitration | Escrow hold status transition with dispute logs |
| Fee extraction | Platform fee wallet skim at settlement time |

## Appendix: Example Pseudocode (commitFunds)
```php
function commitFunds(PDO $db, string $interestId, float $amount, string $investorId) {
  $db->beginTransaction();
  $interest = lockInterestRow($db, $interestId); // SELECT ... FOR UPDATE
  $investment = lockInvestmentRow($db, $interest['investment_id']);
  validateInterestEligibility($interest, $investment, $amount);
  $wallet = lockWallet($db, $investorId);
  if ($wallet['balance_htg'] < $amount) throw new InsufficientFunds();
  insertCommitment(... pending_lock ...);
  createWalletTx(... -amount, type=investment_lock ...);
  updateCommitmentStatus(... locked ...);
  if (newLockedTotal($investment) >= $investment['funding_goal']) markInvestmentFunded();
  $db->commit();
}
```

---
This document is a living design—revise as implementation details emerge.
