import pandas as pd
import psycopg2
import os
import csv

# --- DB Config ---
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# This script identifies MSISDNs from an input CSV that are NOT active subscribers
# for plan_id=6 in the Postgres database and writes them to an output CSV.

# --- File paths ---
input_file = "/app/files/input/AFYACALL-JULY-BASE-clean.csv"
output_file = "/app/files/output/non_docSUB_subscribers.csv"

# --- Connect to Postgres ---
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()

# Fetch all active MSISDNs for plan_id=6
print("📥 Fetching active subscribers from DB...")
cur.execute("""
    SELECT DISTINCT customer_msisdn
    FROM subscription.enticements
    WHERE status='ACTIVE' AND plan_id=6;
""")
active_msisdns = set(str(row[0]).strip() for row in cur.fetchall())

cur.close()
conn.close()

print(f"✅ Loaded {len(active_msisdns)} active MSISDNs from DB")

# --- Stream input CSV and filter ---
chunk_size = 100_000
non_sub_count = 0

# Create output CSV file with header
with open(output_file, "w", newline="") as out_csv:
    writer = csv.writer(out_csv)
    writer.writerow(["MSISDN"])  # write header

    for chunk in pd.read_csv(input_file, chunksize=chunk_size):
        # Clean and strip MSISDNs
        chunk['MSISDN'] = chunk['MSISDN'].astype(str).str.strip()
        # Filter those not in DB
        non_subs = chunk[~chunk['MSISDN'].isin(active_msisdns)]
        # Write out
        for msisdn in non_subs['MSISDN']:
            writer.writerow([msisdn])
        non_sub_count += len(non_subs)

print(f"✅ Done! Found {non_sub_count} non-subscribers.")
print(f"📄 Output saved to {output_file}")
