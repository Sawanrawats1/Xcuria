# Xcuria — AI/ML Layer

This folder is a standalone Python microservice that adds four AI/ML
features on top of your existing PHP/MySQL Xcuria app, **without changing
any of your existing database tables** (only one new table, `attendance`,
is added — see below). It reads from the same `xcuria` database your PHP
app already writes to.

## The four features

| # | Feature | File(s) | Type |
|---|---|---|---|
| 1 | Personalized recommendation engine | `recommender.py` | Content-based + collaborative filtering |
| 2 | Attendance-driven suitability scoring | `suitability.py`, `attendance` table | Explainable weighted scoring |
| 3 | Sentiment analysis on feedback | `sentiment.py` | NLP (lexicon-based, VADER) |
| 4 | Engagement analytics dashboard | `Dashboard/admin.php` (pure PHP+SQL+Chart.js) | Data visualization, not ML |

Analytics is deliberately plain SQL + charts, not a Python/ML feature — it's
there for demo value and admin visibility, and doesn't need to pretend to
be AI to be useful. Keep it framed that way in your report.

## Setup

```bash
cd Recommender
pip install -r requirements.txt
```

**Run the attendance migration once** (adds the `attendance` table used by
feature #2 — doesn't touch anything else):
```bash
mysql -u root -P 3309 xcuria < schema_updates.sql
```
(Or paste `schema_updates.sql`'s contents into phpMyAdmin → SQL tab.)

Edit `config.py` only if your MySQL host/port/credentials differ from
`Includes/db_connect.php` — as shipped, they already match.

Start the service:
```bash
python app.py
```
Leave it running on `http://localhost:5000` alongside XAMPP.

## What's already wired into your PHP files

- **`Dashboard/student.php`** — new "🎯 Recommended For You" section above
  Upcoming Events, calling `recommendations.php`.
- **`Dashboard/faculty.php`** — in the Participants section: attendance
  checkboxes + "Save Attendance" button per event (→ `mark_attendance.php`),
  and a "🎯 View Suitability Ranking" button per event (→ `suitability.php`).
  In the Feedback section: sentiment badges next to each comment, plus an
  aggregate sentiment overview (→ `sentiment.php`).
- **`Dashboard/admin.php`** — new "Analytics" sidebar link opening a section
  with: overall attendance rate, most popular categories chart, and
  department-wise engagement chart. Pure PHP/SQL, no Python call needed.

All PHP proxies (`recommendations.php`, `suitability.php`, `sentiment.php`,
`mark_attendance.php`) fail gracefully if the Python service isn't running —
the relevant UI section just hides itself or shows a message, the rest of
your site keeps working normally. This matters for your demo: if the ML
service crashes mid-viva, the core site doesn't break.

## How each model works (for your report / seminar)

**1. Recommendation engine** — every event becomes a TF-IDF vector
(title + category + description). Every student gets a profile vector from
events they've engaged with (weighted by feedback rating when given),
falling back to declared interests for cold-start students. Combined with
item-based collaborative filtering (built from the student×event interaction
matrix) into a hybrid score. Benchmark it with:
```bash
python evaluate.py --k 5
```
This does a leave-one-out precision@k comparison against your **existing**
plain category-filter (the baseline you already had before any ML). Needs
students with 2+ past participations to produce a number — seed some test
data if you don't have enough yet.

**2. Suitability scoring** — directly answers your original project's core
ask: *"help faculty easily find who is suitable for a given activity."*
For a specific event, ranks its *registered* students by:
`0.5 × attendance_rate (in that interest category) + 0.3 × participation_count (normalized) + 0.2 × avg_rating_given (normalized)`.
Every factor is returned alongside the score — not a black box — so you can
explain in your viva exactly why a student ranks where they do. Falls back
to a neutral 0.5 attendance component (documented via
`attendance_data_available: false` in the API response) until you've
actually marked some attendance.

**3. Sentiment analysis** — uses VADER, a published lexicon/rule-based
sentiment analyzer (Hutto & Gilbert, 2014), not a trained classifier. **Be
upfront about why** if asked in your viva: a trained classifier needs
labeled data (comments manually tagged positive/negative) that you don't
have; VADER needs no training data and is designed specifically for short,
informal text like feedback comments. **Known limitation, worth stating
honestly in your report**: VADER can misjudge mixed or qualified sentences
(e.g. "it was okay, poorly organized though" scored positive in testing) —
a real limitation of lexicon-based methods with negation/contrast, and a
legitimate "future work" point (swap in a trained classifier once you have
enough labeled feedback).

**4. Analytics dashboard** — plain aggregate SQL (most popular categories,
department engagement, attendance rate) rendered with Chart.js. No ML
claim attached to this one — it's there to make the admin view data-driven
and demo well, nothing more.

## Honest limitations to state in your report (don't hide these)

- **Data scale**: a single college over one semester is a small dataset for
  both the recommender and the suitability scores. Report actual numbers
  from `evaluate.py`, don't inflate them.
- **Cold start**: brand-new students with no history get recommendations
  based only on declared interests until they build up activity history —
  this is standard and expected behavior for any recommender system, worth
  naming explicitly rather than presenting as a flaw you missed.
- **Sentiment on mixed sentences**: see above.

## Files

| File | Purpose |
|---|---|
| `config.py` | DB credentials (matches `Includes/db_connect.php`), hybrid weight, top-N |
| `db.py` | Loads events/interests/participation/feedback/attendance from MySQL |
| `recommender.py` | Content-based + CF + hybrid recommender + baseline |
| `suitability.py` | Attendance-driven suitability scoring |
| `sentiment.py` | VADER-based sentiment analysis |
| `app.py` | Flask API: `/recommend`, `/suitability`, `/sentiment`, `/health` |
| `evaluate.py` | precision@k benchmark for the recommender vs baseline |
| `schema_updates.sql` | Adds the `attendance` table (run once) |
