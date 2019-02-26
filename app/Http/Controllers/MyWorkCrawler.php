<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use \Exception as Exception;


class MyWorkCrawler extends Controller{

    public function MyWorkCrawler($start_page, $step){
		$start = microtime(true);
        $client = new Client;
        
        $x = (int) $start_page; 
        $end = (int) $start_page + (int) $step;
        while($x < $end) {
			$page_start = microtime(true);
			echo "i = ".$x.": ";
            $pageUrl = 'https://mywork.com.vn/tuyen-dung/trang/'.$x;
    		$crawler = $client -> request('GET', $pageUrl);
			$jobs = $crawler -> filter('div.item-list') -> filter('a.item');
			if ($jobs -> count() <= 0) {
                echo "NA";
				break;
            } 
            dd($jobs -> count());
			$jobs -> each(
		    	function ($node) {
					try {
						ini_set('max_execution_time', 10000000);				
                        $job_link = $node -> attr('href');
                        
                        if ($job_link == null){
                        } else{
                            $full_link = 'https://mywork.com.vn'.$job_link;
                            
                            //todo separate
                            $fp = fopen('mywork-links.csv', 'a');
                            fputcsv($fp, array($full_link));
                            fclose($fp);
                            
                            // MyWorkCrawler::TimJob($full_link);
                        }
                        
					} catch (Exception $e) {
						// echo 'Caught exception: ',  $e -> getMessage(), "\n";
						$fp = fopen('timviecnhanh-error.csv', 'a');
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

    public function TimJob($url){
		// $job_start = microtime(true);
		$client = new Client;
		// echo 'create client: '.(microtime(true) - $job_start).' secs, ';
		$crawler = $client -> request('GET', $url);
		// echo 'request page: '.(microtime(true) - $job_start).' secs, ';

		$content_crawler = $crawler -> filter('div.detail_job');
		if ($content_crawler -> count() <= 0 ) {
			$fp = fopen('mywork-error.csv', 'a');
			fputcsv($fp, array("ERROR: ", $url), $delimiter = "|");
			fclose($fp);
		} else{
			$content = $content_crawler -> first();
			
			// $header_start = microtime(true);
			$job_title = "n/a";
			$title_crawler = $crawler -> filter('h1.main-title > span');
			if ($title_crawler ->count() > 0 ) {
				$job_title = $title_crawler -> first() -> text();
			}
			// echo 'header: '.(microtime(true) - $header_start).' secs, ';

			// $posted_start = microtime(true);
			$created = "n/a";
			$created_infos = $content -> filter('div.content > p');
			if ($created_infos -> count() > 0 ) {
                foreach ($created_infos as $node) {
                    $created_crawler = new Crawler($node);
                    $label = $created_crawler -> filter('strong') -> first() -> text();
                    if (strpos($label, 'Ngày duyệt') !== false){
                        $created = $created_crawler -> first() -> text();
                        break;
                    }
                }
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

			// $deadline_start = microtime(true);
			// $deadline = $content -> filter('b.text-danger') -> first() -> text();
			// echo 'deadline: '.(microtime(true) - $deadline_start).' secs, ';

            // $salary_start = microtime(true);
            $salary = 'n/a';
            $salary_crawler = $content -> filter('span.text-red');
            if ($salary_crawler -> count() > 0 ) {
				$salary = $salary_crawler -> first() -> text();
            }
            // echo 'salary: '.(microtime(true) - $salary_start).' secs, ';
            
            // $website_start = microtime(true);
			$website = 'n/a';
			$website_crawler = $content -> filter('p.company-name > a');
			if ($website_crawler -> count() > 0 ) {
                $ref = $website_crawler -> first() -> attr('href');
                $website = 'https://mywork.com.vn'.$ref;
			}
            // echo 'website: '.(microtime(true) - $website_start).' secs, ';
            
            // soluong
            // $soluong_start = microtime(true);
            $soluong = "n/a";
			$general_infos = $content -> filter('div.job_detail_general > div.item1 > p');
			if ($general_infos -> count() > 0 ) {
                foreach ($general_infos as $node) {
                    $soluong_crawler = new Crawler($node);
                    $label = $soluong_crawler -> filter('strong') -> first() -> text();
                    if (strpos($label, 'Số lượng cần tuyển') !== false){
                        $soluong = $soluong_crawler -> first() -> text();
                        break;
                    }
                }
            }
			// echo 'soluong: '.(microtime(true) - $soluong_start).' secs, ';

			// $job_des = $content -> filter('td > p') -> first() -> text();

			// $file_start = microtime(true);
			$line = array($job_title
				, trim($company, "\r\n ")
				, $created, trim($deadline, "\r\n ")
				, trim(explode("\n", $salary)[2], "\r\n ")
				, trim(explode("\n", $soluong)[2], "\r\n ")
				, $website
				, explode(":", $address)[1]
				, trim(preg_replace("/[\r\n]*/", "", $job_des), "-")
				, $url);
			
			$fp = fopen('timviecnhanh.csv', 'a');
			fputcsv($fp, $line, $delimiter = "|");
			fclose($fp);
			// echo 'write file: '.(microtime(true) - $file_start).' secs <br>';

			// echo 'Total 1 job: '.(microtime(true) - $job_start).' secs <br>';

		}
	}

	public function AppendToFile($line){
		var_dump($line);
		$fp = fopen('newfile.csv', 'a');
		fputcsv($fp, $line);
		fclose($fp);
	}
}