# 1. from very start:
# - run mysql, http server
# - create metadata tables
# - create data folders

echo "Prepare to run from start."

./scripts/start_db_server.sh
./scripts/init_db.sh
./scripts/create_data_dir.sh

# when we want to run from begining, just clean data and metadata:
./scripts/clean_data_dir.sh all 
./scripts/reset_db.sh reset all 

echo "Done preparing to run from start."
