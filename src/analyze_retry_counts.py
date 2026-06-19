import pandas as pd
import os

# Input CSV file from export_icg_payments.py
input_file = "/app/files/output/icg_payments_2026-06-04.csv"

# Output file path for retry analysis
output_file = "/app/files/output/retry_counts_by_msisdn_plan_2026-06-04.csv"

print(f"Reading transactions from {input_file}...")

# Read the CSV
df = pd.read_csv(input_file)

print(f"Total transaction records: {len(df)}")

# Ensure updated_at is datetime for proper sorting
df['updated_at'] = pd.to_datetime(df['updated_at'], errors='coerce')

# Sort by updated_at descending so latest comes first
df = df.sort_values('updated_at', ascending=False, na_position='last')

# Get the latest status for each (msisdn, plan_code) by keeping first occurrence
latest_statuses = df.drop_duplicates(subset=['msisdn', 'plan_code'], keep='first')[['msisdn', 'plan_code', 'status']]

# Count retries per (msisdn, plan_code)
retry_counts = df.groupby(['msisdn', 'plan_code']).size().reset_index(name='retry_count')

# Merge with latest statuses
retry_counts = retry_counts.merge(latest_statuses, on=['msisdn', 'plan_code'], how='left')

# Rename status to latest_status
retry_counts = retry_counts.rename(columns={'status': 'latest_status'})

# Sort by retry_count descending (highest to lowest)
retry_counts = retry_counts.sort_values('retry_count', ascending=False)

# Reorder columns for clarity
retry_counts = retry_counts[['msisdn', 'plan_code', 'retry_count', 'latest_status']]

# Ensure output directory exists
os.makedirs(os.path.dirname(output_file), exist_ok=True)

# Save to CSV
retry_counts.to_csv(output_file, index=False)

print(f"\nRetry Count Analysis Summary:")
print(f"  Unique (msisdn, plan_code) combinations: {len(retry_counts)}")
print(f"  Max retries: {retry_counts['retry_count'].max()}")
print(f"  Min retries: {retry_counts['retry_count'].min()}")
print(f"  Average retries: {retry_counts['retry_count'].mean():.2f}")
print(f"  Unique statuses: {retry_counts['latest_status'].nunique()}")

print(f"\nTop 10 (msisdn, plan_code) by retry count:")
print(retry_counts.head(10).to_string(index=False))

print(f"\n✅ Done! Retry analysis saved to {output_file}")
# the aim of this script is to analyze the billing data and identify the retry counts for each unique combination of msisdn and plan_code. 
# The script reads a CSV file containing transaction records, processes the data to determine the latest status for each (msisdn, plan_code) pair, 
# counts the number of retries, and then saves the results to a new CSV file. The output includes a summary of the retry counts and the top 10 combinations with the highest retry counts.