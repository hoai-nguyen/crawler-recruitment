# 2. crawl one page from start:
# - (run mysql, http server)
# - reset metadata for that job: job_metadata, url_data
# - clean current data: jobs, links

export PATH=/Applications/XAMPP/xamppfiles/bin:$PATH

job_name=$1

echo "Prepare to crawl "$job_name

function is_valid_job_name () {
  array=("topdev" "topcv" "itviec" "mywork" "uv_mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink" "timviec365" "laodong" "uv_laodong" "tuyencongnhan" "tuyendungsinhvien" "uv_tuyendungsinhvien" "tuyendungcomvn" "uv_tuyendungcomvn" "itguru" "tenshoku" "tenshokuex" "hatalike" "rikunabi" "doda" "enjapan" "uv_kenhtimviec")
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
   	./scripts/start_db_server.sh
	./scripts/clean_data_dir.sh $job_name
	./scripts/reset_db.sh reset $job_name

	echo "Done preparing to crawl "$job_name
else 
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, uv_mywork, timviecnhanh, vieclam24h, findjobs,\
careerlink, timviec365, laodong, uv_laodong, tuyencongnhan, tuyendungsinhvien, uv_tuyendungsinhvien, tuyendungcomvn, uv_tuyendungcomvn, itguru,\
tenshoku, tenshokuex, hatalike, rikunabi, doda, enjapan, uv_kenhtimviec."	
    exit 1
fi






