import os
import time
import psycopg2

# This script extracts the latest 600,000 records from chat.chat_history
# (ordered by id descending) and saves the full result set to a CSV file.

# Load environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Number of latest records to extract
RECORD_LIMIT = 600000

# Output file path
output_file = "/app/files/output/chat_history_latest_600k.csv"

query = f"""
COPY (
    SELECT *
    FROM chat.chat_history
    ORDER BY id DESC
    LIMIT {RECORD_LIMIT}
) TO STDOUT WITH CSV HEADER
"""

print("Starting export from chat.chat_history...")

os.makedirs(os.path.dirname(output_file), exist_ok=True)

conn = psycopg2.connect(
    host=DB_HOST,
    port=DB_PORT,
    dbname=DB_NAME,
    user=DB_USER,
    password=DB_PASSWORD
)

try:
    with conn.cursor() as cur:
        # Writer wrapper that counts lines and bytes while streaming to disk
        class CountingWriter:
            def __init__(self, fh):
                self.fh = fh
                self.lines = 0
                self.bytes = 0

            def write(self, s):
                # Accept both `str` and `bytes` since psycopg2 may pass bytes.
                if isinstance(s, bytes):
                    try:
                        decoded = s.decode("utf-8")
                    except Exception:
                        decoded = s.decode("latin-1")
                    written = self.fh.write(decoded)
                    self.bytes += len(s)
                    self.lines += decoded.count("\n")
                    return written

                # s is a str (text mode); count newline characters
                written = self.fh.write(s)
                try:
                    self.bytes += len(s.encode("utf-8"))
                except Exception:
                    self.bytes += len(s)
                self.lines += s.count("\n")
                return written

            def flush(self):
                return self.fh.flush()

            def close(self):
                return self.fh.close()

        start_ts = time.time()
        with open(output_file, "w", newline="", encoding="utf-8") as f:
            writer = CountingWriter(f)
            cur.copy_expert(query, writer)
        elapsed = time.time() - start_ts

        rows_extracted = max(0, writer.lines - 1)  # subtract header
        file_size = os.path.getsize(output_file)

    print("Export completed without errors.")
    print(f"Rows extracted: {rows_extracted}")
    print(f"Elapsed time: {elapsed:.2f}s")
    def _hr(n):
        for unit in ['B','KB','MB','GB','TB']:
            if n < 1024.0:
                return f"{n:3.1f}{unit}"
            n /= 1024.0
        return f"{n:.1f}PB"

    print(f"Output file size: {_hr(file_size)} ({file_size} bytes)")
    print(f"✅ Done! Export saved to {output_file}")
except Exception as exc:
    print(f"❌ Export failed: {exc}")
finally:
    conn.close()
