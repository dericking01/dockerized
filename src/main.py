import pandas as pd
import psycopg2
import os

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Load MSISDNs from Excel
input_file = "/app/files/input/churned_1_30th.xlsx"
df_input = pd.read_excel(input_file)

# Prepare output DataFrame
results = []

# Connect to Postgres
conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)

cur = conn.cursor()

for msisdn in df_input['customer_msisdn']:
    cur.execute("""
        SELECT updated_at, payable_id 
        FROM billing.payments
        WHERE billable_phone_number = %s AND status = 'SUCCESS'
        ORDER BY updated_at DESC
        LIMIT 1
    """, (str(msisdn),))
    row = cur.fetchone()
    if row:
        updated_at, payable_id = row
    else:
        updated_at, payable_id = None, None

    results.append({
        "customer_msisdn": msisdn,
        "updated_at": updated_at,
        "payable_id": payable_id
    })

cur.close()
conn.close()

# Save output to Excel
df_output = pd.DataFrame(results)
df_output.to_excel("/app/files/output/output_churned_report.xlsx", index=False)
print("✅ Done! Output saved to /app/files/output/output_churned_report.xlsx")
