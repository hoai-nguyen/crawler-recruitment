job_name=$1

echo "Your input: "$job_name

array=("topdev" "topcv" "itviec" "mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink")
if [[ ! " ${array[@]} " =~ "$job_name" ]] || [ "$job_name" = "" ]; then
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink."	
    exit 1
fi

if [ -d "public/data/$job_name" ]; then
	tail -f "public/data/$job_name"/*-link.csv
fi

