<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DBController extends Controller{
    /**
     * Show a list of all of the application's users.
     *
     * @return Response
     */

    public function CheckLinksExist($jobs_links, $database, $table){
		$select_param = "('".implode("','", $jobs_links)."')";
		$select_dialect = "select link from ".$database.".".$table." where link in ";
		$select_query = $select_dialect.$select_param;
		$select_results = DB::select($select_query);

		return $select_results;
	}

    public function index(){
        // $users = DB::select('select * from test where id = ?', [1]);
        $start = microtime(true);
        // $users = DB::select('select * from test where id in (?)', array(2));
        $arr = array("hoai", "nguyen", "van");
        
        array_push($arr, "dfs");
        print_r($arr);

        // $idx = 0;
        // foreach($arr as $el){
        //     $arr[$idx] = "('".$el."')";
        //     $idx++;
        // }
        // $str = implode(",", $arr); 
        // $dialect = "insert into phpmyadmin.mywork(link) values ";
        // $query = $dialect.$str;
        // $inserted = DB::insert($query);
        // print_r($inserted);

        // $select_param = "('".implode("','", $arr)."')";
        // $select_dialect = "select link from phpmyadmin.mywork where link in ";
        // $select_query = $select_dialect.$select_param;
        // $select_results = DB::select($select_query);
        // dd($select_results);

        // return view('user.index', ['users' => $users]);
    }
}