job_name=$1

echo "Your input: "$job_name

function is_valid_job_name () {
  array=("topdev" "topcv" "itviec" "mywork" "uv_mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink" "timviec365" "laodong" "tuyencongnhan" "tuyendungsinhvien" "uv_tuyendungsinhvien" "tuyendungcomvn" "itguru" "tenshoku" "tenshokuex" "hatalike" "rikunabi" "doda" "enjapan")
  job_name=$1
  for i in "${array[@]}"
	do
	    if [ "$i" == "$job_name" ] ; then
		return 0
	    fi
	done
  return 1
}

if is_valid_job_name $job_name; then 
   	echo "public/data/$job_name"

	if [ -d "public/data/$job_name" ]; then
		tail -f "public/data/$job_name"/*-link.csv
	else
		echo "wrong dir!"
	fi
else 
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, uv_mywork, timviecnhanh, vieclam24h, findjobs,\
careerlink, timviec365, laodong, tuyencongnhan, tuyendungsinhvien, uv_tuyendungsinhvien, tuyendungcomvn, itguru,\
tenshoku, tenshokuex, hatalike, rikunabi, doda, enjapan."	
    exit 1
fi
