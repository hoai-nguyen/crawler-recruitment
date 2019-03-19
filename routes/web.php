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

Route::get('github',['uses' => 'TimViecNhanhCrawler@GithubLogin'])->name('github');

Route::get('timviecnhanh',['uses' => 'TimViecNhanhCrawler@CrawlerStarter'])->name('timviecnhanh');
Route::get('mywork/{start_page}/{end_page}',['uses' => 'MyWorkCrawler@MyWorkCrawler'])->name('mywork');
Route::get('mywork',['uses' => 'MyWorkCrawler@CrawlerStarter'])->name('mywork1');
Route::get('findjobs',['uses' => 'FindJobsCrawler@CrawlerStarter'])->name('findjobs');
Route::get('vieclam24h',['uses' => 'ViecLam24HCrawler@CrawlerStarter'])->name('vieclam24h');
Route::get('careerlink',['uses' => 'CareerLinkCrawler@CrawlerStarter'])->name('careerlink');
Route::get('topcv',['uses' => 'TopCVCrawler@CrawlerStarter'])->name('topcv');
Route::get('itviec',['uses' => 'ITViecCrawler@CrawlerStarter'])->name('itviec');
Route::get('topdev',['uses' => 'TopDevCrawler@CrawlerStarter'])->name('topdev');
Route::get('laodong',['uses' => 'LaoDongCrawler@CrawlerStarter'])->name('laodong');
Route::get('timviec365',['uses' => 'TimViec365Crawler@CrawlerStarter'])->name('timviec365');
Route::get('tuyencongnhan',['uses' => 'TuyenCongNhanCrawler@CrawlerStarter'])->name('tuyencongnhan');
Route::get('tuyendungsinhvien',['uses' => 'TuyenDungSinhVienCrawler@CrawlerStarter'])->name('tuyendungsinhvien');
Route::get('tuyendungcomvn',['uses' => 'TuyenDungComVnCrawler@CrawlerStarter'])->name('tuyendungcomvn');
Route::get('itguru',['uses' => 'ITGuruCrawler@CrawlerStarter'])->name('itguru');
Route::get('automotive',['uses' => 'AutomotiveExpoCrawler@CrawlerStarter'])->name('automotive');
Route::get('careerbuilder',['uses' => 'CareerBuilderCrawler@CrawlerStarter'])->name('careerbuilder');