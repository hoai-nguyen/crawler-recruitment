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

timviec365=$dir'/timviec365'
if [ ! -d $timviec365 ]; then
	mkdir -p $timviec365
fi

tuyencongnhan=$dir'/tuyencongnhan'
if [ ! -d $tuyencongnhan ]; then
	mkdir -p $tuyencongnhan
fi

tuyendungsinhvien=$dir'/tuyendungsinhvien'
if [ ! -d $tuyendungsinhvien ]; then
	mkdir -p $tuyendungsinhvien
fi

tuyendungcomvn=$dir'/tuyendungcomvn'
if [ ! -d $tuyendungcomvn ]; then
	mkdir -p $tuyendungcomvn
fi

itguru=$dir'/itguru'
if [ ! -d $itguru ]; then
	mkdir -p $itguru
fi

tenshoku=$dir'/tenshoku'
if [ ! -d $tenshoku ]; then
	mkdir -p $tenshoku
fi

tenshokuex=$dir'/tenshokuex'
if [ ! -d $tenshokuex ]; then
	mkdir -p $tenshokuex
fi

hatalike=$dir'/hatalike'
if [ ! -d $hatalike ]; then
	mkdir -p $hatalike
fi

echo "DONE creating data directories!"
