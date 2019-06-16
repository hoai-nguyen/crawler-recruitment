
page=$1
echo "Your input: "$page

export PATH=/Applications/XAMPP/xamppfiles/bin:$PATH

echo "Start initializing page: "$page
./scripts/init_db.sh
./scripts/create_data_dir.sh
./scripts/reset_db.sh reset $page 

echo "Done initializing page: "$page
