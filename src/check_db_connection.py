import os
import psycopg2

"""Simple script that tests connectivity to the configured Postgres database.

It reads connection details from environment variables and attempts to open
and immediately close a connection.  Useful for container startup checks or
troubleshooting.
"""


def main():
    host = os.getenv("DB_HOST")
    port = os.getenv("DB_PORT", "5432")
    dbname = os.getenv("DB_NAME")
    user = os.getenv("DB_USER")
    password = os.getenv("DB_PASSWORD")

    if not all([host, dbname, user, password]):
        print("⚠️ Missing one or more required DB_* environment variables.")
        return 1

    try:
        conn = psycopg2.connect(
            host=host,
            port=port,
            dbname=dbname,
            user=user,
            password=password,
            connect_timeout=5,
        )
        conn.close()
        print(f"✅ Successfully connected to {dbname}@{host}:{port} as {user}")
        return 0
    except Exception as e:
        print(f"❌ Unable to connect to database: {e}")
        return 1


if __name__ == "__main__":
    exit(main())
