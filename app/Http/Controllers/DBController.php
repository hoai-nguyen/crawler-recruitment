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
    public function index(){
        // $users = DB::select('select * from test where id = ?', [1]);
        $start = microtime(true);
        $users = DB::select('select * from test where id in (?)', array(2));
        print_r($users);
        // return view('user.index', ['users' => $users]);
    }
}