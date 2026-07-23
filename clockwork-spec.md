# Project Specification: Clockwork Life (Native MVP)

## 1. Project Overview & Philosophy

"Clockwork Life" is a minimalist, local-first iOS tracking application backed by a self-hosted PHP server. It tracks a user's alignment with a highly structured daily routine using temporal precision tracking rather than binary streaks.

### The 4 Concentric Core Rings (Visual Grouping)

To prevent cognitive overload and show a clear topography of the day, habits are grouped into four visual rings — the only graphical elements in an otherwise pure monospaced terminal aesthetic. **A ring is a category used for grouping and accent color only; it does not dictate how a habit is scored.** Each habit's scoring mode (its *strictness type*, below) is chosen independently, so any habit in any ring can be Strict, Flexible, Show-Up Bonus, or Chained.

- **Base Ring (The Core):** Fundamental pillars (waking up, bedtime, digital curfews).
- **Health Ring (The Body):** Physical maintenance (cycling commutes, workouts, hydration).
- **Growth Ring (The Mind):** Self-improvement (certification studies, deep-work blocks).
- **Spirit Ring (The Soul):** Grounding routines (prayer, Sunday Mass, mindfulness).

Each ring carries a single accent color (Base = Green, Health = Blue, Growth = Amber, Spirit = Purple). Any strictness suggestions a preset makes for these rings (Strict for pillars, Show-Up for the body, Flexible for study) are sensible **defaults**, not constraints — the user is free to assign any scoring mode to any habit.

### Dynamic Metric Scoring Rules

Scoring is based on **temporal precision** — how close the actual clock-in is to the target time. `TimeVariance` is always measured in **minutes**. The engine is **asymmetric**: a *late* clock-in decays from the target moment, while an *early* clock-in is treated leniently (see "Early vs. Late Clock-In" below).

The four strictness types are chosen per habit, independent of its ring. The first three are scored against the clock and define the late-side decay curve; the fourth is scored against a parent habit (see "Chained Habits" below):

- **Strict:** Tight ~30-minute window. `Score = max(0, 100 - (TimeVariance * 3.33))`
- **Flexible:** Broad ~180-minute window. `Score = max(0, 100 - (TimeVariance * 0.55))`
- **Show-Up Bonus:** Striking execution baseline. The score requires `completed == true` to earn anything — if not completed, the score is **0**. When completed, the user instantly earns 50% credit, and the remaining 50% scales linearly down to 0 across a 120-minute variance window: `Score = 50 + 50 * max(0, (120 - TimeVariance) / 120)`.
- **Chained (CHN):** Contextual habit stacking — scored on the *gap to a parent (anchor) habit*, ignoring the clock entirely. See "Chained Habits (CHN)" below.

### Early vs. Late Clock-In

The grace behavior depends on whether the user clocked in before or after the target time.

**Late clock-in** (`actual >= target`, `TimeVariance = actual - target`): apply the strictness-type formulas above directly. Decay begins immediately at the target moment (no flat late grace).

**Early clock-in** (`actual < target`, `magnitude = target - actual`):

- **Flexible & Show-Up Bonus** (Health, Growth, Spirit): any early clock-in is an automatic **100%** score.
- **Strict** (Base): a **40-minute** early grace window scores **100%**. Beyond 40 minutes early, variance is reduced by the grace and scaled with the standard strict formula: `ΔT = |target - actual| - 40`, then `Score = max(0, 100 - (ΔT * 3.33))`.

> Note: This asymmetry is intentional. For Strict habits, the *early* tolerance (40-minute flat grace) is deliberately wider than the *late* tolerance (~30-minute decay from the target moment).

### Chained Habits (CHN — Contextual Habit Stacking)

> **Status: specified, not yet implemented.** This mode is defined here as the source of truth; the schema, sync layer, and `ScoringEngine` do not implement it yet (see roadmap Phase 5).

Some habits are anchored to *another* habit rather than to the clock — e.g. "start a workout right after waking up." A **Chained** habit (`strictness_type = 'chained'`) references a **parent (anchor) habit** and a **target gap** in minutes, and is scored on how close the actual gap is to that target, completely ignoring the time of day.

- **Parent finish** = the parent entry's `actual_start_time` + `actual_duration_minutes`.
- **Actual gap** = the child's `actual_start_time` − parent finish.
- **On or under target** (`actual_gap <= chain_target_gap_minutes`): promptness earns full credit → **100%**.
- **Over target**: decays with the flexible curve → `Score = max(0, 100 - ((actual_gap - chain_target_gap_minutes) * 0.55))` (decay factor tunable).

**Parent Grace Rule:** if the parent habit has no entry for the day, or its entry is not `completed`, the child **detaches** — it is never penalized for the parent's miss. A detached child falls back to a Show-Up baseline (50% on completion). This keeps a broken chain from unfairly dragging the aggregate score down.

A chained habit's `target_start_time` is retained only for timeline ordering and display; it is not used in the CHN score.

### The Pause Engine

When a calendar date is flagged as `is_paused` (due to a Trek, Hike, or Holiday), that entire date is completely omitted from historical trend lines and aggregate metric calculations to protect mathematical consistency.

## 2. Tech Stack, Architecture & Style Guide

- **Client App:** Native Swift / SwiftUI (iOS 17+). Uses SwiftData for local-first offline execution.
- **Backend REST API:** Lightweight, type-safe PHP 8.3+ running stateless inside a home server Docker container.
- **Database Engine:** MariaDB storing relational sync states.
- **Authentication:** Sign in with Apple (SIWA) and Google Sign-In, both passing verified OpenID Connect JWTs to the PHP server layer. The backend routes a token to the right verifier by its `iss` claim; accounts are linked across providers by **verified email** (one person, one account). Only a provider-verified email is ever stored or used to link.
- **Version Control:** Managed globally via Git.

### Visual Style Guide (Strict Minimalist Terminal)

- **Background:** Absolute Black (`#000000`).
- **Typography:** 100% monospaced font family (System Monospaced or SF Mono). No sans-serif.
- **Colors:** Monochromatic (White/Gray scales) with single accent colors dedicated strictly to the 4 active rings (e.g., Base=Green, Health=Blue, Growth=Amber, Spirit=Purple).
- **UI Elements:** Use ASCII dividers (`+---`, `|`, `.---`) and strict text borders rather than standard iOS rounded cards. Buttons resemble terminal brackets: `[ RUN_CLOCK_IN ]`.

## 3. Relational Database Schema (MariaDB)

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  -- Multi-provider identity. Provider subjects are nullable + unique; a user may
  -- have Apple, Google, or both. `email` is the cross-provider link key (unique),
  -- and only a provider-verified email is ever stored.
  apple_user_id VARCHAR(255) UNIQUE NULL,
  google_user_id VARCHAR(255) UNIQUE NULL,
  email VARCHAR(255) UNIQUE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE habits (
  id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  category ENUM('base', 'health', 'growth', 'spirit') NOT NULL,  -- visual grouping only; does NOT dictate scoring
  strictness_type ENUM('strict', 'flexible', 'show_up_bonus', 'chained') DEFAULT 'strict',
  chain_parent_id VARCHAR(36) NULL,         -- CHN: self-reference to the anchor habit; soft ref, NO foreign key (see §5)
  chain_target_gap_minutes INT NULL,        -- CHN: desired minutes between the parent finishing and this habit starting
  schedule_type ENUM('weekly', 'monthly_relative', 'monthly_absolute') NOT NULL,
  schedule_value VARCHAR(50) NOT NULL,
  target_start_time TIME NOT NULL,
  target_duration_minutes INT NOT NULL,
  has_checklist TINYINT(1) DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE habit_checklists (
  id VARCHAR(36) PRIMARY KEY,
  habit_id VARCHAR(36) NOT NULL,
  task_name VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);

CREATE TABLE daily_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  log_date DATE NOT NULL,
  is_paused TINYINT(1) DEFAULT 0,
  pause_reason ENUM('Hike', 'Trek', 'Holiday', 'Other') NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_date (user_id, log_date)
);

CREATE TABLE habit_entries (
  id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  log_date DATE NOT NULL,
  habit_id VARCHAR(36) NOT NULL,
  actual_start_time TIME NULL,
  actual_duration_minutes INT NULL,
  completed TINYINT(1) DEFAULT 0,
  checklist_state JSON NULL,
  external_source VARCHAR(50) NULL,
  external_id VARCHAR(255) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);
```

> **Note:** The `strictness_type = 'chained'` value and the `chain_parent_id` /
> `chain_target_gap_minutes` columns above are the *specified* target schema for the
> CHN feature (roadmap Phase 5). The live `backend/db/schema.sql` has **not** been
> migrated to this yet — apply it as an additive migration when Phase 5 lands.
> `chain_parent_id` is intentionally a **soft reference** (no SQL foreign key) so
> sync batches never fail on parent/child ordering — see §5.

## 4. User Interface Architecture & Layout Design

### Onboarding & Initialization Presets

Upon initial setup via Apple Sign-In, the application automatically populates template presets so the user does not start with a blank UI:

- **The Foundations Preset:** Wake Up (06:30, Base, Strict), Bedtime (22:30, Base, Strict).
- **The Maintenance Preset:** Deep Maintenance Block (Last Saturday of month, Base, Flexible, Checklist enabled).
- **The Momentum Preset (Phase 5):** Morning Workout — **Health** ring but scored as **Chained**, anchored to "Wake Up" with a 15-minute target gap. Illustrates that ring (grouping) and strictness (scoring) are independent.

### Wireframe 1: Main Dashboard UI Terminal Aesthetic

```
+-------------------------------------------------------+
| CCW_OS v1.0.4                       [SYS_STATUS: ACTIVE] |
+-------------------------------------------------------+
|                                                         |
|  SYS_METRICS:                                          |
|  .-----------------------------------------------.     |
|  | ((    ⭕    ))   [BASE] ................ 95%  |     |
|  | (((   ⭕   )))   [HLTH] ........ 50%           |     |
|  | (((( [⭕] ))))   [GRW]  . 00%                  |     |
|  |((((( ⭕ )))))    [SPRT] ................ 100%  |     |
|  `-----------------------------------------------'     |
|                                                         |
|  [!] COMMAND: >> /exec/pause_today                     |
+-------------------------------------------------------+
| LOGGED_TIMELINE // 2026.06.20                          |
+-------------------------------------------------------+
|                                                         |
|  06:30 | [X] TASK_01: WAKE_UP_ANCHOR                   |
|         TRGT: 06:30 | ACTL: 06:32 | VAR: +02m          |
|         STAT: COMPLETED [STRICT_98%]                   |
|                                                         |
|  07:30 | [>] TASK_02: MORNING_CYCLING_COMMUTE          |
|         TRGT: 45m | ACTL: --m | VAR: --m               |
|         CMD : >> [ RUN_CLOCK_IN ]                       |
|                                                         |
|  09:00 | [ ] TASK_03: CKA_CERT_PREP                    |
|         TRGT: 60m | ACTL: --m | VAR: --m               |
|         STAT: PENDING                                  |
|                                                         |
+-------------------------------------------------------+
| SYS_LOG: 14:25:02 UTC // SYNC_STATUS: OK               |
+-------------------------------------------------------+
```

### Wireframe 2: Sub-Task Checklist View

```
+-------------------------------------------------------+
| CCW_OS // CONFIG_MODE -> DETAIL_VIEW                   |
+-------------------------------------------------------+
|                                                         |
|  IDENTIFIER : Monthly Deep Maintenance                 |
|  CADENCE    : Last Saturday of the Month               |
|  RING_GROUP : [ Base Ring ]                             |
|  METRIC     : [ Show-Up Bonus / Flexible 180m ]         |
|                                                         |
+-------------------------------------------------------+
| ROUTINE_SUB_CHECKLIST                                  |
|                                                         |
|  [X] Deep clean bathroom                               |
|  [X] Sharpen kitchen knives                             |
|  [ ] Scrub stainless steel pots (Baking Soda)           |
|  [ ] Fertilize balcony plants                           |
|                                                         |
+-------------------------------------------------------+
| METRIC_COMPUTATION_OVERRIDE                            |
|                                                         |
|  Target Start Time : 10:00 AM                           |
|  Actual Start Time : 10:15 AM                           |
|                                                         |
|  CURRENT_COMPLIANCE_INDEX: [ 92% ]                       |
+-------------------------------------------------------+
```

## 5. Synchronization Protocol (Last-Write-Wins Delta Sync)

To keep network payloads minimal, synchronization uses a client-driven timeline approach over HTTPS.

`POST /api/sync`

**Headers Required:** `Authorization: Bearer <Apple_Identity_Token>`

**Request Payload Structure:**

```json
{
  "last_sync_timestamp": "2026-06-20 08:00:00",
  "mutations": {
    "habits": [],
    "daily_logs": [],
    "habit_entries": []
  }
}
```

**Server Sync Engine Logic:**

1. Decodes and verifies the Apple ID Token. Resolves internal `user_id`.
2. Iterates over mutations. Performs an `ON DUPLICATE KEY UPDATE` operation for records matching the incoming schema.
3. Queries the database for any records across all 3 tables where `updated_at > last_sync_timestamp` that did not originate from this exact payload.
4. Returns the modern payload delta and the current Server UTC Timestamp to set as the client's next `last_sync_timestamp`.

> **CHN sync note:** `chain_parent_id` (Phase 5) is a **soft reference** — it holds
> another habit's client UUID but has no SQL foreign key. Because all `habits` sync
> as one array under last-write-wins, a hard self-FK would break whenever a child row
> arrives before its parent in the same payload. The link is validated in the app
> layer instead; a dangling `chain_parent_id` simply reads as "detached," which is
> exactly the Parent Grace fallback (§1).

## 6. Implementation Roadmap & Prompt Milestones

- [ ] **Phase 1: Docker, Git, & PHP API Foundations**
  - Initialize Git repository and Docker environment.
  - Spin up MariaDB tables and write a minimalist PHP script executing native Sign in with Apple signature token checking.

- [ ] **Phase 2: SwiftData Model Layers & Onboarding Presets**
  - Construct local SwiftData relational entities matching schema variables.
  - Build the initial welcome check-list script injecting starter habits automatically.

- [ ] **Phase 3: The 4-Ring SwiftUI Dashboard & Checklist Component**
  - Design the chronological daily interface displaying active items matching today's exact date evaluation.
  - Render the 4 concentric category precision rings using SwiftUI shapes, supporting nested checklist dropdown modules.

- [ ] **Phase 4: Sync Worker Engine & Exclusion Math**
  - Implement background URLSession timeline synchronization using Last-Write-Wins timestamps.
  - Verify calculation parameters ensuring paused logging days are correctly removed from metric equations.

- [ ] **Phase 5: Master-Spec Reconciliation (decoupling + CHN)**
  - **Decouple ring from strictness:** treat `category` as visual grouping only and let the habit editor pick any `strictness_type` per habit (the schema already stores them separately — this is a UI/preset change).
  - **Add the Chained (CHN) scoring mode:** additive migration for `chain_parent_id` + `chain_target_gap_minutes` and the `'chained'` enum value; extend `SyncSchema` to carry the new columns; implement gap scoring and the Parent Grace Rule in the `ScoringEngine`.
  - **Client backlog (from the plain-English master spec):** Siri voice logging ("mark … done in Clockwork"), retrospective `[-]`/`[+]` 5-minute step editing, a one-tap Reset-to-Target macro, and a single-focus interactive home-screen widget.