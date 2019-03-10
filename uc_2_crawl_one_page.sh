job_name=$1

echo "Your input: "$job_name

array=("topdev" "topcv" "itviec" "mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink")
if [[ ! " ${array[@]} " =~ "$job_name" ]] || [ "$job_name" = "" ]; then
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink."	
    exit 1
fi

./scripts/start_db_server.sh
./scripts/reset_db.sh continue $job_name

echo "Start crawling data from "$job_name

app1=`netstat -ano | grep 8111 | wc -l`
if [ $app1 -gt 1 ] || [ $app1 -eq 1 ]; then
	gnome-terminal -- /bin/bash -c "curl http://localhost:8111/$job_name"
fi

app2=`netstat -ano | grep 8222 | wc -l`
if [ $app2 -gt 1 ] || [ $app2 -eq 1 ]; then
	gnome-terminal -- /bin/bash -c "curl http://localhost:8222/$job_name"
fi

app3=`netstat -ano | grep 8333 | wc -l`
if [ $app3 -gt 1 ] || [ $app3 -eq 1 ]; then
	gnome-terminal -- /bin/bash -c "curl http://localhost:8333/$job_name"
fi



