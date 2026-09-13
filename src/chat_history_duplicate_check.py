import logging
import os
import time

import psycopg2

# This script investigates a message-count spike in chat.chat_history.
#
# The daily rollup counts rows (COUNT(*)), so if the app wrote each message
# twice a real-looking spike appears. Here we compare raw row counts against
# de-duplicated counts (distinct session_id + message) to tell whether the
# spike on 2026-08-24..26 is genuine traffic or duplicated writes.
#
# Four checks, all logged as tables:
#   A. Daily: human/ai rows vs. distinct rows, and the duplicate delta.
#   B. Daily: per-session duplication ratio (rows / distinct messages).
#   C. Spike window: the actual duplicated messages (with min/max id).
#   D. Spike window: heaviest sessions, to rule out one bot / giant session.

# --- Load environment variables ---
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# --- Date range for the daily overview (inclusive) ---
START_DATE = "2026-08-20"
END_DATE = "2026-08-27"

# --- Narrower "spike" window for the row-level drill-down (inclusive) ---
SPIKE_START = "2026-08-24"
SPIKE_END = "2026-08-26"

# --- How many rows to show in the drill-down tables ---
TOP_N = 40

# --- Logging setup ---
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)

# conv_date is derived from the trailing DD-MM-YYYY in session_id. The regex
# filter keeps TO_DATE off rows whose session_id has no such suffix.
BASE_CTE = """
WITH base AS (
    SELECT
        id,
        session_id,
        message::jsonb AS msg,
        message::text  AS msg_text,
        TO_DATE(
            SUBSTRING(session_id FROM '[0-9]{2}-[0-9]{2}-[0-9]{4}$'),
            'DD-MM-YYYY'
        ) AS conv_date
    FROM chat.chat_history
    WHERE session_id ~ '[0-9]{2}-[0-9]{2}-[0-9]{4}$'
)
"""

# A. Daily raw vs. de-duplicated counts. If human_rows ~= 2 * human_distinct
# on the spike days but ~= 1x before, the spike is a write bug, not traffic.
QUERY_DAILY = BASE_CTE + """
SELECT
    conv_date,
    COUNT(DISTINCT session_id)                                                   AS sessions,
    COUNT(*) FILTER (WHERE msg->>'type' = 'human')                               AS human_rows,
    COUNT(DISTINCT (session_id, msg_text)) FILTER (WHERE msg->>'type' = 'human') AS human_distinct,
    COUNT(*) FILTER (WHERE msg->>'type' = 'ai')                                  AS ai_rows,
    COUNT(DISTINCT (session_id, msg_text)) FILTER (WHERE msg->>'type' = 'ai')    AS ai_distinct,
    COUNT(*) - COUNT(DISTINCT (session_id, msg_text))                            AS duplicate_rows
FROM base
WHERE conv_date BETWEEN %(start)s AND %(end)s
GROUP BY conv_date
ORDER BY conv_date
"""

# B. Per-session duplication ratio by day. avg_dup_ratio near 1.0 = clean,
# near 2.0 = every message stored twice. sessions_doubled counts sessions
# whose ratio >= 1.9 (essentially fully duplicated).
QUERY_SESSION_RATIO = BASE_CTE + """,
per_session AS (
    SELECT
        session_id,
        conv_date,
        COUNT(*)                 AS rows_cnt,
        COUNT(DISTINCT msg_text) AS distinct_cnt
    FROM base
    WHERE conv_date BETWEEN %(start)s AND %(end)s
    GROUP BY session_id, conv_date
)
SELECT
    conv_date,
    COUNT(*)                                                                        AS sessions,
    SUM(rows_cnt)                                                                   AS total_rows,
    SUM(distinct_cnt)                                                               AS total_distinct,
    ROUND(AVG(rows_cnt::numeric / NULLIF(distinct_cnt, 0)), 3)                       AS avg_dup_ratio,
    MAX(ROUND(rows_cnt::numeric / NULLIF(distinct_cnt, 0), 2))                       AS max_dup_ratio,
    COUNT(*) FILTER (WHERE rows_cnt::numeric / NULLIF(distinct_cnt, 0) >= 1.9)       AS sessions_doubled
FROM per_session
GROUP BY conv_date
ORDER BY conv_date
"""

# C. The actual duplicated messages in the spike window. Adjacent min_id/max_id
# (a gap of a few) means the same insert ran twice back-to-back: a double-invoke
# / retry bug. Far-apart ids more likely mean a genuine resend by the user.
QUERY_DUP_ROWS = BASE_CTE + """
SELECT
    session_id,
    MIN(msg->>'type')                                                       AS type,
    LEFT(MIN(COALESCE(msg->'data'->>'content', msg->>'content')), 80)       AS preview,
    COUNT(*)                                                                AS copies,
    MIN(id)                                                                 AS min_id,
    MAX(id)                                                                 AS max_id
FROM base
WHERE conv_date BETWEEN %(spike_start)s AND %(spike_end)s
GROUP BY session_id, msg_text
HAVING COUNT(*) > 1
ORDER BY copies DESC, session_id
LIMIT %(limit)s
"""

# D. Heaviest sessions in the spike window. A few session_ids with thousands of
# messages means the spike is a handful of abnormal sessions, not broad
# duplication.
QUERY_TOP_SESSIONS = BASE_CTE + """
SELECT
    conv_date,
    session_id,
    COUNT(*)                                        AS msgs,
    COUNT(DISTINCT msg_text)                        AS distinct_msgs,
    COUNT(*) FILTER (WHERE msg->>'type' = 'human')  AS human,
    COUNT(*) FILTER (WHERE msg->>'type' = 'ai')     AS ai
FROM base
WHERE conv_date BETWEEN %(spike_start)s AND %(spike_end)s
GROUP BY conv_date, session_id
ORDER BY msgs DESC
LIMIT %(limit)s
"""


def render_table(headers, rows):
    """Render rows as a simple fixed-width text table."""
    str_rows = [["" if v is None else str(v) for v in r] for r in rows]
    widths = [len(h) for h in headers]
    for r in str_rows:
        for i, v in enumerate(r):
            widths[i] = max(widths[i], len(v))

    def line(vals):
        return " | ".join(str(v).ljust(widths[i]) for i, v in enumerate(vals))

    out = [line(headers), "-+-".join("-" * w for w in widths)]
    out += [line(r) for r in str_rows]
    return "\n".join(out)


def run(cur, sql, params, headers, title):
    start = time.time()
    cur.execute(sql, params)
    rows = cur.fetchall()
    elapsed = time.time() - start
    logger.info("%s  (%d rows, %.2fs)\n%s", title, len(rows), elapsed, render_table(headers, rows))
    return rows


logger.info("Connecting to %s@%s:%s ...", DB_NAME, DB_HOST, DB_PORT)
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD,
)
cur = conn.cursor()

logger.info("Overview window: %s .. %s   |   Spike window: %s .. %s", START_DATE, END_DATE, SPIKE_START, SPIKE_END)

range_params = {"start": START_DATE, "end": END_DATE}
spike_params = {"spike_start": SPIKE_START, "spike_end": SPIKE_END, "limit": TOP_N}

daily = run(
    cur, QUERY_DAILY, range_params,
    ["conv_date", "sessions", "human_rows", "human_distinct", "ai_rows", "ai_distinct", "duplicate_rows"],
    "A. Daily raw vs. de-duplicated message counts",
)

run(
    cur, QUERY_SESSION_RATIO, range_params,
    ["conv_date", "sessions", "total_rows", "total_distinct", "avg_dup_ratio", "max_dup_ratio", "sessions_doubled"],
    "B. Per-session duplication ratio by day",
)

run(
    cur, QUERY_DUP_ROWS, spike_params,
    ["session_id", "type", "preview", "copies", "min_id", "max_id"],
    f"C. Duplicated messages in spike window (top {TOP_N})",
)

run(
    cur, QUERY_TOP_SESSIONS, spike_params,
    ["conv_date", "session_id", "msgs", "distinct_msgs", "human", "ai"],
    f"D. Heaviest sessions in spike window (top {TOP_N})",
)

cur.close()
conn.close()

# --- Verdict: aggregate the daily table over the whole window ---
def pct(dup, total):
    return (dup / total * 100) if total else 0.0

human_rows = sum(r[2] for r in daily)
human_distinct = sum(r[3] for r in daily)
ai_rows = sum(r[4] for r in daily)
ai_distinct = sum(r[5] for r in daily)

logger.info("--- Verdict for %s .. %s ---", START_DATE, END_DATE)
logger.info(
    "Human: %d rows, %d distinct  ->  %d duplicate rows (%.1f%%)",
    human_rows, human_distinct, human_rows - human_distinct, pct(human_rows - human_distinct, human_rows),
)
logger.info(
    "AI:    %d rows, %d distinct  ->  %d duplicate rows (%.1f%%)",
    ai_rows, ai_distinct, ai_rows - ai_distinct, pct(ai_rows - ai_distinct, ai_rows),
)
logger.info(
    "If the duplicate %-share is high only on 2026-08-24..26 (see table A per day) and "
    "table B's avg_dup_ratio jumps toward 2.0, the spike is duplicated writes, not real traffic. "
    "Table C's min_id/max_id gaps show whether it was a back-to-back double insert."
)
logger.info("Done.")
