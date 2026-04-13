import os
import csv
import glob

csv.field_size_limit(10**9)

# === CONFIG ===
base_dir        = "/app/files/output/03-Base-csv"
output_dir      = "/app/files/output/03-Base-clean"
dnd_file        = os.path.join(base_dir, "DND_APRIL.csv")
filter_log      = os.path.join(output_dir, "filter_summary.log")

os.makedirs(output_dir, exist_ok=True)

# === Step 1: Load DND MSISDNs into a set ===
print(f"Loading DND MSISDNs from '{dnd_file}'...")

dnd_msisdns = set()
try:
    with open(dnd_file, "r", encoding="utf-8", errors="ignore") as f:
        reader = csv.DictReader(f)
        for row in reader:
            msisdn = row.get("MSISDN", "").strip()
            if msisdn:
                dnd_msisdns.add(msisdn)
except FileNotFoundError:
    print(f"❌ Error: DND file not found at '{dnd_file}'")
    exit(1)

print(f"✅ Loaded {len(dnd_msisdns)} DND MSISDNs\n")

# === Step 2: Process all CSV files (except DND_APRIL.csv) ===
csv_files = sorted([f for f in glob.glob(os.path.join(base_dir, "*.csv")) 
                    if os.path.basename(f) != "DND_APRIL.csv"])

if not csv_files:
    print("No CSV files to process.")
    exit(0)

print(f"Found {len(csv_files)} file(s) to filter.\n")

total_processed = 0
total_filtered  = 0
total_kept      = 0

with open(filter_log, "w", encoding="utf-8") as logfile:
    for csv_path in csv_files:
        basename   = os.path.splitext(os.path.basename(csv_path))[0]
        output_file = os.path.join(output_dir, basename + "_CLEAN.csv")

        file_total   = 0
        file_filtered = 0
        file_kept    = 0

        with open(csv_path, "r", encoding="utf-8", errors="ignore") as infile, \
             open(output_file, "w", newline="", encoding="utf-8") as outfile:

            reader = csv.DictReader(infile)
            writer = csv.DictWriter(outfile, fieldnames=["MSISDN", "TERRITORY"])
            writer.writeheader()

            for row in reader:
                file_total += 1
                msisdn = row.get("MSISDN", "").strip()
                territory = row.get("TERRITORY", "").strip()

                if msisdn in dnd_msisdns:
                    file_filtered += 1
                else:
                    writer.writerow({"MSISDN": msisdn, "TERRITORY": territory})
                    file_kept += 1

        total_processed += file_total
        total_filtered  += file_filtered
        total_kept      += file_kept

        status = f"(filtered={file_filtered})" if file_filtered else ""
        print(f"  {os.path.basename(csv_path):40s} -> {os.path.basename(output_file):45s}  kept={file_kept:>8} {status}")
        
        logfile.write(f"{basename}.csv: total={file_total}, filtered={file_filtered}, kept={file_kept}\n")

print(f"\n✅ Done!")
print(f"Total records processed : {total_processed}")
print(f"Total records filtered  : {total_filtered}")
print(f"Total records kept      : {total_kept}")
print(f"\nOutput directory: {output_dir}")
print(f"Filter log     : {filter_log}")
