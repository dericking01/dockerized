import logging
import os
import time

import pandas as pd
import psycopg2

# --- Load environment variables ---
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# --- Plan under review ---
PLAN_CODE = "921465_P02"

# --- Output file path ---
output_file = "/app/files/output/27_JULY_SMS_INACTIVE_60_DAYS.csv"

# --- Logging setup ---
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)

# This script finds subscribers on PLAN_CODE whose most recent SUCCESS
# payment (billing.icg_payments.created_at) is older than 60 days.
# Subscribers who have NEVER had a SUCCESS payment are also included,
# since it has been (indefinitely) longer than 60 days since their last
# successful payment.
QUERY = """
WITH recent_success AS (
    SELECT
        msisdn,
        MAX(created_at) AS last_success_at
    FROM billing.icg_payments
    WHERE plan_code = %(plan_code)s
      AND status = 'SUCCESS'
    GROUP BY msisdn
),
subscriber_base AS (
    SELECT DISTINCT customer_msisdn
    FROM subscription.subscribers
    WHERE plan_code = %(plan_code)s
)
SELECT
    sb.customer_msisdn AS msisdn,
    rs.last_success_at
FROM subscriber_base sb
LEFT JOIN recent_success rs
    ON rs.msisdn = sb.customer_msisdn
WHERE rs.last_success_at IS NULL
   OR rs.last_success_at < NOW() - INTERVAL '60 days'
"""

logger.info("Connecting to %s@%s:%s ...", DB_NAME, DB_HOST, DB_PORT)
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD,
)
cur = conn.cursor()

logger.info("Querying subscribers on plan_code=%s with last SUCCESS payment > 60 days ago ...", PLAN_CODE)
start = time.time()
cur.execute(QUERY, {"plan_code": PLAN_CODE})
rows = cur.fetchall()
elapsed = time.time() - start

cur.close()
conn.close()

db_msisdns = [str(row[0]).strip() for row in rows]
never_paid_count = sum(1 for row in rows if row[1] is None)
paid_over_60_days_count = len(rows) - never_paid_count

# --- Save to output CSV ---
os.makedirs(os.path.dirname(output_file), exist_ok=True)
df_output = pd.DataFrame({"MSISDN": db_msisdns})
df_output.to_csv(output_file, index=False)

logger.info("Query completed in %.2fs", elapsed)
logger.info("Subscribers found with no SUCCESS payment at all: %d", never_paid_count)
logger.info("Subscribers found with last SUCCESS payment > 60 days ago: %d", paid_over_60_days_count)
logger.info("Done! Exported %d MSISDNs.", len(db_msisdns))
logger.info("Output saved to %s", output_file)
