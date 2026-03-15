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
output_file = "/app/files/output/subscribers_not_on_921465_P02.csv"

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
    SELECT DISTINCT customer_msisdn
    FROM subscription.subscribers
    WHERE plan_code != '921465_P02'
"""
cur.execute(query)
db_msisdns = [str(row[0]).strip() for row in cur.fetchall()]

cur.close()
conn.close()

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": db_msisdns})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Exported {len(db_msisdns)} MSISDNs.")
print(f"Output saved to {output_file}")
