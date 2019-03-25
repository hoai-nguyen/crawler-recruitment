job_name=$1

echo "Your input: "$job_name

array=("topdev" "topcv" "itviec" "mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink" "timviec365" "laodong" "tuyencongnhan" "tuyendungsinhvien" "tuyendungcomvn" "itguru" "tenshoku" "tenshokuex" "hatalike" "rikunabi" "doda")
if [[ ! " ${array[@]} " =~ "$job_name" ]] || [ "$job_name" = "" ]; then
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, careerlink, timviec365, laodong, tuyencongnhan, tuyendungcomvn, tuyendungsinhvien, itguru, tenshoku, tenshokuex, hatalike, rikunabi, doda."	
    exit 1
fi

echo "public/data/$job_name"

if [ -d "public/data/$job_name" ]; then
	tail -f "public/data/$job_name"/*-link.csv
else
	echo "wrong dir!"
fi

