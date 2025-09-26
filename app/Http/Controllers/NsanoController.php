<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class NsanoController extends Controller
{
     /*
        {
            "when_to_process": "ON_APPROVAL",
            "is_recurring": "FALSE",
            "is_scheduled": "FALSE",
            "validate_destination_account": "TRUE",
            "schedule_date": null,
            "schedule_time": null,
            "recurring_time": null,
            "recurring_freq": null,
            "merchant": "655b28d809bcbe19c8ae5123",
            "author": "655b28d809bcbe19c6ae5122",
            "transactions": [
            {
                "amount": 3600,
                "destination_institution": "MTN",
                "destination_account": 34525252,
                "destination_type": "WALLET",
                "contact_number": "260979149115",
                "country": "GH",
                "narration": "CBVs OTP Training",
                "recipient_name": "Alberta Test"
            }
            ]
        },
        {
            "when_to_process": "SCHEDULED",
            "is_recurring": "FALSE",
            "is_scheduled": "TRUE",
            "validate_destination_account": "FALSE",
            "schedule_date": "2024-12-01",
            "schedule_time": "12:00:00",
            "recurring_time": null,
            "recurring_freq": null,
            "merchant": "655b28d809bcbe19c8ae5123",
            "author": "655b28d809bcbe19c6ae5122",
            "transactions": [
            {
                "amount": 1500,
                "destination_institution": "Bank XYZ",
                "destination_account": 12345678,
                "destination_type": "BANK",
                "contact_number": "260979149116",
                "country": "NG",
                "narration": "Payment for services",
                "recipient_name": "John Doe"
            }
            ]
        },
        {
            "when_to_process": "RECURRING",
            "is_recurring": "TRUE",
            "is_scheduled": "FALSE",
            "validate_destination_account": "TRUE",
            "schedule_date": null,
            "schedule_time": null,
            "recurring_time": "08:00:00",
            "recurring_freq": "MONTHLY",
            "merchant": "655b28d809bcbe19c8ae5123",
            "author": "655b28d809bcbe19c6ae5122",
            "transactions": [
            {
                "amount": 5000,
                "destination_institution": "Airtel",
                "destination_account": 98765432,
                "destination_type": "WALLET",
                "contact_number": "260979149117",
                "country": "KE",
                "narration": "Monthly subscription",
                "recipient_name": "Jane Doe"
            },
            {
                "amount": 7500,
                "destination_institution": "Vodafone",
                "destination_account": 11223344,
                "destination_type": "BANK",
                "contact_number": "260979149118",
                "country": "TZ",
                "narration": "Project funding",
                "recipient_name": "David Smith"
            }
            ]
        }
            */
    //TODO
    //1. Add the Upload endpoint
    public function NsanoUpload(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
            //$policy_no = $request->input('policy_no');
            ///api/v1/upload

                //1. Do the select query
                $sql = "SELECT t1.[Name], t1.contactNo, t1.Amount, t1.policyNo, t1.requisitionNo, t1.[type], 
                            t1.Bank, t1.BankBranch, t1.AccountNo, t1.idDescription, t1.idNumber 
                            FROM gltransmittalinfo t1 
                            INNER JOIN paymentmethodsinfo t2 ON t1.paymentMode = t2.Paymethod 
                            INNER JOIN glforequisition t3 ON t1.Requisition = t3.Requistion_no 
                            INNER JOIN glpayment t4 ON t1.Payment = t4.idd 
                            INNER JOIN glapcodes t5 ON t1.paymentType = t5.apCode 
                            INNER JOIN .glrequisitionstatus t6 ON t3.[Status] = t6.req_status_code 
                            WHERE t1.isExtracted = 0 AND (t5.isClaims = 1 OR t5.isPolicyLoan = 1) 
                            AND (t2.isMobileMoney = 1 OR t2.isEFT = 1) AND t6.PVGenerated = 1 
                            AND t1.Amount > 0 AND t4.isReversed = 0 AND t4.isReversal = 0";
                $DBTransactions = DbHelper::getTableRawData($sql);

                    //loop to create the transaction data properly
                    /*{
                        "amount": 3600,
                        "destination_institution": "MTN",
                        "destination_account": 34525252,
                        "destination_type": "WALLET",
                        "contact_number": "260979149115",
                        "country": "GH",
                        "narration": "CBVs OTP Training",
                        "recipient_name": "Alberta Test"
                    }*/
                $Transactions = array();
                for($i=0;$i<sizeof($DBTransactions);$i++){
                        //build the transactions properly
                        $Transactions[$i]['amount'] = $DBTransactions[$i]->Amount;
                        $Transactions[$i]['destination_institution'] = $DBTransactions[$i]->Bank;
                        $Transactions[$i]['destination_account'] = $DBTransactions[$i]->AccountNo;
                        $Transactions[$i]['destination_type'] = "BANK";
                        $Transactions[$i]['contact_number'] = $DBTransactions[$i]->contactNo;
                        $Transactions[$i]['country'] = "GH";
                        $Transactions[$i]['narration'] = "Claim Payment";
                        $Transactions[$i]['recipient_name'] = $DBTransactions[$i]->Name;
                }
               

                $uploadData = array(
                        "when_to_process" => "ON_APPROVAL",
                        "is_recurring" => "FALSE",
                        "is_scheduled" => "FALSE",
                        "validate_destination_account" => "TRUE",
                        "schedule_date" => null,
                        "schedule_time" => null,
                        "recurring_time" => null,
                        "recurring_freq" => null,
                        "merchant" => "655b28d809bcbe19c8ae5123",
                        "author" => "655b28d809bcbe19c6ae5122",
                        "transactions" => $Transactions
                );

              

                //3. update the nsano_status table....
                $http = new \GuzzleHttp\Client(['verify' => false]);
                $headers = [];
                $upload_url = 'api/v1/upload';
                $uploadRequest = $http->post($upload_url, 
                    [
                        'headers' => $headers,
                        'form_params' => $uploadData
                    ]
                );

                //4. check the uploadRequest & if successfully uploaded, then log it
                if ($uploadRequest->getStatusCode() === 200) {
                    $uploadRequest = $uploadRequest->getBody()->getContents();
                    $responsedata = json_decode($uploadRequest, true);
                    if($responsedata['code'] == "00"){
                        $NsanoSerial = DbHelper::getColumnValue('CompanyInfo', 'id', 1, 'NsanoSerial');
                        $UploadSerial = str_pad($NsanoSerial, 6, 0, STR_PAD_LEFT);
                        $this->smartlife_db->table('CompanyInfo')
                                ->where('id', 1)
                                ->update(['NsanoSerial' => $NsanoSerial+1]);
                        //5. log it
                        $response_data = array(
                            "UploadId" => $UploadSerial,
                            "ProcessingStatus" => "PENDING",
                            "CreatedOn" => Carbon::now()
                        );
                        $response_id = $this->smartlife_db->table('nsano_status')->insertGetId($response_data);

                        //6. update the transmital table with uploadserial
                        //iterate through the selected records...
                        for($i=0;sizeof($DBTransactions);$i++){
                            //Do an update query
                            $this->smartlife_db->table('gltransmittalinfo')
                                ->where('idd', $DBTransactions[$i]->idd)
                                ->update(['UploadSerial' => $UploadSerial]);
                        }
                    }
                }


                $res = array(
                    'success' => true,
                    'message' => "Successfully Uploaded"
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

    //2. Add the Upload Status Endpoint
    public function NsanoUploadStatus(Request $request)
    {
        try {

            $UploadSerial = $request->input('UploadSerial');
            //1. Pass the UploadId 
            $url_path = "/api/v1/upload/.$UploadSerial./status";
            $http = new \GuzzleHttp\Client(['verify' => false]);
            $statusRequest = $http->get($url_path);

            if ($statusRequest->getStatusCode() === 200) {
                $statusRequest = $statusRequest->getBody()->getContents();
                $responsedata = json_decode($statusRequest, true);
                if($responsedata['code'] == "00"){
                    $data = json_decode($responsedata['data'], true);
                    $update_data = array(
                        "ProcessingStatus" => $data["processing_status"],
                        "ApprovalStatus" => $data["approval_status"],
                        "ProcessedTransactions" => $data["processed_transactions"],
                        "TotalTransactions" => $data["total_transactions"],
                        "UpdatedOn" => Carbon::now()
                    );
                    $this->smartlife_db->table('nsano_status')
                            ->where('UploadId', $UploadSerial)
                            ->update($update_data);
                }
            }



            //health questionnaire
            $res = array(
                'success' => true,
                'message' => "Upload Status Successfully updated"
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

    //3. Add the Transaction Status Endpoint
    public function NsanoTransactionStatus(Request $request)
    {
        try {

            //transaction status
            $UploadSerial = $request->input('UploadSerial');
            //1. query transactions from gltransmittal where UploadSerial = UploadSerial
            $sql = "SELECT t1.[Name], t1.contactNo, t1.Amount, t1.policyNo, t1.requisitionNo, t1.[type], 
                            t1.Bank, t1.BankBranch, t1.AccountNo, t1.idDescription, t1.idNumber 
                            FROM gltransmittalinfo t1 
                            INNER JOIN paymentmethodsinfo t2 ON t1.paymentMode = t2.Paymethod 
                            INNER JOIN glforequisition t3 ON t1.Requisition = t3.Requistion_no 
                            INNER JOIN glpayment t4 ON t1.Payment = t4.idd 
                            INNER JOIN glapcodes t5 ON t1.paymentType = t5.apCode 
                            INNER JOIN .glrequisitionstatus t6 ON t3.[Status] = t6.req_status_code 
                            WHERE t1.UploadSerial = '$UploadSerial' ";
            $DBTransactions = DbHelper::getTableRawData($sql);

            //2. Iterate through each querying the db the status of each transaction while inserting into 
            //nsano_response...
            for($i=0;$i<sizeof($DBTransactions);$i++){
                $transactionId = $DBTransactions[$i]->idd;
                $merchantId = $UploadSerial;
                $url_path = "/api/v1/transaction/.$transactionId./.$merchantId./status";
                $http = new \GuzzleHttp\Client(['verify' => false]);
                $statusRequest = $http->get($url_path);
    
                if ($statusRequest->getStatusCode() === 200) {
                    $statusRequest = $statusRequest->getBody()->getContents();
                    $responsedata = json_decode($statusRequest, true);
                    if($responsedata['code'] == "00"){
                        $data = json_decode($responsedata['data'], true);
                        $update_data = array(
                            "TransactionId" => $data["transactionId"],
                            "Status" => $data["status"],
                            "Amount" => $data["amount"],
                            "Account" => $data["destination_account"],
                            "ModifiedAt" => $data["modified_at"],
                            "CreatedOn" => Carbon::now()
                        );

                        $qry = $this->smartlife_db->table('nsano_response as p')
                            ->select('p.id')
                            ->where(array('p.TransactionId' => $data["transactionId"]));
                        $rslt = $qry->first();
                        if(isset($rslt)){
                            //update...
                            $this->smartlife_db->table('nsano_response')
                            ->where('UploadId', $UploadSerial)
                            ->update($update_data);
                        }else{
                            //insert...
                            $response_id = $this->smartlife_db->table('nsano_response')->insertGetId($update_data);
                        }
                    }
                }
            }

   
            $res = array(
                'success' => true,
                'message' => "Transaction Status Successfully Updated"
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
