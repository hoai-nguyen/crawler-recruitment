echo "Merging crawled data into one file." 
DATE=`date '+%Y-%m-%d_%H:%M:%S'`
filename="recruitment_data_"$DATE".csv"

cat ./public/data/*/*data.csv >> ./public/data/$filename
echo "Merged all crawled data to `pwd`/public/data/$filename!"
