"""
evaluate.py

Benchmarks the hybrid recommender against your EXISTING baseline
(plain interest-category filtering, i.e. what Xcuria does today without ML)
using a train/test split of real participation data.

Method (leave-one-out style, standard for small recommender datasets):
For each student with 2+ participated events:
  - Hide their most recent participated event ("held-out" event)
  - Train/build both models on everything else
  - Ask each model for its top-K recommendations
  - Check whether the held-out event appears in that top-K list

precision@k here = (# students where the held-out event appears in top-K)
                    / (# students evaluated)

This is exactly the metric named in the project write-up. Run this after
you have a reasonable amount of real participation + feedback data —
with only a handful of rows the result will be noisy, which is fine to
state honestly in your report (see the write-up's "data scale" caveat).

Usage:
    python evaluate.py --k 5
"""

import argparse
import pandas as pd
from db import load_all
from recommender import XcuriaRecommender


def build_holdout_split(data):
    """
    For each user with >=2 participation rows, hold out one event_id as the
    'test' target and remove it from participation/feedback for training.
    Returns (train_data, holdouts) where holdouts is {user_id: held_out_event_id}.
    """
    participation = data["participation"].copy()
    counts = participation.groupby("user_id").size()
    eligible_users = counts[counts >= 2].index.tolist()

    holdouts = {}
    rows_to_drop = []

    for user_id in eligible_users:
        user_rows = participation[participation["user_id"] == user_id]
        held_out_row = user_rows.iloc[-1]  # most recent row as proxy for "latest"
        holdouts[user_id] = held_out_row["event_id"]
        rows_to_drop.append(held_out_row.name)

    train_participation = participation.drop(index=rows_to_drop)

    train_feedback = data["feedback"].copy()
    for user_id, event_id in holdouts.items():
        train_feedback = train_feedback[
            ~((train_feedback["user_id"] == user_id) & (train_feedback["event_id"] == event_id))
        ]

    train_data = dict(data)
    train_data["participation"] = train_participation
    train_data["feedback"] = train_feedback

    return train_data, holdouts


def precision_at_k(recommender, holdouts, k, use_baseline=False):
    hits = 0
    for user_id, held_out_event in holdouts.items():
        if use_baseline:
            recs = recommender.baseline_recommend(user_id, top_n=k, only_upcoming=False)
        else:
           recs = [r["id"] for r in recommender.recommend(user_id, top_n=k, only_upcoming=False, apply_threshold=False)]
        if held_out_event in recs:
            hits += 1
    return hits / len(holdouts) if holdouts else 0.0


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--k", type=int, default=5, help="top-K for precision@k")
    args = parser.parse_args()

    data = load_all()
    train_data, holdouts = build_holdout_split(data)

    if not holdouts:
        print("Not enough participation history yet to evaluate "
              "(need students with 2+ event participations). "
              "Collect more usage data, then re-run this script.")
        return

    recommender = XcuriaRecommender(train_data)

    baseline_p = precision_at_k(recommender, holdouts, args.k, use_baseline=True)
    hybrid_p = precision_at_k(recommender, holdouts, args.k, use_baseline=False)

    print(f"Evaluated on {len(holdouts)} students (leave-one-out)")
    print(f"Baseline (category filter)  precision@{args.k}: {baseline_p:.3f}")
    print(f"Hybrid recommender          precision@{args.k}: {hybrid_p:.3f}")

    if baseline_p > 0:
        lift = (hybrid_p - baseline_p) / baseline_p * 100
        print(f"Relative improvement over baseline: {lift:+.1f}%")
    else:
        print("Baseline precision is 0 — report absolute hybrid score instead of relative lift.")


if __name__ == "__main__":
    main()
