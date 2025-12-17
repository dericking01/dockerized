import pandas as pd
import pymysql
import os
import time

# ------------ CONFIG ------------
DB_HOST = "192.168.1.11"
DB_PORT = 3306
DB_NAME = "afyacallproduction"
DB_USER = "derrickdb"
DB_PASSWORD = "Derrick#@!2023"

OUTPUT_DIR = "/app/files/output"
CHUNK_SIZE = 50000   # Stream 50k rows at a time

os.makedirs(OUTPUT_DIR, exist_ok=True)
# --------------------------------

print("🚀 Starting Streaming Smart Bango Transaction Extractor...")
start_time = time.time()

# Connect
conn = pymysql.connect(
    host=DB_HOST,
    port=DB_PORT,
    user=DB_USER,
    password=DB_PASSWORD,
    database=DB_NAME,
    cursorclass=pymysql.cursors.SSCursor,  # STREAMING cursor
    autocommit=True,
    read_timeout=300,
    write_timeout=300
)

cur = conn.cursor()

print("📥 Executing optimized streaming query...")

query = """
SELECT
    t.created_at,
    t.amount_IN,
    t.currency,
    p.name,
    t.status,
    t.response,
    t.product_id,
    c.msisdn
FROM (
    SELECT msisdn
    FROM smart_bangos
    WHERE created_at BETWEEN '2023-11-01' AND '2024-04-30'
    GROUP BY msisdn
) sb
JOIN customers c ON sb.msisdn = c.msisdn
JOIN transactions t 
    ON c.id = t.customer_id 
    AND t.created_at BETWEEN '2023-11-01' AND '2024-04-30'
    AND t.status = 1
LEFT JOIN products p ON p.id = t.product_id;
"""

cur.execute(query)

# Column names for DataFrame
columns = [
    "transaction_date", "amount", "method", "product_name",
    "status", "response", "product", "msisdn"
]

# Track monthly files already created
written_files = set()
total_rows = 0

while True:
    rows = cur.fetchmany(CHUNK_SIZE)
    if not rows:
        break

    total_rows += len(rows)
    print(f"📦 Processing chunk → {len(rows)} rows (Total: {total_rows})")

    df = pd.DataFrame(rows, columns=columns)

    # Convert date to datetime object
    df["transaction_date"] = pd.to_datetime(df["transaction_date"])

    # Extract month (YYYY-MM)
    df["month"] = df["transaction_date"].dt.to_period("M")

    # Process each month in the chunk
    for month, group in df.groupby("month"):
        month_str = str(month).replace("-", "_")  # 2023_11
        filepath = f"{OUTPUT_DIR}/{month_str}_transactions.csv"

        # Write header only once per file
        write_header = filepath not in written_files

        group.drop(columns=["month"], inplace=False).to_csv(
            filepath,
            mode="a",
            index=False,
            header=write_header
        )

        if write_header:
            written_files.add(filepath)

        print(f"   ✔ Saved batch to {filepath} (+{len(group)} rows)")

cur.close()
conn.close()

elapsed = round(time.time() - start_time, 2)
print(f"\n✅ STREAMING JOB COMPLETE in {elapsed} seconds!")
print(f"📊 Total rows processed: {total_rows}")