import pandas as pd
import os
import csv

# === Increase max CSV field size ===
csv.field_size_limit(10**9)  # 1 billion chars per field

# === CONFIG ===
input_file = "/app/files/input/afya_base_analysis.txt"
output_file = "/app/files/output/afya_base_analysis.csv"
bad_rows_log = "/app/files/output/bad_rows.log"
delimiter = "\t"

os.makedirs(os.path.dirname(output_file), exist_ok=True)

# === COLUMNS ===
col_names = [
    "MSISDN", "SUB_TYPE", "GENDER", "AGE", "ARPU_SEGMENT", "SMARTPHONE_USER",
    "ACS_CHARGE", "ACS_USER", "VAS_CHARGE", "VAS_USER", "TERRITORY", "COMMERCIAL_REGION"
]

# === CHUNK CONFIG ===
chunk_size = 200000

print(f"Reading and converting '{input_file}' ...")

# === Function to safely read file line by line and write clean CSV ===
with open(input_file, "r", encoding="utf-8", errors="ignore") as infile, \
     open(output_file, "w", newline="", encoding="utf-8") as outfile, \
     open(bad_rows_log, "w", encoding="utf-8") as badfile:

    writer = csv.writer(outfile)
    writer.writerow(col_names)

    for i, line in enumerate(infile, 1):
        parts = line.strip().split(delimiter)
        if len(parts) == len(col_names):
            writer.writerow(parts)
        else:
            badfile.write(f"Line {i} skipped (expected {len(col_names)} cols, got {len(parts)}): {line}\n")

print("✅ Conversion complete!")
print(f"Output file: {output_file}")
print(f"Bad rows logged to: {bad_rows_log}")
print(f"Total lines processed: {i}")