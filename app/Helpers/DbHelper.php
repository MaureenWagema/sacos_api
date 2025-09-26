<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Carbon\Carbon;


class DbHelper
{
    static function updateColumnValue($table_name, $col, $col_val, $change_col, $change_val)
    {
        $where = array(
            $col => $col_val
        );
        $table_data = array(
            $change_col => $change_val
        );
        DB::connection('glico_db')->table($table_name)
            ->where($where)
            ->update($table_data);

        return true;
    }
    static function getColumnValue($table_name, $col, $col_val, $return_col)
    {
        $where = array(
            $col => $col_val
        );
        $qry = DB::connection('glico_db')->table($table_name)
            ->select($return_col)
            ->whereNotNull($col)
            ->where($where);

        $results = $qry->first();
        if (isset($results)) {
            return $results->$return_col;
        } else {
            return null;
        }
    }
    static function insertRecord($table_name, $table_data, $user_id = null)
    {
        $res = array();
        /*if($user_id == null){
            $user_id = \Auth::user()->id;
        }*/
        try {
            //just incase they were not passed
            DB::connection('glico_db')->transaction(function () use ($table_name, $table_data, $user_id, &$res) {
                $table_data['created_on'] = Carbon::now();
                $table_data['created_by'] = $user_id;
                $record_id = DB::connection('glico_db')->table($table_name)->insertGetId($table_data);
                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'Data Saved Successfully!!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        return $res;
    }
    static function updateRecord($table_name, $table_data, $where, $user_id = null)
    {
        $res = array();
        try {
            // just in case they were not passed
            DB::connection('glico_db')->transaction(function () use ($table_name, $table_data, $where, $user_id, &$res) {
                $table_data['dola'] = Carbon::now();
                $table_data['altered_by'] = $user_id;
                DB::connection('glico_db')->table($table_name)
                    ->where($where)
                    ->update($table_data);
                $res = array(
                    'success' => true,
                    'message' => 'Record updated successfully!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
        }
        // Add a return statement to return the result
        return $res;
    }

    static function getSingleRecord($table, $where, $con)
    {
        $record = DB::connection('glico_db')->table($table)->where($where)->first();
        return $record;
    }

    static function getSingleRecordColValue($table, $where, $col)
    {
        $val = DB::connection('glico_db')->table($table)->where($where)->value($col);
        return $val;
    }

    static function getTableData($table_name, $where, $col)
    {
        $qry = DB::connection('glico_db')->table($table_name)
            ->where($where);

        $results = $qry->get();
        return $results;
    }

    static function getTableRawData($qry)
    {
        $results = DB::connection('glico_db')->select(DB::connection('glico_db')->raw($qry));
        return $results;
    }

    /*static function recordExists($table_name, $where, $con)
    {
        $recordExist = DB::connection($con)->table($table_name)->where($where)->get();
        if ($recordExist && count($recordExist) > 0) {
            return true;
        }
        return false;
    }

    //new
    public static function insertMultipleRecordNoTransaction($table_name, $table_data, $user_id, $con = 'pgsql')
    {
        $record_id = DB::connection($con)->table($table_name)->insert($table_data);
        $data = serialize($table_data);
        $audit_detail = array(
            'table_name' => $table_name,
            'table_action' => 'insert',
            'record_id' => $record_id,
            'current_tabledata' => $data,
            'ip_address' => self::getIPAddress(),
            'created_by' => $user_id,
            'created_at' => Carbon::now()
        );
      self::logAuditedTables($table_name, $record_id, $user_id, "Insert");
      DB::connection('audit_db')->table('tra_misaudit_trail')->insert($audit_detail);
        return $record_id;
    }
    static function insertMultipleRecords($table_name, $table_data, $user_id=null, $con)
    {
        $res = array();
        if($user_id == null){
            $user_id = \Auth::user()->id;
        }
        try {

            DB::transaction(function () use ($con, $table_name, $table_data, $user_id, &$res) {

                $res = array(
                    'success' => true,
                    'affected_rows' => self::insertMultipleRecordNoTransaction($table_name, $table_data, $user_id, $con),
                    'message' => 'Data Saved Successfully!!'
                );
            }, 5);
        }
        catch (\PDOException $exception) {
            $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "insertRecord");
        } catch (\Exception $exception) {
           $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "insertRecord");
        } catch (\Throwable $throwable) {
            $res = self::sys_error_handler($throwable->getMessage(), 3, "Database Error Check error for details", "insertRecord");
        }
        return $res;
    }*/



    /*static function deleteRecordNoAudit($table_name, $where_data)
    {
        $res = array();
        try {
            DB::transaction(function () use ($table_name, $where_data, &$res) {
                $affectedRows = $this->smartlife_db->table($table_name)->where($where_data)->delete();
                if ($affectedRows) {
                    $res = array(
                        'success' => true,
                        'message' => 'Delete request executed successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Zero number of rows affected. No record affected by the delete request!!'
                    );
                }
            }, 5);
        } catch (\PDOException $exception) {
            $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "deleteRecordNoAudit");
        } catch (\Exception $exception) {
           $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "deleteRecordNoAudit");
        } catch (\Throwable $throwable) {
            $res = self::sys_error_handler($throwable->getMessage(), 3, "Database Error Check error for details", "deleteRecordNoAudit");
        }
        return $res;
    }


    static function updateRecord($table_name, $where_opt, $current_data, $user_id, $con)
    {
        $res = array();
        if($user_id == null){
            $user_id = \Auth::user()->id;
        }
        try {
            $previous_data = self::getPreviousRecords($table_name, $where_opt, $con);
            if ($previous_data['success'] == false) {
                $previous_data = [];
            }
            $previous_data = $previous_data['results'];

            if(!isset($current_data['dola'])){
                $current_data['dola'] = Carbon::now();
            }
            if(!isset($current_data['altered_by'])){
                $current_data['altered_by'] = $user_id;
            }
            //unset just in case they were passed during update
            unset($current_data['created_on']);
            unset($current_data['created_by']);

            DB::transaction(function () use ($con, $table_name, $previous_data, $where_opt, $current_data, $user_id, &$res) {
                $update = self::updateRecordNoTransaction($con, $table_name, $previous_data, $where_opt, $current_data, $user_id);
                if ($update['success'] == true) {
                    $res = array(
                        'success' => true,
                        'record_id' => $update['record_id'],
                        'message' => 'Data updated Successfully!!'
                    );
                } else {
                    $res = $update;

                }
            }, 5);
        }
        catch (\PDOException $exception) {
            $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "updateRecord");
        } catch (\Exception $exception) {
            $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "updateRecord");
        } catch (\Throwable $throwable) {
            $res = self::sys_error_handler($throwable->getMessage(), 3, "Database Error Check error for details", "updateRecord");
        }
        return $res;
    }

    static function deleteRecord($table_name, $where_data, $user_id, $con)
    {
        $res = array();
        if($user_id == null){
            $user_id = \Auth::user()->id;
        }
        try {
             $previous_data = self::getPreviousRecords($table_name, $where_data, $con);
            if ($previous_data['success'] == false) {
                $previous_data = [];
            }
            $previous_data = $previous_data['results'];

            DB::transaction(function () use ($con, $table_name, $previous_data, $where_data, $user_id, &$res) {
                if (self::deleteRecordNoTransaction($table_name, $previous_data, $where_data, $user_id, $con)) {
                    $res = array(
                        'success' => true,
                        'message' => 'Delete request executed successfully!!'
                    );
                } else {
                    $res = array(
                        'success' => false,
                        'message' => 'Zero number of rows affected. No record affected by the delete request!!'
                    );
                }
            }, 5);
        }
        catch (\PDOException $exception) {
            $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "deleteRecord");
        } catch (\Exception $exception) {
           $res = self::sys_error_handler($exception->getMessage(), 3, "Database Error Check error for details", "deleteRecord");
        } catch (\Throwable $throwable) {
            $res = self::sys_error_handler($throwable->getMessage(), 3, "Database Error Check error for details", "deleteRecord");
        }
        return $res;
    }*/

}