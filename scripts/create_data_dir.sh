echo "Creating data directories: ..."
echo "Current directory: "`pwd`
dir='./public/data'
echo "Data directory: "`pwd`'/public/data'
echo "Creating data directories: ..."

if [ ! -d $dir ]; then
	mkdir -p $dir
fi

topdev=$dir'/topdev'
if [ ! -d $topdev ]; then
	mkdir -p $topdev
fi

topcv=$dir'/topcv'
if [ ! -d $topcv ]; then
	mkdir -p $topcv
fi

itviec=$dir'/itviec'
if [ ! -d $itviec ]; then
	mkdir -p $itviec
fi

vieclam24h=$dir'/vieclam24h'
if [ ! -d $vieclam24h ]; then
	mkdir -p $vieclam24h
fi

mywork=$dir'/mywork'
if [ ! -d $mywork ]; then
	mkdir -p $mywork
fi

timviecnhanh=$dir'/timviecnhanh'
if [ ! -d $timviecnhanh ]; then
	mkdir -p $timviecnhanh
fi

careerlink=$dir'/careerlink'
if [ ! -d $careerlink ]; then
	mkdir -p $careerlink
fi

findjobs=$dir'/findjobs'
if [ ! -d $findjobs ]; then
	mkdir -p $findjobs
fi

findjobs=$dir'/laodong'
if [ ! -d $findjobs ]; then
	mkdir -p $findjobs
fi

echo "DONE creating data directories!"
