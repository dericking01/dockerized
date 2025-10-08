import pandas as pd
import os

# This script finds MSISDNs that are present in both of two input CSV files
# and writes them to an output CSV file.

# Input and output file paths
input_file1 = "/app/files/output/01_OCT_non_SMS_subscribers.csv"
input_file2 = "/app/files/output/02_OCT_non_DOCSUB_subscribers.csv"
output_file = "/app/files/output/matched_msisdns.csv"

# Read MSISDNs from both CSVs
df1 = pd.read_csv(input_file1)
df2 = pd.read_csv(input_file2)

# Normalize MSISDNs as strings and strip spaces
msisdns1 = set(df1['MSISDN'].astype(str).str.strip())
msisdns2 = set(df2['MSISDN'].astype(str).str.strip())

# Find intersection (present in both files)
matched_msisdns = msisdns1.intersection(msisdns2)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": sorted(matched_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Found {len(matched_msisdns)} matching MSISDNs.")
print(f"Output saved to {output_file}")
