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

Route::get('get',['uses' => 'TimViecNhanhCrawler@DemoCrawlerFunc'])->name('get');
Route::get('getpage',['uses' => 'TimViecNhanhCrawler@ManyNodeCrawlerFuncOpt'])->name('getpage');
Route::get('github',['uses' => 'TimViecNhanhCrawler@GithubLogin'])->name('github');
Route::get('timviec2',['uses' => 'TimViecNhanhCrawler@TimJob2'])->name('timviec2');
Route::get('test',['uses' => 'TimViecNhanhCrawler@Test'])->name('test');
Route::get('testparam/{start}/{step}',['uses' => 'TimViecNhanhCrawler@TestParameter'])->name('testparam');


Route::get('timviecnhanh/{start_page}/{step}',['uses' => 'TimViecNhanhCrawler@TimViecNhanh'])->name('timviecnhanh');
Route::get('mywork/{start_page}/{step}',['uses' => 'MyWorkCrawler@MyWorkCrawler'])->name('mywork');
