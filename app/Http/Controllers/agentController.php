<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class agentController extends Controller
{
    public function generate_agentno()
    {
        $agent_no = null;
        //get the policy_serial
        $qry = $this->smartlife_db->table('CompanyInfo as p')
            ->select('p.next_agent_no')
            ->where(array('p.id' => 1));
        $results = $qry->first();
        //generate policy no
        $agent_no = date("Y") . str_pad($results->next_agent_no, 5, 0, STR_PAD_LEFT);
        return $agent_no;
    }
    public function calculateAge($birthdate)
    {
        if (empty($birthdate))
            return null;
        $today = Carbon::today();
        $diff = $today->diff(Carbon::parse($birthdate));
        return $diff->format('%y');
    }
    
    public function parseFullName($fullName) {
        // Split the full name into an array of words
        $nameArray = explode(' ', $fullName);
    
        // The last element of the array is considered the surname
        $surname = array_pop($nameArray);
    
        // The remaining elements are part of the other names
        $otherNames = implode(' ', $nameArray);
    
        // Return an associative array with surname and other names
        return ['surname' => $surname, 'otherNames' => $otherNames];
    }

    //post agent registration
    public function AgentsRegistration(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $agent_no = $this->generate_agentno();
                $status_code = DbHelper::getColumnValue('AgentstatusInfo', 'HasNotDoneExam', 1, 'id');

                $IsAttachedToAbranch = $request->input('IsAttachedToAbranch');
                $IsAttachedToUnit = $request->input('IsAttachedToUnit');
                $IsAttachedToTeam = $request->input('IsAttachedToTeam');
                $IsAttachedToRegion = $request->input('IsAttachedToRegion');
                $HierachyDetails = 1;

                if (isset($IsAttachedToAbranch) && (int) $IsAttachedToAbranch == 1) {
                    $HierachyDetails = DbHelper::getColumnValue('AgentsHierachyInfo', 'IsAttachedToAbranch', 1, 'id');
                }
                if (isset($IsAttachedToUnit) && (int) $IsAttachedToUnit == 1) {
                    $HierachyDetails = DbHelper::getColumnValue('AgentsHierachyInfo', 'IsAttachedToUnit', 1, 'id');
                }
                if (isset($IsAttachedToTeam) && (int) $IsAttachedToTeam == 1) {
                    $HierachyDetails = DbHelper::getColumnValue('AgentsHierachyInfo', 'IsAttachedToTeam', 1, 'id');
                }
                if (isset($IsAttachedToRegion) && (int) $IsAttachedToRegion == 1) {
                    $HierachyDetails = DbHelper::getColumnValue('AgentsHierachyInfo', 'IsAttachedToRegion', 1, 'id');
                }

                $fullnames = $this->parseFullName($request->input('name'));
                //save here
                $table_data = array(
                    'AgentNoCode' => $agent_no,
                    'agent_no' => $agent_no,
                    'SourceOfEntry' => 3,
                    'BusinessChannel' => $request->input('BusinessChannel'),
                    'BancassuranceBankLink' => $request->input('BancassuranceBankLink'),
                    //'AllDocumentReceived' => $request->input('AllDocumentReceived'),
                    //'StopPFDeduction' => $request->input('StopPFDeduction'),
                    //'StatusCode' => $request->input('StatusCode'),
                    'IsAttachedToAbranch' => $request->input('IsAttachedToAbranch'),
                    'IsAttachedToUnit' => $request->input('IsAttachedToUnit'),
                    'IsAttachedToTeam' => $request->input('IsAttachedToTeam'),
                    'IsAttachedToRegion' => $request->input('IsAttachedToRegion'),
                    'HierachyDetails' => $HierachyDetails,
                    //'ManagerialFlag' => $request->input('ManagerialFlag'),
                    //'UseThisOverrideRate' => $request->input('UseThisOverrideRate'),
                    'BranchName' => $request->input('BranchName'),
                    'UnitName' => $request->input('UnitName'),
                    'TeamName' => $request->input('TeamName'),

                    'surname' => $fullnames['surname'],
                    'other_name' => $fullnames['otherNames'],

                    'name' => $request->input('name'),
                    'mobile' => $request->input('mobile'),
                    //'mobile2' => $request->input('mobile2'),
                    //'RestrictCommission' => $request->input('RestrictCommission'),
                    //'CommissionCutoffDate' => $request->input('CommissionCutoffDate'),
                    //'appointed_on' => $request->input('appointed_on'),
                    //'stopped_date' => $request->input('stopped_date'),
                    'Compliance_licence' => $request->input('Compliance_licence'),
                    'licenceNumber' => $request->input('licenceNumber'),
                    'LicenceStartDate' => $request->input('LicenceStartDate'),
                    'LicenceExpiryDate' => $request->input('LicenceExpiryDate'),
                    'Emailaddress' => $request->input('Emailaddress'),
                    'physicaladdress' => $request->input('physicaladdress'),

                    'GpsCode' => $request->input('GpsCode'),
                    'postalcode' => $request->input('postalcode'),
                    'postaladdress' => $request->input('postaladdress'),
                    'Country' => '001',
                    'RegionName' => $request->input('RegionName'),
                    'bank_code' => $request->input('bank_code'),
                    'bank_branch' => $request->input('bank_branch'),
                    'bank_ac' => $request->input('bank_ac'),
                    'Bank_ac_Name' => $request->input('Bank_ac_Name'),
                    'EducationLevel' => $request->input('EducationLevel'),
                    'RecruitedBy' => $request->input('RecruitedBy'),
                    'TelcoCode' => $request->input('TelcoCode'),
                    'mobileMOMO' => $request->input('mobileMOMO'),
                    'MOMOWalletName' => $request->input('MOMOWalletName'),
                    'payment_method' => $request->input('payment_method'),
                    'id_type' => $request->input('id_type'),

                    'IdentityNumber' => $request->input('IdentityNumber'),
                    'KRANumber' => $request->input('KRANumber'),
                    'gender' => $request->input('gender'),
                    'marital_status' => $request->input('marital_status'),
                    'birthdate' => $request->input('birthdate'),
                    'EntryAge' => $request->input('EntryAge'),
                    'EmploymentType' => $request->input('EmploymentType'),
                    'SellingExperience' => $request->input('SellingExperience'),
                    'ExperienceSector' => $request->input('ExperienceSector'),
                    'StatusCode' => $status_code,
                    'FromRecruitementPortal' => 1,
                    'created_on' => Carbon::now(),
                    'created_by' => 'RecruitementPortal',
                    'RecordSaved' => 1,
                    'EntryAge' => $this->calculateAge($request->input('birthdate')),

                    
                    'CurrentManagerLevel' => DbHelper::getColumnValue('ManagerPromotionLevel', 'LevelCode', 1, 'id'),
                    'IsforMicro' => 0,
                    'DateSynched' => Carbon::now()
                    //'PromotionMinimumPeriod' => $request->input('PromotionMinimumPeriod'),
                );

                $record_id = $this->smartlife_db->table('agents_info')->insertGetId($table_data);
                //$request->$record_id = $record_id;

                //$input = $request->all();
                //$input['record_id'] = $record_id;


                $this->migrateAgentImage($request,$record_id);
                //TODO save all beneficiaries as well...
                //post agent beneficiary

                //Names, marital_status, email,relationship,id_type,idNumber,birthdate,EntryAge,mobile
                //beneficiaries
                $beneficiaries_array = array();
                $beneficiaries_arr = $request->input('beneficiaries');

                if (isset($beneficiaries_arr)) {
                    $this->smartlife_db->table('AgentBeneficiaryInfo')->where('AgentIdKey', '=', $record_id)->delete();
                    for ($i = 0; $i < sizeof($beneficiaries_arr); $i++) {
                        $beneficiaries_array[$i]['AgentIdKey'] = $record_id;
                        $beneficiaries_array[$i]['Names'] = $beneficiaries_arr[$i]['Names'];
                        $beneficiaries_array[$i]['marital_status'] = $beneficiaries_arr[$i]['marital_status'];
                        $beneficiaries_array[$i]['email'] = $beneficiaries_arr[$i]['email'];
                        $beneficiaries_array[$i]['birthdate'] = $beneficiaries_arr[$i]['birthdate'];
                        if ($beneficiaries_array[$i]['birthdate'] == "null") {
                            $beneficiaries_array[$i]['birthdate'] = null;
                        }
                        $beneficiaries_array[$i]['relationship'] = $beneficiaries_arr[$i]['relationship'];
                        $beneficiaries_array[$i]['id_type'] = $beneficiaries_arr[$i]['id_type'];
                        $beneficiaries_array[$i]['idNumber'] = $beneficiaries_arr[$i]['idNumber'];
                        $beneficiaries_array[$i]['EntryAge'] = $beneficiaries_arr[$i]['EntryAge'];
                        $beneficiaries_array[$i]['mobile'] = $beneficiaries_arr[$i]['mobile'];

                        $beneficiaries_id = $this->smartlife_db->table('AgentBeneficiaryInfo')->insertGetId($beneficiaries_array[$i]);
                    }
                }

                //update CompanyInfo 
                $next_agent_no = DbHelper::getColumnValue('CompanyInfo', 'id', 1, 'next_agent_no');
                $this->smartlife_db->table('CompanyInfo')
                    ->where(
                        array(
                            "id" => 1
                        )
                    )->update(array("next_agent_no" => $next_agent_no + 1));




                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'agent_no' => $agent_no,
                    'message' => 'Agent Registered Successfully!!'
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
    //TODO---1.Get Agent Details....
    public function getAgentDetails(Request $request)
    {
        try {

            $total_submitted_proposals = 0;
            $total_policies = 0;
            $agent_no = $request->input('agentNo');
            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');

            //count mob_proposals
            $total_submitted_proposals = $this->smartlife_db->table('proposalinfo')
            ->where('agent_no', $agentId)
            ->count();
            
            //count policies
            $total_policies = $this->smartlife_db->table('polinfo')
            ->where('agent_no', $agentId)
            ->count();
            
            
            $AgentDetails = $this->smartlife_db->table('agents_info as p')
                ->select(
                    'p.AgentNoCode',
                    'p.name',
                    'd.description as CurrentManagerLevelName',
                    'p.IsActive',
                    'p.Isinstitution',
                    'p.mobile',
                    'e.description as UnitDescription'
                )
                ->leftJoin('ManagerPromotionLevel as d', 'd.id', '=', 'p.CurrentManagerLevel')
                ->leftJoin('AgentsunitsInfo as e', 'e.id', '=', 'p.UnitName')
                ->where('p.IsActive', 1)
                ->where('p.BusinessChannel', 1)
                ->where('p.AgentNoCode', $agent_no)
                ->first();

            //get clawback details
            /*$sql = "SELECT t1.*,t2.policy_no FROM AgentsClawBackDatainfo t1 
            LEFT JOIN polinfo t2 ON t2.id=t1.PolicyIdKey
            WHERE t1.AgentIdKey='$agentId'";
            $AgentClawBack = DbHelper::getTableRawData($sql);
            */

            //AgentDetails
            $res = array(
                'success' => true,
                'AgentDetails' => $AgentDetails,
                'total_submitted_proposals' => $total_submitted_proposals,
                'total_policies' => $total_policies,
                //'AgentClawBack' => $AgentClawBack
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

    //TODO---2.Edit Agent Details...
    public function editAgentsDetails(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $table_data = json_decode($request->input('tableData'));
                if (isset($table_data->id)) {
                    $id = $table_data->id;
                    unset($table_data->id);
                }
                
                $table_data = json_decode(json_encode($table_data), true);
                $this->smartlife_db->table('agents_info')
                    ->where(
                        array(
                            "id" => $id
                        )
                    )
                    ->update($table_data);
                $record_id = $id;
                $res = array(
                    'success' => true,
                    //'record_id' => $record_id,
                    'message' => 'Agent Details Edit Successfully'
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

    public function migrateAgentImage(Request $request,$rcd_id=null)
    {
        try {
            $record_id = $request->input('record_id');
            if(!isset($record_id)) $record_id = $rcd_id;
            $photo = $request->input('photo');
            $id_front = $request->input('id_front');

            $photo_binary = file_get_contents($photo);
            $id_binary = file_get_contents($id_front);

            $this->smartlife_db->table('agents_info')
                ->where('id', $record_id)
                ->update([
                    'photo' => DB::raw("0x" . bin2hex($photo_binary)),
                    'IdFront' => DB::raw("0x" . bin2hex($id_binary))
                ]);

            $res = array(
                'success' => true,
                'message' => 'Image synchronization successful'
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



    ////migrate NIC certificates/////
    public function migrateAgentNICDocs(Request $request,$rcd_id=null)
    {
        try {
            $file = $request->file('file');
            $agent_no = $request->input('agent_no');
            $fileTypeId = $request->input('fileTypeId');
            $StartDate = $request->input('StartDate');
            $ExpiryDate = $request->input('ExpiryDate');

            if(!isset($agent_no)){
                return $res = array(
                    'success' => false,
                    'message' => 'Agent No does not passed'
                );
            }

            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            if(!isset($agentId)){
                return $res = array(
                    'success' => false,
                    'message' => 'Agent Not found'
                );
            }

            $this->saveNICFile($file, $agentId, $fileTypeId,$StartDate,$ExpiryDate);

            $res = array(
                'success' => true,
                'message' => 'NIC docs successfully migrated'
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

    /*public function saveNICFile($file, $agentId, $fileTypeId,$StartDate=null,$ExpiryDate=null)
    {
        $fileName = $agentId."-".$file->getClientOriginalName();
        //Display File Extension
        $file->getClientOriginalExtension();
        //Display File Real Path
        $file->getRealPath();
        //Display File Size
        $file_size = $file->getSize();
        //Display File Mime Type
        $file->getMimeType();
        //$destinationPath = 'C:\Users\User\Documents\SmartLife\PolicyDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', 6, 'FileStoreLocationPath');
        $file->move($destinationPath, $fileName);

        //insert photo 
        $image_path = $destinationPath . "\\" . $fileName;
        $image_binary = file_get_contents($image_path);
        // Convert the binary data to a base64-encoded string
        $encoded_image = base64_encode($image_binary);

        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();


        //DB::raw("0x" . bin2hex($image_binary))
        //Oid,size,FileName,Content,OptimisticLockField
        $file_data = array(
            'Oid' => $uuid,
            'size' => $file_size,
            'FileName' => $fileName,
            'Content' => DB::raw("0x" . bin2hex($image_binary)),
            'OptimisticLockField' => 1
        );
        $record_id = $this->smartlife_db->table('FileData')->insertGetId($file_data);
        // DB::statement("INSERT INTO FileData (Oid, size, FileName, Content, OptimisticLockField) VALUES (?, ?, ?, ?, ?)", [
        //     $uuid,
        //     $file_size,
        //     $fileName,
        //     $image_binary,  // Bind the binary data directly
        //     1
        // ]);

        //AgentIdKey,DocumentType,StartDate,ExpiryDate,Comment,FileLink,created_on
        $table_data = array(
            'AgentIdKey' => $agentId,
            'DocumentType' => $fileTypeId,
            'StartDate' => $StartDate,
            'ExpiryDate' => $ExpiryDate,
            'Comment' => 'Migrated from Recruitment Portal',
            'FileLink' => $uuid,
            'created_on' => Carbon::now()
        );
        $record_id = $this->smartlife_db->table('AgentsStoreObject')->insertGetId($table_data);
        

        

    }*/

    public function saveNICFile($file, $agentId, $fileTypeId,$StartDate=null,$ExpiryDate=null)
    {
        $fileName = $agentId."-".$file->getClientOriginalName();
        //Display File Extension
        $file->getClientOriginalExtension();
        //Display File Real Path
        $file->getRealPath();
        //Display File Size
        $file_size = $file->getSize();
        //Display File Mime Type
        $file->getMimeType();
        //$destinationPath = 'C:\Users\User\Documents\SmartLife\PolicyDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', 6, 'FileStoreLocationPath');
        $file->move($destinationPath, $fileName);

        //insert photo 
        $image_path = $destinationPath . "\\" . $fileName;
        $image_binary = file_get_contents($image_path);
        // Convert the binary data to a base64-encoded string
        $encoded_image = base64_encode($image_binary);

        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();


        //DB::raw("0x" . bin2hex($image_binary))
        //Oid,size,FileName,Content,OptimisticLockField
        $file_data = array(
            'AgentId' => $agentId,
            'DocumentTypeId' => 6,
            'received_flag' => 0,
            'date_received' => Carbon::now(),
            'created_on' => Carbon::now(),
            'File' => $uuid,
            'Description' => $fileName,
            'created_on' => Carbon::now(),
            'AgentDocumentTypeId' => $fileTypeId
        );
        $fileId = $this->smartlife_db->table('AgentsFileinfo')->insertGetId($file_data);

        $table_data = array(
            'Oid' => $uuid,
            'FileId' => $fileId,
            'FileName' => $fileName,
            'Size' => $file_size,
        );
        $this->smartlife_db->table('AgentsStoreObject')->insertGetId($table_data);

        //IF image is photo or ghana card, save in the db....
        if($fileTypeId == "1" || $fileTypeId == 1){
            //ghana card
            $this->smartlife_db->table('agents_info')
            ->where('id', $agentId)
            ->update([
                'IdFront' => DB::raw("0x" . bin2hex($image_binary))
            ]);
        }
        if($fileTypeId == "9" || $fileTypeId == 9){
            //photo
            $this->smartlife_db->table('agents_info')
            ->where('id', $agentId)
            ->update([
                'photo' => DB::raw("0x" . bin2hex($image_binary))
            ]);
        }
    }

    /////////sync image//////////
    public function syncAgentImage(Request $request, $record_id)
    {
        try {
            $photo = $request->file('photo');
            $id_front = $request->file('id_front');

            $photo_binary = file_get_contents($photo->getPathname());
            $id_binary = file_get_contents($id_front->getPathname());

            $this->smartlife_db->table('agents_info')
                ->where('id', $record_id)
                ->update([
                    'photo' => DB::raw("0x" . bin2hex($photo_binary)),
                    'IdFront' => DB::raw("0x" . bin2hex($id_binary))
                ]);

            $res = array(
                'success' => true,
                'message' => 'Image synchronization successful'
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
    ////////end of sync image////

    //getAgentLoans
    public function getAgentLoans(Request $request)
    {
        try {
            $agent_no = $request->input('agent_no');
            $rcd_id = $request->input('id');
            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $sql = "SELECT * FROM payrollItemIssuing p WHERE p.PayNo=" . $agentId;
            if (isset($rcd_id)) {
                $sql .= " AND p.id=" . $rcd_id;
            }
            $AgentLoans = DbHelper::getTableRawData($sql);

            //AgentLoans
            $res = array(
                'success' => true,
                'AgentLoans' => $AgentLoans
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

    public function saveAgentLoanRequest(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $table_data = json_decode($request->input('tableData'));
                if (isset($table_data->id)) {
                    $id = $table_data->id;
                    unset($table_data->id);
                }
                //$table_data->date_synced = date('Y-m-d H:i:s');
                //$table_data->created_on = date('Y-m-d H:i:s');
                $table_data->RequestDate = date('Y-m-d H:i:s');
                $table_data->PayNo = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $table_data->PayNo, 'id');
                //get the bussinessChannel of agent
                $BusinessChannel = DbHelper::getColumnValue('agents_info', 'id', $table_data->PayNo, 'BusinessChannel');
                $table_data->CurrentPeriodYear = DbHelper::getColumnValue('CommissionCategoryInfo', 'id', $BusinessChannel, 'period_year');
                $table_data->CurrentPeriodMonth = DbHelper::getColumnValue('CommissionCategoryInfo', 'id', $BusinessChannel, 'period_month');

                $table_data = json_decode(json_encode($table_data), true);

                if (isset($id) && (int) $id > 0) {
                    //update
                    $this->smartlife_db->table('payrollItemIssuing')
                        ->where(
                            array(
                                "id" => $id
                            )
                        )
                        ->update($table_data);
                    $record_id = $id;
                } else {
                    //insert
                    $record_id = $this->smartlife_db->table('payrollItemIssuing')->insertGetId($table_data);
                }


                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'Loan Request Successfully!!'
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


    //get Telcos
    public function getTelcos(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM pay_source_mainteinance p WHERE p.TelcoCompany=1";
            $Telcos = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Telcos' => $Telcos
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

    //get AgentsEmploymentType
    public function getAgentsEmploymentType(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsEmploymentType p";
            $AgentsEmploymentType = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsEmploymentType' => $AgentsEmploymentType
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

    //get Agent's current period
    public function getAgentPeriod(Request $request)
    {
        try {
            $agent_no = $request->input('agent_no');
            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');

            $category = 'IsForIndividualLife';
            if ($BusinessChannel == 5) {
                $category = 'IsForMicroLife';
            }
            $AgentPeriod['period_year'] = DbHelper::getColumnValue('CommissionCategoryInfo', $category, 1, 'period_year');
            $AgentPeriod['Period_month'] = DbHelper::getColumnValue('CommissionCategoryInfo', $category, 1, 'period_month');



            //health questionnaire
            $res = array(
                'success' => true,
                'AgentPeriod' => $AgentPeriod,
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

    //getAgentCommission
    //get Agent's commission
    public function getAgentCommission(Request $request)
    {
        try {
            $agent_no = $request->input('agent_no');
            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $period_year = $request->input('period_year');
            $Period_month = $request->input('Period_month');

            
            $FinancialAdvisorCategory = $request->input('FinancialAdvisorCategory');
            $PayrollCategory = $request->input('PayrollCategory');


            //t.period_year= 2022 AND t.Period_month=4 AND 
            $sql = "SELECT t2.name AS client_name, t1.policy_no, t.FinancialAdvisorCategory,
            t.PayrollCategory, t.payment_date, 
            t.ReceiptNoOLD,t.received, t.comm_payable, t.overrideComm1,t.overRate1,
            t3.current_premiums 
            FROM prmtranscomm t 
            LEFT JOIN polinfo t1 ON t.PolicyId=t1.id
            INNER JOIN clientinfo t2 ON t1.client_number = t2.client_number
            INNER JOIN prmtransinfo t3 ON t3.id=t.PrmTransId
            WHERE (t.agent_no =$agentId)";
            if (isset($period_year) && isset($Period_month)) {
                $sql .= " AND t.period_year=$period_year AND t.Period_month=$Period_month 
                 AND t.FinancialAdvisorCategory=$FinancialAdvisorCategory AND 
                 t.PayrollCategory=$PayrollCategory";
            }
            $AgentCommission = DbHelper::getTableRawData($sql);

            //override commission
            $sql = "SELECT t2.name AS client_name, t1.policy_no, t.FinancialAdvisorCategory,
            t.PayrollCategory, t.payment_date, 
            t.ReceiptNoOLD,t.received, 
            ROUND(t.comm_payable,2) 'comm_payable',
            ROUND(t.overrideComm1,2) 'overrideComm1',
            ROUND(t.overRate1,2) 'overRate1',
            t3.current_premiums 
            FROM prmtranscomm t 
            LEFT JOIN polinfo t1 ON t.PolicyId=t1.id
            INNER JOIN clientinfo t2 ON t1.client_number = t2.client_number
            INNER JOIN prmtransinfo t3 ON t3.id=t.PrmTransId
            WHERE (t.Direct_agent_no =$agentId)";
            if (isset($period_year) && isset($Period_month)) {
                $sql .= " AND t.period_year=$period_year AND t.Period_month=$Period_month 
                 AND t.FinancialAdvisorCategory=$FinancialAdvisorCategory AND 
                 t.PayrollCategory=$PayrollCategory";
            }
            $AgentOverrideCommission = DbHelper::getTableRawData($sql);

            // $sql = "SELECT total_a, total_b, ROUND((total_a + total_b), 2) AS total_sum
            // FROM (
            //     SELECT ROUND(SUM(comm_payable), 2) AS total_a, ROUND(SUM(overrideComm1), 2) AS total_b
            //     FROM prmtranscomm WHERE (agent_no =$agentId OR Direct_agent_no =$agentId) AND 
			// 	(period_year=$period_year AND Period_month=$Period_month AND 
            //      FinancialAdvisorCategory=$FinancialAdvisorCategory AND PayrollCategory=$PayrollCategory)
            // ) subquery";
            $sql = "SELECT ROUND((t.basic_pay + t.overide_comm + t.overide_comm2), 2) AS total_sum FROM 
                pyemployeeinfo t 
                WHERE t.pay_no=$agentId AND t.period_year=$period_year AND 
                t.period_month=$Period_month  AND t.PayrollCategory=$PayrollCategory 
                AND t.FinancialAdvisorCategory=$FinancialAdvisorCategory";
            $AgentTotals = DbHelper::getTableRawData($sql);

            $payslip_month =(int)$Period_month + 1;
            if($payslip_month == 13){
                $payslip_month = 1;
                $period_year = $period_year + 1;
            }
            //PaySlip
            $sql = "SELECT t2.para_name 'PAYSLIP_NAME',t1.amount 'AMOUNT' FROM pytransinfo t1 
                    INNER JOIN pyparainfo t2 ON t1.para_code = t2.id 
                    LEFT JOIN  agents_info t3 ON t1.pay_no=t3.id
                    WHERE t1.period_year =$period_year AND t1.period_month = $payslip_month 
                    AND t3.AgentNoCode='$agent_no' AND t1.PayrollCategory=$PayrollCategory 
                    ORDER BY t2.seq_no";
            $PaySlip = DbHelper::getTableRawData($sql);


            
            $res = array(
                'success' => true,
                'AgentCommission' => $AgentCommission,
                'AgentOverrideCommission' => $AgentOverrideCommission,
                'AgentTotals' => $AgentTotals,
                'PaySlip' => $PaySlip
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

    //get Region
    public function getRelationships(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT code,description FROM relationship_mainteinance p";
            $Relationships = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Relationships' => $Relationships
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

    //get Region
    public function getRegions(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM Towns p";
            $Regions = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Regions' => $Regions
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

    //get bank codes
    public function getBanks(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT bank_code,description FROM bankcodesinfo p";
            $Banks = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Banks' => $Banks
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

    //get bank branches
    public function getBankBranches(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT id,bankBranchCode,bankBranchName,bank_code FROM bankmasterinfo p";
            $BankBranches = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'BankBranches' => $BankBranches
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

    //get recruited by
    public function getRecruitedBy(Request $request)
    {
        try {
            $agent_contact_no = $request->input('agent_contact_no');

            $query = $this->smartlife_db->table('agents_info')
                ->select('id', 'agent_no', 'name');

            if ($agent_contact_no) {
                $query->where('mobile', '=', $agent_contact_no);
            }

            $RecruitedBy = $query->get();

            // health questionnaire
            if ($RecruitedBy->isEmpty()) {
                $message = "No agent found with the specified agent contact number.";
            } elseif (!$agent_contact_no) {
                $message = "Agent contact number not provided. Displaying all agents.";
            } else {
                $message = "Agent with the specified agent contact number retrieved successfully.";
            }

            $res = array(
                'success' => true,
                'message' => $message,
                'RecruitedBy' => $RecruitedBy
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

    //get id types
    public function getIdTypes(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM identity_types p";
            $IdTypes = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'IdTypes' => $IdTypes
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

    //get gender
    public function getGender(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM gender_info p";
            $Gender = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Gender' => $Gender
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

    //get marital status
    public function getMaritalStatus(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM MaritalStatusInfo p";
            $MaritalStatusInfo = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'MaritalStatusInfo' => $MaritalStatusInfo
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


    //get experience sector
    public function getExprienceSector(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsExperienceSector p";
            $AgentsExperienceSector = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsExperienceSector' => $AgentsExperienceSector
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

    //get compliance
    public function getAgentsComplianceLicense(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsCompliance_Licence p";
            $AgentsComplianceLicense = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsComplianceLicense' => $AgentsComplianceLicense
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

    //get payment method
    public function getAgentsPaymentMethods(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsPaymethodInfo p";
            $AgentsPaymethods = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsPaymethods' => $AgentsPaymethods
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
    //get Regions
    public function getAgentsRegions(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsRegionInfo p";
            $AgentsRegionInfo = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsRegionInfo' => $AgentsRegionInfo
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
    //get Branches
    public function getAgentsBranches(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsBranchInfo p";
            $AgentsBranchInfo = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsBranchInfo' => $AgentsBranchInfo
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
    //get unit
    public function getAgentsUnits(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsunitsInfo p";
            $AgentsunitsInfo = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsunitsInfo' => $AgentsunitsInfo
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
    //get teams
    public function getAgentsTeams(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsTeamsInfo p";
            $AgentsTeamsInfo = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsTeamsInfo' => $AgentsTeamsInfo
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
    //get channel
    public function getAgentsChannel(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM agentsChannel p WHERE p.ShowInPortal=1";
            $agentsChannel = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'agentsChannel' => $agentsChannel
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

    //get Education level
    public function getAgentsEducationLevel(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM EducationLevel p";
            $agentsEducationLevel = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'agentsEducationLevel' => $agentsEducationLevel
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

    //get AgentsFileChecklist
    public function getAgentsFileChecklist(Request $request)
    {
        try {
            //$agent_no = $request->input('agent_no');
            $sql = "SELECT * FROM AgentsFileChecklist p";
            $AgentsFileChecklist = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsFileChecklist' => $AgentsFileChecklist
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
}