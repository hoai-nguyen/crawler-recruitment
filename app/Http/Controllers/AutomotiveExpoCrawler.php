<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

use App\Http\Controllers\Common;

class AutomotiveExpoCrawler extends Controller{

	const DATE_FORMAT = "Ymd";
	const INPUT_DATE_FORMAT = "d-m-Y";
	const SLASH = DIRECTORY_SEPARATOR;
	const DATA_PATH = "exhibitors";
	const EXHIBITOR_DATA = "exhibitors";
	const EXHIBITOR_DATA_NO_CONTACT = "exhibitors-no-contact";


	public function CrawlerStarter(){
		ini_set('max_execution_time', 10000000);		
		$start = microtime(true);
		error_log("Start crawling AutomotiveExpo ...");

		
		try {
			$database = env("DB_DATABASE");
			if ($database == null)  $database = Common::DB_DEFAULT;

			$exhibitions = array(
				"https://jan2019.tems-system.com/exhiSearch/AUTO/eng/"
				, "http://jan2019.tems-system.com/exhiSearch/ROBO/eng/"
				, "http://jan2019.tems-system.com/exhiSearch/SFE/eng/"
			);
			// , "http://jan2019.tems-system.com/exhiSearch/INW/eng/"
			// , "http://jan2019.tems-system.com/exhiSearch/WEA/eng/"

			foreach( $exhibitions as $exhibition){
				$exhibitionUrl = $exhibition."ExhiList";
				error_log($exhibitionUrl);
				$client = new Client(); 
				$crawler = $client -> request('GET', $exhibitionUrl);
				$exhibitors = $crawler -> filter("#01") -> filter("li");
				
				foreach( $exhibitors as $el){
					error_log($idx);
					$start = microtime(true);

					$company_crl = new Crawler($el);
					$company = $company_crl -> text();
					$atag = $company_crl -> filter("a");
					$tel = "";
					$website = "";
					$email = "";
					
					if ($atag -> count() > 0){
						$id = $atag -> attr("val-id");
						$type = $atag -> attr("val-type");
						$link =  $exhibition."Details?id=$id&type=$type";
						error_log("Link = ".$link);
						$client = new Client(); 
						$page_crawler = $client -> request('GET', $link);
						$details = $page_crawler -> filter("div.search_detail_info") -> filter("td");
						foreach( $details as $node){
							$crl = new Crawler($node);
							$text = $crl -> text();
							if (strpos($text, 'TEL') !== false){
								$tel_arr = preg_split("/：/", $text);
								$tel = "";
								if (sizeof($tel_arr) > 1){
									$tel = Common::RemoveTrailingChars($tel_arr[1]);
								}
							} else if (strpos($text, 'URL') !== false){
								$website_crl = $crl -> filter("a");
								if ($website_crl -> count() > 0){
									$website = $website_crl -> attr("href");
									if (strpos($website, 'http') !== false){
										$page_crl = $client -> request('GET', $website);
										if ($page_crl -> count() > 0){
											$email = Common::ExtractEmailFromText($page_crl -> text());
										}
									}
								}
							}
						}
					}
					error_log("company: ".$company.", website: ".$website.", tel: ".$tel.", email: ".$email);

					$exhibitor_data = array(
						$company
						, $tel
						, $email
						, $website
					);

					$file_path = public_path('data').self::SLASH.self::DATA_PATH.self::SLASH;

					if (!Common::IsNullOrEmpty($website) or !Common::IsNullOrEmpty($tel)){
						Common::AppendArrayToFile($exhibitor_data, $file_path.self::EXHIBITOR_DATA.'.csv', "|");
					} else {
						Common::AppendArrayToFile($exhibitor_data, $file_path.self::EXHIBITOR_DATA_NO_CONTACT.'.csv', "|");
					}
				}
			}
		 } catch (\Exception $e) {
		 	error_log($e -> getMessage());
		 }
		
		$time_elapsed_secs = microtime(true) - $start;
		error_log('Total Execution Time: '.$time_elapsed_secs.' secs');
		error_log("DONE!");

		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}
	
	
}