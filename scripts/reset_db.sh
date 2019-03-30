echo "Cleaning database: ..."
echo "Your input: job_type=$1, job_name=$2"

job_type=$1
job_name=$2

f_continue () {
	job=$1
	echo "RESET $job!"
	mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='$job';"
	echo "DONE"
}

f_reset () {
	job=$1
	echo "RESET $job!"
	mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='$job';TRUNCATE TABLE phpmyadmin.$job;"
	echo "DONE"
}

f_reset_crawler () {
	job=$1
	echo "RESET $job!"
	mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='$job';TRUNCATE TABLE phpmyadmin.crawler_$job;"
	echo "DONE"
}

f_usage(){
	job_name=$1
	echo "Sorry, there is no job name: $job_name"
	echo ""
	echo "Usage: ./reset_db.sh option1 option2"
	echo "Available options 1: continue, reset"
	echo "Available options 2: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, \
	careerlink, laodong, timviec365, tuyencongnhan, tuyendungcomvn, uv_tuyendungcomvn, itguru, tenshoku, tenshokuex, \
	hatalike, rikunabi, doda, enjapan, tuyendungsinhvien, uv_tuyendungsinhvien."
	echo "Example: To continue crawling from last run for topdev, we use: ./reset_db.sh continue topdev"
	echo ""
}

if [ $job_type = 'continue' ]; then
    case $job_name in
	topdev)
		f_continue topdev
		;;
	topcv)
		f_continue topcv
		;;
	itviec)
		f_continue itviec
		;;
	vieclam24h)
		f_continue vieclam24h
		;;
	timviecnhanh)
		f_continue timviecnhanh
		;;
	mywork)
		f_continue mywork
		;;
	uv_mywork)
		f_continue uv_mywork
		;;
	findjobs)
		f_continue findjobs
		;;
	careerlink)
		f_continue careerlink
		;;
	careerbuilder)
		f_continue careerbuilder
		;;
	laodong)
		f_continue laodong
		;;
	uv_laodong)
		f_continue uv_laodong
		;;
	timviec365)
		f_continue timviec365
		;;	
	tuyencongnhan)
		f_continue tuyencongnhan
		;;
	tuyendungsinhvien)
		f_continue tuyendungsinhvien
		;;
	tuyendungcomvn)
		f_continue tuyendungcomvn
		;;
	uv_tuyendungcomvn)
		f_continue uv_tuyendungcomvn
		;;
	itguru)
		f_continue itguru
		;;
	tenshoku)
		f_continue tenshoku
		;;
	tenshokuex)
		f_continue tenshokuex
		;;
	hatalike)
		f_continue hatalike
		;;
	rikunabi)
		f_continue rikunabi
		;;
	doda)
		f_continue doda
		;;
	enjapan)
		f_continue enjapan
		;;
	all)
		echo "RESET all!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; TRUNCATE TABLE phpmyadmin.job_metadata;"
		echo "DONE"
		;;
	*)
		f_usage $job_name
		;;
	esac
elif [ $job_type = 'reset' ]; then
    case $job_name in
	topdev)
		f_reset topdev
		;;
	topcv)
		f_reset topcv
		;;
	itviec)
		f_reset itviec
		;;
	vieclam24h)
		f_reset vieclam24h
		;;
	timviecnhanh)
		f_reset timviecnhanh
		;;
	mywork)
		f_reset mywork
		;;
	mywork)
		f_reset_crawler mywork
		;;
	findjobs)
		f_reset findjobs
		;;
	careerlink)
		f_reset careerlink
		;;
	careerbuilder)
		f_reset careerbuilder
		;;
	laodong)
		f_reset_crawler laodong
		;;
	uv_laodong)
		f_reset_crawler uv_laodong
		;;
	timviec365)
		f_reset_crawler timviec365
		;;
	tuyencongnhan)
		f_reset_crawler tuyencongnhan
		;;
	tuyendungsinhvien)
		f_reset_crawler tuyendungsinhvien
		;;
	uv_tuyendungsinhvien)
		f_reset_crawler uv_tuyendungsinhvien
		;;
	tuyendungcomvn)
		f_reset_crawler tuyendungcomvn
		;;
	uv_tuyendungcomvn)
		f_reset_crawler uv_tuyendungcomvn
		;;
	itguru)
		f_reset_crawler itguru
		;;
	tenshoku)
		f_reset_crawler tenshoku
		;;
	tenshokuex)
		f_reset_crawler tenshokuex
		;;
	hatalike)
		f_reset_crawler hatalike
		;;
	rikunabi)
		f_reset_crawler rikunabi
		;;
	doda)
		f_reset_crawler doda
		;;
	enjapan)
		f_reset_crawler enjapan
		;;
	all)
		echo "RESET all!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin;\
			TRUNCATE TABLE phpmyadmin.job_metadata;TRUNCATE TABLE phpmyadmin.careerbuilder;\
			TRUNCATE TABLE phpmyadmin.careerlink;TRUNCATE TABLE phpmyadmin.findjobs;\
			TRUNCATE TABLE phpmyadmin.mywork;TRUNCATE TABLE phpmyadmin.timviecnhanh;\
			TRUNCATE TABLE phpmyadmin.vieclam24h;TRUNCATE TABLE phpmyadmin.itviec;\
			TRUNCATE TABLE phpmyadmin.topcv;TRUNCATE TABLE phpmyadmin.topdev;\
			TRUNCATE TABLE phpmyadmin.crawler_laodong;TRUNCATE TABLE phpmyadmin.crawler_uv_laodong;\
			TRUNCATE TABLE phpmyadmin.crawler_timviec365;\
			TRUNCATE TABLE phpmyadmin.crawler_tuyendungsinhvien; TRUNCATE TABLE phpmyadmin.crawler_uv_tuyendungsinhvien;\
			TRUNCATE TABLE phpmyadmin.crawler_tuyendungcomvn;TRUNCATE TABLE phpmyadmin.crawler_uv_tuyendungcomvn;\
			TRUNCATE TABLE phpmyadmin.crawler_tuyencongnhan;TRUNCATE TABLE phpmyadmin.crawler_itguru;\
			TRUNCATE TABLE phpmyadmin.crawler_tenshoku;TRUNCATE TABLE phpmyadmin.crawler_tenshokuex;\
			TRUNCATE TABLE phpmyadmin.crawler_hatalike;TRUNCATE TABLE phpmyadmin.crawler_rikunabi;\
			TRUNCATE TABLE phpmyadmin.crawler_doda;TRUNCATE TABLE phpmyadmin.crawler_enjapan;"
		echo "DONE"
		;;
	*)
		f_usage $job_name
		;;
	esac
else
	echo "Sorry, there is no job type: $job_type"
	echo ""
	echo "Usage: ./reset_db.sh option1 option2"
	echo "Available options 1: continue, reset"
	echo "Available options 2: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, \
	careerlink, laodong, uv_laodong, timviec365, tuyencongnhan, tuyendungsinhvien, uv_tuyendungsinhvien, tuyendungcomvn, uv_tuyendungcomvn, itguru, \
	tenshoku, tenshokuex, hatalike, rikunabi, doda, enjapan."
	echo "Example: To start crawling from beginning for topdev, we use: ./reset_db.sh reset topdev"
	echo "Example: To continue crawling from last run for topdev, we use: ./reset_db.sh continue topdev"
	echo ""
fi

echo "DONE cleaning database."


