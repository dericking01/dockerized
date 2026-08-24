import os
import psycopg2

# Prints, per day, the count of distinct (msisdn, plan_code) customers per
# product from billing.icg_payments, for a fixed date range.

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Date filter for the report
START_DATE = "2026-08-11 00:00:00"
END_DATE = "2026-08-13 23:59:59"

query = """
SELECT
  DATE(created_at) AS date,
  CASE
    WHEN plan_code = '921465_P01' THEN 'IVR'
    WHEN plan_code = '921465_P02' THEN 'SMS'
    WHEN plan_code = '921465_P03' THEN 'DR_SUB'
    WHEN plan_code = '921465_P04' THEN '1K Bundle'
    WHEN plan_code = '921465_P05' THEN '2K Bundle'
    WHEN plan_code = '921465_P06' THEN '3K Bundle'
    ELSE plan_code
  END AS product,
  COUNT(DISTINCT (msisdn, plan_code)) AS customers
FROM billing.icg_payments
WHERE created_at BETWEEN %s AND %s
GROUP BY date, product
ORDER BY date, customers DESC;
"""

conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)
cur = conn.cursor()
cur.execute(query, (START_DATE, END_DATE))
rows = cur.fetchall()
cur.close()
conn.close()

date_w = 10
product_w = max(7, max((len(str(r[1])) for r in rows), default=7))
customers_w = max(9, max((len(str(r[2])) for r in rows), default=9))

header = f"{'date':<{date_w}} {'product':<{product_w}} {'customers':>{customers_w}}"
print(header)
print("-" * len(header))

current_date = None
date_total = 0
for date, product, customers in rows:
    if current_date is not None and date != current_date:
        print(f"{str(current_date):<{date_w}} {'TOTAL':<{product_w}} {date_total:>{customers_w}}")
        print("-" * len(header))
        date_total = 0
    current_date = date
    date_total += customers
    print(f"{str(date):<{date_w}} {product:<{product_w}} {customers:>{customers_w}}")

if current_date is not None:
    print(f"{str(current_date):<{date_w}} {'TOTAL':<{product_w}} {date_total:>{customers_w}}")
    print("-" * len(header))

print(f"\n{len(rows)} rows")
