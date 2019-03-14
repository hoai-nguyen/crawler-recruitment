echo "Cleaning database: ..."
echo "Your input: job_type=$1, job_name=$2"

job_type=$1
job_name=$2


if [ $job_type = 'continue' ]; then
    case $job_name in
	topdev)
		echo "RESET topdev!"
		#mysql -u root -p  phpmyadmin < scripts/reset_topdev.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='topdev';"
		echo "DONE"
		;;
	topcv)
		echo "RESET topcv!"
		#mysql -u root -p  phpmyadmin < scripts/reset_topcv.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='topcv';"
		echo "DONE"
		;;
	itviec)
		echo "RESET itviec!"
		#mysql -u root -p  phpmyadmin < scripts/reset_itviec.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='itviec';"
		echo "DONE"
		;;
	vieclam24h)
		echo "RESET vieclam24h!"
		#mysql -u root -p  phpmyadmin < scripts/reset_vieclam24h.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='vieclam24h';"
		echo "DONE"
		;;
	timviecnhanh)
		echo "RESET timviecnhanh!"
		#mysql -u root -p  phpmyadmin < scripts/reset_timviecnhanh.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='timviecnhanh';"
		echo "DONE"
		;;
	mywork)
		echo "RESET mywork!"
		#mysql -u root -p  phpmyadmin < scripts/reset_mywork.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='mywork';"
		echo "DONE"
		;;
	findjobs)
		echo "RESET findjobs!"
		#mysql -u root -p  phpmyadmin < scripts/reset_findjobs.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='findjobs';"
		echo "DONE"
		;;
	careerlink)
		echo "RESET careerlink!"
		#mysql -u root -p  phpmyadmin < scripts/reset_careerlink.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='careerlink';"
		echo "DONE"
		;;
	careerbuilder)
		echo "RESET careerbuilder!"
		#mysql -u root -p  phpmyadmin < scripts/reset_careerbuilder.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='careerbuilder';"
		echo "DONE"
		;;
	laodong)
		echo "RESET laodong!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='laodong';"
		echo "DONE"
		;;
	timviec365)
		echo "RESET timviec365!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='timviec365';"
		echo "DONE"
		;;	
	tuyencongnhan)
		echo "RESET tuyencongnhan!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='tuyencongnhan';"
		echo "DONE"
		;;
	all)
		echo "RESET all!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; TRUNCATE TABLE phpmyadmin.job_metadata;"
		echo "DONE"
		;;
	*)
		echo "Sorry, there is no job name: $job_name"
		echo ""
		echo "Usage: ./reset_db.sh option1 option2"
		echo "Available options 1: continue, reset"
		echo "Available options 2: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, careerlink, laodong, timviec365, tuyencongnhan"
		echo "Example: To continue crawling from last run for topdev, we use: ./reset_db.sh continue topdev"
		echo ""
		;;
	esac
elif [ $job_type = 'reset' ]; then
    case $job_name in
	topdev)
		echo "RESET topdev!"
		#mysql -u root -p  phpmyadmin < scripts/reset_topdev.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='topdev';TRUNCATE TABLE phpmyadmin.topdev;"
		echo "DONE"
		;;
	topcv)
		echo "RESET topcv!"
		#mysql -u root -p  phpmyadmin < scripts/reset_topcv.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='topcv';TRUNCATE TABLE phpmyadmin.topcv;"
		echo "DONE"
		;;
	itviec)
		echo "RESET itviec!"
		#mysql -u root -p  phpmyadmin < scripts/reset_itviec.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='itviec';TRUNCATE TABLE phpmyadmin.itviec;"
		echo "DONE"
		;;
	vieclam24h)
		echo "RESET vieclam24h!"
		#mysql -u root -p  phpmyadmin < scripts/reset_vieclam24h.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='vieclam24h';TRUNCATE TABLE phpmyadmin.vieclam24h;"
		echo "DONE"
		;;
	timviecnhanh)
		echo "RESET timviecnhanh!"
		#mysql -u root -p  phpmyadmin < scripts/reset_timviecnhanh.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='timviecnhanh';TRUNCATE TABLE phpmyadmin.timviecnhanh;"
		echo "DONE"
		;;
	mywork)
		echo "RESET mywork!"
		#mysql -u root -p  phpmyadmin < scripts/reset_mywork.sql
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='mywork';TRUNCATE TABLE phpmyadmin.mywork;"
		echo "DONE"
		;;
	findjobs)
		echo "RESET findjobs!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='findjobs';TRUNCATE TABLE phpmyadmin.findjobs;"
		echo "DONE"
		;;
	careerlink)
		echo "RESET careerlink!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='careerlink';TRUNCATE TABLE phpmyadmin.careerlink;"
		echo "DONE"
		;;
	careerbuilder)
		echo "RESET careerbuilder!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='careerbuilder';TRUNCATE TABLE phpmyadmin.careerbuilder;"
		echo "DONE"
		;;
	laodong)
		echo "RESET laodong!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='laodong';TRUNCATE TABLE phpmyadmin.crawler_laodong;"
		echo "DONE"
		;;
	timviec365)
		echo "RESET timviec365!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='timviec365';TRUNCATE TABLE phpmyadmin.crawler_timviec365;"
		echo "DONE"
		;;
	tuyencongnhan)
		echo "RESET tuyencongnhan!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; DELETE FROM phpmyadmin.job_metadata WHERE job_name='tuyencongnhan';TRUNCATE TABLE phpmyadmin.crawler_tuyencongnhan;"
		echo "DONE"
		;;
	all)
		echo "RESET all!"
		mysql --user="root" --password="" --database="phpmyadmin" --execute="use phpmyadmin; TRUNCATE TABLE phpmyadmin.job_metadata;TRUNCATE TABLE phpmyadmin.careerbuilder;TRUNCATE TABLE phpmyadmin.careerlink;TRUNCATE TABLE phpmyadmin.findjobs;TRUNCATE TABLE phpmyadmin.mywork;TRUNCATE TABLE phpmyadmin.timviecnhanh;TRUNCATE TABLE phpmyadmin.vieclam24h;TRUNCATE TABLE phpmyadmin.itviec;TRUNCATE TABLE phpmyadmin.topcv;TRUNCATE TABLE phpmyadmin.topdev;TRUNCATE TABLE phpmyadmin.crawler_laodong;TRUNCATE TABLE phpmyadmin.crawler_timviec365;"
		echo "DONE"
		;;
	*)
		echo "Sorry, there is no job name: $job_name"
		echo ""
		echo "Usage: ./reset_db.sh option1 option2"
		echo "Available options 1: continue, reset"
		echo "Available options 2: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, careerlink, laodong, timviec365, tuyencongnhan"
		echo "Example: To start crawling from beginning for topdev, we use: ./reset_db.sh reset topdev"
		echo ""
		;;
	esac
else
	echo "Sorry, there is no job type: $job_type"
	echo ""
	echo "Usage: ./reset_db.sh option1 option2"
	echo "Available options 1: continue, reset"
	echo "Available options 2: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, careerlink, laodong, timviec365, tuyencongnhan"
	echo "Example: To start crawling from beginning for topdev, we use: ./reset_db.sh reset topdev"
	echo "Example: To continue crawling from last run for topdev, we use: ./reset_db.sh continue topdev"
	echo ""
fi

echo "DONE cleaning database."


