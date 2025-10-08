import pandas as pd
import psycopg2
import os

# This script compares MSISDNs from an input CSV file with those in a Postgres database
# and outputs the matching MSISDNs to another CSV file.
# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Input and output file paths
input_file = "/app/files/output/03_OCT_non_IVR_subscribers.csv"
output_file = "/app/files/output/acquired.csv"

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

# Query database
query = """
  SELECT
    DISTINCT c.msisdn AS new_customers
      FROM customer.customers c
      JOIN subscription.enticements e ON c.msisdn = e.customer_msisdn
      WHERE c.created_at::date BETWEEN '2025-10-03' AND '2025-10-04'
  AND e.created_at::date = c.created_at::date
  AND e.plan_id = 5;

"""
cur.execute(query)
db_msisdns = set(str(row[0]).strip() for row in cur.fetchall())

cur.close()
conn.close()

# Find intersection
matched_msisdns = input_msisdns.intersection(db_msisdns)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": list(matched_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Found {len(matched_msisdns)} matching MSISDNs.")
print(f"Output saved to {output_file}")
