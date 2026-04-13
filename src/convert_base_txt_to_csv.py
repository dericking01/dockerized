import os
import csv
import glob

csv.field_size_limit(10**9)

# === CONFIG ===
input_dir  = "/app/files/input/03-Base"
output_dir = "/app/files/output/03-Base-csv"
bad_rows_log = os.path.join(output_dir, "bad_rows.log")
delimiter = "\t"

os.makedirs(output_dir, exist_ok=True)

# Expected input columns (positional)
INPUT_COLS  = ["MPA_MSISDN", "TERRITORY"]
OUTPUT_COLS = ["MSISDN", "TERRITORY"]

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

                if len(parts) == len(INPUT_COLS):
                    msisdn    = parts[0].strip()
                    territory = parts[1].strip()
                    writer.writerow([msisdn, territory])
                    file_rows += 1
                else:
                    badfile.write(
                        f"[{os.path.basename(txt_path)}] Line {i} skipped "
                        f"(expected {len(INPUT_COLS)} cols, got {len(parts)}): {line}"
                    )
                    file_bad += 1

        total_converted += file_rows
        total_bad       += file_bad
        status = f"  bad={file_bad}" if file_bad else ""
        print(f"  {os.path.basename(txt_path):40s} -> {os.path.basename(output_path)}  ({file_rows} rows{status})")

print(f"\nDone. Total rows written: {total_converted} | Bad rows skipped: {total_bad}")
print(f"Output directory : {output_dir}")
print(f"Bad rows log     : {bad_rows_log}")
