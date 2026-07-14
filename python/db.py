"""PostgreSQL connection helper."""

import psycopg2
import psycopg2.extras
import os

# PostgreSQL connection config
DB_CONFIG = {
    'dbname': 'roll_lot_viewer',
    'user': 'roll_lot_user',
    'password': 'roll_lot_secure_2026',
    'host': '127.0.0.1',
    'port': 5432
}


class DictRow:
    """Makes psycopg2 result behave like a dict with [] access."""

    def __init__(self, row_dict):
        self._dict = row_dict

    def __getitem__(self, key):
        return self._dict[key]

    def get(self, key, default=None):
        return self._dict.get(key, default)

    def __contains__(self, key):
        return key in self._dict

    def keys(self):
        return self._dict.keys()


def get_connection():
    """Return a new PostgreSQL connection."""
    conn = psycopg2.connect(**DB_CONFIG)
    conn.autocommit = False  # Use transactions
    return conn


def execute_query(sql, params=None):
    """Execute a query and return results as list of dict-like objects."""
    conn = get_connection()
    try:
        cur = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        cur.execute(sql, params or ())
        rows = cur.fetchall()
        return [DictRow(dict(row)) for row in rows]
    finally:
        conn.close()


def execute_many(sql, data):
    """Execute a query for each row in data."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.executemany(sql, data)
        conn.commit()
    finally:
        conn.close()
