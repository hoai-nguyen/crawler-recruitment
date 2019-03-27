#echo "Your input: "$1

#PORT_NUMBER=$1
kill -9 `lsof -i | grep php | awk 'NR!=1 {print $2}'`

echo "Done!"
