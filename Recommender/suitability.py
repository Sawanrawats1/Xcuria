"""
suitability.py

Answers the original project goal directly: "help faculty/groups easily find
who is suitable for a given activity." Unlike a plain registration list, this
ranks a specific event's registered students using their REAL engagement
history in that event's interest category:

    score = w1 * attendance_rate + w2 * participation_count (normalized)
            + w3 * avg_feedback_rating_given (normalized)

- attendance_rate: of all past events in this category the student
  registered for, what fraction did they actually attend (present)?
  This is the "attendance-driven" part — it needs the `attendance` table.
- participation_count: how many events in this category has the student
  registered for overall (capped/normalized so one very active student
  doesn't dominate the scale).
- avg_feedback_rating_given: average rating (1-5) the student has given
  for past events in this category, as a proxy for genuine engagement
  rather than passive/no-show registration.

Each factor is returned alongside the final score (not just a single
opaque number) — this is deliberate: faculty should be able to see WHY a
student is ranked highly, and you should be able to explain the scoring
transparently in your viva rather than presenting a black box.

If the `attendance` table is empty (schema_updates.sql not run yet, or no
attendance marked so far), attendance_rate defaults to a neutral 0.5 for
everyone and the ranking falls back to participation + rating signal only —
this is stated explicitly in the returned response so the faculty UI (and
you, in your report) can be honest about data maturity.
"""

import pandas as pd
import numpy as np

# Tune these after you have real attendance data — see README for guidance.
WEIGHTS = {
    "attendance_rate": 0.5,
    "participation_count": 0.3,
    "avg_rating_given": 0.2,
}


def _normalize(series: pd.Series) -> pd.Series:
    if series.max() == series.min():
        return series.apply(lambda x: 0.5 if series.max() > 0 else 0.0)
    return (series - series.min()) / (series.max() - series.min())


def suitability_ranking(data: dict, event_id: int, top_n: int = 10):
    events = data["events"]
    participation = data["participation"]
    feedback = data["feedback"].copy()
    attendance = data["attendance"]
    students = data["students"]

    # Defensive: coerce rating to numeric in case MySQL hands it back as
    # string/object dtype, same issue as recommender.py.
    feedback["rating"] = pd.to_numeric(feedback["rating"], errors="coerce")

    target_event = events[events["id"] == event_id]
    if target_event.empty:
        return {"status": "error", "message": "Event not found"}

    interest_id = target_event.iloc[0]["interest_id"]

    # Students actually registered for THIS event — we only rank among them,
    # since the point is to help faculty pick among people who already signed up.
    # participation table uses column name `user_id` (see Dashboard/participate.php)
    registered_ids = participation[participation["event_id"] == event_id]["user_id"].tolist()

    if not registered_ids:
        return {"status": "success", "event_id": event_id, "attendance_data_available": False,
                "rankings": []}

    # All events in the same interest category (for building each student's history)
    category_event_ids = set(events[events["interest_id"] == interest_id]["id"])

    rows = []
    attendance_data_available = len(attendance) > 0

    for student_id in registered_ids:
        # Past registrations by this student in this category
        student_participation = participation[
            (participation["user_id"] == student_id)
            & (participation["event_id"].isin(category_event_ids))
        ]
        participation_count = len(student_participation)

        # Attendance rate within that category
        if attendance_data_available:
            student_attendance = attendance[
                (attendance["student_id"] == student_id)
                & (attendance["event_id"].isin(category_event_ids))
            ]
            if len(student_attendance) > 0:
                attendance_rate = (student_attendance["status"] == "present").mean()
            else:
                attendance_rate = 0.5  # no attendance record yet for this student/category
        else:
            attendance_rate = 0.5  # global fallback — table not populated yet

        # Average rating this student has GIVEN for past events in this category
        student_feedback = feedback[
            (feedback["user_id"] == student_id)
            & (feedback["event_id"].isin(category_event_ids))
        ]
        avg_rating_given = student_feedback["rating"].mean() if len(student_feedback) > 0 else 3.0
        if pd.isna(avg_rating_given):
            avg_rating_given = 3.0

        rows.append({
            "student_id": int(student_id),
            "participation_count": participation_count,
            "attendance_rate": float(attendance_rate),
            "avg_rating_given": float(avg_rating_given),
        })

    df = pd.DataFrame(rows)
    df["participation_count_norm"] = _normalize(df["participation_count"])
    df["avg_rating_given_norm"] = df["avg_rating_given"] / 5.0  # 1-5 scale -> 0-1

    df["score"] = (
        WEIGHTS["attendance_rate"] * df["attendance_rate"]
        + WEIGHTS["participation_count"] * df["participation_count_norm"]
        + WEIGHTS["avg_rating_given"] * df["avg_rating_given_norm"]
    )

    df = df.merge(students, left_on="student_id", right_on="id", how="left")
    df = df.sort_values("score", ascending=False).head(top_n)

    rankings = df[[
        "student_id", "name", "department", "year", "score",
        "attendance_rate", "participation_count", "avg_rating_given"
    ]].to_dict(orient="records")

    return {
        "status": "success",
        "event_id": event_id,
        "attendance_data_available": attendance_data_available,
        "rankings": rankings,
    }
