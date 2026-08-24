import csv
import os
from collections import defaultdict

# This script compares MSISDNs between /app/files/output/06-Base-clean and
# /app/files/output/07-Base-clean, grouped by geographic zone (CENTRAL,
# NORTH, SOUTH, LAKE, DAR), and logs how many MSISDNs from the 06 base are
# also present in the 07 base, per zone.
#
# Filenames carry an NSP/SP tag that is irrelevant to the zone grouping
# (e.g. DARCOAST_NSP_BASE_CLEAN.csv and NEW_DAR&COAST_SP_CLEAN.csv are both
# DAR), so files are grouped purely by the zone keyword they contain.
# DND files and "*_not_in_base.csv" diagnostic files are not zones/bases
# and are skipped.

DIR_06 = "/app/files/output/06-Base-clean"
DIR_07 = "/app/files/output/07-Base-clean"

# Order matters only in that each pattern must be unambiguous; DAR matches
# DARCOAST / DAR&COAST / DAR_COAST as well as plain DAR.
ZONE_KEYWORDS = [
    ("CENTRAL", "CENTRAL"),
    ("NORTH", "NORTH"),
    ("SOUTH", "SOUTH"),
    ("LAKE", "LAKE"),
    ("DAR", "DAR"),
]


def detect_zone(filename):
    upper = filename.upper()
    for zone, keyword in ZONE_KEYWORDS:
        if keyword in upper:
            return zone
    return None


def zone_files(directory):
    """Group CSV base files in a directory by zone, ignoring NSP/SP, DND
    and derivative "*_not_in_base.csv" diagnostic files."""
    grouped = defaultdict(list)
    skipped = []
    for filename in sorted(os.listdir(directory)):
        if not filename.lower().endswith(".csv"):
            continue
        if "not_in_base" in filename.lower():
            continue
        if "DND" in filename.upper():
            continue
        zone = detect_zone(filename)
        if zone is None:
            skipped.append(filename)
            continue
        grouped[zone].append(os.path.join(directory, filename))
    return grouped, skipped


def load_msisdns(filepath):
    """Read MSISDNs from a base CSV file.

    Some base files pack "MSISDN,GENDER,AGE" into a single quoted MSISDN
    field (e.g. "255746116585,M         ,39"), leaving GENDER/AGE empty,
    and may have a truncated final line with an unterminated quote, which
    pandas' C parser rejects but the csv module tolerates. Taking the part
    before the first comma works for both this malformed layout and the
    normal one where MSISDN is already its own field.
    """
    msisdns = set()
    with open(filepath, newline="") as f:
        reader = csv.reader(f)
        next(reader, None)  # skip header
        for row in reader:
            if not row:
                continue
            msisdn = row[0].split(",")[0].strip()
            if msisdn and msisdn.lower() != "nan":
                msisdns.add(msisdn)
    return msisdns


def load_zone_msisdns(filepaths):
    msisdns = set()
    for filepath in filepaths:
        msisdns |= load_msisdns(filepath)
    return msisdns


zones_06, skipped_06 = zone_files(DIR_06)
zones_07, skipped_07 = zone_files(DIR_07)

if skipped_06:
    print(f"Skipped (no zone matched) in 06-Base-clean: {skipped_06}")
if skipped_07:
    print(f"Skipped (no zone matched) in 07-Base-clean: {skipped_07}")

all_zones = sorted(set(zones_06) | set(zones_07))

print("\n=== MSISDN overlap between 06-Base-clean and 07-Base-clean, by zone ===")
total_06 = 0
total_07 = 0
total_matched = 0

for zone in all_zones:
    files_06 = zones_06.get(zone, [])
    files_07 = zones_07.get(zone, [])

    if not files_06 or not files_07:
        print(f"{zone}: skipped (missing in {'06' if not files_06 else '07'}-Base-clean)")
        continue

    msisdns_06 = load_zone_msisdns(files_06)
    msisdns_07 = load_zone_msisdns(files_07)
    matched = msisdns_06 & msisdns_07

    total_06 += len(msisdns_06)
    total_07 += len(msisdns_07)
    total_matched += len(matched)

    print(
        f"{zone}: {len(matched)} of {len(msisdns_06)} (06) also present in "
        f"07 ({len(msisdns_07)} total in 07)"
    )

print("\n=== Totals across all zones ===")
print(f"06-Base-clean total MSISDNs: {total_06}")
print(f"07-Base-clean total MSISDNs: {total_07}")
print(f"Matched: {total_matched}")
