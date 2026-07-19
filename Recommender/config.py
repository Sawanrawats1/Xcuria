"""
config.py
Same credentials as Xcuria - Copy/Includes/db_connect.php.
Change these if your XAMPP MySQL setup differs.
"""

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",       # blank for default XAMPP
    "database": "xcuria",
    "port": 3309,          # matches db_connect.php; use 3306/3307 if yours differs
}

# Weight given to content-based score vs collaborative-filtering score
# in the final hybrid ranking. 0.5 / 0.5 is a neutral starting point —
# tune this after you run evaluate.py and compare precision@k at a few values.
HYBRID_ALPHA = 0.5

# How many recommendations to return by default
DEFAULT_TOP_N = 5
