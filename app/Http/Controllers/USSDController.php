<?php

namespace App\Http\Controllers;

use App\Helpers\DbHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class USSDController extends Controller
{
    //
    public function getProposalDetails(Request $request)
    {
        try {
            $proposal_no = $request->input('proposal_no');

            $query = $this->smartlife_db->table('MicroProposalInfo AS m')
                ->select(
                    //'m.ProposalNumber AS policy_no',
                    'c.name AS CLIENT_NAME',
                    'm.Client AS CLIENT_NO',
                    'c.mobile AS MOBILE',
                    'p.description AS PLAN_NAME',
                    'm.SumAssured AS SUM_ASSURED',
                    'm.BasicPremium AS PREMIUM',
                    'pt.decription AS PAYMENT_METHOD',
                    'pm.description AS PAY_MODE',
                    'c.id_type AS ID_TYPE',
                    'c.IdNumber AS ID_NUMBER',
                    's.description AS STATUS'
                )
                ->join('clientinfo AS c', 'm.Client', '=', 'c.client_number')
                ->join('planinfo AS p', 'm.Plan', '=', 'p.plan_code')
                ->join('payment_type AS pt', 'm.PayMethod', '=', 'pt.payment_mode')
                ->join('paymentmodeinfo AS pm', 'm.PayMode', '=', 'pm.id')
                ->join('statuscodeinfo AS s', 'm.Status', '=', 's.status_code')
                ->where('m.ProposalNumber', $proposal_no);

            $results = $query->first();

            if ($results === null) {
                // Policy number not found
                $res = array(
                    'success' => false,
                    'message' => 'Proposal number not found.'
                );
            } else {
                $res = array(
                    'success' => true,
                    'proposal' => $results
                );
            }

        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => 'An error occurred while retrieving policy details: ' . $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => 'A throwable error occurred: ' . $throwable->getMessage()
            );
        }

        return $res;
    }


    public function getPolicyDetails(Request $request)
    {
        try {


            $policy_no = $request->input('policy_no');
            $query = $this->smartlife_db->table('MicroPolicyInfo AS mp')
                ->select(
                    'mp.PolicyTerm AS TERM',
                    's.description AS STATUS',
                    DB::raw("CONCAT('Ghc ', mp.Received) AS PREMIUM_PAID"),
                    DB::raw("CAST(mp.EffectiveDate AS DATE) AS START"),
                    DB::raw("CAST(DATEADD(YEAR, mp.PolicyTerm, mp.EffectiveDate) AS DATE) AS EXPIRY")
                )
                ->join('statuscodeinfo AS s', 's.status_code', '=', 'mp.Status')
                ->where('mp.PolicyNumber', $policy_no);

            $results = $query->first();



            if ($results === null) {
                // Policy number not found
                $res = array(
                    'success' => false,
                    'message' => 'Policy number not found.'
                );
            } else {
                $res = array(
                    'success' => true,
                    'policy' => $results
                );
            }

        } catch (\Exception $exception) {
            $res = array(
                'success' => false,
                'message' => 'An error occurred while retrieving policy details: ' . $exception->getMessage()
            );
        } catch (\Throwable $throwable) {
            $res = array(
                'success' => false,
                'message' => 'A throwable error occurred: ' . $throwable->getMessage()
            );
        }

        return $res;
    }

    public function updateConsortium(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                // $data = json_encode($request->input('Data'));
                // $data = json_decode($data);
                //
                //$agent_name = "";//DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'name');
                if ($request->has('Data')) {
                    $dataPayload = '{' . $request->input('Data') . '}';
                    $data = json_decode($dataPayload);

                    if ($data === null) {
                        throw new \Exception("Invalid request. Unable to decode 'Data' input.");
                    }
                } else {
                    $data = (object) array(
                        'Policy_no' => $request->input('Policy_no'),
                        'Amount' => $request->input('Amount'),
                        'TransactionId' => $request->input('TransactionId'),
                        'customer_mobile_no' => $request->input('customer_mobile_no'),

                    );
                }

                if (!property_exists($data, 'Policy_no') || !property_exists($data, 'Amount') || !property_exists($data, 'TransactionId') || !property_exists($data, 'customer_mobile_no')) {
                    throw new \Exception(json_encode(['error' => 'Invalid request. Missing required properties in Data payload.']));
                }

                $phoneRegex = '/^(\+\d{1,3})?\d{10}$|^(\+\d{1,3})?\d{12}$/';

                if (!preg_match($phoneRegex, $data->customer_mobile_no)) {
                    throw new \Exception(json_encode(['error' => 'Invalid phone number format in customer_mobile_no property.']));
                }

                $table_data = array(
                    'Payment_Type' => 6,
                    'Policy_no' => $data->Policy_no,
                    'ResponseCode' => "0000",
                    'CustomerMobileNumber' => $data->customer_mobile_no,
                    'Description' => $data->Description ?? null,
                    'TransactionId' => $data->TransactionId ?? null,
                    'ClientReference' => $data->Policy_no,
                    'Charges' => $data->Charges ?? null,
                    'AmountAfterCharges' => $data->Amount,
                    'AmountCharged' => $data->AmountCharged ?? null,
                    'Amount' => $data->Amount,
                    'Gateway' => 2,
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


    public function setAllBeneficiaries(Request $request)
    {
        try {
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $res = array();
                $where_arr = array();
                //just pass the proposal_no or policy_no
                $policy_no = $request->input('policy_no');
                $proposal_no = $request->input('policy_no');
                //$policy_no = $request->input('policy_no');
                $proposal_id = null;
                $policy_id = null;
                if (isset($policy_no)) {
                    //$policy_id = $this->getIDFromName('smartlife_db', 'polinfo', 'id', 'policy_no', $policy_no);
                    $proposal_id = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $proposal_no, 'Id');
                    $policy_id = DbHelper::getColumnValue('MicroPolicyInfo', 'ProposalNumber', $proposal_id, 'Id');
                    if ($proposal_id == null) {
                        $res = array(
                            'success' => false,
                            'message' => 'Enter a valid Policy no or proposal not appraised. Kindly pay first'
                        );
                        return response()->json($res);
                    }
                    $where_arr = array(
                        'Proposal' => $proposal_id
                    );
                } else {
                    $where_arr = array(
                        'Proposal' => $proposal_id
                    );
                }


                $res = array();

                //insert - Relationship
                $benef_array = $request->input('benef_data');
                $percentage_allocated = 0;
                if (isset($benef_array)) {
                    for ($i = 0; $i < sizeof($benef_array); $i++) {
                        $percentage_allocated += $benef_array[$i]['AllocationPercentage'];
                        $benef_array[$i]['Proposal'] = $proposal_id;
                        $benef_array[$i]['Policy'] = $policy_id;
                        $benef_array[$i]['created_on'] = date("Y-m-d h:i:s");
                        //$benef_array[$i]['Names'] = $benef_array[$i]['OtherNames'] . ' ' . $benef_array[$i]['Surname'];
                        //$benef_array[$i]['OtherNames'] = $benef_array[$i]['OtherNames'] . ' ' . $benef_array[$i]['Surname'];
                    }
                    if ($percentage_allocated == 100) {
                        $this->smartlife_db->table('MicroBeneficiaryInfo')->where($where_arr)->delete();
                        $this->smartlife_db->table('MicroBeneficiaryInfo')->insert($benef_array);
                    } else {
                        $res = array(
                            'success' => true,
                            'message' => 'Percentage allocated is: ' . $percentage_allocated . '. Ensure is 100'
                        );
                        return response()->json($res);
                    }
                }

                $res = array(
                    'success' => true,
                    'message' => 'Beneficiaries Successfully set/updated'
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
        return response()->json($res);
    }

    //getAllBeneficiaries
    public function getAllBeneficiaries(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $proposal_id = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $policy_no, 'Id');
            

            $sql = "SELECT p.Names,p.AllocationPercentage,p.BirthDate,
                p.Mobile,p.Relationship
                FROM MicroBeneficiaryInfo p WHERE p.Proposal=$proposal_id";
            

            $Beneficiaries = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Beneficiaries' => $Beneficiaries
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

    public function makeAClaim(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $policy_id = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
            if(!isset($policy_id)){
                return array(
                    'success' => false,
                    'message' => "Policy not found. Wait for the proposal to be appraised"
                );
            }
            
            $claim_type = $request->input('claim_type');

            $table_data = array(
                'claim_type' => $claim_type,
                'policy_id' => $policy_id,
                'status' => 'OPEN',
                'created_on' => Carbon::now()
            );

            //insert into 
            $record_id = $this->smartlife_db->table('UssdEClaims')->insertGetId($table_data);
            //insert into 
            $table_data = array(
                'claim_type' => $claim_type,
                'MicroPolicy' => $policy_id,
                'IsForMicro' => 1,
                'created_on' => Carbon::now(),
                'IsFromUSSD'=> 1,
                'RequestDate' => Carbon::now(),
                'statuscode' => 13,
                'HasBeenPicked' => 0,
                'mobile_id' => ''
            );
            $eClaimsId = $this->smartlife_db->table('eClaimsEntries')->insertGetId($table_data);

            //health questionnaire
            $res = array(
                'success' => true,
                'message' => "Claim Submitted Successfully"
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

    public function getAClaim(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $policy_id = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
            

            $sql = "SELECT d.Description AS claim_type, p.status FROM UssdEclaims p 
                LEFT JOIN claims_types d ON d.claim_type = p.claim_type
                WHERE p.policy_id=$policy_id";
            

            $Beneficiaries = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Beneficiaries' => $Beneficiaries
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