import re
from datetime import date

import pandas as pd
import psycopg2
import os

# This script checks how many MSISDNs from an input CSV of inactive SMS
# chatbot users are also present in chat.incoming_sms_logs after a cutoff
# timestamp, and how many interacted with the chatbot (chat.chat_history)
# after a cutoff date (i.e. they re-engaged since being marked inactive).

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/input/3_AUG_INACTIVE_60_DAYS_SMS_PROMO.csv"
sms_logs_output_file = "/app/files/output/3_AUG_INACTIVE_60_DAYS_SMS_PROMO_in_sms_logs.csv"
chat_history_output_file = "/app/files/output/3_AUG_INACTIVE_60_DAYS_SMS_PROMO_in_chat_history.csv"

SMS_LOGS_CUTOFF = "2026-08-03 13:00:00"
CHAT_HISTORY_CUTOFF_DATE = date(2026, 8, 3)
PAYMENTS_CUTOFF_DATE = "2026-08-05"

# session_id format: "<msisdn>-dd-mm-yyyy", e.g. "255724312337-26-07-2026"
SESSION_ID_RE = re.compile(r"^(\d+)-(\d{2})-(\d{2})-(\d{4})$")

def normalize_msisdn(series):
    # Force string dtype up front so pandas never silently infers float64
    # (which turns e.g. 254712345678 into "254712345678.0" and breaks matching).
    s = series.astype(str).str.strip()
    s = s.str.lstrip("+")
    s = s.str.replace(r"\.0$", "", regex=True)
    return set(s[~s.isin(["", "nan", "None"])])

# Read MSISDNs from input CSV
df_input = pd.read_csv(input_file, dtype={"MSISDN": str})
input_msisdns = normalize_msisdn(df_input["MSISDN"])

# Connect to Postgres
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()

# Query MSISDNs that messaged in after the cutoff (stored with a leading '+')
query = "SELECT DISTINCT msisdn FROM chat.incoming_sms_logs WHERE created_at > %s;"
cur.execute(query, (SMS_LOGS_CUTOFF,))
log_msisdns = normalize_msisdn(pd.Series([row[0] for row in cur.fetchall()]))

# Query chatbot session ids and pull out MSISDNs whose session date is after the cutoff
cur.execute("SELECT session_id FROM chat.chat_history;")
chat_msisdns = set()
for (session_id,) in cur.fetchall():
    match = SESSION_ID_RE.match(str(session_id).strip())
    if not match:
        continue
    msisdn, day, month, year = match.groups()
    session_date = date(int(year), int(month), int(day))
    if session_date > CHAT_HISTORY_CUTOFF_DATE:
        chat_msisdns.add(msisdn)

# Find input MSISDNs that are also present in the SMS logs / chat history
matched_sms_msisdns = input_msisdns.intersection(log_msisdns)
matched_chat_msisdns = input_msisdns.intersection(chat_msisdns)

# Of those who responded (found in chat.incoming_sms_logs), count payment
# records by status after the cutoff date.
payment_status_counts = pd.DataFrame(columns=["status", "count"])
if matched_sms_msisdns:
    cur.execute(
        """
        SELECT status, COUNT(*) AS count
        FROM billing.icg_payments
        WHERE msisdn = ANY(%s) AND created_at > %s
        GROUP BY status
        ORDER BY count DESC;
        """,
        (list(matched_sms_msisdns), PAYMENTS_CUTOFF_DATE)
    )
    payment_status_counts = pd.DataFrame(cur.fetchall(), columns=["status", "count"])

cur.close()
conn.close()

# Save results to output CSVs
pd.DataFrame({"MSISDN": sorted(matched_sms_msisdns)}).to_csv(sms_logs_output_file, index=False)
pd.DataFrame({"MSISDN": sorted(matched_chat_msisdns)}).to_csv(chat_history_output_file, index=False)

print(f"✅ Done! {len(matched_sms_msisdns)} of {len(input_msisdns)} input MSISDNs were found in "
      f"chat.incoming_sms_logs after {SMS_LOGS_CUTOFF}.")
print(f"Output saved to {sms_logs_output_file}")

print(f"✅ Done! {len(matched_chat_msisdns)} of {len(input_msisdns)} input MSISDNs interacted with "
      f"the chatbot (chat.chat_history) after {CHAT_HISTORY_CUTOFF_DATE}.")
print(f"Output saved to {chat_history_output_file}")

print(f"\nPayment status counts for {len(matched_sms_msisdns)} responders "
      f"(billing.icg_payments, created_at > {PAYMENTS_CUTOFF_DATE}):")
print(payment_status_counts.to_string(index=False))
