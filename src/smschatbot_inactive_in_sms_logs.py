import pandas as pd
import psycopg2
import os

# This script checks how many MSISDNs from an input CSV of inactive SMS
# chatbot users are also present in chat.incoming_sms_logs after a cutoff
# timestamp (i.e. they messaged back in since being marked inactive).

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/input/27_JULY_SMSCHATBOT_INACTIVE_60_DAYS.csv"
output_file = "/app/files/output/27_JULY_SMSCHATBOT_INACTIVE_60_DAYS_in_sms_logs.csv"

CUTOFF = "2026-07-27 13:00:00"

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
cur.execute(query, (CUTOFF,))
log_msisdns = normalize_msisdn(pd.Series([row[0] for row in cur.fetchall()]))

cur.close()
conn.close()

# Find input MSISDNs that are also present in the SMS logs
matched_msisdns = input_msisdns.intersection(log_msisdns)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": sorted(matched_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! {len(matched_msisdns)} of {len(input_msisdns)} input MSISDNs were found in "
      f"chat.incoming_sms_logs after {CUTOFF}.")
print(f"Output saved to {output_file}")
