import logging
import os
from datetime import datetime
from typing import Iterable, Set

import pandas as pd
import psycopg2


# Database configuration from environment variables
DB_HOST = os.getenv("DB_HOST")
DB_PORT = os.getenv("DB_PORT", "5432")
DB_NAME = os.getenv("DB_NAME")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")

# Query parameters
TARGET_DATE = os.getenv("TARGET_DATE", "2026-04-15")
TARGET_STATUS = os.getenv("TARGET_STATUS", "SUCCESS")

# CSV files to compare
INPUT_FILES = [
    "/app/files/output/15_APR_2026_DR_NSP_SOUTH.csv",
    "/app/files/output/15_APR_2026_IVR_NSP_CENTRAL.csv",
    "/app/files/output/15_APR_2026_SMS_NSP_SOUTH.csv",
]

OUTPUT_FILE = "/app/files/output/15_APR_2026_FILES_NOT_IN_ENTICEMENT_LOGS.csv"


def configure_logging() -> None:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s | %(levelname)s | %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
    )


def normalize_msisdn(values: Iterable[object]) -> Set[str]:
    normalized = set()
    for value in values:
        if pd.isna(value):
            continue

        msisdn = str(value).strip()
        if msisdn:
            normalized.add(msisdn)

    return normalized


def load_msisdns_from_files(file_paths: list[str]) -> Set[str]:
    all_msisdns = set()

    for file_path in file_paths:
        if not os.path.exists(file_path):
            logging.warning("File not found, skipping: %s", file_path)
            continue

        df = pd.read_csv(file_path)
        if "MSISDN" not in df.columns:
            logging.warning("'MSISDN' column missing in %s, skipping", file_path)
            continue

        file_msisdns = normalize_msisdn(df["MSISDN"])
        logging.info(
            "Loaded %d unique MSISDNs from %s",
            len(file_msisdns),
            file_path,
        )
        all_msisdns.update(file_msisdns)

    logging.info("Total unique MSISDNs collected from files: %d", len(all_msisdns))
    return all_msisdns


def fetch_db_msisdns() -> Set[str]:
    query = """
        SELECT customer_msisdn
        FROM subscription.enticement_logs
        WHERE created_at::date = %s
          AND status = %s;
    """

    conn = psycopg2.connect(
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
    )

    try:
        with conn.cursor() as cursor:
            cursor.execute(query, (TARGET_DATE, TARGET_STATUS))
            rows = cursor.fetchall()
    finally:
        conn.close()

    db_msisdns = normalize_msisdn(row[0] for row in rows)
    logging.info(
        "Fetched %d unique MSISDNs from DB for date=%s, status=%s",
        len(db_msisdns),
        TARGET_DATE,
        TARGET_STATUS,
    )
    return db_msisdns


def save_output(msisdns: Set[str], output_file: str) -> None:
    output_df = pd.DataFrame({"MSISDN": sorted(msisdns)})
    output_df.to_csv(output_file, index=False)
    logging.info("Saved %d MSISDNs to %s", len(msisdns), output_file)


def validate_env() -> None:
    required = {
        "DB_HOST": DB_HOST,
        "DB_NAME": DB_NAME,
        "DB_USER": DB_USER,
        "DB_PASSWORD": DB_PASSWORD,
    }

    missing = [key for key, value in required.items() if not value]
    if missing:
        raise ValueError(f"Missing required environment variables: {', '.join(missing)}")


def main() -> None:
    configure_logging()
    started_at = datetime.now()
    logging.info("Starting MSISDN comparison against subscription.enticement_logs")

    validate_env()

    file_msisdns = load_msisdns_from_files(INPUT_FILES)
    db_msisdns = fetch_db_msisdns()

    not_in_db = file_msisdns.difference(db_msisdns)
    logging.info("MSISDNs present in files but absent in DB query: %d", len(not_in_db))

    save_output(not_in_db, OUTPUT_FILE)

    elapsed = datetime.now() - started_at
    logging.info("Completed in %s", elapsed)


if __name__ == "__main__":
    main()
