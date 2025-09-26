<?php

namespace App\Http\Controllers;

//namespace App\Helpers\DbHelper;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    protected $smartlife_db;
    protected $slams_db;

    public function __construct()
    {
        $this->smartlife_db = DB::connection('glico_db'); //life
        $this->slams_db = DB::connection('mysql');
    }

    public function testDatabaseConnection()
    {
        try {
            $tables = $this->smartlife_db->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");

            $tableNames = array_column($tables, 'TABLE_NAME');
            var_dump($tableNames);
        } catch (\Exception $exception) {
            echo 'Error: ' . $exception->getMessage();
        }
    }

}