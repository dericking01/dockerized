import csv
import pandas as pd
import psycopg2
import os

# This script finds MSISDNs from an input CSV file that are NOT present
# in the customer.customers table of the Postgres database.

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/output/06-Base-clean/CENTRAL_NSP_BASE_CLEAN.csv"
output_file = "/app/files/output/06-Base-clean/JUL26_CENTRAL_NSP_BASE_CLEAN_not_in_base.csv"

def normalize_msisdn(series):
    # Force string dtype up front so pandas never silently infers float64
    # (which turns e.g. 254712345678 into "254712345678.0" and breaks matching).
    s = series.astype(str).str.strip()
    s = s.str.replace(r"\.0$", "", regex=True)
    return set(s[~s.isin(["", "nan", "None"])])

# Read MSISDNs from CSV
# Note: this file's rows pack "MSISDN,GENDER,AGE" into the single quoted
# MSISDN field (e.g. "255746116585,M         ,39"), leaving GENDER/AGE
# empty, so the real MSISDN is only the part before the first comma. The
# file also has a truncated final line with an unterminated quote, which
# pandas' C parser rejects (EOF inside string) but the csv module tolerates.
with open(input_file, newline="") as f:
    reader = csv.reader(f)
    next(reader, None)  # skip header
    raw_msisdns = [row[0].split(",")[0] for row in reader if row]
input_msisdns = normalize_msisdn(pd.Series(raw_msisdns))

# Connect to Postgres
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()

# Query all customer MSISDNs
query = "SELECT DISTINCT customer_msisdn FROM subscription.subscribers;"
cur.execute(query)
db_msisdns = normalize_msisdn(pd.Series([row[0] for row in cur.fetchall()]))

cur.close()
conn.close()

# Find MSISDNs that are in input but NOT in DB
non_existing_msisdns = input_msisdns.difference(db_msisdns)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": list(non_existing_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Found {len(non_existing_msisdns)} MSISDNs NOT in subscription.subscribers.")
print(f"Output saved to {output_file}")
