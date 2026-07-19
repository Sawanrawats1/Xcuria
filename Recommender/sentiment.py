"""
sentiment.py

NLP-based sentiment analysis on student feedback comments.

Honest note on the technique chosen, for your report/viva:
Training a supervised sentiment classifier (e.g. TF-IDF + Logistic
Regression) needs labeled data (comments manually tagged positive/negative)
which you don't have. Rather than faking labels or overclaiming a "trained
model," this uses VADER (Valence Aware Dictionary and sEntiment Reasoner) —
a lexicon- and rule-based sentiment analyzer that's a legitimate, published
NLP technique (Hutto & Gilbert, 2014) designed specifically for short,
informal text like this. It needs no training data, which is the correct
and defensible choice given your data constraints — say exactly this if
asked "why not a trained classifier?" in the viva.

If you later collect enough manually-labeled feedback (even 100-200
examples), swapping this for a trained scikit-learn classifier is a
natural "future work" extension — mention that in your report too.
"""

from vaderSentiment.vaderSentiment import SentimentIntensityAnalyzer

_analyzer = SentimentIntensityAnalyzer()


def analyze_text(text: str) -> dict:
    """Returns {'label': 'positive'|'neutral'|'negative', 'compound': float}"""
    if not text or not text.strip():
        return {"label": "neutral", "compound": 0.0}

    scores = _analyzer.polarity_scores(text)
    compound = scores["compound"]

    # Standard VADER thresholds
    if compound >= 0.05:
        label = "positive"
    elif compound <= -0.05:
        label = "negative"
    else:
        label = "neutral"

    return {"label": label, "compound": compound}


def sentiment_for_faculty_events(data: dict, faculty_id: int):
    """
    Returns per-feedback sentiment labels plus a per-event aggregate summary,
    scoped to events created by the given faculty member.
    """
    events = data["events"]
    feedback = data["feedback"]
    students = data["students"]

    faculty_event_ids = set(events[events["created_by"] == faculty_id]["id"])
    relevant_feedback = feedback[feedback["event_id"].isin(faculty_event_ids)].copy()

    if relevant_feedback.empty:
        return {"status": "success", "per_feedback": [], "per_event_summary": []}

    relevant_feedback["sentiment"] = relevant_feedback["comment"].apply(
        lambda c: analyze_text(c)["label"]
    )
    relevant_feedback["compound"] = relevant_feedback["comment"].apply(
        lambda c: analyze_text(c)["compound"]
    )

    merged = relevant_feedback.merge(
        events[["id", "title"]], left_on="event_id", right_on="id", how="left", suffixes=("", "_event")
    ).merge(
        students[["id", "name"]], left_on="user_id", right_on="id", how="left", suffixes=("", "_student")
    )

    per_feedback = merged[[
        "feedback_id", "event_id", "title", "user_id", "name", "rating",
        "comment", "sentiment", "compound"
    ]].rename(columns={"title": "event_title", "name": "student_name"}).to_dict(orient="records")

    summary = (
        relevant_feedback.groupby("event_id")["sentiment"]
        .value_counts()
        .unstack(fill_value=0)
        .reset_index()
    )
    for col in ["positive", "neutral", "negative"]:
        if col not in summary.columns:
            summary[col] = 0

    summary = summary.merge(events[["id", "title"]], left_on="event_id", right_on="id", how="left")
    per_event_summary = summary[["event_id", "title", "positive", "neutral", "negative"]].rename(
        columns={"title": "event_title"}
    ).to_dict(orient="records")

    return {
        "status": "success",
        "per_feedback": per_feedback,
        "per_event_summary": per_event_summary,
    }
