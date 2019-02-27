<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;

class FindJobsCrawler extends Controller{

    public function FindJobsCrawler($start_page, $step){
		$start = microtime(true);
        $client = new Client;
        
        $x = (int) $start_page; 
        $end = (int) $start_page + (int) $step;
        while($x < $end) {
			$page_start = microtime(true);
			echo "i = ".$x.": ";
            $pageUrl = 'https://www.findjobs.vn/viec-lam-vi?page='.$x;
    		$crawler = $client -> request('GET', $pageUrl);

			$jobs = $crawler -> filter('#job_list > li.row');
			if ($jobs -> count() <= 0) {
                echo "NA";
				break;
            } 
			$jobs -> each(
		    	function ($node) {
					try {
						ini_set('max_execution_time', 10000000);	

						$link_node = $node -> filter('a') -> each(
							function ($node){
								if ($node -> attr('itemprop') != null 
										and strpos($node -> attr('itemprop'), 'title') !== false
										and $node -> attr('href') != null)
									return $node -> attr('href');
							}
						);
						$job_link = current(array_filter($link_node));

						if ($job_link == null 
								or strcmp($job_link, 'https://www.findjobs.vn') < 0
								or strcmp('https://www.findjobs.vn/viec-lam-vi', $job_link) == 0
								){
							$fp = fopen('findjobs-error.csv', 'a');
							fputcsv($fp, array($job_link));
							fclose($fp);
						} else{
							//todo separate
							$fp = fopen('findjobs-links.csv', 'a');
							fputcsv($fp, array($job_link));
							fclose($fp);
							
							$created = $node -> filter("span.activedate") -> text();
							$created = trim($created, "\r\n\t");
							
							FindJobsCrawler::TimJob($job_link, $created);
						}
					} catch (Exception $e) {
						// echo 'Caught exception: ',  $e -> getMessage(), "\n";
						$fp = fopen('findjobs-error.csv', 'a');
						fputcsv($fp, array("ERROR: ", $e -> getMessage()), $delimiter = "|");
						fclose($fp);
					}
				}
			);
			$x++;
			$page_total_time = microtime(true) - $page_start;
			echo '<b>Total execution time of page '.$x.":</b> ".$page_total_time.' secs<br>';
		} 
		$time_elapsed_secs = microtime(true) - $start;
		echo '<b>Total Execution Time:</b> '.$time_elapsed_secs.' secs<br>';
		echo "\nDONE!";
	}
	
    public function TimJob($url, $created){
		$url = "https://www.findjobs.vn/viec-lam-senior-talent-acquisition-j19485vi.html";
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('#job_detail');
		if ($content_crawler -> count() <= 0 ) {
			$fp = fopen('findjobs-error.csv', 'a');
			fputcsv($fp, array("ERROR: ", $url), $delimiter = "|");
			fclose($fp);
		} else{
			$content = $content_crawler -> first();
			// $header_start = microtime(true);
			$job_title = "n/a";
			$title_crawler = $crawler -> filter('h1.title');
			if ($title_crawler -> count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $company_start = microtime(true);
            $company = "n/a";
			$company_crawler = $content -> filter('h2.company');
			if ($company_crawler -> count() > 0 ) {
				$company = $company_crawler -> first() -> text();
			}
			// echo 'company: '.(microtime(true) - $company_start).' secs, ';

			// $address_start = microtime(true);
			// $address = "n/a";
			// echo 'address: '.(microtime(true) - $address_start).' secs, ';
            // $website_start = microtime(true);
			// $website = 'n/a';
            // echo 'website: '.(microtime(true) - $website_start).' secs, ';
			$company_details = $crawler -> filter('div.detail > dl.dl-horizontal > dd');
			$address = "n/a";
			$website = "n/a";
			if ($company_details -> count() > 0){
				foreach($company_details as $node){
					$comd_crawler = new Crawler($node);
					$itemprop = $comd_crawler -> attr('itemprop');
					$atag = $comd_crawler -> filter('a');
					if (strpos($itemprop, 'address') !== false){
						$address = $comd_crawler -> text();
					} 
					if ($atag -> count() > 0){
						$website = $atag -> attr('href');
					}
				}
			}
			$address = trim($address, "\r\n ");
			
            // $salary_start = microtime(true);
            $salary = 'n/a';
            $salary_crawler = $content -> filter('ol.job_attribs > li > span');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> last() -> text();
			}
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';

			// $job_des 
			$jds = $content -> filter('ul') -> first();
			$job_des = "n/a";
			if ($jds -> count() > 0){
				$job_des = $jds -> text();
			}
			$job_des = trim($job_des, "\r\n -");
			$job_des = preg_replace("/[\r\n]/", " ", $job_des);

			// $mobile = FindJobsCrawler::ExtractMobile($contact);
			$mobile = "";
			$email = "";
			$soluong = "";
			$deadline = "";
			$contact = "";

			// $file_start = microtime(true);
			$line = array($mobile
				, $email
				, $contact
				, $company
				, $address
				, $job_title
				, $salary
				, $job_des
                , $created
                , $deadline
				, $soluong
				, $website
				, $url);
			
			$fp = fopen('findjobs.csv', 'a');
			fputcsv($fp, $line, $delimiter = "|");
			fclose($fp);
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
}