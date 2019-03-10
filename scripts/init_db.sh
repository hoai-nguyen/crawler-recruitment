echo "Init database. Please enter database password. Default password is empty ("")."

mysql -u root -p < scripts/init_db.sql

echo "DONE initializing database."
