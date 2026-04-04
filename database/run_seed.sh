#!/bin/bash
# run_seed.sh — Import GlobexSky demo seed data
# Usage: bash database/run_seed.sh
# Run from the repository root after all schema files have been imported.
#
# Override defaults via environment variables:
#   DB_USER=myuser DB_NAME=mydb bash database/run_seed.sh

DB_USER="${DB_USER:-root}"
DB_NAME="${DB_NAME:-globexsky_db}"

mysql -u "${DB_USER}" -p "${DB_NAME}" < database/seed_demo_data.sql
echo "Demo data seeded successfully!"
