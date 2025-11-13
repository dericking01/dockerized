import pandas as pd
import psycopg2
import os

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Output file path
output_file = "/app/files/output/customers_products.csv"

# Connect to Postgres
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()
# This script identifies customers and their latest products engaged in the last 90 days.
# Query: get latest product per msisdn in last 90 days including latest payment date
query = """
WITH latest_payments AS (
    SELECT DISTINCT ON (p.billable_phone_number, p.payable_id)
        p.billable_phone_number AS msisdn,
        p.payable_id,
        pl.name AS product_name,
        p.updated_at
    FROM billing.payments p
    JOIN subscription.plans pl
        ON p.payable_id::bigint = pl.id   -- ✅ explicit cast
    WHERE p.status = 'SUCCESS'
      AND p.updated_at >= NOW() - INTERVAL '90 days'
    ORDER BY p.billable_phone_number, p.payable_id, p.updated_at DESC
)
SELECT c.msisdn, lp.product_name, lp.updated_at
FROM customer.customers c
JOIN latest_payments lp
  ON c.msisdn = lp.msisdn
WHERE c.msisdn LIKE '255%';
-- This will give us the latest payment date for each product per customer in the last 90 days
"""

cur.execute(query)
rows = cur.fetchall()
cur.close()
conn.close()

# Convert to DataFrame
df_output = pd.DataFrame(rows, columns=["MSISDN", "Product", "LatestPaymentDate"])
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Exported {len(df_output)} records.")
print(f"Output saved to {output_file}")