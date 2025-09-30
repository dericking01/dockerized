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
input_file = "/app/files/input/02_SEPT_DOC_SUB_400k.csv"
output_file = "/app/files/output/02_SEPT_DOC_SUB_400k_NOT_IN_CUSTOMERS.csv"

# Read MSISDNs from CSV
df_input = pd.read_csv(input_file)
input_msisdns = set(df_input['MSISDN'].astype(str).str.strip())

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
query = "SELECT DISTINCT msisdn FROM customer.customers;"
cur.execute(query)
db_msisdns = set(str(row[0]).strip() for row in cur.fetchall())

cur.close()
conn.close()

# Find MSISDNs that are in input but NOT in DB
non_existing_msisdns = input_msisdns.difference(db_msisdns)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": list(non_existing_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Found {len(non_existing_msisdns)} MSISDNs NOT in customer.customers.")
print(f"Output saved to {output_file}")
