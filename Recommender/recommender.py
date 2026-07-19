"""
recommender.py

Implements the two models described in the project proposal:

1. CONTENT-BASED FILTERING
   Each event is represented as a text document (title + interest category +
   description) and turned into a TF-IDF vector. Each student is represented
   by the average TF-IDF vector of the events they've engaged with (weighted
   by their feedback rating when available), plus a boost from their
   explicitly chosen interests (user_interests table). Recommending = ranking
   upcoming events by cosine similarity to the student's profile vector.

2. COLLABORATIVE FILTERING (item-based)
   Built from the user-event interaction matrix (participation = implicit
   signal, feedback.rating = explicit signal when present). Two events are
   "similar" if the same students engaged with both. A student's score for an
   unseen event is a weighted sum of their past interactions with events
   similar to it.

3. HYBRID
   final_score = alpha * content_score + (1 - alpha) * cf_score
   This is what the /recommend endpoint returns, ranked descending, with
   already-participated and past events excluded.

Everything here operates on the pandas DataFrames returned by db.load_all(),
so it can be unit-tested / evaluated without a live Flask server.
"""

import numpy as np
import pandas as pd
from datetime import datetime
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

from config import HYBRID_ALPHA


def _combine_date_time(events: pd.DataFrame) -> pd.Series:
    """
    Combines `date` + `time` columns into one datetime, handling the case
    where MySQL hands back `time` as a datetime.timedelta object (common
    with pymysql for TIME columns) instead of a plain string — string-
    concatenating a timedelta produces something pd.to_datetime can't
    parse, which silently produces NaT for every row (this hit real data
    during testing: every single event parsed as NaT until this fix).
    """
    dates = pd.to_datetime(events["date"], errors="coerce")

    def _time_to_timedelta(val):
        if pd.isna(val):
            return pd.Timedelta(0)
        if isinstance(val, pd.Timedelta):
            return val
        try:
            return pd.to_timedelta(str(val))
        except Exception:
            return pd.Timedelta(0)

    times = events["time"].apply(_time_to_timedelta)
    return dates + times


class XcuriaRecommender:
    def __init__(self, data: dict):
        self.events = data["events"].copy()
        self.interests = data["interests"].copy()
        self.user_interests = data["user_interests"].copy()
        self.participation = data["participation"].copy()
        self.feedback = data["feedback"].copy()

        self._prepare()

    # ------------------------------------------------------------------ #
    # Setup
    # ------------------------------------------------------------------ #
    def _prepare(self):
        # Text field used for content-based similarity
        self.events["text"] = (
            self.events["title"].fillna("")
            + " "
            + self.events["interest_name"].fillna("")
            + " "
            + self.events["description"].fillna("")
        )

        self.event_ids = self.events["id"].tolist()
        self.event_index = {eid: i for i, eid in enumerate(self.event_ids)}

        # TF-IDF matrix over all events (upcoming + past — past events are
        # needed to build user profiles from history even though they won't
        # be recommended themselves)
        self.vectorizer = TfidfVectorizer(stop_words="english")
        self.event_vectors = self.vectorizer.fit_transform(self.events["text"])

        # Interaction matrix for collaborative filtering:
        # rows = events, columns = users, value = rating if feedback exists
        # else 1.0 for plain participation (implicit signal)
        interactions = self.participation.merge(
            self.feedback[["user_id", "event_id", "rating"]],
            on=["user_id", "event_id"],
            how="left",
        )
        # Defensive: MySQL may hand back rating as a string/object dtype
        # depending on the actual column type used in your database, so
        # coerce explicitly rather than assuming it's already numeric.
        interactions["rating"] = pd.to_numeric(interactions["rating"], errors="coerce")
        interactions["weight"] = interactions["rating"].fillna(3.0)  # neutral
        # normalize rating 1-5 -> 0.2-1.0 so it blends with implicit 1.0 signal
        interactions["weight"] = interactions["weight"].astype(float) / 5.0

        self.interactions = interactions

        # Build event-user matrix for item-based CF
        if len(interactions) > 0:
            pivot = interactions.pivot_table(
                index="event_id", columns="user_id", values="weight", fill_value=0.0
            )
            # reindex so every known event has a row, even ones with 0 interactions
            pivot = pivot.reindex(self.event_ids, fill_value=0.0)
            self.item_user_matrix = pivot
            if pivot.shape[0] > 1:
                self.item_similarity = cosine_similarity(pivot.values)
            else:
                self.item_similarity = np.zeros((1, 1))
        else:
            self.item_user_matrix = pd.DataFrame(index=self.event_ids)
            self.item_similarity = np.zeros((len(self.event_ids), len(self.event_ids)))

    # ------------------------------------------------------------------ #
    # Content-based score
    # ------------------------------------------------------------------ #
    def _user_profile_vector(self, user_id):
        history = self.interactions[self.interactions["user_id"] == user_id]

        # Interest-based component: always computed if the student has
        # declared interests, regardless of whether they also have history.
        # Fixes a real bug found in testing — a student with any
        # participation history would previously have their explicit
        # interest selections ignored entirely, relying only on text
        # similarity to past events (which fails when event descriptions
        # are short/generic and don't textually overlap, even though the
        # category genuinely matches what the student selected).
        liked_interest_ids = self.user_interests[
            self.user_interests["user_id"] == user_id
        ]["interest_id"].tolist()

        interest_vector = None
        if liked_interest_ids:
            mask = self.events["interest_id"].isin(liked_interest_ids)
            idxs = self.events[mask].index.tolist()
            if idxs:
                interest_vector = np.asarray(self.event_vectors[idxs].mean(axis=0))

        # History-based component
        history_vector = None
        if len(history) > 0:
            idxs = [self.event_index[eid] for eid in history["event_id"] if eid in self.event_index]
            weights = history["weight"].values
            if idxs:
                vecs = self.event_vectors[idxs]
                history_vector = np.asarray(vecs.multiply(weights.reshape(-1, 1)).mean(axis=0))

        # Blend whichever signals are available
        if interest_vector is not None and history_vector is not None:
            return (interest_vector + history_vector) / 2.0
        if interest_vector is not None:
            return interest_vector
        if history_vector is not None:
            return history_vector
        return None  # true cold start — no signal at all

    def content_scores(self, user_id):
        """Returns a dict {event_id: score} for ALL events (0-1 range)."""
        profile = self._user_profile_vector(user_id)
        if profile is None:
            return {eid: 0.0 for eid in self.event_ids}

        sims = cosine_similarity(profile, self.event_vectors).flatten()
        return {eid: float(sims[i]) for i, eid in enumerate(self.event_ids)}

    # ------------------------------------------------------------------ #
    # Collaborative filtering score
    # ------------------------------------------------------------------ #
    def cf_scores(self, user_id):
        """Returns a dict {event_id: score} using item-based CF."""
        history = self.interactions[self.interactions["user_id"] == user_id]
        if len(history) == 0 or self.item_similarity.sum() == 0:
            return {eid: 0.0 for eid in self.event_ids}

        scores = {}
        seen_idxs = [self.event_index[eid] for eid in history["event_id"] if eid in self.event_index]
        seen_weights = history["weight"].values

        for eid in self.event_ids:
            target_idx = self.event_index[eid]
            sims = self.item_similarity[target_idx, seen_idxs]
            if sims.sum() == 0:
                scores[eid] = 0.0
            else:
                scores[eid] = float(np.dot(sims, seen_weights) / (sims.sum() + 1e-9))

        return scores

    # ------------------------------------------------------------------ #
    # Hybrid — this is what the API exposes
    # ------------------------------------------------------------------ #
    def recommend(self, user_id, top_n=5, alpha=HYBRID_ALPHA, only_upcoming=True, apply_threshold=True):
        content = self.content_scores(user_id)
        cf = self.cf_scores(user_id)

        already_seen = set(
            self.participation[self.participation["user_id"] == user_id]["event_id"]
        )

        now = pd.Timestamp(datetime.now())
        events = self.events.copy()
        if only_upcoming:
            dt = _combine_date_time(events)
            events = events[dt > now]

        candidates = events[~events["id"].isin(already_seen)].copy()

        def score_row(eid):
            return alpha * content.get(eid, 0.0) + (1 - alpha) * cf.get(eid, 0.0)

        candidates["score"] = candidates["id"].apply(score_row)
        # Only recommend events with genuine relevance in production (student
        # dashboard) — but NOT during evaluate.py's benchmarking, where we
        # need the full ranked list to fairly compare against the baseline
        # (which has no such filter). Applying it during evaluation was
        # silently shrinking the hybrid model's candidate list below top_n
        # and unfairly deflating its measured precision@k.
        if apply_threshold:
            candidates = candidates[candidates["score"] > 0.01]
        candidates = candidates.sort_values("score", ascending=False).head(top_n)

        # Convert date/time to plain strings — MySQL hands these back as
        # datetime.date / datetime.timedelta objects via our manual fetch
        # in db.py, and Flask's JSON encoder can't serialize a Timedelta
        # directly ("Object of type Timedelta is not JSON serializable").
        result = candidates[
            ["id", "title", "interest_name", "date", "time", "location", "score"]
        ].copy()
        result["date"] = result["date"].astype(str)
        # Strip the "0 days " prefix pandas adds when stringifying a
        # Timedelta, so the UI shows a clean "15:15:00" instead of
        # "0 days 15:15:00".
        result["time"] = result["time"].apply(
            lambda t: str(t).split("days")[-1].strip() if "day" in str(t) else str(t)
        )

        return result.to_dict(orient="records")

    # ------------------------------------------------------------------ #
    # Baseline used for benchmarking (matches your existing PHP logic:
    # show events that match the student's chosen interest categories,
    # unranked / equally weighted). Used only by evaluate.py.
    # ------------------------------------------------------------------ #
    def baseline_recommend(self, user_id, top_n=5, only_upcoming=True):
        liked_interest_ids = set(
            self.user_interests[self.user_interests["user_id"] == user_id]["interest_id"]
        )
        already_seen = set(
            self.participation[self.participation["user_id"] == user_id]["event_id"]
        )

        events = self.events.copy()
        if only_upcoming:
            now = pd.Timestamp(datetime.now())
            dt = _combine_date_time(events)
            events = events[dt > now]

        candidates = events[
            events["interest_id"].isin(liked_interest_ids)
            & ~events["id"].isin(already_seen)
        ]
        # No ranking model in the baseline — just return in original (id) order,
        # exactly like a plain SQL WHERE interest_id IN (...) query would.
        return candidates.head(top_n)["id"].tolist()