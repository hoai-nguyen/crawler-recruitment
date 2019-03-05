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
Route::get('findjobs',['uses' => 'MFindJobsCrawler@CrawlerStarter'])->name('findjobs');
Route::get('vieclam24h',['uses' => 'ViecLam24HCrawler@CrawlerStarter'])->name('vieclam24h');
Route::get('careerlink',['uses' => 'CareerLinkCrawler@CrawlerStarter'])->name('careerlink');
Route::get('careerbuilder',['uses' => 'CareerBuilderCrawler@CrawlerStarter'])->name('careerbuilder');
Route::get('testdb',['uses' => 'DBController@index'])->name('testdb');
