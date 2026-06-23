"""pymysql connection helper."""

import pymysql
from config import DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME


def get_connection():
    """Return a new PyMySQL connection."""
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        charset="utf8mb4",
        autocommit=True,
    )


def execute_query(sql, params=None):
    """Execute a single query and return all results as list of dicts."""
    conn = get_connection()
    try:
        with conn.cursor(pymysql.cursors.DictCursor) as cur:
            cur.execute(sql, params)
            return cur.fetchall()
    finally:
        conn.close()


def execute_many(sql, data):
    """Execute a query for each row in *data* (list of tuples/dicts)."""
    conn = get_connection()
    try:
        with conn.cursor() as cur:
            cur.executemany(sql, data)
        conn.commit()
    finally:
        conn.close()
