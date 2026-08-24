import os
import csv
import glob

csv.field_size_limit(10**9)

# === CONFIG ===
input_dir  = "/app/files/input/07-Base"
output_dir = "/app/files/output/07-Base-csv"
bad_rows_log = os.path.join(output_dir, "bad_rows.log")
delimiter = "\t"

os.makedirs(output_dir, exist_ok=True)

# Expected input columns (positional)
INPUT_COLS  = ["MPA_MSISDN", "GENDER", "AGE"]
OUTPUT_COLS = ["MSISDN", "GENDER", "AGE"]

txt_files = sorted(glob.glob(os.path.join(input_dir, "*.txt")))

if not txt_files:
    print(f"No .txt files found in '{input_dir}'")
    exit(0)

print(f"Found {len(txt_files)} .txt file(s) to process.\n")

total_converted = 0
total_bad       = 0

with open(bad_rows_log, "w", encoding="utf-8") as badfile:
    for txt_path in txt_files:
        basename    = os.path.splitext(os.path.basename(txt_path))[0]
        output_path = os.path.join(output_dir, basename + ".csv")

        file_rows = 0
        file_bad  = 0

        with open(txt_path, "r", encoding="utf-8", errors="ignore") as infile, \
             open(output_path, "w", newline="", encoding="utf-8") as outfile:

            writer = csv.writer(outfile)
            writer.writerow(OUTPUT_COLS)

            for i, line in enumerate(infile, 1):
                # Skip the header line if present
                if i == 1 and line.strip().upper().startswith("MPA_MSISDN"):
                    continue

                parts = line.strip().split(delimiter)

                # MSISDN is required; other fields are optional.
                msisdn = parts[0].strip() if len(parts) > 0 else ""
                if msisdn:
                    gender = parts[1].strip() if len(parts) > 1 else ""
                    age    = parts[2].strip() if len(parts) > 2 else ""
                    writer.writerow([msisdn, gender, age])
                    file_rows += 1
                else:
                    badfile.write(
                        f"[{os.path.basename(txt_path)}] Line {i} skipped "
                        f"(missing MSISDN): {line}"
                    )
                    file_bad += 1

        total_converted += file_rows
        total_bad       += file_bad
        status = f"  bad={file_bad}" if file_bad else ""
        print(f"  {os.path.basename(txt_path):40s} -> {os.path.basename(output_path)}  ({file_rows} rows{status})")

print(f"\nDone. Total rows written: {total_converted} | Bad rows skipped: {total_bad}")
print(f"Output directory : {output_dir}")
print(f"Bad rows log     : {bad_rows_log}")
