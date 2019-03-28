# 2. crawl one page from start:
# - (run mysql, http server)
# - reset metadata for that job: job_metadata, url_data
# - clean current data: jobs, links

export PATH=/Applications/XAMPP/xamppfiles/bin:$PATH

job_name=$1

echo "Prepare to crawl "$job_name

array=("topdev" "topcv" "itviec" "mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink" "timviec365" "laodong" "tuyencongnhan" "tuyendungsinhvien" "uv_tuyendungsinhvien" "itguru" "tenshoku" "tenshokuex" "hatalike" "rikunabi" "doda" "enjapan")
if [[ ! " ${array[@]} " =~ "$job_name" ]] || [ "$job_name" = "" ]; then
    echo "Please provide job_name."
    echo "Available job_name: topdev, topcv, itviec, mywork, timviecnhanh, vieclam24h, findjobs, \
    careerlink, timviec365, laodong, tuyencongnhan, tuyendungsinhvien, uv_tuyendungsinhvien, itguru, tenshoku, tenshokuex,\
     hatalike, rikunabi, doda, enjapan."	
    exit 1
fi

./scripts/start_db_server.sh
./scripts/clean_data_dir.sh $job_name
./scripts/reset_db.sh reset $job_name

echo "Done preparing to crawl "$job_name




