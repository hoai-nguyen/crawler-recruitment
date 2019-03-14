
echo "Start initializing data."

header="Phone|Email|Company Name|Address|Job Name|Salary|Job Description|Posted Date|Application Deadline|Quantity|Website"

declare -a pages=("topdev" "topcv" "itviec" "mywork" "timviecnhanh" "vieclam24h" "findjobs" "careerlink" "laodong" "timviec365")

for page in "${pages[@]}"
do
	file="public/data/"$page/$page"_data.csv"
	dir="public/data/"$page

	if [ ! -d $dir ]; then
		mkdir $dir
		echo $dir": Created!"
	fi

	if [ -d $dir ] && [ ! -f $file ]; then
		echo "$header" >> $file
		echo $file": OK"
	fi
done

echo "End initializing data."

