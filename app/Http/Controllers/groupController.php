<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class groupController extends Controller
{
    //TODO - 
    //1. get scheme members
    //POS Registration
    public function getSchemeMembers(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Agents info if exists
            $scheme_no = $request->input('scheme_no');
            $schemeId = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');

            /*$query = $this->smartlife_db->table('glmembersinfo')
            ->select('*')
            ->where('SchemeID', '=', $schemeId); WHERE p.status='001'*/
            $where_arr = array(
                'SchemeID' => $schemeId,
                'status' => '001'
            );
            $query = $this->smartlife_db->table('glmembersinfo')
                ->join('glifestatus', 'glmembersinfo.status', '=', 'glifestatus.status_code')
                ->select('glmembersinfo.*', 'glifestatus.Description as status_name')
                ->where($where_arr)
                ->take(100);

            $results = $query->get();

            $class_code = DbHelper::getColumnValue('polschemeinfo', 'policy_no',$scheme_no,'class_code');

            $res = array(
                'success' => true,
                'scheme_no' => $scheme_no,
                'Members' => $results,
                'class_code' => $class_code 
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }
    //2. add new member to scheme
    public function addMemberToScheme(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                ////SchemeID,policy_no,member_no, Names, dob,commence_date,status,exit_date, TotalPremium,Salary

                $table_data = json_decode($request->input('tableData'));
                $policy_no = $table_data->policy_no;
                $SchemeID = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $policy_no, 'schemeID');
                $table_data->SchemeID = $SchemeID;
                if (isset($table_data->MemberId)) {
                    $MemberId = $table_data->MemberId;
                    unset($table_data->MemberId);
                }
                //$table_data->date_synced = date('Y-m-d H:i:s');
                //$table_data->created_on = date('Y-m-d H:i:s');
                $table_data->created_on = date('Y-m-d H:i:s');
                $table_data = json_decode(json_encode($table_data), true);

                if (isset($MemberId) && (int) $MemberId > 0) {
                    //update
                    $this->smartlife_db->table('glmembersinfo')
                        ->where(
                            array(
                                "MemberId" => $MemberId
                            )
                        )
                        ->update($table_data);
                    $record_id = $MemberId;
                } else {
                    //insert
                    $record_id = $this->smartlife_db->table('glmembersinfo')->insertGetId($table_data);
                }

                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'New Member added Successfully!!'
                );

            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //get quoatations
    public function getGroupQuotations(Request $request)
    {
        try {
            $res = array();
            //TODO
            $ID = $request->input('id');
            $query = $this->smartlife_db->table('eGroupQuotations')
                ->select('*');
            if (isset($ID) && (int) $ID > 0) {
                $query = $this->smartlife_db->table('eGroupQuotations')
                    ->select('*')->where('ID', '=', $ID);
            }

            $results = $query->get();

            $res = array(
                'success' => true,
                'Quotes' => $results
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //save Group Quote
    public function saveQuoteGroup(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                ////SchemeID,policy_no,member_no, Names, dob,commence_date,status,exit_date, TotalPremium,Salary

                $table_data = json_decode($request->input('tableData'));
                if (isset($table_data->ID)) {
                    $ID = $table_data->ID;
                    unset($table_data->ID);
                }
                if (isset($table_data->IndividualClient)) {
                    unset($table_data->IndividualClient);
                }

                //IndividualClient
                $table_data->QuotationDate = date('Y-m-d H:i:s');
                $table_data = json_decode(json_encode($table_data), true);

                if (isset($ID) && (int) $ID > 0) {
                    //update
                    $this->smartlife_db->table('eGroupQuotations')
                        ->where(
                            array(
                                "ID" => $ID
                            )
                        )
                        ->update($table_data);
                    $record_id = $ID;
                } else {
                    //insert
                    $record_id = $this->smartlife_db->table('eGroupQuotations')->insertGetId($table_data);
                }

                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'New Member added Successfully!!'
                );

            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //get Member Statement
    public function getMemberStatement(Request $request)
    {
        try {
            $member_no = $request->input('member_no');
            $memberId = DbHelper::getColumnValue('glmembersinfo', 'member_no', $member_no, 'MemberId');
            $sql = "SELECT t.FundYear,t.PrevCashvalue,(t.ActualEmpPension+t.ActualCompPension) InvestAmt,
            (t.EmpInterest+t.CompInterest) IntAmt,(t.EmpWithdrawal+t.CompWithdrawal) AmtWithdrawn,t.TotalPension 
            FROM penmemberfund t WHERE t.MemberId=$memberId ORDER BY t.FundYear";
            $MemberStatement = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'MemberStatement' => $MemberStatement
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }


    ////Save Claim Group Files/////////
    //Image Sync
    public function syncClaimGroupImage(Request $request)
    {
        try {
            //for base64
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $myFile = $request->file('myFile');
                $req_code = $request->input('req_code');
                $Description = DbHelper::getColumnValue('claim_requirement', 'reg_code', $req_code, 'description');
                $eClaimId = $request->input('eClaimId');
                $signature = $request->input('signature');
                $category_id = 2;

                $fileName = $eClaimId . ".png"; //"signature.png";

                if (isset($myFile))
                    $this->savePhysicalGroupFile($myFile, $category_id, $req_code, $eClaimId, $Description);
                if (isset($signature))
                    $this->saveStringGroupFile($signature, $category_id, $req_code, $eClaimId, $fileName);

                $res = array(
                    'success' => true,
                    'record_id' => $eClaimId,
                    'message' => 'Data Synced Successfully!!'
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    public function savePhysicalGroupFile($file, $category_id, $req_code, $eClaimId, $Description)
    {
        $fileName = $file->getClientOriginalName();
        //Display File Extension
        $file->getClientOriginalExtension();
        //Display File Real Path
        $file->getRealPath();
        //Display File Size
        $file_size = $file->getSize();
        //Display File Mime Type
        $file->getMimeType();
        //Move Uploaded File
        //FileCategoriesStore
        //$destinationPath = 'C:\Users\kgach\Documents\SmartLife\ClaimsDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');
        $file->move($destinationPath, $file->getClientOriginalName());
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();

        //insert into mob_proposalFileAttachment
        //claim_no,code,received_flag,date_received,MicroClaim,eClaimNumber,File,Description
        $table_data = array(
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            'code' => $req_code,
            'received_flag' => 0,
            'date_received' => Carbon::now(),
            //'MicroClaim' => 0,
            'EclaimsEntrieId' => $eClaimId,
            'File' => $uuid,
            'DocumentType' => $category_id,
            'Description' => $fileName //$Description,
        );
        $record_id = $this->smartlife_db->table('glifeclaimsreqinfo')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'GLifeClaimsRequirement' => $record_id,
            //$eClaimId,
            'Size' => $file_size,
        );
        $record_id = $this->smartlife_db->table('GLifeClaimsStoreObject')->insertGetId($table_data);
    }
    public function saveStringGroupFile($file, $category_id, $req_code, $eClaimId, $fileName)
    {
        //$destinationPath = 'C:\Users\kgach\Documents\SmartLife\ClaimsDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');
        file_put_contents($destinationPath . '\\' . $fileName, base64_decode($file));

        $image_path = $destinationPath . "\\" . $eClaimId . ".png";
        $image_binary = file_get_contents($image_path);
        $this->smartlife_db->table('eClaimsEntries')
            ->where('id', $eClaimId)
            ->update([
                'ClientSignature' => DB::raw("0x" . bin2hex($image_binary)),
                'statuscode' => 13
            ]);
        /*$this->smartlife_db->table('eClaimsEntries')
            ->where('id', $eClaimId)
            ->update(['ClientSignature' => DB::raw("0x" . bin2hex($image_binary))]);*/

        //insert into mob_proposalFileAttachment
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();
        $table_data = array(
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            'code' => $req_code,
            'received_flag' => 0,
            'date_received' => Carbon::now(),
            //'MicroClaim' => 0,
            'EclaimsEntrieId' => $eClaimId,
            'File' => $uuid,
            'DocumentType' => $category_id,
            'Description' => $fileName //$Description,
        );
        $record_id = $this->smartlife_db->table('glifeclaimsreqinfo')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'GLifeClaimsRequirement' => $record_id,
            //$eClaimId,
            'Size' => 570,
        );
        $record_id = $this->smartlife_db->table('GLifeClaimsStoreObject')->insertGetId($table_data);
    }
    //////////end of Claim Files///


}