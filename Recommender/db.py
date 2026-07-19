"""
db.py
Connects to the same MySQL database your PHP app uses (Includes/db_connect.php)
and loads the tables the recommender needs as pandas DataFrames.

NOTE: we deliberately do NOT use pandas.read_sql() here. It has a known
compatibility issue with raw PyMySQL (non-SQLAlchemy) connections — pandas
even warns about this ("pandas only supports SQLAlchemy connectable...").
In testing against real data, it silently corrupted column values (a `time`
column came back containing the literal string "time" for every row).
Fetching rows manually via a DictCursor and building DataFrames from the
resulting list-of-dicts sidesteps that entirely and is what actually works.
"""

import pymysql
import pandas as pd
from config import DB_CONFIG


def get_connection():
    return pymysql.connect(
        host=DB_CONFIG["host"],
        user=DB_CONFIG["user"],
        password=DB_CONFIG["password"],
        database=DB_CONFIG["database"],
        port=DB_CONFIG["port"],
        cursorclass=pymysql.cursors.DictCursor,
    )


def _fetch_df(conn, query, columns=None):
    """Runs `query` and returns a DataFrame built directly from the rows,
    bypassing pandas.read_sql(). If the query returns zero rows, `columns`
    (if given) ensures the DataFrame still has the right column names
    instead of being a completely empty/columnless DataFrame."""
    with conn.cursor() as cur:
        cur.execute(query)
        rows = cur.fetchall()
    if not rows and columns:
        return pd.DataFrame(columns=columns)
    return pd.DataFrame(rows)


def load_all():
    """
    Returns a dict of DataFrames: events, interests, user_interests,
    participation, feedback, students, attendance.
    """
    conn = get_connection()
    try:
        events = _fetch_df(conn, """
            SELECT e.id, e.interest_id, e.created_by, e.title, e.date, e.time,
                   e.location, e.description, i.name AS interest_name
            FROM events e
            LEFT JOIN interests i ON e.interest_id = i.id
        """, columns=["id", "interest_id", "created_by", "title", "date", "time",
                       "location", "description", "interest_name"])

        interests = _fetch_df(conn, "SELECT id, name FROM interests",
                               columns=["id", "name"])

        user_interests = _fetch_df(conn, "SELECT user_id, interest_id FROM user_interests",
                                    columns=["user_id", "interest_id"])

        participation = _fetch_df(conn, "SELECT user_id, event_id FROM participation",
                                   columns=["user_id", "event_id"])

        feedback = _fetch_df(conn, """
            SELECT id AS feedback_id, user_id, event_id, rating, comment
            FROM feedback
        """, columns=["feedback_id", "user_id", "event_id", "rating", "comment"])

        students = _fetch_df(conn, """
            SELECT id, name, department, year FROM users WHERE role = 'student'
        """, columns=["id", "name", "department", "year"])

        # Populated once you've run schema_updates.sql and started marking
        # attendance from the faculty dashboard. Empty DataFrame otherwise.
        try:
            attendance = _fetch_df(conn, "SELECT event_id, student_id, status FROM attendance",
                                    columns=["event_id", "student_id", "status"])
        except Exception:
            attendance = pd.DataFrame(columns=["event_id", "student_id", "status"])

        return {
            "events": events,
            "interests": interests,
            "user_interests": user_interests,
            "participation": participation,
            "feedback": feedback,
            "students": students,
            "attendance": attendance,
        }
    finally:
        conn.close()