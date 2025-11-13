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
output_file = "/app/files/output/11_NOV_IVR_inactive_customers_90days.csv"

# --- Connect to Postgres ---
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()
# This script identifies customers who are on plan_id=1 and have been ACTIVE but have not made any SUCCESS payments in the last 90 days.
# --- Query: plan_id=1 ACTIVE customers with no SUCCESS payment in last 90 days ---
query = """
WITH recent_payers AS (
    SELECT DISTINCT p.billable_phone_number AS msisdn
    FROM billing.payments p
    WHERE p.status = 'SUCCESS'
      AND p.updated_at >= NOW() - INTERVAL '90 days'
)
SELECT DISTINCT c.msisdn
FROM customer.customers c
JOIN subscription.enticements se 
    ON c.msisdn = se.customer_msisdn
LEFT JOIN recent_payers rp 
    ON c.msisdn = rp.msisdn
WHERE c.msisdn LIKE '255%'
  AND se.plan_id = 5
  AND se.status = 'ACTIVE'
  AND rp.msisdn IS NULL;
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

print(f"✅ Done! Exported {len(df_output)} active plan-1 customers without payment in >90 days.")
print(f"Output saved to {output_file}")
