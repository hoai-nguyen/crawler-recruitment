echo "Cleaning data directories: ..."
echo "Your input: $1" # param1

case $1 in
	topdev)
		echo "RESET topdev!"
		topdev='public/data/topdev'
		if [ -d $topdev ]; then
		  rm -rf $topdev/*.csv
		fi
		echo "DONE"
		;;
	topcv)
		echo "RESET topcv!"
		topdev='public/data/topcv'
		if [ -d $topcv ]; then
		  rm -rf $topcv/*.csv
		fi
		echo "DONE"
		;;
	itviec)
		echo "RESET itviec!"
		itviec='public/data/itviec'
		if [ -d $itviec ]; then
		  rm -rf $itviec/*.csv
		fi
		echo "DONE"
		;;
	vieclam24h)
		echo "RESET vieclam24h!"
		vieclam24h='public/data/vieclam24h'
		if [ -d $vieclam24h ]; then
		  rm -rf $vieclam24h/*.csv
		fi
		echo "DONE"
		;;
	timviecnhanh)
		echo "RESET timviecnhanh!"
		timviecnhanh='public/data/timviecnhanh'
		if [ -d $timviecnhanh ]; then
		  rm -rf $timviecnhanh/*.csv
		fi
		echo "DONE"
		;;
	mywork)
		echo "RESET mywork!"
		mywork='public/data/mywork'
		if [ -d $mywork ]; then
		  rm -rf $mywork/*.csv
		fi
		echo "DONE"
		;;
	findjobs)
		echo "RESET findjobs!"
		findjobs='public/data/findjobs'
		if [ -d $findjobs ]; then
		  rm -rf $findjobs/*.csv
		fi
		echo "DONE"
		;;
	careerlink)
		echo "RESET careerlink!"
		careerlink='public/data/careerlink'
		if [ -d $careerlink ]; then
		  rm -rf $careerlink/*.csv
		fi
		echo "DONE"
		;;
	careerbuilder)
		echo "RESET careerbuilder!"
		careerbuilder='public/data/careerbuilder'
		if [ -d $careerbuilder ]; then
		  rm -rf $careerbuilder/*.csv
		fi
		echo "DONE"
		;;
	laodong)
		echo "RESET laodong!"
		laodong='public/data/laodong'
		if [ -d $laodong ]; then
		  rm -rf $laodong/*.csv
		fi
		echo "DONE"
		;;
	timviec365)
		echo "RESET timviec365!"
		timviec365='public/data/timviec365'
		if [ -d $timviec365 ]; then
		  rm -rf $timviec365/*.csv
		fi
		echo "DONE"
		;;
	tuyencongnhan)
		echo "RESET tuyencongnhan!"
		timviec365='public/data/tuyencongnhan'
		if [ -d $tuyencongnhan ]; then
		  rm -rf $tuyencongnhan/*.csv
		fi
		echo "DONE"
		;;
	all)
		echo "RESET all!"
		alldata='public/data'
		if [ -d $alldata ]; then
		  rm -rf $alldata/*/*-data.csv
		  rm -rf $alldata/*/*-link.csv
		fi
		echo "DONE"
		;;
	*)
		echo "Sorry, there is no directory name: $1"
		echo ""
		echo "Usage: ./script/clean_data_dir.sh [option]"
		echo "Available options: topdev, topcv, itviec, vieclam24h, timviecnhanh, mywork, findjobs, careerlink, laodong, timviec365, tuyencongnhan"
		echo "Example: To clean crawled data for topdev crawler, we use: ./script/clean_data_dir.sh topdev"
		echo ""
		;;
esac

echo "DONE cleaning."



