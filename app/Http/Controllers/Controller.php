<?php

namespace App\Http\Controllers;

//namespace App\Helpers\DbHelper;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;

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

    //send email function 
    public function sendEmail($to, $subject, $message, $cert_path = null, $conditions_path = null): object
    {
        try {

            // Send email, attaching files only when provided
            Mail::send([], [], function ($mail) use ($to, $subject, $message, $cert_path, $conditions_path) {
                $mail->to($to)
                    ->subject($subject)
                    ->html($message);

                if (!empty($cert_path) && file_exists($cert_path)) {
                    $mail->attach($cert_path);
                }

                if (!empty($conditions_path) && file_exists($conditions_path)) {
                    $mail->attach($conditions_path);
                }
            });

            // Mail::raw($message, function ($mail) use ($to, $subject) {
            //     $mail->to($to)->subject($subject);
            // });

            // if (Mail::failures()) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'Failed to send email to some or all recipients.',
            //         'failedRecipients' => Mail::failures()
            //     ], 500);
            //     //return false;
            // }

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully.'
            ], 200);
            //return true;
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred.',
                'error' => $th->getMessage()
            ], 500);
            //return false;
        }
    }

}