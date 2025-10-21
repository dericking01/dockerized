import pandas as pd
import os
# this script finds MSISDNs that are present in the first input CSV file
# but absent in the second input CSV file, and writes them to an output CSV file.

# Input and output file paths
input_file1 = "/app/files/output/10_OCT_2Bnon_IVR_subscribers.csv"
input_file2 = "/app/files/output/matched_msisdns_latest.csv"
output_file = "/app/files/output/non_matching_msisdns_10_OCT_IVR.csv"

# Read MSISDNs from both CSVs
df1 = pd.read_csv(input_file1)
df2 = pd.read_csv(input_file2)

# Normalize MSISDNs as strings and strip spaces
msisdns1 = set(df1['MSISDN'].astype(str).str.strip())
msisdns2 = set(df2['MSISDN'].astype(str).str.strip())

# Find MSISDNs present in file1 but absent in file2
non_matching_msisdns = msisdns1.difference(msisdns2)

# Save to output CSV
df_output = pd.DataFrame({"MSISDN": sorted(non_matching_msisdns)})
df_output.to_csv(output_file, index=False)

print(f"✅ Done! Found {len(non_matching_msisdns)} MSISDNs present in file1 but not in file2.")
print(f"Output saved to {output_file}")
