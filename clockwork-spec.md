# Project Specification: Clockwork Life (Native MVP)

## 1. Project Overview & Philosophy

"Clockwork Life" is a minimalist, local-first iOS tracking application backed by a self-hosted PHP server. It tracks a user's alignment with a highly structured daily routine using temporal precision tracking rather than binary streaks.

### The 4 Concentric Core Rings

To prevent cognitive overload and show a clear topography of the day, habits are grouped into four visual rings (the only graphical elements in an otherwise pure monospaced terminal aesthetic):

- **Base Ring (The Core):** Fundamental pillars (Waking up, bedtime, digital curfews). Rule: Strict.
- **Health Ring (The Body):** Physical maintenance (Cycling commutes, workouts, hydration). Rule: Show-Up Bonus.
- **Growth Ring (The Mind):** Self-improvement (Certification studies, deep work blocks). Rule: Flexible.
- **Spirit Ring (The Soul):** Grounding routines (Prayer, Sunday Mass, mindfulness). Rule: Flexible.

### Dynamic Metric Scoring Rules

Scoring is based on **temporal precision** — how close the actual clock-in is to the target time. `TimeVariance` is always measured in **minutes**. The engine is **asymmetric**: a *late* clock-in decays from the target moment, while an *early* clock-in is treated leniently (see "Early vs. Late Clock-In" below).

The three strictness types define the late-side decay curve:

- **Strict** (Base ring): Tight ~30-minute window. `Score = max(0, 100 - (TimeVariance * 3.33))`
- **Flexible** (Growth, Spirit rings): Broad ~180-minute window. `Score = max(0, 100 - (TimeVariance * 0.55))`
- **Show-Up Bonus** (Health ring): Striking execution baseline. The score requires `completed == true` to earn anything — if not completed, the score is **0**. When completed, the user instantly earns 50% credit, and the remaining 50% scales linearly down to 0 across a 120-minute variance window: `Score = 50 + 50 * max(0, (120 - TimeVariance) / 120)`.

### Early vs. Late Clock-In

The grace behavior depends on whether the user clocked in before or after the target time.

**Late clock-in** (`actual >= target`, `TimeVariance = actual - target`): apply the strictness-type formulas above directly. Decay begins immediately at the target moment (no flat late grace).

**Early clock-in** (`actual < target`, `magnitude = target - actual`):

- **Flexible & Show-Up Bonus** (Health, Growth, Spirit): any early clock-in is an automatic **100%** score.
- **Strict** (Base): a **40-minute** early grace window scores **100%**. Beyond 40 minutes early, variance is reduced by the grace and scaled with the standard strict formula: `ΔT = |target - actual| - 40`, then `Score = max(0, 100 - (ΔT * 3.33))`.

> Note: This asymmetry is intentional. For Strict habits, the *early* tolerance (40-minute flat grace) is deliberately wider than the *late* tolerance (~30-minute decay from the target moment).

### The Pause Engine

When a calendar date is flagged as `is_paused` (due to a Trek, Hike, or Holiday), that entire date is completely omitted from historical trend lines and aggregate metric calculations to protect mathematical consistency.

## 2. Tech Stack, Architecture & Style Guide

- **Client App:** Native Swift / SwiftUI (iOS 17+). Uses SwiftData for local-first offline execution.
- **Backend REST API:** Lightweight, type-safe PHP 8.3+ running stateless inside a home server Docker container.
- **Database Engine:** MariaDB storing relational sync states.
- **Authentication:** Sign in with Apple (SIWA) passing verified JWT tokens to the PHP server layer.
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
  apple_user_id VARCHAR(255) UNIQUE NOT NULL,
  email VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE habits (
  id VARCHAR(36) PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  category ENUM('base', 'health', 'growth', 'spirit') NOT NULL,
  strictness_type ENUM('strict', 'flexible', 'show_up_bonus') DEFAULT 'strict',
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

## 4. User Interface Architecture & Layout Design

### Onboarding & Initialization Presets

Upon initial setup via Apple Sign-In, the application automatically populates template presets so the user does not start with a blank UI:

- **The Foundations Preset:** Wake Up (06:30, Base, Strict), Bedtime (22:30, Base, Strict).
- **The Maintenance Preset:** Deep Maintenance Block (Last Saturday of month, Base, Flexible, Checklist enabled).

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