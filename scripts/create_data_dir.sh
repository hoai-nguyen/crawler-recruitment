echo "Creating data directories: ..."
echo "Current directory: "`pwd`
dir='./public/data'
candidate_dir='./public/data/candidates'
echo "Data directory: "`pwd`'/public/data'
echo "Creating data directories: ..."

if [ ! -d $dir ]; then
	mkdir -p $dir
fi

if [ ! -d $candidate_dir ]; then
	mkdir -p $candidate_dir
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

uv_mywork=$candidate_dir'/mywork'
if [ ! -d $uv_mywork ]; then
	mkdir -p $uv_mywork
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

laodong=$dir'/laodong'
if [ ! -d $laodong ]; then
	mkdir -p $laodong
fi

uv_laodong=$candidate_dir'/laodong'
if [ ! -d $uv_laodong ]; then
	mkdir -p $uv_laodong
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

uv_tuyendungsinhvien=$candidate_dir'/tuyendungsinhvien'
if [ ! -d $uv_tuyendungsinhvien ]; then
	mkdir -p $uv_tuyendungsinhvien
fi

tuyendungcomvn=$dir'/tuyendungcomvn'
if [ ! -d $tuyendungcomvn ]; then
	mkdir -p $tuyendungcomvn
fi

uv_tuyendungcomvn=$candidate_dir'/tuyendungcomvn'
if [ ! -d $uv_tuyendungcomvn ]; then
	mkdir -p $uv_tuyendungcomvn
fi

uv_kenhtimviec=$candidate_dir'/uv_kenhtimviec'
if [ ! -d $uv_kenhtimviec ]; then
	mkdir -p $uv_kenhtimviec
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

rikunabi=$dir'/rikunabi'
if [ ! -d $rikunabi ]; then
	mkdir -p $rikunabi
fi

doda=$dir'/doda'
if [ ! -d $doda ]; then
	mkdir -p $doda
fi

enjapan=$dir'/enjapan'
if [ ! -d $enjapan ]; then
	mkdir -p $enjapan
fi

echo "DONE creating data directories!"
