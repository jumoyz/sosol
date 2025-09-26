# Investments Feature (MVP) – Implementation Summary

Date: 2025-09-20
Status: MVP complete (Create, List, Detail, Pledge / Interest Recording)

## 1. Overview
The Investments feature enables founders (entrepreneurs) to list funding opportunities and investors to express interest (pledge). This is a lightweight first phase designed to evolve toward escrow, confirmation workflows, and sector-based matching.

## 2. Core Pages
- `?page=investments` – Listing with search, sector filter, status filter, progress bars, interest counts.
- `?page=investment-create` – Form to create a new opportunity (draft or open, public/private visibility).
- `?page=investment-details&id=...` – Full detail view with progress, pitch links, investor interest list, and pledge form (for non-owner users).

## 3. Database Objects
### Table: investments
| Column | Type | Notes |
|--------|------|-------|
| id | CHAR(36) PK | UUID v4 |
| user_id | CHAR(36) FK users(id) | Creator / owner |
| title | VARCHAR(150) | Required |
| sector | VARCHAR(80) | Filterable |
| funding_goal | DECIMAL(14,2) | Required > 0 |
| amount_raised | DECIMAL(14,2) | Sum of current interests (simple additive) |
| equity_offered | DECIMAL(5,2) NULL | Percentage or return; optional |
| pitch_deck | VARCHAR(255) NULL | URL placeholder (file upload future) |
| video_url | VARCHAR(255) NULL | Optional video pitch link |
| description | TEXT NULL | Business summary |
| end_date | DATE NULL | Funding round end |
| status | ENUM(draft,open,funded,closed,cancelled) | Creation limited to draft/open |
| visibility | ENUM(public,private) | Public listing filter |
| archived | TINYINT(1) | Soft archival flag |
| sector_tags | JSON NULL | Future multi-tag / recommendation |
| created_at / updated_at | DATETIME | Managed automatically |

### Table: investment_interests
| Column | Type | Notes |
|--------|------|-------|
| id | CHAR(36) PK | UUID v4 |
| investment_id | CHAR(36) FK investments(id) | Cascade delete |
| investor_id | CHAR(36) FK users(id) | Pledging user |
| amount_pledged | DECIMAL(14,2) | > 0 |
| message | VARCHAR(500) NULL | Optional note |
| status | ENUM(interested,confirmed,declined,withdrawn) | Only 'interested' used now |
| created_at / updated_at | DATETIME | Tracking |

## 4. Business Logic (MVP)
- Creating an investment sets `amount_raised = 0.00` and chosen `status` (draft or open).
- Pledge (interest) submission:
  - Inserts into `investment_interests` with status `interested`.
  - Immediately increments `amount_raised` (simple additive model – will refine later).
  - Auto-flips status to `funded` if `amount_raised >= funding_goal` after update.
- Owner cannot pledge to own investment.
- Remaining need check prevents over-pledges beyond goal.

## 5. Validation Rules
- Title length >= 3.
- Sector required.
- funding_goal > 0.
- equity_offered nullable; if present 0–100.
- end_date must not be in past.
- status limited to {draft, open} at creation.
- visibility limited to {public, private}.
- Pledge amount > 0 and ≤ remaining need.

## 6. Activity Logging (Hooks)
Logged via `logActivity()`:
- `investment_interest` (investor perspective)
- `investment_received_interest` (owner perspective)
- `investment_funded` (on threshold reach)

(Notifications table not directly integrated yet—this is a future step.)

## 7. Security Notes
- Auth required for all investment pages.
- Owner gating on pledge form.
- Basic server-side validation across creation + pledge flows.
- No file uploads yet (avoids storage security concerns in MVP).

## 8. Inline TODO Anchors
- Escrow integration placeholder in pledge form footer comment.
- Recommendation / related investments panel placeholder in `investments.php` and `investment-details.php`.
- Owner management controls stub (status transitions, export) in details sidebar.

## 9. Deferred / Next Phase Candidates
| Category | Enhancement | Notes |
|----------|-------------|-------|
| Funding Logic | Separate committed vs soft interest | Add `amount_committed` + interest status transitions |
| Escrow | Wallet-based locking & release | Requires wallet transaction + refund logic |
| Owner Controls | Change status (draft→open→closed), archive | Add endpoint + permission checks |
| Matching Engine | Sector clustering + tag weighting | Use `sector_tags` JSON; build recommendation query |
| Notifications | Direct user notifications on interest & funded | Extend notifications subsystem |
| Reporting | Export investor list (CSV) | Admin/owner only |
| Risk & Compliance | Add KYC gating for investor pledges | Check `users.kyc_verified` before pledging |
| Valuation Fields | pre_money_valuation, implied_post_money | Display in owner + detail view |
| Attachments | Dedicated table for documents | Replace URL fields with managed assets |
| Rate Limiting | Prevent spam pledges | Session/IP + user-based throttle |
| Pagination | Large dataset scalability | Cursor or OFFSET/LIMIT with indexes |

## 10. Index & Performance Notes
- Recommended additional composite index: `(status, sector)` if sector/status filtering becomes heavy.
- Consider partial materialization of progress for analytics (later).

## 11. Potential Data Integrity Improvements
- Replace immediate increment with derived query of confirmed interests only.
- Add trigger or scheduled job to auto-close expired open rounds (end_date < today and not funded => status closed).

## 12. Testing Suggestions
Manual:
1. Create draft investment – ensure not visible unless status filter changed.
2. Create open investment – verify listing presence.
3. Pledge from a second user – check progress bar increments.
4. Pledge to reach goal – ensure status flips to funded and further pledge blocked (remaining need check).
5. Attempt over-pledge – expect validation error.

Future Automated (Proposed PHPUnit or lightweight CLI harness):
- Investment creation validation matrix.
- Pledge concurrency (simulate race) to test boundary funding goal.

## 13. Security Hardening Roadmap
- Add CSRF tokens to creation & pledge forms.
- Sanitize long text (strip disallowed HTML if WYSIWYG added).
- Ownership re-check server-side before any future status transitions.

## 14. Migration Rollback (Manual Strategy)
To rollback (manual):
```sql
DROP TABLE IF EXISTS investment_interests;
DROP TABLE IF EXISTS investments;
```
(Only if safe and no dependent production data.)

## 15. Summary
The Investments MVP establishes the structural foundation (data model + key flows). It is intentionally conservative (no irreversible fund movement yet) and ready for incremental enhancement (escrow, confirmations, matching, analytics). Suggestions above are ordered to deliver compounding platform value with manageable risk.

---
Maintainer Notes: Keep future schema changes additive; avoid destructive alterations to support forward compatibility for analytics and compliance later.

## 16. Related Design & Test Artifacts (Added Later)
- Escrow & wallet integration draft: see `ESCROW_WALLET_DESIGN.md` for proposed commitment lifecycle, tables, flows, and state machine.
- Test scaffold introduced (PHPUnit) with basic logic tests in `tests/InvestmentLogicTest.php`; run via `composer test` after installing dev dependencies.
- UI polish implemented: pagination, sorting, progress color thresholds, “Ending Soon” and “Funded” badges.
- Future test coverage targets: pledge concurrency race, funded threshold flip, pagination query correctness, sorting field safety (whitelist enforcement).
