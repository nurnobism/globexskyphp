#!/bin/bash
# run_seed.sh — Import GlobexSky demo seed data
# Usage: bash database/run_seed.sh
# Run from the repository root after all schema files have been imported.

mysql -u bidybxoc_globexsky -p bidybxoc_globexsky < database/seed_demo_data.sql
echo "Demo data seeded successfully!"
