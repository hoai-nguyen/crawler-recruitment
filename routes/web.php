<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('timviecnhanh',['uses' => 'TimViecNhanhCrawler@CrawlerStarter'])->name('timviecnhanh');
Route::get('mywork',['uses' => 'MyWorkCrawler@CrawlerStarter'])->name('mywork');
Route::get('uv_mywork',['uses' => 'UVMyWorkCrawler@CrawlerStarter'])->name('uv_mywork');
Route::get('findjobs',['uses' => 'FindJobsCrawler@CrawlerStarter'])->name('findjobs');
Route::get('vieclam24h',['uses' => 'ViecLam24HCrawler@CrawlerStarter'])->name('vieclam24h');
Route::get('careerlink',['uses' => 'CareerLinkCrawler@CrawlerStarter'])->name('careerlink');
Route::get('topcv',['uses' => 'TopCVCrawler@CrawlerStarter'])->name('topcv');
Route::get('itviec',['uses' => 'ITViecCrawler@CrawlerStarter'])->name('itviec');
Route::get('topdev',['uses' => 'TopDevCrawler@CrawlerStarter'])->name('topdev');
Route::get('laodong',['uses' => 'LaoDongCrawler@CrawlerStarter'])->name('laodong');
Route::get('uv_laodong',['uses' => 'UVLaoDongCrawler@CrawlerStarter'])->name('uv_laodong');
Route::get('timviec365',['uses' => 'TimViec365Crawler@CrawlerStarter'])->name('timviec365');
Route::get('tuyencongnhan',['uses' => 'TuyenCongNhanCrawler@CrawlerStarter'])->name('tuyencongnhan');
Route::get('tuyendungsinhvien',['uses' => 'TuyenDungSinhVienCrawler@CrawlerStarter'])->name('tuyendungsinhvien');
Route::get('uv_tuyendungsinhvien',['uses' => 'UVTuyenDungSinhVienCrawler@CrawlerStarter'])->name('uv_tuyendungsinhvien');
Route::get('tuyendungcomvn',['uses' => 'TuyenDungComVnCrawler@CrawlerStarter'])->name('tuyendungcomvn');
Route::get('uv_tuyendungcomvn',['uses' => 'UVTuyenDungComVnCrawler@CrawlerStarter'])->name('uv_tuyendungcomvn');
Route::get('uv_kenhtimviec',['uses' => 'UVKenhTimViecCrawler@CrawlerStarter'])->name('uv_kenhtimviec');
Route::get('itguru',['uses' => 'ITGuruCrawler@CrawlerStarter'])->name('itguru');
Route::get('tenshoku',['uses' => 'TenshokuCrawler@CrawlerStarter'])->name('tenshoku');
Route::get('tenshokuex',['uses' => 'TenshokuExCrawler@CrawlerStarter'])->name('tenshokuex');
Route::get('hatalike',['uses' => 'HatalikeCrawler@CrawlerStarter'])->name('hatalike');
Route::get('rikunabi',['uses' => 'RikunabiCrawler@CrawlerStarter'])->name('rikunabi');
Route::get('doda',['uses' => 'DodaCrawler@CrawlerStarter'])->name('doda');
Route::get('enjapan',['uses' => 'EnJapanCrawler@CrawlerStarter'])->name('enjapan');
Route::get('careerbuilder',['uses' => 'CareerBuilderCrawler@CrawlerStarter'])->name('careerbuilder');
Route::get('automotive',['uses' => 'AutomotiveExpoCrawler@CrawlerStarter'])->name('automotive');

