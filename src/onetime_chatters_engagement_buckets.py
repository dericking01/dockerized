import logging
import os
import re
from collections import Counter
from datetime import date

import pandas as pd
import psycopg2

# This script checks how many MSISDNs from an input CSV of one-time chatters
# actually engaged with the chatbot, i.e. sent at least one message
# (chat.chat_history rows where message->>'type' = 'human') on or after
# DATE_CUTOFF. Engagement is measured as the number of human messages sent
# per MSISDN (on/after the cutoff), then bucketed into ranges: 0 (no
# engagement), 1-2, 3-5, 6-10, 11+.

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/output/13_SEPT_ONETIME_CHATTERS_ACTIVE_SUBS.csv"
output_file = "/app/files/output/13_SEPT_ONETIME_CHATTERS_ACTIVE_SUBS_engagement_counts.csv"

# session_id format: "<msisdn>-dd-mm-yyyy", e.g. "255797400196-14-09-2026"
SESSION_ID_RE = re.compile(r"^(\d+)-(\d{2})-(\d{2})-(\d{4})$")

# Only count human messages sent on or after this date
DATE_CUTOFF = date(2026, 9, 13)

# --- Logging setup ---
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)


def normalize_msisdn(series):
    # Force string dtype up front so pandas never silently infers float64
    # (which turns e.g. 254712345678 into "254712345678.0" and breaks matching).
    s = series.astype(str).str.strip()
    s = s.str.lstrip("+")
    s = s.str.replace(r"\.0$", "", regex=True)
    return set(s[~s.isin(["", "nan", "None"])])


def bucket_for(count):
    if count == 0:
        return "0 (no engagement)"
    if count <= 2:
        return "1-2"
    if count <= 5:
        return "3-5"
    if count <= 10:
        return "6-10"
    return "10+"


BUCKET_ORDER = ["0 (no engagement)", "1-2", "3-5", "6-10", "10+"]

# Read MSISDNs from input CSV
df_input = pd.read_csv(input_file, dtype={"MSISDN": str})
input_msisdns = normalize_msisdn(df_input["MSISDN"])
logger.info("Loaded %d input MSISDNs from %s", len(input_msisdns), input_file)

# Connect to Postgres
logger.info("Connecting to %s@%s:%s ...", DB_NAME, DB_HOST, DB_PORT)
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()

# Pull only the session_id for human-authored messages (the message an MSISDN
# sent), keeping the row thin instead of pulling the full jsonb message body.
cur.execute("SELECT session_id FROM chat.chat_history WHERE message->>'type' = 'human';")

human_msg_counts = Counter()
skipped = 0
before_cutoff = 0
for (session_id,) in cur.fetchall():
    match = SESSION_ID_RE.match(str(session_id).strip())
    if not match:
        skipped += 1
        continue
    msisdn, day, month, year = match.groups()
    session_date = date(int(year), int(month), int(day))
    if session_date < DATE_CUTOFF:
        before_cutoff += 1
        continue
    if msisdn in input_msisdns:
        human_msg_counts[msisdn] += 1

cur.close()
conn.close()

if skipped:
    logger.info("Skipped %d human-message rows with an unparseable session_id", skipped)
logger.info("Skipped %d human-message rows before cutoff date %s", before_cutoff, DATE_CUTOFF)

# Build per-MSISDN results, including input MSISDNs with zero messages
records = []
for msisdn in sorted(input_msisdns):
    count = human_msg_counts.get(msisdn, 0)
    records.append({"MSISDN": msisdn, "human_message_count": count, "bucket": bucket_for(count)})

df_results = pd.DataFrame(records)
os.makedirs(os.path.dirname(output_file), exist_ok=True)
df_results.to_csv(output_file, index=False)

# Bucket summary
bucket_counts = df_results["bucket"].value_counts().reindex(BUCKET_ORDER, fill_value=0)

engaged_count = len(input_msisdns) - bucket_counts["0 (no engagement)"]

logger.info(
    "✅ Done! %d of %d input MSISDNs engaged (sent >=1 human message) with the chatbot on/after %s.",
    engaged_count, len(input_msisdns), DATE_CUTOFF
)
logger.info("Output saved to %s", output_file)

logger.info("Engagement buckets (by number of human messages sent on/after %s):", DATE_CUTOFF)
for bucket in BUCKET_ORDER:
    logger.info("  %-18s: %d MSISDNs", bucket, bucket_counts[bucket])

print(f"\n✅ Done! {engaged_count} of {len(input_msisdns)} input MSISDNs engaged "
      f"(sent >=1 human message) with the chatbot on/after {DATE_CUTOFF}.")
print(f"Output saved to {output_file}\n")
print(f"Engagement buckets (by number of human messages sent on/after {DATE_CUTOFF}):")
print(bucket_counts.rename("count").to_string())
