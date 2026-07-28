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
input_file = "/app/files/output/06-Base-clean/LAKE_SP_BASE_CLEAN.csv"
output_file = "/app/files/output/JULY_26_NSP_LAKE_CLEAN_not_in_base.csv"

def normalize_msisdn(series):
    # Force string dtype up front so pandas never silently infers float64
    # (which turns e.g. 254712345678 into "254712345678.0" and breaks matching).
    s = series.astype(str).str.strip()
    s = s.str.replace(r"\.0$", "", regex=True)
    return set(s[~s.isin(["", "nan", "None"])])

# Read MSISDNs from CSV
df_input = pd.read_csv(input_file, dtype={"MSISDN": str})
input_msisdns = normalize_msisdn(df_input['MSISDN'])

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
