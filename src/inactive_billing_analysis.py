import os
import time
import psycopg2

# ---------------------------------------------------------------------------
# DB connection (same env-var pattern as the rest of the project)
# ---------------------------------------------------------------------------
DB_HOST     = os.getenv("DB_HOST")
DB_PORT     = os.getenv("DB_PORT", "5432")
DB_NAME     = os.getenv("DB_NAME")
DB_USER     = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# ---------------------------------------------------------------------------
# Strategy: do ALL heavy lifting inside Postgres.
#
#   • billing.icg_payments  → 600 M rows  (never pulled into Python)
#   • subscription.subscribers → 1.5 M rows
#
# The query runs in three logical steps that Postgres executes as one plan:
#   1. Aggregate billing.icg_payments once  →  last SUCCESS date per msisdn
#   2. Distinct subscriber MSISDNs          →  the authoritative base
#   3. LEFT JOIN + two FILTER counts        →  final numbers
#
# Python only receives a single result row.
#
# ⚡ INDEX TIP: this query benefits greatly from:
#     CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_icg_payments_status_msisdn_created
#     ON billing.icg_payments (status, msisdn, created_at DESC)
#     WHERE status = 'SUCCESS';
#   If that index doesn't exist yet the query will still work but will do a
#   full sequential scan of the 600 M-row table.
# ---------------------------------------------------------------------------

QUERY = """
WITH
-- Step 1 ─ Last successful payment date per msisdn (one pass over big table)
last_success AS (
    SELECT
        msisdn,
        MAX(created_at) AS last_success_at
    FROM billing.icg_payments
    WHERE status = 'SUCCESS'
    GROUP BY msisdn
),

-- Step 2 ─ Distinct active subscriber base
subscriber_base AS (
    SELECT DISTINCT customer_msisdn
    FROM subscription.subscribers
),

-- Step 3 ─ Attach last-success date to each subscriber (NULL = never billed)
subscriber_last_payment AS (
    SELECT
        sb.customer_msisdn,
        ls.last_success_at
    FROM subscriber_base sb
    LEFT JOIN last_success ls
           ON ls.msisdn = sb.customer_msisdn
)

-- Step 4 ─ Single-pass count with FILTER (two thresholds, one scan)
SELECT
    COUNT(*)                                                         AS total_subscribers,

    COUNT(*) FILTER (
        WHERE last_success_at IS NULL
           OR last_success_at < CURRENT_TIMESTAMP - INTERVAL '60 days'
    )                                                                AS no_success_gt_60_days,

    COUNT(*) FILTER (
        WHERE last_success_at IS NULL
           OR last_success_at < CURRENT_TIMESTAMP - INTERVAL '90 days'
    )                                                                AS no_success_gt_90_days,

    -- Bonus: subscribers who have NEVER had a successful payment at all
    COUNT(*) FILTER (WHERE last_success_at IS NULL)                  AS never_billed
FROM subscriber_last_payment;
"""


def fmt_num(n):
    """Format integer with thousands separator."""
    return f"{n:,}"


def pct(part, total):
    """Percentage string rounded to 2 dp."""
    if total == 0:
        return "N/A"
    return f"{(part / total) * 100:.2f}%"


def main():
    print("=" * 60)
    print("  Inactive Billing Analysis")
    print("  Checking last successful payment per subscriber")
    print("=" * 60)

    # ── Validate env vars ────────────────────────────────────────────────────
    missing = [v for v in ("DB_HOST", "DB_NAME", "DB_USER", "DB_PASSWORD")
               if not os.getenv(v)]
    if missing:
        print(f"❌ Missing environment variable(s): {', '.join(missing)}")
        return 1

    # ── Connect ──────────────────────────────────────────────────────────────
    print(f"\nConnecting to {DB_NAME}@{DB_HOST}:{DB_PORT}…")
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            port=DB_PORT,
            dbname=DB_NAME,
            user=DB_USER,
            password=DB_PASSWORD,
            # Use a server-side cursor would not help here (single-row result),
            # but set a long statement timeout just in case.
            options="-c statement_timeout=0",
        )
    except Exception as e:
        print(f"❌ Connection failed: {e}")
        return 1

    # ── Run query ────────────────────────────────────────────────────────────
    print("Running analysis query (this may take several minutes on 600 M rows)…\n")
    start = time.time()

    try:
        with conn.cursor() as cur:
            cur.execute(QUERY)
            row = cur.fetchone()
    except Exception as e:
        print(f"❌ Query failed: {e}")
        conn.close()
        return 1
    finally:
        conn.close()

    elapsed = time.time() - start

    # ── Unpack results ───────────────────────────────────────────────────────
    total, gt60, gt90, never = row

    # ── Report ───────────────────────────────────────────────────────────────
    print("=" * 60)
    print("  RESULTS")
    print("=" * 60)
    print(f"  Total subscribers              : {fmt_num(total)}")
    print()
    print(f"  No successful payment in >60 days")
    print(f"    Count  : {fmt_num(gt60)}")
    print(f"    % base : {pct(gt60, total)}")
    print()
    print(f"  No successful payment in >90 days")
    print(f"    Count  : {fmt_num(gt90)}")
    print(f"    % base : {pct(gt90, total)}")
    print()
    print(f"  Never billed successfully (no record at all)")
    print(f"    Count  : {fmt_num(never)}")
    print(f"    % base : {pct(never, total)}")
    print()
    print(f"  ⏱  Query completed in {elapsed:.1f}s")
    print("=" * 60)
    print("✅ Done!")
    return 0


if __name__ == "__main__":
    exit(main())
# aim of this script is to analyze the billing data and identify subscribers who have not had a successful payment in the 
# last 60 or 90 days, as well as those who have never had a successful payment. 
# The analysis is performed entirely within the PostgreSQL database to efficiently handle the large dataset of 600 million rows in the billing.icg_payments table. The results are printed in a clear and formatted manner, showing both counts and percentages for each category of inactivity.