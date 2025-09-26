<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class claimController extends Controller
{
    //wrongful from slams.............
    //claims entries
    public function insertSLAMSWrongful(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                //StaffNumber, FullName, IsForBanks, BankName, IsForEmployer, Employer_TelcoName, PaymentDate

                $InsertData = array(
                    "StaffNumber" => $request->input('StaffNumber'),
                    "FullName" => $request->input('FullName'),
                    "IsForBanks" => $request->input('IsForBanks'),
                    "BankName" => $request->input('BankName'),
                    "IsForEmployer" => $request->input('IsForEmployer'),
                    "Employer_TelcoName" => $request->input('Employer_TelcoName'),
                    "PaymentDate" => $request->input('PaymentDate'),
                    "Reason" => $request->input('Reason'),
                    "created_on" => Carbon::now(),
                    "created_by" => $request->input('user_id')
                );

                
                $record_id = $this->smartlife_db->table('MissingPwdClaimUpload')->insertGetId($InsertData);
                
                $pos_log_data = array(
                    'ClientName' => $request->input('FullName'),
                    'staff_no' => $request->input('StaffNumber'),
                    'Activity' => 1,
                    'Narration' => $request->input('Reason'),
                    'eClaimId' => null,
                    'eEndorsementId' => null,
                    'created_on' => Carbon::now(),
                    'created_by' => $request->input('user_id')
                );
                $pos_log_id = $this->smartlife_db->table('pos_log')->insertGetId($pos_log_data);
                
                
                $res = array(
                    'success' => true,
                    'message' => 'Wrongful SLAMS Claim Successfully Submitted'
                );
            }, 1);
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


    //claims entries
    public function insertClaimEntries(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $statuscode = $request->input('statuscode');//14;
                if(!isset($statuscode)){
                    $statuscode = 14;
                }
                $id = $request->input('id');
                $PolicyId = $request->input('PolicyId'); //DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'id');
                $mobile_id = $request->input('mobile_id');
                $policy_no = $request->input('policyno');
                $MicroPolicy = $request->input('MicroPolicy');
                if (isset($MicroPolicy) && $MicroPolicy > 0) {
                    $PolicyId = null;
                }
                if (isset($policy_no)) {
                    $PolicyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
                    if (!isset($PolicyId)) {
                        $PolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'ProposalNumber', $policy_no, 'Id');
                    }
                }
                $ProposalNumber = $request->input('ProposalNumber');
                $claim_type = $request->input('claim_type');
                $PartialWithdPurpose = $request->input('PartialWithdPurpose');
                $CurrentCashValue = $request->input('CurrentCashValue');
                $PreviousloanAmount = $request->input('PreviousloanAmount');
                $AmountAppliedFor = $request->input('AmountAppliedFor');
                $ClaimCause = $request->input('ClaimCause');
                $DoctorName = $request->input('DoctorName');
                $event_date = $request->input('event_date');
                $ClaimantName = $request->input('ClaimantName');
                $ClaimantMobile = $request->input('ClaimantMobile');
                $PaymentOptions = $request->input('PaymentOptions');
                $TermInMonths = $request->input('TermInMonths');
                $Pay_method = $request->input('ClaimDefaultPay_method');
                $isClaimPayChange = $request->input('isClaimPayChange');
                $LoanPolicies = $request->input('LoanPolicies');
                $IsFromClient = $request->input('IsFromClient');

                $IsWrongful = false;
                if($claim_type == "RFU" || $claim_type == "RFULOAN" || $claim_type == "RFD" || $claim_type == "RFDLOAN"){
                    $IsWrongful = true;
                }

                

                //explode it and re-write it to ids seperated by comma
                if(isset($LoanPolicies)){
                    $tmp_loanpolicy = "";
                    $tmp_i = 0;
                    $loanPolicyArray = explode(',', $LoanPolicies);
                    // Loop through the array and print each element
                    foreach ($loanPolicyArray as $loanPolicy) {
                        $Policy_Id = DbHelper::getColumnValue('polinfo', 'policy_no', $loanPolicy, 'id');
                        if($tmp_i == 0){
                            $tmp_loanpolicy = $Policy_Id;
                        }else{
                            $tmp_loanpolicy = ",".$Policy_Id;
                        }
                        $tmp_i++;
                    }
                    $LoanPolicies = $tmp_loanpolicy;
                }



                if(!isset($isClaimPayChange) && isset($Pay_method)){
                    $ClaimDefaultPay_method = $request->input('ClaimDefaultPay_method');
                    $ClaimDefaultTelcoCompany = $request->input('ClaimDefaultTelcoCompany');
                    $ClaimDefaultMobileWallet = $request->input('ClaimDefaultMobileWallet');
                    $ClaimDefaultEFTBank_code = $request->input('ClaimDefaultEFTBank_code');
                    $ClaimDefaultEFTBankBranchCode = $request->input('ClaimDefaultEFTBankBranchCode');
                    $ClaimDefaultEFTBank_account = $request->input('ClaimDefaultEFTBank_account');
                    $ClaimDefaultEftBankaccountName = $request->input('ClaimDefaultEftBankaccountName');
                } 
                $user_id = $request->input('user_id');
                $Reason = $request->input('Reason');
                $id_type = $request->input('id_type');
                $IdNumber = $request->input('IdNumber');

                if(isset($isClaimPayChange) && $isClaimPayChange){
                    $ClaimDefaultPay_method = $request->input('ClaimDefaultPay_method');
                    $ClaimDefaultTelcoCompany = $request->input('ClaimDefaultTelcoCompany');
                    $ClaimDefaultMobileWallet = $request->input('ClaimDefaultMobileWallet');
                    $ClaimDefaultEFTBank_code = $request->input('ClaimDefaultEFTBank_code');
                    $ClaimDefaultEFTBankBranchCode = $request->input('ClaimDefaultEFTBankBranchCode');
                    $ClaimDefaultEFTBank_account = $request->input('ClaimDefaultEFTBank_account');
                    $ClaimDefaultEftBankaccountName = $request->input('ClaimDefaultEftBankaccountName');
                }else{
                    if(!isset($Pay_method)){
                        $ClaimDefaultPay_method = $request->input('ClaimDefaultPay_methodD');
                        $ClaimDefaultTelcoCompany = $request->input('ClaimDefaultTelcoCompanyD');
                        $ClaimDefaultMobileWallet = $request->input('ClaimDefaultMobileWalletD');
                        $ClaimDefaultEFTBank_code = $request->input('ClaimDefaultEFTBank_codeD');
                        $ClaimDefaultEFTBankBranchCode = $request->input('ClaimDefaultEFTBankBranchCodeD');
                        $ClaimDefaultEFTBank_account = $request->input('ClaimDefaultEFTBank_accountD');
                        $ClaimDefaultEftBankaccountName = $request->input('ClaimDefaultEftBankaccountNameD');
                    }
                }

                //LoanPaySource, LoanStaffNo
                $LoanDefaultPay_method = $request->input('LoanDefaultPay_method');
                $LoanPaySource = $request->input('LoanPaySource');
                $LoanStaffNo = $request->input('LoanStaffNo');
                $LoanDefaultTelcoCompany = $request->input('LoanDefaultTelcoCompany');
                if(isset($LoanDefaultTelcoCompany)){
                    $LoanPaySource=$LoanDefaultTelcoCompany;
                }

                $LoanDefaultMobileWallet = $request->input('LoanDefaultMobileWallet');
                $LoanDefaultEFTBank_code = $request->input('LoanDefaultEFTBank_code');
                $LoanDefaultEFTBankBranchCode = $request->input('LoanDefaultEFTBankBranchCode');
                $LoanDefaultEFTBank_account = $request->input('LoanDefaultEFTBank_account');
                $LoanDefaultEftBankaccountName = $request->input('LoanDefaultEftBankaccountName');
                $FuneralMembersInfo = $request->input('FuneralMembersInfo');
                

                //get client_name, 
                $client_no = DbHelper::getColumnValue('polinfo', 'id', $PolicyId, 'client_number');
                if(!isset($client_no)){
                    $client_no = DbHelper::getColumnValue('MicroPolicyInfo', 'Id', $MicroPolicy, 'Client');
                }
                if(!isset($client_no)){
                    $client_no = DbHelper::getColumnValue('proposalinfo', 'proposal_no', $ProposalNumber, 'client_number');
                }
                if(!isset($client_no)){
                    $client_no = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $ProposalNumber, 'Client');
                }
                //use clientId to fetch client_name
                $ClientName = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'name');

                $username = $user_id; //DbHelper::getColumnValue('portal_users', 'id',$user_id,'username');
                $branch_id = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'Branch');


                $Emp_code = $request->input('Emp_code');
                $BankCode = $request->input('BankCode');
                $ReferenceNumber = $request->input('ReferenceNumber');
                //$DateFrom = $request->input('DateFrom');
                //$DateTo = $request->input('DateTo');

                //TODO - Validate entry of staff no if its wrongful claim
                if($IsWrongful && ($statuscode == 13 || $statuscode == "13")){
                    if($claim_type == "RFU"){
                        //employer
                        $sql = "SELECT * FROM checkoffinfo t1 
                        WHERE (t1.Is_unknown=1 OR t1.Is_unknown=1 ) AND 
                        (t1.IsRefunded=0 OR t1.IsRefunded=0) AND t1.Emp_code ='$Emp_code' AND 
                        t1.staff_no ='$ReferenceNumber'";
                        $PaySourceRawData = DbHelper::getTableRawData($sql);
                        if(sizeof($PaySourceRawData) === 0){
                            //return;
                            return $res = array(
                                'success' => false,
                                'message' => 'Claim not Submited!. Missing wrongful record. Check staff Number'
                            );
                        }
                    }
                    if($claim_type == "RFULOAN"){
                        //employer
                        $sql = "SELECT * FROM Loancheckoffinfo t1 
                        INNER JOIN LoanReceipts t2 ON t1.LoanReceiptKey= t2.IdKey  
                        WHERE (t1.Is_unknown=1 OR t1.Is_unknown=1) AND (t1.IsRefunded=0 OR t1.IsRefunded=0) 
                        AND t2.EmpCode = '$Emp_code' AND 
                        t1.ReferenceNumber ='$ReferenceNumber'";
                        $PaySourceRawData = DbHelper::getTableRawData($sql);
                        if(sizeof($PaySourceRawData) === 0){
                            //return;
                            return $res = array(
                                'success' => false,
                                'message' => 'Claim not Submited!. Missing wrongful record. Check staff Number'
                            );
                        }
                    }
                    if($claim_type == "RFD"){
                        //employer
                        $sql = "SELECT * FROM Deduct t1 
                        WHERE (t1.Is_unknown=1 OR t1.Is_unknown=1 ) AND 
                        (t1.IsRefunded=0 OR t1.IsRefunded=0) AND t1.Bank_code ='$BankCode' AND 
                        t1.BankAccountNumber ='$ReferenceNumber'";
                        $PaySourceRawData = DbHelper::getTableRawData($sql);
                        if(sizeof($PaySourceRawData) === 0){
                            //return;
                            return $res = array(
                                'success' => false,
                                'message' => 'Claim not Submited!. Missing wrongful record. Check staff Number'
                            );
                        }
                    }
                    if($claim_type == "RFDLOAN"){
                        //employer
                        $sql = "SELECT * FROM Loancheckoffinfo t1 
                        INNER JOIN LoanReceipts t2 ON t1.LoanReceiptKey= t2.IdKey  
                        WHERE (t1.Is_unknown=1 OR t1.Is_unknown=1) AND (t1.IsRefunded=0 OR t1.IsRefunded=0) 
                        AND t2.BankCode ='$BankCode' AND 
                        t1.ReferenceNumber = '$ReferenceNumber'";
                        $PaySourceRawData = DbHelper::getTableRawData($sql);
                        if(sizeof($PaySourceRawData) === 0){
                            //return;
                            return $res = array(
                                'success' => false,
                                'message' => 'Claim not Submited!. Missing wrongful record. Check staff Number'
                            );
                        }
                    }
                }

                //LoanStaffNo
                //insert or update...
                //do a search where
                $FlagShowClaimnant = 0;
                if($claim_type != "RFD" && $claim_type != "RFU" && 
                $claim_type != "RFDLOAN" && $claim_type != "RFULOAN" && $claim_type != "REP"){
                    $FlagShowClaimnant = 1;
                    if(empty($PolicyId)){
                        $sql = "select * from eClaimsEntries p where (p.MicroPolicy=$MicroPolicy and p.claim_type='$claim_type') and (p.statuscode=13 or p.statuscode=14) and p.IsCancelled IS NULL";
                    }else{
                        $sql = "select * from eClaimsEntries p where (p.PolicyId=$PolicyId and p.claim_type='$claim_type') and (p.statuscode=13 or p.statuscode=14) and p.IsCancelled IS NULL";
                    } 
                }else{

                    if(isset($id)){
                        $sql = "select * from eClaimsEntries p where p.id=$id";
                    }else{
                        /*else{
                            $sql = "select * from eClaimsEntries p where (p.claim_type='$claim_type') and (p.statuscode=13 or p.statuscode=14)";
                        } */
                        if($claim_type == "REP"){
                            $sql = "select * from eClaimsEntries p where (p.ProposalNumber='$ProposalNumber' and p.claim_type='$claim_type') and (p.statuscode=13 or p.statuscode=14)";
                        }
                    }

                }

                $table_data = array(
                    'mobile_id' => '',
                    'HasBeenPicked' => 0,
                    'created_on' => Carbon::now(),
                    'RequestDate' => Carbon::now(),
                    'statuscode' => $statuscode,
                    'claim_type' => $claim_type,
                    'PolicyId' => $PolicyId,
                    'PartialWithdPurpose' => $PartialWithdPurpose,
                    'CurrentCashValue' => $CurrentCashValue,
                    'PreviousloanAmount' => $PreviousloanAmount,
                    'AmountAppliedFor' => $AmountAppliedFor,
                    'ClientName' => $ClientName,
                    'ClaimCause' => $ClaimCause,
                    'DoctorName' => $DoctorName,
                    'event_date' => $event_date,
                    'ClaimantName' => $ClaimantName,
                    'ClaimantMobile' => $ClaimantMobile,
                    'PaymentOptions' => $PaymentOptions,
                    'MicroPolicy' => $MicroPolicy,
                    'TermInMonths' => $TermInMonths,
                    'Pay_method' => $Pay_method,
                    'branch_id' => $branch_id,
                    'id_type'=>$id_type,
                    'IdNumber'=>$IdNumber,
                    'ClaimDefaultPay_method'=>$ClaimDefaultPay_method,
                    'ClaimDefaultTelcoCompany'=>$ClaimDefaultTelcoCompany,
                    'ClaimDefaultMobileWallet'=>$ClaimDefaultMobileWallet,
                    'ClaimDefaultEFTBank_code'=>$ClaimDefaultEFTBank_code,
                    'ClaimDefaultEFTBankBranchCode' => $ClaimDefaultEFTBankBranchCode,
                    'ClaimDefaultEFTBank_account' => $ClaimDefaultEFTBank_account,
                    'ClaimDefaultEftBankaccountName' => $ClaimDefaultEftBankaccountName,
                    'LoanPayMethod'=>$LoanDefaultPay_method,
                    'LoanPayEmpCode'=>$LoanPaySource,
                    'LoanPayemployee_no'=>$LoanStaffNo,
                    'MobileWallet'=>$LoanDefaultMobileWallet,
                    'LoanPayBank_code'=>$LoanDefaultEFTBank_code,
                    'LoanPayBankBranchCode' => $LoanDefaultEFTBankBranchCode,
                    'LoanPayBank_account' => $LoanDefaultEFTBank_account,
                    'LoanPayBankaccountName' => $LoanDefaultEftBankaccountName,
                    'FuneralMembersInfo' => $FuneralMembersInfo,
                    'Reason' => $Reason,
                    'Emp_code' => $Emp_code,
                    'BankCode' => $BankCode,
                    'ReferenceNumber' => $ReferenceNumber,
                    'FlagShowClaimnant' => $FlagShowClaimnant,
                    'LoanPolicies' => $LoanPolicies, 
                    'ProposalNumber' => $ProposalNumber
                );

                
                
                if(isset($sql)) $MobProposalsArr = DbHelper::getTableRawData($sql);


                if (isset($MobProposalsArr) && sizeof($MobProposalsArr) > 0) {
                    $record_id = $MobProposalsArr[0]->id;
                    $table_data['altered_by'] = $user_id;
                    $table_data['dola'] = date('Y-m-d H:i:s');
                    //update..
                    $this->smartlife_db->table('eClaimsEntries')
                        ->where(
                            array(
                                "ID" => $record_id
                            )
                        )
                        ->update($table_data);
                } else {
                    if (isset($id) && (int) $id > 0) {
                        $table_data['altered_by'] = $user_id;
                        $table_data['dola'] = date('Y-m-d H:i:s');
                        //update
                        $this->smartlife_db->table('eClaimsEntries')
                            ->where(
                                array(
                                    "id" => $id
                                )
                            )
                            ->update($table_data);
                        $record_id = $id;
                    } else {
                        $table_data['created_by'] = $user_id;
                        $table_data['created_on'] = date('Y-m-d H:i:s');

                        //insert
                        $is_maturity = DbHelper::getColumnValue('claims_types', 'claim_type', $claim_type, 'isMaturity');
                        $isSurrender = DbHelper::getColumnValue('claims_types', 'claim_type', $claim_type, 'isSurrender');
                        $claimTypeName = DbHelper::getColumnValue('claims_types', 'claim_type', $claim_type, 'Description');
                        if(isset($PolicyId) && (float)$PolicyId > 0 && $is_maturity == 1){
                            //ensure no other maturity exits
                            $qry = $this->smartlife_db->table('claim_notificationinfo as p')
                                    ->select('*')
                                    ->where(array(
                                        'p.claim_type' => $claim_type,
                                        'p.PolicyId' => $PolicyId,
                                        'p.IsCancelled' => 0,
                                        'p.isdecline' => 0
                                ));
                            $results_claims = $qry->first();
                            if(isset($results_claims)){
                                $res = array(
                                    'success' => false,
                                    'message' => 'Active '.$claimTypeName.' Claim detected, this policy has a '.$claimTypeName.' claim with number: '.$results_claims->claim_no
                                );
                                return response()->json($res);
                            }else{
                                $record_id = $this->smartlife_db->table('eClaimsEntries')->insertGetId($table_data);
                            }
                        }else{
                            $record_id = $this->smartlife_db->table('eClaimsEntries')->insertGetId($table_data);
                        }
                        
                    }
                }

                //

                //update clientinfo payment details...
                if (isset($isClaimPayChange) && $isClaimPayChange && isset($claim_type) && $claim_type != "DTH" && !empty($ClaimDefaultPay_method)) {

                    //TODO-Create change of client details endorsement
                    //1. write down the diary
                    /*$diary = "Change of Payment details to: \n";
                    $ClaimDefaultPay_methodName = DbHelper::getColumnValue('payment_type', 'payment_mode', $ClaimDefaultPay_method, 'decription');
                    $diary .= "Payment Type: " . $ClaimDefaultPay_methodName . "\n";

                    if ($ClaimDefaultPay_method == "6") {
                        //mobile money
                        $ClaimDefaultTelcoCompanyName = DbHelper::getColumnValue('pay_source_mainteinance', 'emp_code', $ClaimDefaultTelcoCompany, 'Name');
                        $diary .= "Telco Company: " . $ClaimDefaultTelcoCompanyName . "\n";
                        $diary .= "Mobile Wallet: " . $ClaimDefaultMobileWallet . "\n";
                    }

                    if ($ClaimDefaultPay_method == "7" || $ClaimDefaultPay_method == "9") {
                        //bank details
                        $ClaimDefaultEFTBank_codeName = DbHelper::getColumnValue('bankcodesinfo', 'bank_code', $ClaimDefaultEFTBank_code, 'description');
                        $ClaimDefaultEFTBankBranchCodeName = DbHelper::getColumnValue('bankmasterinfo', 'id', $ClaimDefaultEFTBankBranchCode, 'bankBranchName');
                        $diary .= "Bank: " . $ClaimDefaultEFTBank_codeName . "\n";
                        $diary .= "Bank Branch: " . $ClaimDefaultEFTBankBranchCodeName . "\n";
                        $diary .= "Bank Account Name: " . $ClaimDefaultEftBankaccountName . "\n";
                        $diary .= "Bank Account: " . $ClaimDefaultEFTBank_account . "\n";
                    }

                    //MicroPolicy
                    if (isset($MicroPolicy) && $MicroPolicy > 0) {
                        $endorsementData = array(
                            "Endorsementtype" => 19,
                            //client details
                            "RequestDate" => Carbon::now(),
                            "MicroPolicy" => $PolicyId,
                            "Reason" => $diary,
                            "ClaimDefaultPay_method"=>$ClaimDefaultPay_method,
                            "ClaimDefaultTelcoCompany"=>$ClaimDefaultTelcoCompany,
                            "ClaimDefaultMobileWallet"=>$ClaimDefaultMobileWallet,
                            "ClaimDefaultEFTBank_code"=>$ClaimDefaultEFTBank_code,
                            "ClaimDefaultEFTBankBranchCode" => $ClaimDefaultEFTBankBranchCode,
                            "ClaimDefaultEFTBank_account" => $ClaimDefaultEFTBank_account,
                            "ClaimDefaultEftBankaccountName" => $ClaimDefaultEftBankaccountName,
                            "created_by" => $user_id,
                            "created_on" => Carbon::now(),
                            'branch_id' => $branch_id
                        );
                    } else {
                        $endorsementData = array(
                            "Endorsementtype" => 19,
                            //client details
                            "RequestDate" => Carbon::now(),
                            "PolicyNumber" => $PolicyId,
                            "Reason" => $diary,
                            "ClaimDefaultPay_method"=>$ClaimDefaultPay_method,
                            "ClaimDefaultTelcoCompany"=>$ClaimDefaultTelcoCompany,
                            "ClaimDefaultMobileWallet"=>$ClaimDefaultMobileWallet,
                            "ClaimDefaultEFTBank_code"=>$ClaimDefaultEFTBank_code,
                            "ClaimDefaultEFTBankBranchCode" => $ClaimDefaultEFTBankBranchCode,
                            "ClaimDefaultEFTBank_account" => $ClaimDefaultEFTBank_account,
                            "ClaimDefaultEftBankaccountName" => $ClaimDefaultEftBankaccountName,
                            "created_by" => $user_id,
                            "created_on" => Carbon::now(),
                            'branch_id' => $branch_id
                        );
                    }

                    //StatusDescription IS NULL OR StatusDescription = "SUBMITTED"
                    if(!empty($PolicyId)){
                        $sql = "SELECT TOP 1 * FROM eEndorsmentEntries p WHERE p.Endorsementtype=19 AND (p.StatusDescription IS NULL OR p.StatusDescription='SUBMITTED') AND (p.PolicyNumber=$PolicyId OR p.MicroPolicy=$PolicyId)";
                        $EndorsementArr = DbHelper::getTableRawData($sql);

                        if (sizeof($EndorsementArr) > 0) {
                            $this->smartlife_db->table('eEndorsmentEntries')
                                ->where(
                                    array(
                                        "id" => $EndorsementArr[0]->id
                                    )
                                )
                                ->update($endorsementData);
                        } else {
                            $this->smartlife_db->table('eEndorsmentEntries')->insertGetId($endorsementData);
                        }
                    }*/


                    $payment_data = array(
                        'id_type'=>$id_type,
                        'IdNumber'=>$IdNumber,
                        'ClaimDefaultPay_method'=>$ClaimDefaultPay_method,
                        'ClaimDefaultTelcoCompany'=>$ClaimDefaultTelcoCompany,
                        'ClaimDefaultMobileWallet'=>$ClaimDefaultMobileWallet,
                        'ClaimDefaultEFTBank_code'=>$ClaimDefaultEFTBank_code,
                        'ClaimDefaultEFTBankBranchCode' => $ClaimDefaultEFTBankBranchCode,
                        'ClaimDefaultEFTBank_account' => $ClaimDefaultEFTBank_account,
                        'ClaimDefaultEftBankaccountName' => $ClaimDefaultEftBankaccountName,
                    );
                    $this->smartlife_db->table('clientinfo')
                    ->where(array(
                        "client_number" => $client_no
                    ))
                    ->update($payment_data);
                }

               
                //insert into pos_log
                $staff_no = "";
                $pos_log_e_id = DbHelper::getColumnValue('pos_log', 'eClaimId', $record_id, 'id');
                if(!isset($pos_log_e_id)){
                    if(isset($PolicyId)){
                        $staff_no = DbHelper::getColumnValue('polinfo', 'id', $PolicyId, 'SearchReferenceNumber');
                        if(!$staff_no){
                            $staff_no = DbHelper::getColumnValue('proposalinfo', 'proposal_no', $ProposalNumber, 'SearchReferenceNumber');
                            if(!isset($staff_no)){
                                $staff_no = "";
                            }
                        }
                    }
                    if(isset($MicroPolicy)){
                        $staff_no = DbHelper::getColumnValue('MicroPolicyInfo', 'Id', $MicroPolicy, 'EmployeeNumber');
                    }

                    //narration is the claim_type
                    $narration = DbHelper::getColumnValue('claims_types', 'claim_type', $claim_type, 'Description');
                    $policy_number = DbHelper::getColumnValue('polinfo', 'id', $PolicyId, 'policy_no');
                    $narration .= " (Policy Number: ".$policy_number.")";
                        
                    $pos_log_data = array(
                        'ClientName' => $ClaimantName,
                        'staff_no' => $staff_no,
                        'Activity' => 1,
                        'Narration' => $narration,
                        'eClaimId' => $record_id,
                        'eEndorsementId' => null,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $user_id
                    );
                    if(!isset($IsFromClient)){
                        $pos_log_id = $this->smartlife_db->table('pos_log')->insertGetId($pos_log_data);
                    }
                    
                }

                //health questionnaire
                $res = array(
                    'success' => true,
                    'claim_id' => $record_id,
                    'message' => 'Claim Successfully Saved!'
                );
            }, 1);
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

    public function removeDuplicateClaimTypes(array $objects) {
        // Use an associative array to store unique claim_type values
        $uniqueClaimTypes = array();
    
        // Filter out duplicate claim_type values
        $filteredArray = array_filter($objects, function ($item) use (&$uniqueClaimTypes) {
            $claimType = $item->claim_type;
    
            // Check if claim_type is already in the uniqueClaimTypes array
            if (!isset($uniqueClaimTypes[$claimType])) {
                // Add claim_type to the uniqueClaimTypes array
                $uniqueClaimTypes[$claimType] = true;
                return true; // Include the item in the filtered array
            }
    
            return false; // Exclude the item from the filtered array
        });
    
        return array_values($filteredArray);
    }

    //get Claims Attached files
    public function getClaimTypeGroup(Request $request)
    {
        try {
            //get files for eClaim
            //$rcd_id = $request->input('rcd_id');

            $sql = "SELECT p.*,d.Description AS SchemeBenefit FROM claims_types p 
                    INNER JOIN SchemeBenefitConfig d ON p.claim_type = d.ClaimType";
            $ClaimType = DbHelper::getTableRawData($sql);

            $objects = [
                (object)["claim_type" => "0054", "OldCode" => null, "Description" => "MARRIAGE BENEFIT"],
                (object)["claim_type" => "0054", "OldCode" => null, "Description" => "MARRIAGE BENEFIT"],
                (object)["claim_type" => "010", "OldCode" => null, "Description" => "SPOUSAL COVER"],
                (object)["claim_type" => "010", "OldCode" => null, "Description" => "SPOUSAL COVER"],
                (object)["claim_type" => "011", "OldCode" => null, "Description" => "ANOTHER CLAIM TYPE"],
                // Add more objects with different claim_types
            ];
            
            // Call the function to remove duplicates based on claim_type
            $result = $this->removeDuplicateClaimTypes($ClaimType);

            $res = array(
                'success' => true,
                'ClaimType' => $result//$this->removeDuplicateClaimTypes($ClaimType)
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

    //insert group eClaims
    public function insertGroupClaimEntries(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $id = $request->input('id');
                $claim_type = $request->input('claim_type');
                $EventDate = $request->input('event_date');
                $member_no = $request->input('member_no');
                $PayMethod = $request->input('PayMethod');
                $LoanType = $request->input('LoanType');

                if(isset($claim_type)){
                    $SchemeBenefit = DbHelper::getColumnValue('SchemeBenefitConfig', 'ClaimType', $claim_type, 'id');
                }
                

                $PayBeneficiary = 0;
                $PayScheme = 0;
                $PayMember = 0;
                if (!isset($PayMethod)) {
                    $PayScheme = 1;
                } else {
                    if (isset($PayMethod) && $PayMethod == "1")
                        $PayScheme = 1;
                    if (isset($PayMethod) && $PayMethod == "2")
                        $PayBeneficiary = 1;
                    if (isset($PayMethod) && $PayMethod == "3")
                        $PayMember = 1;
                }
                //get MemberID
                $MemberID = DbHelper::getColumnValue('glmembersinfo', 'member_no', $member_no, 'MemberId');
                //get SchemeID
                $SchemeID = DbHelper::getColumnValue('glmembersinfo', 'member_no', $member_no, 'SchemeID');



                $table_data = array(
                    'claim_type' => $claim_type,
                    'SchemeID' => $SchemeID,
                    'MemberID' => $MemberID,
                    'EventDate' => $EventDate,
                    'NoticeDate' => Carbon::now(),
                    'PayBeneficiary' => $PayBeneficiary,
                    'PayScheme' => $PayScheme,
                    'PayMember' => $PayMember,
                    'LoanType' => $LoanType,
                    'SchemeBenefit' => $SchemeBenefit
                );

                //insert into 
                if (isset($id) && (int) $id > 0) {
                    //$table_data['AlteredBy'] = $user_id;
                    $table_data['Dola'] = date('Y-m-d H:i:s');
                    //update
                    $this->smartlife_db->table('eClaimEntriesGroup')
                        ->where(
                            array(
                                "ID" => $id
                            )
                        )
                        ->update($table_data);
                    $record_id = $id;
                } else {
                    //$table_data['CreatedBy'] = $user_id;
                    $table_data['CreatedOn'] = date('Y-m-d H:i:s');
                    //insert
                    $record_id = $this->smartlife_db->table('eClaimEntriesGroup')->insertGetId($table_data);
                }

                //health questionnaire
                $res = array(
                    'success' => true,
                    'claim_id' => $record_id,
                    'message' => 'Claim Successfully Saved!'
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

    //get Claims Attached files
    public function getClaimFiles(Request $request)
    {
        try {
            //get files for eClaim
            $rcd_id = $request->input('rcd_id');
            $is_group = $request->input('is_group');

            if(!isset($is_group) || $is_group == 0){
                $sql = "SELECT p.*,d.description AS file_desc from claimsreqinfo p 
                inner join claim_requirement d on d.reg_code=p.code 
                where p.eClaimNumber=$rcd_id";           
            }else{
                $sql = "SELECT p.*,d.description AS file_desc from glifeclaimsreqinfo p 
                inner join claim_requirement d on d.reg_code=p.code 
                where p.EclaimsEntrieId=$rcd_id"; 
            }
            $Files = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'Files' => $Files
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


    ////Save Claim Files/////////
    //Image Sync
    public function syncClaimImage(Request $request)
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
                $IsClientSigned = $request->input('IsClientSigned');
                $category_id = 2;

                $fileName = $eClaimId . ".png"; 

                if (isset($myFile))
                    $this->savePhysicalFile($myFile, $category_id, $req_code, $eClaimId, $Description);
                if (isset($signature))
                    $this->saveStringFile($signature, $category_id, $req_code, $eClaimId, $fileName,$IsClientSigned);

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

    public function savePhysicalFile($file, $category_id, $req_code, $eClaimId, $Description)
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
        //$destinationPath = 'C:\Users\User\Documents\SmartLife\ClaimsDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');
        $file->move($destinationPath, $file->getClientOriginalName());
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();

        //insert into mob_proposalFileAttachment
        //claim_no,code,received_flag,date_received,MicroClaim,eClaimNumber,File,Description

        //check if file already exists
        $sql = "SELECT p.* FROM claimsreqinfo p WHERE p.eClaimNumber=$eClaimId AND p.code='$req_code'";
        $claimsreqinfoArr = DbHelper::getTableRawData($sql);
        $table_data = array(
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            'code' => $req_code,
            'received_flag' => 0,
            'date_received' => Carbon::now(),
            //'MicroClaim' => 0,
            'eClaimNumber' => $eClaimId,
            'File' => $uuid,
            'Description' => $fileName //$Description,
        );

        if (sizeof($claimsreqinfoArr) > 0) {
            //update..
            $record_id = $claimsreqinfoArr[0]->id;
            $this->smartlife_db->table('claimsreqinfo')
            ->where(
                array(
                    "id" => $record_id
                )
            )
            ->update($table_data);
        }else{
            $record_id = $this->smartlife_db->table('claimsreqinfo')->insertGetId($table_data);
        }

        

        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'RequestedClaim' => $eClaimId,
            'Size' => $file_size,
        );
        $record_id = $this->smartlife_db->table('ClaimsStoreObject')->insertGetId($table_data);
    }

    function base64ToVarbinary($base64)
    {
        $binary = base64_decode($base64);
        return bin2hex($binary);
    }

    public function saveStringFile($file, $category_id, $req_code, $eClaimId, $fileName,$IsClientSigned=null)
    {
        // $prefix = "data:image/png;base64,";
        // if (strpos($file, $prefix) === 0) {
        //     return str_replace($prefix, '', $file);
        // }
        //echo $file;
        //echo "am here";
        $file = substr($file, 22);
        //echo $file;
        //$destinationPath = 'C:\xampp\htdocs\SmartLifeDocuments\ClaimsDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');

        file_put_contents($destinationPath . '\\' . $eClaimId . '.png', base64_decode($file));
        $image_path = $destinationPath . "\\" . $eClaimId . ".png";
        $image_binary = file_get_contents($image_path);

        $this->smartlife_db->table('eClaimsEntries')
            ->where('id', $eClaimId)
            ->update([
                'ClientSignature' => DB::raw("0x" . bin2hex($image_binary)),
                'statuscode' => 13,
                'IsClientSigned' => $IsClientSigned
            ]);

        //insert into mob_proposalFileAttachment
        $sql = "SELECT p.* FROM claimsreqinfo p WHERE p.eClaimNumber=$eClaimId AND p.code='$req_code'";
        $claimsreqinfoArr = DbHelper::getTableRawData($sql);
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();
        $table_data = array(
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            'code' => $req_code,
            'received_flag' => 0,
            'date_received' => Carbon::now(),
            //'MicroClaim' => 0,
            'eClaimNumber' => $eClaimId,
            'File' => $uuid,
            'Description' => $fileName //$Description,
        );
        if (sizeof($claimsreqinfoArr) > 0) {
            //update..
            $record_id = $claimsreqinfoArr[0]->id;
            $this->smartlife_db->table('claimsreqinfo')
            ->where(
                array(
                    "id" => $record_id
                )
            )
            ->update($table_data);
        }else{
            $record_id = $this->smartlife_db->table('claimsreqinfo')->insertGetId($table_data);
        }
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'RequestedClaim' => $eClaimId,
            'Size' => 570,
        );
        $record_id = $this->smartlife_db->table('ClaimsStoreObject')->insertGetId($table_data);
    }
    //////////end of Claim Files///

    //fetch claims to sign... 
    public function getClaimsToSign(Request $request)
    {
        try {
            $res = array();
            //created_by, is_micro
            $created_by = $request->input('created_by');
            $is_micro = $request->input('is_micro');
            if($is_micro == "1"){
                $sql = "SELECT p.Id 'id',d.PolicyNumber 'policy_no',p.claim_type,p.ClaimantName,
                p.ClaimantMobile,p.created_on,p.IsClientSigned 
                FROM eClaimsEntries p 
                INNER JOIN MicroPolicyInfo d ON p.MicroPolicy=d.id
                WHERE p.created_on > '2024-07-20' AND
                (p.IsClientSigned=0 OR p.IsClientSigned IS NULL) AND 
                p.created_by = '$created_by' 
                ORDER BY p.id DESC";
            }else{
                $sql = "SELECT p.id,d.policy_no,p.PolicyId,p.claim_type,p.ClaimantName,
                p.ClaimantMobile,p.created_on,p.IsClientSigned 
                FROM eClaimsEntries p 
                INNER JOIN polinfo d ON p.PolicyId=d.id
                WHERE p.created_on > '2024-07-20' AND
                (p.IsClientSigned=0 OR p.IsClientSigned IS NULL) AND 
                p.created_by = '$created_by'
                ORDER BY p.id DESC";
            }
            
            $Claims = DbHelper::getTableRawData($sql);
            //health questionnaire
            $res = array(
                'success' => true,
                'Claims' => $Claims
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

    //get history of group claims
    public function getGroupHistoryClaims(Request $request)
    {
        try {
            $res = array();

            //
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $source_type = $request->input('source_type');

            $filter_array = array();//t3.id=6
            if($source_type == "2"){
                $filter_array = array(
                    "p.processed" => 1
                );
            } else if($source_type == "3"){
                $filter_array = array(
                    "s.id" => 6
                );
            }

            $results = $this->smartlife_db->table('glifeclaimsnotification as p')
                ->select('p.*', 's.description as claim_status', 'g.member_no as Emp_code')
                ->join('polschemeinfo as i', 'i.schemeID', '=', 'p.Scheme')
                ->join('ClaimHistoryInfo as h', 'p.id', '=', 'h.GlifeClaim_no')
                ->join('ClaimStatusInfo as s', 'h.statuscode', '=', 's.id')
                ->join('glmembersinfo as g', 'g.MemberId', '=', 'p.MemberIdKey')
                ->where($filter_array)
                ->whereBetween('p.notification_date', [$date_from, $date_to]) 
                ->orderBy('p.notification_date', 'DESC')
                ->get();
           
            $res = array(
                'success' => true,
                'Claims' => $results
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


    //get history of claims
    public function getHistoryClaims(Request $request)
    {
        try {
            $res = array();

            $client_no = $request->input('client_no');
            $policy_no = $request->input('policy_no');

            $ReferenceNumber = $request->input('staff_no');
            $id = $request->input('id');
            $is_micro = $request->input('is_micro');
            $is_dashboard = $request->input('is_dashboard');
            $is_md_coo = $request->input('is_md_coo');


            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            $criteria = $request->input('criteria');
            if(isset($criteria)) {
                if($criteria == "1"){
                    $policy_no = $request->input('search_entry');
                } else if($criteria == "2"){
                    $ReferenceNumber = $request->input('search_entry');
                }
            }


            $sql = "SELECT d.NarrationForRefunds,d.id,d.PolicyId,d.event_date,d.ClaimCause,d.claim_type,d.claim_no,
            d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,
            d.AmountAppliedFor,d.claimant AS ClaimantName,d.IsCancelled,d.processed,d.Cancel_narration,
            d.Pay_method,d.PaymentOptions,d.total_proceeds, d.total_deductions, d.net_payment, d.pay_due_date,
            d.created_by,d.created_on,d.branch_id,d.statusCode, 
            p.policy_no, n.description as ClaimStatus,
            f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 
            f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,
            f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,
            i.Pay_method, h.decription AS payment_method, i.Bank, j.description AS bank_name, 
            i.BankBranch, k.bankBranchName AS bank_branch_name, i.BankAccount, i.MobileNumber, 
            i.TelcoCompany, i.cheque_no,  q.Names 'Deceased' 
            FROM claim_notificationinfo d 
            LEFT JOIN eClaimsEntries m ON m.id = d.RequestedClaim
            LEFT JOIN ClaimStatusInfo n ON n.id = m.statuscode 
            LEFT JOIN claimsinfo i ON i.claim_no = d.id
            LEFT JOIN polinfo p ON d.PolicyId=p.id 
            LEFT JOIN clientinfo f ON f.client_number=p.client_number 
            LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch
            LEFT JOIN payment_type h ON h.payment_mode=i.Pay_method
            LEFT JOIN bankcodesinfo j ON j.bank_code=i.Bank
            LEFT JOIN bankmasterinfo k ON k.id=i.BankBranch
            LEFT JOIN funeralmembers q ON q.id=d.FuneralMembersInfo";
            if (isset($client_no) && $client_no != 'undefined') {
                $sql .= " WHERE p.client_number='$client_no'";
            } else if (isset($policy_no) && $policy_no != 'undefined') {
                $sql .= " WHERE p.policy_no='$policy_no'";
            } else if (isset($id)) {
                $sql .= " WHERE d.id=$id";
            } else if (isset($is_dashboard) && $is_dashboard == "1") {
                if(!isset($date_from) || !isset($date_to)){
                    $date_from = date('Y-m-d');
                    $date_to = date('Y-m-d');
                }//RequestDate
                $sql .= " WHERE d.pay_due_date BETWEEN '$date_from' AND '$date_to'";
                
            } else if (isset($is_md_coo)) {
                if(!isset($date_from) || !isset($date_to)){
                    $date_from = date('Y-m-d');
                    $date_to = date('Y-m-d');
                }//RequestDate
                $sql .= " WHERE d.created_on BETWEEN '$date_from' AND '$date_to'";
                
            } else if (isset($ReferenceNumber)) {
                $sql .= " WHERE p.SearchReferenceNumber='$ReferenceNumber'";
            }

            if(isset($criteria) || isset($is_md_coo)) {
                //TODO-Add the ones at enquiry stage only
                $sql_enquiry = "SELECT p.policy_no,d.id,d.claim_type,d.created_on,d.created_by,d.claim_no,
                        p.status_code, 
                        n.description AS ClaimStatus,d.IsCancelled,d.Cancel_narration,
                        g.glbranch_name as branch_name, i.Names 'Deceased'   
                        FROM eClaimsEntries d 
                        INNER JOIN polinfo p ON p.id=d.PolicyId
                        LEFT JOIN ClaimStatusInfo n ON n.id = d.statuscode 
                        LEFT JOIN claim_notificationinfo e ON e.RequestedClaim=d.id
                        LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch 
                        LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo
                        WHERE e.RequestedClaim IS NULL AND d.statuscode <> 14 ";
                if (isset($ReferenceNumber)) {
                    $sql_enquiry .= " AND p.SearchReferenceNumber='gli10213'";
                }
                if (isset($policy_no)) {
                    $sql_enquiry .= " AND p.policy_no='$policy_no'";
                }
                if (isset($is_md_coo)) {
                    if(!isset($date_from) || !isset($date_to)){
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }
                    $sql .= " AND d.created_on BETWEEN '$date_from' AND '$date_to'";
                }

            }


            if (isset($is_micro) && $is_micro == 1) {
                $sql = "SELECT d.Id,d.Policy,h.EventDate AS event_date,h.ClaimCause,d.claim_type,h.ClaimNumber AS claim_no,
                h.[PartialWidthrawalReasons] AS PartialWithdPurpose,h.CurrentCashValue,
                h.AmountAppliedFor,d.claimant AS ClaimantName,d.IsCancelled,d.processed,d.Cancel_narration,
                d.Pay_method,h.PaymentOptions,d.total_proceeds, d.total_deductions, d.net_payment, d.pay_due_date,
                d.created_by,d.created_on,h.Branch AS branch_id,p.ProposalNumber AS policy_no,
                d.payment_flag AS statuscode, n.description as ClaimStatus,
                f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 
                f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,
                f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name  
                FROM MicroClaimsInfo d 
                INNER JOIN MicroPolicyInfo p ON d.Policy=p.Id 
                INNER JOIN clientinfo f ON f.client_number=p.Client 
                INNER JOIN MicroClaimNotification h ON h.Id=d.MicroClaim 
                LEFT JOIN eClaimsEntries m ON m.id = h.RequestedClaim
                LEFT JOIN ClaimStatusInfo n ON n.id = m.statuscode 
                LEFT JOIN glBranchInfo g ON h.Branch=g.glBranch";
                if (isset($client_no) && $client_no != 'undefined') {
                    $sql .= " WHERE p.Client='$client_no' ";
                } else if (isset($policy_no) && $policy_no != 'undefined') {
                    $sql .= " WHERE p.PolicyNumber='$policy_no' ";
                } else if (isset($id)) {
                    $sql .= " WHERE d.Id=$id";
                } else if (isset($is_dashboard) && $is_dashboard == "1") {
                    if(!isset($date_from) || !isset($date_to)){
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }//RequestDate
                    $sql .= " WHERE d.pay_due_date BETWEEN '$date_from' AND '$date_to'";
                }

                if(isset($criteria)) {
                    //TODO-Add the ones at enquiry stage only
                    $sql_enquiry = "SELECT p.PolicyNumber,d.id,d.claim_type,d.created_on,d.created_by,d.claim_no,p.status_code,
                            n.description AS ClaimStatus,d.IsCancelled,d.Cancel_narration,
                            g.glbranch_name as branch_name  
                            FROM eClaimsEntries d 
                            INNER JOIN MicroPolicyInfo p ON d.Micro=p.Id 
                            LEFT JOIN ClaimStatusInfo n ON n.id = d.statuscode 
                            LEFT JOIN claim_notificationinfo e ON e.RequestedClaim=d.id
                            LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch
                            WHERE e.RequestedClaim IS NULL AND d.statuscode <> 14 ";
                    if (isset($ReferenceNumber)) {
                        $sql_enquiry .= " AND p.SearchReferenceNumber='gli10213'";
                    }
                    if (isset($policy_no)) {
                        $sql_enquiry .= " AND p.PolicyNumber='$policy_no'";
                    }
                }
            }

            
            $Claims = DbHelper::getTableRawData($sql);
            if(isset($criteria)){
                $ClaimsE = DbHelper::getTableRawData($sql_enquiry);
                $Claims = array_merge($Claims, $ClaimsE);
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'Claims' => $Claims
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

    //get group claims........
    public function getGroupClaims(Request $request)
    {
        try {
            $res = array();

            $scheme_no = $request->input('scheme_no');
            $SchemeID = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');
            
            /*$sql = "SELECT
            p.id,p.Scheme,p.claim_no,p.branch_id,p.notification_date,p.total_proceeds,p.total_deductions,p.net_payment,
            CASE 
                WHEN p.IsCancelled = 'True' THEN 1
                WHEN p.IsCancelled = 'False' THEN 0
                ELSE NULL -- Handle other cases if needed
            END AS IsCancelled,
            -- Map 'True' to 1 and 'False' to 0 for the 'Approved' column
            CASE 
                WHEN p.Approved = 'True' THEN 1
                WHEN p.Approved = 'False' THEN 0
                ELSE NULL -- Handle other cases if needed
            END AS Approved,
            e.name AS client_name,
            g.glbranch_name,
            q.Description AS ClaimName
        FROM glifeclaimsnotification p 
            INNER JOIN polschemeinfo d ON d.schemeID=p.Scheme
            INNER JOIN glifeclientinfo e ON e.Id=d.schemeID
            INNER JOIN claims_types q ON q.claim_type=p.claim_type
            LEFT JOIN glBranchInfo g ON p.branch_id=g.glBranch
            WHERE d.schemeID=$SchemeID 
            ORDER BY p.id DESC";*/
            //INNER JOIN glmembersinfo j ON j.SchemeID=p.Scheme
            //, j.MemberId, j.Names, j.member_no

            $sql = "SELECT
            p.id,p.Scheme,p.claim_no,p.branch_id,p.notification_date,p.total_proceeds,p.total_deductions,p.net_payment,
            p.IsCancelled, p.Approved, e.name AS client_name,
            g.glbranch_name,
            q.Description AS ClaimName
            FROM glifeclaimsnotification p 
            INNER JOIN polschemeinfo d ON d.schemeID=p.Scheme
            INNER JOIN glifeclientinfo e ON e.Id=d.schemeID
            INNER JOIN claims_types q ON q.claim_type=p.claim_type
            
            LEFT JOIN glBranchInfo g ON p.branch_id=g.glBranch
            WHERE d.schemeID=$SchemeID 
            ORDER BY p.id DESC";

            //echo $sql;
            $Claims = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Claims' => $Claims
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

    //get claims details
    public function getClientClaims(Request $request)
    {
        try {
            $res = array();

            $client_no = $request->input('client_no');
            $policy_no = $request->input('policy_no');
            $proposal_no = $request->input('proposal_no');
            $id = $request->input('id');
            $is_micro = $request->input('is_micro');

            $is_dashboard = $request->input('is_dashboard');

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            $sql = "SELECT h.NarrationForRefunds,d.ProposalNumber,d.id,d.PolicyId,d.event_date,d.ClaimCause,d.RequestDate,d.claim_type,d.claim_no,
            d.ClientName,d.mobile_id,d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,
            d.AmountAppliedFor,d.ClaimantName,d.ClaimantMobile, d.Cancel_narration,
            d.Pay_method,d.PaymentOptions,d.statuscode,d.StatusDescription,d.HasBeenPicked,d.IsFromClientPortal,
            d.IsFromPosPortal,d.IsFromSmartLife,d.created_by,d.created_on,d.branch_id,p.policy_no,
            e.description AS statuscode,
            f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 
            f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,
            f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,
            d.requisition_no,d.pv_no,d.SpecificReason,q.Description AS claim_name,
            i.Names 'Deceased'
            FROM eClaimsEntries d 
            INNER JOIN claims_types q ON q.claim_type=d.claim_type";
            if(isset($proposal_no) && !isset($policy_no)){
                $sql .= " INNER JOIN proposalinfo p ON d.ProposalNumber=p.proposal_no 
                INNER JOIN clientinfo f ON f.client_number=p.client_number 
                INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 
                LEFT JOIN claim_notificationinfo h ON h.RequestedClaim=d.id 
                LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 
                LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";
            }else{
                $sql .= " INNER JOIN polinfo p ON d.PolicyId=p.id 
                INNER JOIN clientinfo f ON f.client_number=p.client_number 
                INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 
                LEFT JOIN claim_notificationinfo h ON h.RequestedClaim=d.id 
                LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 
                LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";
            }
            
            if (isset($client_no)) {
                $sql .= " WHERE p.client_number='$client_no' AND d.statuscode <> 14";
            } else if (isset($policy_no)) {
                $sql .= " WHERE p.policy_no='$policy_no' AND d.statuscode <> 14";
            } else if (isset($proposal_no)) {
                $sql .= " WHERE d.ProposalNumber='$proposal_no' AND d.statuscode <> 14";
            } else if (isset($id)) {
                $sql .= " WHERE d.id=$id";
            } else if (isset($date_from) || (isset($is_dashboard) && $is_dashboard == "1")) {
                if(!isset($date_from) || !isset($date_to)){
                    $date_from = date('Y-m-d');
                    $date_to = date('Y-m-d');
                }//RequestDate
                $sql .= " WHERE (d.RequestDate BETWEEN '$date_from' AND '$date_to') AND d.statuscode <> 14";
            }

            if (isset($is_micro) && $is_micro == 1) {
                $sql = "SELECT d.id,d.MicroPolicy as PolicyId,d.event_date,d.ClaimCause,d.RequestDate,d.claim_type,d.claim_no,
                d.ClientName,d.mobile_id,d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,
                d.AmountAppliedFor,d.ClaimantName,d.ClaimantMobile,d.Pay_method,d.PaymentOptions,d.statuscode,
                d.StatusDescription,d.HasBeenPicked, d.Cancel_narration,
                d.IsFromClientPortal,d.IsFromPosPortal,d.IsFromSmartLife,d.created_by,d.created_on,d.branch_id,
                p.ProposalNumber AS policy_no,
                e.description AS statuscode,
                f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 
                f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,
                f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,
                d.requisition_no,d.pv_no,d.SpecificReason,q.Description AS claim_name     
                FROM eClaimsEntries d 
                INNER JOIN claims_types q ON q.claim_type=d.claim_type ";
                if(isset($proposal_no) && !isset($policy_no)){
                    $sql .= "LEFT JOIN MicroProposalInfo p ON d.ProposalNumber=p.ProposalNumber 
                    INNER JOIN clientinfo f ON f.client_number=p.Client 
                    INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 
                    LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";
                }else{
                    $sql .= "LEFT JOIN MicroPolicyInfo p ON d.MicroPolicy=p.Id 
                    INNER JOIN clientinfo f ON f.client_number=p.Client 
                    INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 
                    LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 
                    LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";
                }
                if (isset($client_no)) {
                    $sql .= " WHERE p.Client='$client_no' AND d.statuscode <> 14";
                } else if (isset($policy_no)) {
                    $sql .= " WHERE p.PolicyNumber='$policy_no' AND d.statuscode <> 14";
                } else if (isset($proposal_no)) {
                    $sql .= " WHERE d.ProposalNumber='$proposal_no' AND d.statuscode <> 14";
                } else if (isset($id)) {
                    $sql .= " WHERE d.id=$id";
                } else if (isset($date_from) || (isset($is_dashboard) && $is_dashboard == "1")) {
                    if(!isset($date_from) || !isset($date_to)){
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }//RequestDate
                    $sql .= " WHERE (d.RequestDate BETWEEN '$date_from' AND '$date_to') AND d.statuscode <> 14";
                }
            }
            //echo $sql;
            $Claims = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Claims' => $Claims
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

    //get claims details
    public function getClaimAttachments(Request $request)
    {
        try {
            $res = array();

            $claim_type = $request->input('claim_type');

            $sql = "SELECT p.id,p.req_code,d.description,p.IsMandatory FROM claimtyperequirementinfo p 
            INNER JOIN claim_requirement d  ON p.req_code=d.reg_code WHERE p.claim_type='$claim_type'";
            $Attachments = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Attachments' => $Attachments
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