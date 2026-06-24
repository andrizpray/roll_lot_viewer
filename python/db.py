"""SQLite connection helper."""

import sqlite3
from config import DB_PATH


class DictRow:
    """Makes sqlite3 Row behave like a dict with [] access."""

    def __init__(self, row, columns):
        self._row = row
        self._columns = columns

    def __getitem__(self, key):
        if isinstance(key, int):
            return self._row[key]
        idx = self._columns.index(key)
        return self._row[idx]

    def get(self, key, default=None):
        try:
            return self[key]
        except (ValueError, IndexError):
            return default

    def __contains__(self, key):
        return key in self._columns

    def keys(self):
        return self._columns


def get_connection():
    """Return a new SQLite connection with row_factory for dict-like access."""
    conn = sqlite3.connect(DB_PATH)
    conn.execute("PRAGMA journal_mode=WAL")
    conn.execute("PRAGMA busy_timeout=5000")
    return conn


def execute_query(sql, params=None):
    """Execute a query and return results as list of dict-like objects."""
    conn = get_connection()
    try:
        cur = conn.cursor()
        cur.execute(sql, params or ())
        columns = [desc[0] for desc in cur.description] if cur.description else []
        rows = cur.fetchall()
        return [DictRow(row, columns) for row in rows]
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
