<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;
use Illuminate\Support\Facades\DB;

class MyWorkCrawler extends Controller{

	const TABLE = "mywork";
	const MYWORK_DATA_PATH = 'mywork_test'; // CI must create directory in
	const MYWORK_DATA = 'mywork-data-';
	const MYWORK_ERROR = 'mywork-error-';
	const MYWORK_LINK = 'mywork-link-';
	const MYWORK_HOME = 'https://mywork.com.vn';
	const LABEL_CONTACT = 'Người liên hệ';
	const LABEL_DEADjob_data = 'Hạn nộp hồ sơ';
	const LABEL_QUANTITY = 'Số lượng cần tuyển';
	const LABEL_APPROVER = 'Ngày duyệt';
	const DATE_FORMAT = "Ymd";
	const SLASH = DIRECTORY_SEPARATOR;


	public function CheckLinksExist($jobs_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$select_param = "('".implode("','", $jobs_links)."')";
		$select_dialect = "select link from ".$database.".".$table." where link in ";
		$select_query = $select_dialect.$select_param;
		$existing_links = DB::select($select_query);

		return $existing_links;
	}

	public function InsertLinks($new_links, $database="phpmyadmin", $table){
		if (env("DATABASE") == null) $database="phpmyadmin";

		$insert_links = array();
		foreach($new_links as $el){
			array_push($insert_links, "('".$el."')");
		}
		$insert_param = implode(",", $insert_links);
		$insert_dialect = "insert into ".$database.".".$table."(link) values ";
		$insert_query = $insert_dialect.$insert_param;
		$insert_results = DB::insert($insert_query);
		
		return $insert_results;
	}

    public function MyWorkCrawler($start_page, $step){
		$DATA_PATH = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::SLASH;

		$start = microtime(true);
        $client = new Client;
        
        $x = (int) $start_page; 
        $end = (int) $start_page + (int) $step;
        while($x < $end) {
			$page_start = microtime(true);
			echo "page = ".$x.": ";
			
			$pageUrl = self::MYWORK_HOME.'/tuyen-dung/trang/'.$x;
    		$crawler = $client -> request('GET', $pageUrl);
			$jobs = $crawler -> filter('div.item-list') -> first() -> filter('a.item');
			if ($jobs -> count() <= 0) {
				MyWorkCrawler::AppendStringToFile("No job found on ".$pageUrl
				, $DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
				break;
			} 

			// get job links
			$jobs_links = $jobs -> each(
		    	function ($node) {
					try {
                        $job_link = $node -> attr('href');
                        if ($job_link != null){
                            return $job_link;
						}
					} catch (\Exception $e) {
						$file_name = public_path('data').self::SLASH.self::MYWORK_DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv';
						MyWorkCrawler::AppendStringToFile(substr($e -> getTraceAsString (), 0, 1000), $file_name);
					}
				}
			);
			
			// select duplicated records
			$existing_links = MyWorkCrawler::CheckLinksExist($jobs_links, env("DATABASE"), $table="mywork");
			$duplicated_links = array();
			foreach($existing_links as $row){
				$link = $row -> link;
				array_push($duplicated_links, $link);
			}

			// deduplicate
			$new_links = array_diff($jobs_links, $duplicated_links);

			if (is_array($new_links) and sizeof($new_links) > 0){
				// crawl each link
				foreach ($new_links as $job_link) {
					try {
						ini_set('max_execution_time', 10000000);				
						
						if ($job_link == null){
						} else{
							$full_link = self::MYWORK_HOME.$job_link;
							MyWorkCrawler::CrawlJob($full_link, $DATA_PATH);
							
							MyWorkCrawler::AppendStringToFile($full_link
							, $DATA_PATH.self::MYWORK_LINK.date(self::DATE_FORMAT).'.csv');

							// this slow-down performance.
							$inserted = MyWorkCrawler::InsertLinks(array($job_link), env("DATABASE"), $table="mywork");
						}
					} catch (\Exception $e) {
						MyWorkCrawler::AppendStringToFile(substr($e -> getTraceAsString (), 0, 1000)
						, $DATA_PATH.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
					}
				}
			}
			$x++;

			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 
		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "DONE!";
	}
	
    public function CrawlJob($url, $data_path){

		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('div.detail_job');
		if ($content_crawler -> count() <= 0 ) {
			MyWorkCrawler::AppendStringToFile("ERROR: Failed to crawl ".$url
			, $data_path.self::MYWORK_ERROR.date(self::DATE_FORMAT).'.csv');
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "n/a";
			$title_crawler = $crawler -> filter('h1.main-title > span');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
            }
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "n/a";
			$created_infos = $content -> filter('div.content > p');
			if ($created_infos -> count() > 0 ) {
                foreach ($created_infos as $node) {
                    $created_crawler = new Crawler($node);
					$label_crawler = $created_crawler -> filter('strong');
					if ($label_crawler -> count() > 0){
						$label = $label_crawler -> first() -> text();
						if (strpos($label, self::LABEL_APPROVER) !== false){
							$created = $created_crawler -> first() -> text();
							break;
						}
					}
                }
			}
			if (strpos($created, ':') !== false){
				$created = trim(explode(":", $created)[1], "\r\n ");
			}
			// echo 'posted time: '.(microtime(true) - $posted_start).' secs, ';
			
			// $company_start = microtime(true);
            $company = "n/a";
			$company_crawler = $content -> filter('p.company-name > a > strong');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
            }
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $address_start = microtime(true);
			$address = "n/a";
			$address_crawler = $content -> filter('p.address > span');
			if ($address_crawler -> count() > 0 ) {
				$address = $address_crawler -> first() -> text();
            }
			// echo 'address: '.(microtime(true) - $address_start).' secs, ';

            // $salary_start = microtime(true);
            $salary = 'n/a';
            $salary_crawler = $content -> filter('span.text_red');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> first() -> text();
            }
            $salary = trim(preg_replace("/[\r\n]*/", "", $salary), " ");
            $salary = str_replace("  ", "", $salary);
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';
            
            // $website_start = microtime(true);
			$website = 'n/a';
			$website_crawler = $content -> filter('p.company-name > a');
			if ($website_crawler -> count() > 0 ) {
                $ref = $website_crawler -> first() -> attr('href');
                $website = self::MYWORK_HOME.$ref;
            }
            // echo 'website: '.(microtime(true) - $website_start).' secs, ';
            
            // $soluong_start = microtime(true);
            $soluong = "n/a";
			$general_infos = $content -> filter('div.job_detail_general > div.item1 > p');
			if ($general_infos -> count() > 0 ) {
                foreach ($general_infos as $node) {
                    $soluong_crawler = new Crawler($node);
                    $label = $soluong_crawler -> filter('strong') -> first() -> text();
                    if (strpos($label, self::LABEL_QUANTITY) !== false){
                        $soluong = $soluong_crawler -> first() -> text();
                        break;
                    }
                }
			}
			if (strpos($soluong, ':') !== false){
				$soluong = trim(explode(":", $soluong)[1], "\r\n ");
			}
			// echo 'soluong: '.(microtime(true) - $soluong_start).' secs, ';

            // $deadjob_data_start = microtime(true);
            $contact = "n/a";
            $deadjob_data = 'n/a';
            $contact_infos = $content -> filter('div.box-contact > div.row');
			if ($contact_infos -> count() > 0 ) {
                foreach ($contact_infos as $node) {
                    $contact_crawler = new Crawler($node);
                    
                    $label = $contact_crawler -> filter('div.label-contact');
                    if ($label -> count() > 0){
                        $label = $label -> first() -> text();
                        if (strpos($label, self::LABEL_CONTACT) !== false){
                            $contact = $contact_crawler -> filter('div') -> last() -> text();
                        } else if (strpos($label, self::LABEL_DEADjob_data) !== false){
                            $deadjob_data = $contact_crawler -> filter('div') -> last() -> text();
                            $deadjob_data = preg_replace("/[\r\n ]*/", "", $deadjob_data);
                        }
                    }
                }
            }
			// echo 'deadjob_data: '.(microtime(true) - $deadjob_data_start).' secs, ';

			// $job_des 
			$jds = $content -> filter('div.multiple > div.mw-box-item');
			$job_des = "n/a";
			$idx = 0;
			if ($jds -> count() > 0){
				foreach ($jds as $node) {
					$jd = new Crawler($node);
					if ($idx == 2){
						$job_des = $jd -> text();
						break;
					}
					$idx++;
				}
			}
			$job_des = trim($job_des, "\r\n -");

			$mobile = MyWorkCrawler::ExtractMobile($contact);
			$email = "";

			// $file_start = microtime(true);
			$job_data = array($mobile
				, $email
				, $contact
				, $company
				, $address
				, $job_title
				, $salary
				, $job_des
                , $created
                , $deadjob_data
				, $soluong
				, $website
				, $url);
			
			MyWorkCrawler::AppendArrayToFile($job_data
			, $data_path.self::MYWORK_DATA.date(self::DATE_FORMAT).'.csv', "|");

			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';
			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';
		}
	}

	public function ExtractMobile($contact){
		preg_match_all('!\d+!', $contact, $matches);

		$mobiles_str = "";
		$len = count($matches[0]);
		if ($len > 0){
			$nums = $matches[0];
			$mobiles = array();
			$mobile_tmp = "";
			for ($x = 0; $x < $len; $x++) {
				$num = $nums[$x];
				if (strlen($mobile_tmp.$num) <= 12){
					$mobile_tmp = $mobile_tmp.$num;
				} else {
					array_push($mobiles, $mobile_tmp);
					$mobile_tmp = $num;
				}
				if ($x == $len - 1){
					array_push($mobiles, $mobile_tmp);
				}
			} 
			$mobiles_str = implode(",", $mobiles);
		} 
		return $mobiles_str;
	}

	public function AppendArrayToFile($arr, $file_name, $limiter="|"){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, $arr, $delimiter = $limiter);
		fclose($fp);
	}

	public function AppendStringToFile($str, $file_name){
		$fp = fopen($file_name, 'a');
		fputcsv($fp, array($str));
		fclose($fp);
	}
}