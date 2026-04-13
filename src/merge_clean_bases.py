import csv
import os

csv.field_size_limit(10**9)

# === CONFIG ===
input_files = [
    "/app/files/output/03-Base-clean/NSP_MARCH_26_LAKE_CLEAN.csv",
    "/app/files/output/03-Base-clean/NSP_MARCH_26_LAKE_2_CLEAN.csv",
]
output_file = "/app/files/output/03-Base-clean/NSP_MARCH_26_LAKE_MERGED_CLEAN.csv"
duplicate_log = "/app/files/output/03-Base-clean/NSP_MARCH_26_LAKE_MERGED_duplicates.log"

required_columns = ["MSISDN", "TERRITORY"]

os.makedirs(os.path.dirname(output_file), exist_ok=True)

seen_msisdns = set()
total_rows = 0
kept_rows = 0
duplicate_rows = 0

with open(output_file, "w", newline="", encoding="utf-8") as outfile, \
     open(duplicate_log, "w", encoding="utf-8") as log_file:
    writer = csv.DictWriter(outfile, fieldnames=required_columns)
    writer.writeheader()

    for input_file in input_files:
        print(f"Processing {input_file}...")

        if not os.path.exists(input_file):
            raise FileNotFoundError(f"Input file not found: {input_file}")

        with open(input_file, "r", encoding="utf-8", errors="ignore") as infile:
            reader = csv.DictReader(infile)

            missing_columns = [col for col in required_columns if col not in (reader.fieldnames or [])]
            if missing_columns:
                raise ValueError(
                    f"Missing required columns {missing_columns} in {input_file}. "
                    f"Found: {reader.fieldnames}"
                )

            for line_number, row in enumerate(reader, start=2):
                total_rows += 1
                msisdn = (row.get("MSISDN") or "").strip()
                territory = (row.get("TERRITORY") or "").strip()

                if not msisdn:
                    duplicate_rows += 1
                    log_file.write(
                        f"[{os.path.basename(input_file)}:{line_number}] skipped empty MSISDN\n"
                    )
                    continue

                if msisdn in seen_msisdns:
                    duplicate_rows += 1
                    log_file.write(
                        f"[{os.path.basename(input_file)}:{line_number}] duplicate MSISDN {msisdn}\n"
                    )
                    continue

                seen_msisdns.add(msisdn)
                writer.writerow({"MSISDN": msisdn, "TERRITORY": territory})
                kept_rows += 1

print("Done.")
print(f"Input rows read     : {total_rows}")
print(f"Unique rows written : {kept_rows}")
print(f"Duplicate rows drop : {duplicate_rows}")
print(f"Output file         : {output_file}")
print(f"Duplicate log       : {duplicate_log}")