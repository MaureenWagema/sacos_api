<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\DbHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\Input;

class collectionsController extends Controller
{
    //TODO - 1. Sync clients and polices of the agent
    public function getClientnPolicies(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            // put in a transaction the whole process of syncing data...
            $sql = "SELECT * FROM MicroClientInfo p INNER JOIN MicroProposalInfo d ON p.Id=d.Client WHERE d.Agent='$agent_id'";
            $Clients = DbHelper::getTableRawData($sql);

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $Policies = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'agent_no' => $agent_no,
                'Clients' => $Clients,
                'Policies' => $Policies
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

    /*public function getClientnPolicies(Request $request)
    {
        try{
            $res=array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'id');
            // put in a transaction the whole process of syncing data...
            $sql = "SELECT * FROM MicroClientInfo p INNER JOIN MicroProposalInfo d ON p.Id=d.Client WHERE d.Agent='$agent_id'";
            $Clients = DbHelper::getTableRawData($sql);

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $Policies = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'agent_no' => $agent_no,
                'Clients' => $Clients,
                'Policies' => $Policies
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        }catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }*/

    function getFirstNumber($inputString) {
        // Check if the string contains a comma
        if (strpos($inputString, ',') !== false) {
            // Explode the string using the comma as a delimiter
            $parts = explode(',', $inputString);
            
            // Return the first part of the array
            return trim($parts[0]);
        }
    
        // If no comma is found, return the original string
        return trim($inputString);
    }
    
    //AND p.sms_attachment IS NULL
    public function sendNewBusinessSMS(Request $request)
    {
        try {
            $sql = "SELECT p.ID,p.proposal_no,p.date_synced,p.mobile,p.agent_code,d.name,d.mobile AS agent_mobile,d.BusinessChannel FROM mob_prop_info p 
                INNER JOIN agents_info d ON d.id=p.agent_code
                WHERE p.ClientSignature IS NULL AND p.date_synced BETWEEN '2023-10-01' AND '2023-11-30' 
                AND d.BusinessChannel <> 5 AND p.plan_code <> 10 AND p.mobile_id IS NULL AND d.mobile <> '' 
                AND p.sms_attachment<>5";
            $AgentsEmploymentType = DbHelper::getTableRawData($sql);
            $sent_i = 0;

            if(sizeof($AgentsEmploymentType) > 0){
                for($i=0;$i<sizeof($AgentsEmploymentType);$i++){
                    $sent_i++;
                    $proposal_no = $AgentsEmploymentType[$i]->proposal_no;
                    $client_mobile_no = $AgentsEmploymentType[$i]->mobile;
                    $mobile_no = $this->getFirstNumber($AgentsEmploymentType[$i]->agent_mobile);
                    
                    $ID = $AgentsEmploymentType[$i]->ID;
        
        
                    $msg = 'Kindly Attach photo, id and signature for '.$proposal_no.' client contact '.$client_mobile_no.' via mproposal before 4th Dec 2023';
                    
                    if (substr($mobile_no, 0, 1) == '0') {
                        $mobile_no = "233" . ltrim($mobile_no, '0');
                    }
        
                    $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;
                    //exit();
                    $client = new \GuzzleHttp\Client;
                    $smsRequest = $client->get($url_path);
    
                    
                    
                    $this->smartlife_db->table('mob_prop_info')
                    ->where(
                        array(
                            "ID" => $ID
                        ))->update(array('sms_attachment' => 1));
                }
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'sent' => $sent_i
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

    public function getAgentPrompts(Request $request)
    {
        try {
            $agent_no = $request->input('agent_no');
            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            $sql="SELECT m.PaymentDate,m.Reference,m.Received,t2.PolicyNumber,t3.name 
                FROM MicroPremiumTransactions m 
                INNER JOIN MicroPolicyInfo t2 ON t2.Id=m.Policy
                INNER JOIN clientinfo t3 ON t3.client_number=t2.[Client]
                WHERE m.PaymentStatus = 'P' AND m.Received > 0 AND m.IsPremiumTransfer = 0 
                AND m.IsManual = 0 AND CAST(m.PaymentDate AS  Date) BETWEEN '$date_from' AND '$date_to' 
                AND m.Agent = $agentId";

            $Premiums = DbHelper::getTableRawData($sql);
            //health questionnaire
            $res = array(
                'success' => true,
                'Premiums' => $Premiums
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


    public function sendOTP(Request $request)
    {
        try {
            $msg = $request->input('msg');
            $mobile_no = $request->input('mobile_no');
            $is_recruitment_link = $request->input('is_recruitment_link');
            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }

            $otp = mt_rand(1000, 9999);
            if($is_recruitment_link == "1"){
                $msg = $msg;
            }else{
                $msg .= "\nKindly provide security code: " . $otp . " to the GLICO Agent";
            }
            
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);


            //health questionnaire
            $res = array(
                'success' => true,
                'otp' => $otp
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
    //2. send otp
    public function sendOTPs(Request $request)
    {
        try {
            //TODO
            //push the amount only client_no 
            $res = array();

            $agent_no = $request->input('agent_no');
            $policy_no = $request->input('policy_no');
            $client_no = $request->input('client_no');
            $amount = $request->input('amount');
            //$market_code = $request->input('market_code');
            //$agent_id = DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'id');
            $agent_name = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'name');
            $mobile_no = "233204194298"; //"233244790337";//DbHelper::getColumnValue('MicroClientInfo', 'ClientNumber',$client_no,'Mobile');

            //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glifelife01&password=Glico@2021!&sender=Glicolife&SMSText=".$message."&GSM=".$mobileno;
            $otp = mt_rand(1000, 9999);
            $msg = "We GLICO acknowledge " . $amount . " GHC collected for policy: " . $policy_no . " Kindly provide security code: " . $otp . " to Agent: " . $agent_name;
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);


            //health questionnaire
            $res = array(
                'success' => true,
                'otp' => $otp
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
    //3. save the amount in the table
    public function receiveOTP(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $agent_no = $request->input('agent_no');
                $policy_no = $request->input('policy_no');
                $client_no = $request->input('client_no');
                $amount = $request->input('amount');
                $market_code = $request->input('market_code');
                //$payment_date = $request->input('payment_date');
                $payment_date = $request->input('payment_date');
                $payment_type = $request->input('payment_type');

                $otp = $request->input('otp');
                $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
                $agent_name = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'name');

                $table_data = array(
                    'Agent_no' => $agent_id,
                    'Policy_no' => $policy_no,
                    'Client_no' => $client_no,
                    'Amount' => $amount,
                    'Payment_date' => date($payment_date),
                    'Payment_Type' => $payment_type,
                    'Market_code' => $market_code,
                    'OTP' => $otp,
                    'IsMicro' => 1,
                    'created_on' => Carbon::now()
                );

                //insert into 
                $record_id = $this->smartlife_db->table('collection_payments')->insertGetId($table_data);

                //health questionnaire
                $res = array(
                    'success' => true,
                    'agent_no' => $agent_no,
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


    //4. handle remittance -baas
    public function Remit(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $total_amount = 0;
            $transactions = array();
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            // put in a transaction the whole process of syncing data...
            $sql = "SELECT p.Payment_date,p.Amount,p.Policy_no FROM collection_payments p WHERE p.Payment_type=1 AND p.Batch IS NULL AND p.Agent_no='$agent_id' ORDER BY p.Payment_date ASC";
            $transactions = DbHelper::getTableRawData($sql);

            $sql = "SELECT SUM(p.Amount) AS total_amount FROM collection_payments p WHERE p.Payment_type=1 AND p.Batch IS NULL AND p.Agent_no='$agent_id'";
            $result_amount = DbHelper::getTableRawData($sql);
            if (sizeof($result_amount) > 0) {
                $total_amount = $result_amount[0]->total_amount;
            }

            $batch = null;

            $sql = "SELECT p.CollectionSerial FROM agents_info p WHERE p.id=15";
            $result_serial = DbHelper::getTableRawData($sql);
            if (sizeof($result_serial) > 0) {
                if ($result_serial[0]->CollectionSerial == null) {
                    $batch = $agent_no . '-1';
                } else {
                    $batch = $agent_no . '-' . $result_serial[0]->CollectionSerial;
                }
            }

            //
            $res = array(
                'success' => true,
                'transactions' => $transactions,
                'total_amount' => $total_amount,
                'batch' => $batch
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

    public function updateRemit(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $agent_no = $request->input('agent_no');
                $total_amount = 0;
                $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
                // put in a transaction the whole process of syncing data...
                $sql = "SELECT p.Payment_date,p.Amount,p.Policy_no FROM collection_payments p WHERE p.Payment_type=1 AND p.Batch IS NULL AND p.Agent_no='$agent_id' ORDER BY p.Payment_date ASC";
                $transactions = DbHelper::getTableRawData($sql);

                $sql = "SELECT SUM(p.Amount) AS total_amount FROM collection_payments p WHERE p.Payment_type=1 AND p.Batch IS NULL AND p.Agent_no='$agent_id'";
                $result_amount = DbHelper::getTableRawData($sql);
                if (sizeof($result_amount) > 0) {
                    $total_amount = $result_amount[0]->total_amount;
                }

                $batch = null;
                $serial = 0;

                $sql = "SELECT p.CollectionSerial FROM agents_info p WHERE p.id=15";
                $result_serial = DbHelper::getTableRawData($sql);
                if (sizeof($result_serial) > 0) {
                    if ($result_serial[0]->CollectionSerial == null) {
                        $batch = $agent_no . '-1';
                        $serial = 1;
                    } else {
                        $batch = $agent_no . '-' . $result_serial[0]->CollectionSerial;
                        $serial = $result_serial[0]->CollectionSerial;
                    }
                }

                //update collections_payments and create the Batch record update the collectionSerial in agents info
                //insert the batch
                //created_on,BatchNumber,PaymentDate,Agent,ExpectedAmount
                $table_data = array(
                    'created_on' => Carbon::now(),
                    'BatchNumber' => $batch,
                    'PaymentDate' => Carbon::now(),
                    'Agent' => $agent_id,
                    'ExpectedAmount' => $total_amount
                );
                $batch_id = $this->smartlife_db->table('BatchAllocation')->insertGetId($table_data);

                //update Agents info serial
                $this->smartlife_db->table('agents_info')
                    ->where(
                        array(
                            "id" => $agent_id
                        )
                    )
                    ->update(array("CollectionSerial" => $serial + 1));

                //update the batch_id in that agents records
                $this->smartlife_db->table('collection_payments')
                    ->where(
                        array(
                            "agent_no" => $agent_id,
                            "Batch" => null
                        )
                    )
                    ->update(array("Batch" => $batch_id));


                $res = array(
                    'success' => true,
                    'batch' => $batch
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

    public function updateHubtel(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                // $data = json_encode($request->input('Data'));
                // $data = json_decode($data);
                //
                //$agent_name = "";//DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'name');
                // if ($request->has('Data')) {
                //     $dataPayload = '{' . $request->input('Data') . '}';
                //     $data = json_decode($dataPayload);

                //     if ($data === null) {
                //         throw new \Exception("Invalid request. Unable to decode 'Data' input.");
                //     }
                // } else {
                //     $data = (object) array(
                //         'Policy_no' => $request->input('Policy_no'),
                //         'Amount' => $request->input('Amount'),
                //         //'Description' => $request->inpu('Description')
                //     );
                // }

                // if (!property_exists($data, 'Policy_no') || !property_exists($data, 'Amount')) {
                //     throw new \Exception("Invalid request. Missing required properties in 'Data' payload.");
                // }

                // Generate a unique TransactionId using the Str class
                //$uniqueTransactionId = Str::uuid()->toString();

                
                // Generate a unique TransactionId using the Str class
                //$uniqueTransactionId = Str::uuid()->toString();
                // $table_data = array(
                //     'Payment_Type' => 6,
                //     'Policy_no' => $data->Policy_no,
                //     'ResponseCode' => $request->input('ResponseCode'),
                //     'Description' => $data->Description ?? null,
                //     'TransactionId' => $data->TransactionId ?? null,
                //     // Use the unique TransactionId
                //     'ClientReference' => $data->ClientReference ?? null,
                //     'Charges' => $data->Charges ?? null,
                //     'AmountAfterCharges' => $data->AmountAfterCharges ?? null,
                //     'AmountCharged' => $data->AmountCharged ?? null,
                //     'Amount' => $data->Amount,
                //     'Gateway' => 1,
                //     'Payment_date' => Carbon::now(),
                //     'created_on' => Carbon::now()
                // );

                $data = json_encode($request->input('Data'));
                $data = json_decode($data);

                $table_data = array(
                    'Payment_Type' => 6,
                    'Policy_no' => $data->ClientReference,
                    'ResponseCode' => $request->input('ResponseCode'),
                    'Description' => $data->Description ?? null,
                    'TransactionId' => $data->TransactionId ?? null,
                    // Use the unique TransactionId
                    'ClientReference' => $data->ClientReference ?? null,
                    'Charges' => $data->Charges ?? null,
                    'AmountAfterCharges' => $data->AmountAfterCharges ?? null,
                    'AmountCharged' => $data->AmountCharged ?? null,
                    'Amount' => $data->AmountAfterCharges,
                    'Gateway' => 1,
                    'Payment_date' => Carbon::now(),
                    'created_on' => Carbon::now()
                );

                //insert into 
                $record_id = $this->smartlife_db->table('collection_payments')->insertGetId($table_data);

                //health questionnaire
                $res = array(
                    'success' => true,
                    'id' => $record_id,
                    'message' => 'Transaction inserted successfully',
                );
            }, 5);
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res, 400);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res, 500);
        }
        return response()->json($res);
    }

    public function getPaymentHistory(Request $request)
    {
        try {
            $policyNo = $request->input('policy_no');

            if (!$request->has('policy_no')) {
                $res = array(
                    'success' => false,
                    'message' => "Invalid request. Missing 'policy_no' parameter."
                );
                return response()->json($res);
            }

            $paymentHistory = $this->smartlife_db
                ->table('collection_payments')
                ->where('Policy_no', $policyNo)
                ->get();

            $res = array(
                'success' => true,
                'message' => 'Payment history retrieved successfully',
                'data' => $paymentHistory,
            );
        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res, 400);
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res, 500);
        }

        return response()->json($res);
    }


    public function deductionHubtel(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $data = json_encode($request->input('Data'));
                $data = json_decode($data);
                //
                //$agent_name = "";//DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'name');
                //'Gateway' => 1,
                $table_data = array(
                    'Payment_Type' => 6,
                    'Policy_no' => $data->Description,
                    'ResponseCode' => $request->input('ResponseCode'),
                    'OrderId' => $data->OrderId,
                    'Description' => $data->Description,
                    'RecurringInvoiceId' => $data->RecurringInvoiceId,
                    'TransactionId' => $data->TransactionId,
                    'ClientReference' => $data->ClientReference,
                    'ExternalTransactionId' => $data->ExternalTransactionId,
                    'OrderDate' => $data->OrderDate,
                    'InvoiceEndDate' => $data->InvoiceEndDate,
                    'CustomerMobileNumber' => $data->CustomerMobileNumber,
                    'Charges' => $data->Charges,
                    'AmountAfterCharges' => $data->AmountAfterCharges,
                    'AmountCharged' => $data->AmountCharged,
                    'Amount' => $data->AmountAfterCharges,
                    'InitialAmount' => $data->InitialAmount,
                    'RecurringAmount' => $data->RecurringAmount,
                    'Payment_date' => Carbon::now(),
                    'created_on' => Carbon::now(),
                    'Gateway' => 1
                );

                //insert into 
                $record_id = $this->smartlife_db->table('collection_payments')->insertGetId($table_data);

                //health questionnaire
                $res = array(
                    'success' => true,
                    'id' => $record_id,
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
}