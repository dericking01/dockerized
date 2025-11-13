import pandas as pd
import psycopg2
import os

# --- Load environment variables ---
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# --- Output file path ---
output_file = "/app/files/output/customers_inactive_90days.csv"

# --- Connect to Postgres ---
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()
# This script identifies customers who have NOT had any SUCCESS payments in the last 90 days.
# --- Query: customers who have NOT had a SUCCESS payment in last 90 days ---
query = """
WITH latest_payment AS (
    SELECT
        p.billable_phone_number AS msisdn,
        MAX(p.updated_at) AS last_success_date
    FROM billing.payments p
    WHERE p.status = 'SUCCESS'
    GROUP BY p.billable_phone_number
)
SELECT c.msisdn
FROM customer.customers c
LEFT JOIN latest_payment lp
  ON c.msisdn = lp.msisdn
WHERE c.msisdn LIKE '255%'
  AND (
        lp.last_success_date IS NULL
        OR lp.last_success_date < NOW() - INTERVAL '90 days'
      );
"""

# --- Execute query ---
cur.execute(query)
rows = cur.fetchall()

# --- Close connections ---
cur.close()
conn.close()

# --- Convert to DataFrame ---
df_output = pd.DataFrame(rows, columns=["MSISDN"])

# --- Save to CSV ---
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Exported {len(df_output)} inactive customers (>90 days).")
print(f"Output saved to {output_file}")