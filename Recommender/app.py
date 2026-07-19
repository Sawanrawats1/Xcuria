"""
app.py
Flask microservice exposing the recommendation engine over HTTP.

Run it with:
    python app.py

It listens on http://localhost:5000 by default. Your PHP dashboard calls
GET /recommend?user_id=<id>&top_n=5 and gets back JSON — see
php_integration/recommendations.php for the exact calling code.

NOTE ON PERFORMANCE: for a college-scale dataset (hundreds/low-thousands of
events and students) rebuilding the model on every request is fine and keeps
the code simple, which matters more for a viva than micro-optimizing.
If your dataset grows, cache the XcuriaRecommender instance and only rebuild
it every few minutes (see the commented-out cache block below).
"""

from flask import Flask, request, jsonify
from db import load_all
from recommender import XcuriaRecommender, _combine_date_time
from suitability import suitability_ranking
from sentiment import sentiment_for_faculty_events
from config import DEFAULT_TOP_N

app = Flask(__name__)


def get_recommender():
    data = load_all()
    return XcuriaRecommender(data)


@app.route("/health", methods=["GET"])
def health():
    return jsonify({"status": "ok"})


@app.route("/recommend", methods=["GET"])
def recommend():
    user_id = request.args.get("user_id", type=int)
    top_n = request.args.get("top_n", default=DEFAULT_TOP_N, type=int)
    alpha = request.args.get("alpha", default=None, type=float)

    if not user_id:
        return jsonify({"status": "error", "message": "user_id is required"}), 400

    try:
        rec = get_recommender()
        kwargs = {"top_n": top_n}
        if alpha is not None:
            kwargs["alpha"] = alpha
        results = rec.recommend(user_id, **kwargs)
        return jsonify({"status": "success", "user_id": user_id, "recommendations": results})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/suitability", methods=["GET"])
def suitability():
    event_id = request.args.get("event_id", type=int)
    top_n = request.args.get("top_n", default=10, type=int)

    if not event_id:
        return jsonify({"status": "error", "message": "event_id is required"}), 400

    try:
        data = load_all()
        result = suitability_ranking(data, event_id, top_n=top_n)
        return jsonify(result)
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/sentiment", methods=["GET"])
def sentiment():
    faculty_id = request.args.get("faculty_id", type=int)

    if not faculty_id:
        return jsonify({"status": "error", "message": "faculty_id is required"}), 400

    try:
        data = load_all()
        result = sentiment_for_faculty_events(data, faculty_id)
        return jsonify(result)
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500


@app.route("/debug", methods=["GET"])
def debug():
    from datetime import datetime
    import pandas as pd
    user_id = request.args.get("user_id", type=int)
    data = load_all()
    events = data["events"]
    participation = data["participation"]

    now = pd.Timestamp(datetime.now())
    dt = _combine_date_time(events)
    upcoming = events[dt > now]

    already_seen = set(participation[participation["user_id"] == user_id]["event_id"]) if user_id else set()

    return jsonify({
        "server_now": str(now),
        "total_events": len(events),
        "upcoming_events_count": len(upcoming),
        "upcoming_event_ids": upcoming["id"].tolist(),
        "sample_dt_parsed": dt.astype(str).tolist()[:5],
        "sample_raw_time_values": events["time"].astype(str).tolist()[:5],
        "user_already_participated": list(already_seen),
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)