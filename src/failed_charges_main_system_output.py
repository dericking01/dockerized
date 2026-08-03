import pandas as pd
import psycopg2
import os

# This script cross-checks failed_charges.csv against billing.icg_payments,
# using external_reference to look up the matching payment description, and
# writes the result into a new MAIN_SYSTEM_OUTPUT column for every record.

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/input/failed_charges.csv"
output_file = "/app/files/output/failed_charges_with_main_system_output.csv"

NOT_FOUND = "record not found"

# Read input CSV, keeping all original columns as-is
df = pd.read_csv(input_file, dtype=str)
references = df["external_reference"].astype(str).str.strip()

# Connect to Postgres
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()

# Look up descriptions for every distinct reference in one query
ref_list = [r for r in references.unique() if r and r.lower() != "nan"]
query = """
    SELECT third_party_reference_number, description
    FROM billing.icg_payments
    WHERE third_party_reference_number = ANY(%s);
"""
cur.execute(query, (ref_list,))
ref_to_description = {}
for third_party_reference_number, description in cur.fetchall():
    ref_to_description.setdefault(str(third_party_reference_number).strip(), description)

cur.close()
conn.close()

# Add MAIN_SYSTEM_OUTPUT column, marking references absent from the DB
df["MAIN_SYSTEM_OUTPUT"] = references.map(lambda ref: ref_to_description.get(ref, NOT_FOUND))

df.to_csv(output_file, index=False)

found_count = int((df["MAIN_SYSTEM_OUTPUT"] != NOT_FOUND).sum())
print(f"✅ Done! {found_count} of {len(df)} records matched in billing.icg_payments.")
print(f"Output saved to {output_file}")
