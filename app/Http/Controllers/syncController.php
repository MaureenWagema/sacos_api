<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class syncController extends Controller
{
    //TODO .... generate policy no
    public function generate_policyno($plan_code, $agent_code)
    {
        $policy_no = null;
        //get the policy_serial
        $qry = $this->smartlife_db->table('planinfo as p')
            ->select('p.policy_serial', 'p.PlanOldName', 'p.PlanDesc')
            ->where(array('p.plan_code' => $plan_code));
        $results = $qry->first();
        //BusinessChannel
        $BusinessChannel = DbHelper::getColumnValue('agents_info', 'id', $agent_code, 'BusinessChannel');


        $policy_no = $results->PlanDesc . '-' . date("Y") . '-' . str_pad($results->policy_serial, 5, 0, STR_PAD_LEFT);

        //update policy serial...
        $policy_serial = $results->policy_serial;
        //update policy serial here
        $this->smartlife_db->table('planinfo')
            ->where(
                array(
                    "plan_code" => $plan_code
                )
            )->update(array('policy_serial' => $policy_serial + 1));

        return $policy_no;
    }

    public function LifeAnidaso(Request $request)
    {
        try {

            $total_premium = $request->input('total_premium');
            $PlanCode = 10; //$request->input('PlanCode');
            $plan_id = 10;
            $anb = $request->input('anb');
            $term = $request->input('term');
            //$gender = $request->input('gender'); 
            //$class_code = $request->input('class_code'); 
            $rider_array = array();

            $pol_fee = DbHelper::getColumnValue('planinfo', 'plan_code', $PlanCode, 'policy_fee');

            $sum_assured = 0;
            $inv_prem = 0;
            $transfer_charge = 0;
            $rider_prem = 0;
            $tmp_risk = 0;

            //TR...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='03' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '03'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $term_rider_rate = $results_prem->rate;
                    $tr_rate_basis = $results_prem->rate_basis;
                }

                $term_rider_sa = $r_rows[0]->SumAssured;
                $term_rider_prem = ($term_rider_sa * $term_rider_rate) / $tr_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "03",
                    'r_sa' => number_format((float) $term_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $term_rider_prem, 2, '.', ''),
                );
            }


            //AI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='01' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '01'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $ai_rider_rate = $results_prem->rate;
                    $ai_rate_basis = $results_prem->rate_basis;
                }

                $ai_rider_sa = $r_rows[0]->SumAssured;
                $ai_rider_prem = ($ai_rider_sa * $ai_rider_rate) / $ai_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "01",
                    'r_sa' => number_format((float) $ai_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $ai_rider_prem, 2, '.', ''),
                );
            }

            //HCI...
            $sql = "SELECT * FROM rider_premuim_rates WHERE (rider_code='02' AND PlanCode='$PlanCode' AND SumAssured IS NOT null AND Premium='$total_premium')";
            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                //$rider_rate_basis = DbHelper::getColumnValue('rider_info', 'rider_code','03','rate_basis');
                //$term_rider_sa = ($term_rider_prem / $rider_rate) * $rider_rate_basis;

                $qry = $this->smartlife_db->table('plan_rider_config as p')
                    ->select('*')
                    ->where(array('p.plan_code' => $plan_id, 'p.rider_code' => '02'));
                $results_prem = $qry->first();
                if ($results_prem) {
                    $hci_rider_rate = $results_prem->rate;
                    $hci_rate_basis = $results_prem->rate_basis;
                }

                $hci_rider_sa = $r_rows[0]->SumAssured;
                $hci_rider_prem = ($hci_rider_sa * $hci_rider_rate) / $hci_rate_basis;

                $rider_array[] = array(
                    'r_rider' => "02",
                    'r_sa' => number_format((float) $hci_rider_sa, 2, '.', ''),
                    'r_premium' => number_format((float) $hci_rider_prem, 2, '.', ''),
                );
            }


            $rider_prem = $term_rider_prem + $ai_rider_prem + $hci_rider_prem;
            $inv_prem = $total_premium - $rider_prem;
            $sum_assured = $term_rider_sa + $ai_rider_sa + $hci_rider_sa;

            $res_msg = "For GHc $total_premium benefits \nDeath (Policy Holder): Ghc$term_rider_sa \nAccident (Pol Holder): Ghc$ai_rider_sa \nHCI (Policy Holder): Ghc$hci_rider_sa";


            $res = array(
                'success' => true,
                'sum_assured' => number_format((float) $sum_assured, 2, '.', ''),
                'policy_fee' => number_format((float) $pol_fee, 2, '.', ''),
                'inv_prem' => number_format((float) $inv_prem, 2, '.', ''),
                'rider_prem' => number_format((float) $rider_prem, 2, '.', ''),
                'total_premium' => number_format((float) $total_premium, 2, '.', ''),
                //'transfer_charge' => number_format((float)$transfer_charge, 2, '.', ''),
                'riders' => $rider_array,
                'message' => 'Anidaso Premiums calculated Successfully!!',
                'res_msg' => $res_msg
            );
            return response()->json($res);
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

    //Handle the Validations
    public function validateProposal(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $dependants_arr = array();
            $dependants_arr = json_decode($request->input('dependants'));
            $beneficiaries_arr = array();
            $beneficiaries_arr = json_decode($request->input('beneficiaries'));

            //Validations
            $signature = $request->input('signature');
            $id_front = $request->file('id_front');
            $photo = $request->file('photo');
            $plan_code = $request->input('plan_id');
            if (!isset($plan_code)) {
                $plan_code = $request->input('plan_code');
            }

            //1. check signature...
            if (!isset($signature)) {
                return $res = array(
                    'success' => false,
                    'message' => 'Proposal not Submitted. Kindly Attach the Signature!!',
                );
            }

            //2. check id front...
            if (!isset($id_front)) {
                return $res = array(
                    'success' => false,
                    'message' => 'Proposal not Submitted. Kindly Attach the ID Front!!',
                );
            }

            //3. check client photo....
            if (!isset($photo)) {
                return $res = array(
                    'success' => false,
                    'message' => 'Proposal not Submitted. Kindly Attach the Client Photo!!',
                );
            }

            //4. if product has dependants... check the dependants
            $HasFuneralMembers = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'HasFuneralMembers');
            if ($HasFuneralMembers == "1" || $HasFuneralMembers == 1) {
                //check if dependants array is not set
                if (!isset($dependants_arr)) {
                    return $res = array(
                        'success' => false,
                        'message' => 'Proposal not Submitted. Kindly add dependants!!',
                    );
                } else {
                    if (sizeof($dependants_arr) == 0) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Kindly add dependants!!',
                        );
                    }
                }
            }
            //5. Always check for beneficiaries....
            if (!isset($beneficiaries_arr)) {
                return $res = array(
                    'success' => false,
                    'message' => 'Proposal not Submitted. Kindly add Beneficiaries!!',
                );
            } else {
                if (sizeof($beneficiaries_arr) == 0) {
                    return $res = array(
                        'success' => false,
                        'message' => 'Proposal not Submitted. Kindly add Beneficiaries!!',
                    );
                }
            }






            $res = array(
                'success' => false,
                'message' => 'Proposal Submited Successfully!!',
            );

            //}, 1);
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


    //
    public function synProposal(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $edwa_proposal_no = "";
                $country_code = $request->input('country_code');
                if (!isset($country_code)) {
                    $country_code = "001";
                }
                $pay_method_code = $request->input('pay_method_code');
                $staff_number = $request->input('employer_no');
                $record_id = $request->input('ID');
                $agent_code = $request->input('agent_code');
                $IsWebComplete = $request->input('IsWebComplete');
                $plan_code = $request->input('plan_id');
                if (!isset($plan_code)) {
                    $plan_code = $request->input('plan_code');
                }
                $term = $request->input('term');

                //////////validations///////////////////
                //exclude from the web and also exclude from micro
                if (!isset($IsWebComplete)) {
                    $dependants_arr = array();
                    $dependants_arr = json_decode($request->input('dependants'));
                    $beneficiaries_arr = array();
                    $beneficiaries_arr = json_decode($request->input('beneficiaries'));

                    $isReSubmit = $request->input('isReSubmit');
                    if (isset($isReSubmit) && ($isReSubmit == 1 || $isReSubmit == "1")) {
                        //we check if record exits then
                        $mobile = $request->input('mobile');
                        $plan_code = $request->input('plan_id');
                        if (!isset($plan_code)) {
                            $plan_code = $request->input('plan_code');
                        }
                        $sql = "SELECT p.ID FROM mob_prop_info p WHERE p.mobile='$mobile' AND p.plan_code='$plan_code'";
                        $polinfo = DbHelper::getTableRawData($sql);
                        if (isset($polinfo) && sizeof($polinfo) > 0) {
                            return $res = array(
                                'success' => false,
                                'message' => 'Proposal already exists therefore cannot be resubmitted!!'
                            );
                        }
                    }


                    //Validations
                    $signature = $request->input('signature');
                    $id_front = $request->file('id_front');
                    $photo = $request->file('photo');
                    $plan_code = $request->input('plan_id');
                    if (!isset($plan_code)) {
                        $plan_code = $request->input('plan_code');
                    }

                    //1. check signature...
                    if (!isset($signature)) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Kindly Attach the Signature!!',
                        );
                    }

                    //2. check id front...
                    if (!isset($id_front)) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Kindly Attach the ID Front!!',
                        );
                    }

                    //3. check client photo....
                    if (!isset($photo)) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Kindly Attach the Client Photo!!',
                        );
                    }

                    //4. if product has dependants... check the dependants
                    $HasFuneralMembers = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'HasFuneralMembers');
                    if ($HasFuneralMembers == "1" || $HasFuneralMembers == 1) {
                        //check if dependants array is not set
                        if (!isset($dependants_arr)) {
                            return $res = array(
                                'success' => false,
                                'message' => 'Proposal not Submitted. Kindly add dependants!!',
                            );
                        } else {
                            if (sizeof($dependants_arr) == 0) {
                                return $res = array(
                                    'success' => false,
                                    'message' => 'Proposal not Submitted. Kindly add dependants!!',
                                );
                            }
                        }
                    }
                    //5. Always check for beneficiaries....
                    if (!isset($beneficiaries_arr)) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Kindly add Beneficiaries!!',
                        );
                    } else {
                        if (sizeof($beneficiaries_arr) == 0) {
                            return $res = array(
                                'success' => false,
                                'message' => 'Proposal not Submitted. Kindly add Beneficiaries!!',
                            );
                        }
                    }
                    if (!isset($term) || $term == 0) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Term is Zero!!',
                        );
                    }
                    if (!isset($pay_method_code)) {
                        return $res = array(
                            'success' => false,
                            'message' => 'Proposal not Submitted. Payment Method is not set!!',
                        );
                    }
                    if ($pay_method_code == 2 || $pay_method_code == '2') {
                        if (!isset($staff_number)) {
                            return $res = array(
                                'success' => false,
                                'message' => 'Proposal not Submitted. Staff No is not set!!',
                            );
                        }
                    }
                }
                //////////end of validations///////////

                if (isset($agent_code)) {
                    $agent_code = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $request->input('agent_code'), 'id');
                } else {
                    $agent_code = DbHelper::getColumnValue('agents_info', 'IsDirectAgent', 1, 'id');
                }
                $agent_codeSecond = $request->input('agent_codeSecond');
                $agent_codeSecondId = null;
                if (isset($agent_codeSecond)) {
                    $agent_codeSecondId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_codeSecond, 'id');
                    $isActive = DbHelper::getColumnValue('agents_info', 'id', $agent_codeSecondId, 'IsActive');
                    if (!$isActive) {
                        $res = array(
                            'status' => false,
                            'msg' => 'Second agent is not active!!'
                        );
                        return response()->json($res);
                    }
                } else {
                    $agent_codeSecond = null;
                }

                $HasBeenPicked = $request->input('HasBeenPicked');
                if (!isset($HasBeenPicked)) {
                    $HasBeenPicked = 0;
                }

                //Handle the dates here
                $last_consult = $request->input('last_consult');
                if ($last_consult == "" || (isset($last_consult) && ($last_consult == "null" || $last_consult == "NaN-NaN-NaN"))) {
                    $last_consult = null;
                }
                $deduction_date = $request->input('deduction_date');
                if ($deduction_date == "" || (isset($deduction_date) && $deduction_date == "null" || $deduction_date == "NaN-NaN-NaN")) {
                    $deduction_date = null;
                }
                $start_drinking = $request->input('start_drinking');
                if ($start_drinking == "" || (isset($start_drinking) && ($start_drinking == "null" || $start_drinking == "NaN-NaN-NaN"))) {
                    $start_drinking = null;
                }
                $start_smoking = $request->input('start_smoking');
                if ($start_smoking || (isset($start_smoking) && ($start_smoking == "null" || $start_smoking == "NaN-NaN-NaN"))) {
                    $start_smoking = null;
                }

                $DateFrom = $request->input('DateFrom');
                if ($DateFrom == "" || (isset($DateFrom) && ($DateFrom == "null" || $DateFrom == "NaN-NaN-NaN"))) {
                    $DateFrom = null;
                }
                $DateTo = $request->input('DateTo');
                if ($DateTo == "" || (isset($DateTo) && ($DateTo == "null" || $DateTo == "NaN-NaN-NaN"))) {
                    $DateTo = null;
                }

                $plan_code = $request->input('plan_id');
                if (!isset($plan_code)) {
                    $plan_code = $request->input('plan_code');
                }

                $sum_assured = $request->input('sum_assured');
                //check if non_medical_limit in planinfo exceeds the sum_assured is_med_uw_req
                $is_med_uw_req = 0;
                $non_medical_limit = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'non_medical_limit');
                if (isset($non_medical_limit) && (float) $non_medical_limit > 0) {
                    if ($sum_assured > $non_medical_limit) {
                        $is_med_uw_req = 1;
                    }
                }

                $doc_delivery_mode = $request->input('doc_delivery_mode');
                if (!isset($doc_delivery_mode)) {
                    $doc_delivery_mode = 1;
                }

                $DependantPremium = $request->input('DependantPremium');
                if (!isset($DependantPremium)) {
                    $DependantPremium = 0;
                }

                $is_topup = $request->input('is_top_up');
                //if(!isset($is_topup) || $is_topup == false){
                if (isset($record_id) && (int)$record_id > 0) {
                    //do nothing...
                    $proposal_no = DbHelper::getColumnValue('mob_prop_info', 'ID', $record_id, 'proposal_no');
                } else {
                    $proposal_no = null;
                    if (isset($plan_code)) {
                        $proposal_no = $this->generate_policyno($plan_code, $agent_code);
                    }
                }

                //set total premium 
                $total_premium = $request->input('TotalPremium');
                if (!isset($total_premium)) {
                    $total_premium = $request->input('TotalPremium');
                    if (!isset($total_premium)) {
                        $total_premium = $request->input('modal_premium');
                    }
                }

                //check business channel and if its life then don't allow totalpremium of zero
                if ((float)$total_premium > 0  || $HasBeenPicked == 1) {
                    //do nothing....

                } else {
                    //
                    $res = array(
                        'status' => false,
                        'msg' => 'Proposal Not Submited!!. Total Premium cannot be Zero!!. Kindly go to Policy Details and do the right thing'
                    );
                    return response()->json($res);
                }

                $EntryCategory = null; //$request->input('EntryCategory');
                //if (!isset($EntryCategory)) {
                //$EntryCategory = 1;
                //}
                $annual_premium = $request->input('annual_premium');;
                if (!isset($annual_premium)) {
                    $annual_premium = 0;
                } //
                $IsPep = $request->input('IsPep');
                if (!isset($IsPep)) {
                    $IsPep = 0;
                } //DurationDays
                $DurationDays = $request->input('DurationDays');
                if (!isset($DurationDays)) {
                    $DurationDays = 0;
                }

                $prem_escalator = $request->input('bo_inc');
                if (!isset($prem_escalator) || $prem_escalator == 0) {
                    $prem_escalator = 1;
                }

                $escalator_rate = $request->input('percentage_increase');
                if (!isset($escalator_rate)) {
                    $default_rate = DbHelper::getColumnValue('PremiumIncrementPercentage', 'DefaultRate', 1, 'id');
                    $escalator_rate = $default_rate;
                }

                $employer_code = $request->input('employer_code');
                $telco = $request->input('telco');
                if (isset($telco)) {
                    $employer_code = $telco;
                }

                $topup_policyno = $request->input('topup_policyno');
                if (isset($topup_policyno)) {
                    $topup_policyno = DbHelper::getColumnValue('polinfo', 'policy_no', $topup_policyno, 'id');
                }

                $IsSavingsAccount = $request->input('account_type');
                $IsCurrentAccount = 0;
                if (isset($IsSavingsAccount)) {
                    if ((int) $IsSavingsAccount > 0) {
                        $IsSavingsAccount = 1;
                        $IsCurrentAccount = 0;
                    } else {
                        $IsSavingsAccount = 0;
                        $IsCurrentAccount = 1;
                    }
                }

                $id_type = $request->input('id_type');
                $term = $request->input('term');
                $anb = $request->input('anb');
                $policy_fee = $request->input('pol_fee');
                $inv_prem = $request->input('inv_premium');
                $rider_prem = $request->input('rider_premium');
                $basic_premium = $request->input('basic_premium');
                $riders_input = $request->input('riders');
                $modal_premium = $request->input('modal_premium');

                if ($pay_method_code == 3 || $pay_method_code == '3') {
                    $pay_method_code = '5';
                }
                $currency = $request->input('currency');
                $paymode_code = $request->input('paymode_code');
                $res_msg = '';

                $is_it_consortium = $request->input('is_it_consortium');
                if ($is_it_consortium == 1) {
                    //TODO...
                    //1. do the calculations and return the riders here..
                    //$jsonResponse = $this->LifeAnidaso($request);
                    //$jsonStartIndex = strpos($jsonResponse, '{');

                    // Extract the JSON data from the response
                    //$jsonData = substr($jsonResponse, $jsonStartIndex);

                    // Decode the JSON data into a PHP associative array
                    //$ITConsortiumRslt = json_decode($jsonData, true);

                    //$ITConsortiumRslt = $this->LifeAnidaso($request);
                    //print_r($ITConsortiumRslt);
                    //exit();
                    $plan_code = 51;
                    $term = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'Term');
                    $sum_assured = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'SumAssuredRate');
                    $policy_fee = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'policy_fee');
                    $basic_premium = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'rate_basis');
                    $total_premium = (int)$basic_premium + (int)$policy_fee;
                    $modal_premium = $total_premium;
                    //$inv_prem = $ITConsortiumRslt['inv_prem'];
                    //$rider_prem = $ITConsortiumRslt['rider_prem'];


                    //$riders_input = json_encode($ITConsortiumRslt['riders']);
                    //$res_msg = $ITConsortiumRslt['res_msg'];
                    $res_msg = "For GHc $total_premium benefits \nDeath (Policy Holder): Ghc$sum_assured \nTotal Permanent Disability (Pol Holder): Ghc7,000 and E-Health Services";

                    $EntryCategory = 4;
                    $pay_method_code = 6;
                    $currency = 1;
                    $paymode_code = 7;
                    $id_type = "ID";
                } else {
                    $is_it_consortium = 0;
                }

                if ($plan_code == "45" || $plan_code == "52") {
                    $paymode_code = DbHelper::getColumnValue('paymentmodeinfo', 'plan_code', $plan_code, 'id');
                    $maxMatAge = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'maxMatAge');
                    $term = (int)$maxMatAge - $anb;
                    $pay_method_code = 5;
                }

                $second_l_name = $request->input('second_l_name');

                //TODO - Get current underwriting status...
                //if current status is hold then make AllowMproposalEdit = 1
                $confirmed_otp_date = $request->input('confirmed_otp_date');
                if (
                    $confirmed_otp_date == "" ||
                    (isset($confirmed_otp_date) && ($confirmed_otp_date == "null" || $confirmed_otp_date == "NaN-NaN-NaN"))
                ) {
                    $confirmed_otp_date = null;
                } else {
                    $confirmed_otp_date = Carbon::parse($confirmed_otp_date)->format('Y-m-d H:i:s');
                }
                $confirmed_otp = $request->input('confirmed_otp');


                $table_data = array(
                    //'confirmed_otp_date' => $confirmed_otp_date,
                    //'confirmed_otp' => $confirmed_otp,
                    'mobile_id' => $request->input('mobile_id'),
                    'surname' => $request->input('surname'),
                    'other_name' => $request->input('other_name'),
                    'employer' => $employer_code,
                    'email' => $request->input('email'),
                    'mobile' => $request->input('mobile'),
                    'marital_status' => $request->input('marital_status_code'),
                    'gender' => $request->input('gender_code'),
                    'good_health' => (bool) $request->input('good_health'),
                    'health_condition' => $request->input('health_condition'),
                    'Country' => $country_code,

                    'DualCitiizenship' => $request->input('DualCitiizenship'),
                    'Country2' => $request->input('Country2'),
                    'GpsCode' => $request->input('GpsCode'),
                    'SRCNumber' => $request->input('SRCNumber'),

                    'city' => $request->input('city'),
                    'occupation' => $request->input('occupation_code'),
                    'Dob' => $request->input('dob'),
                    'anb' => $anb,
                    'home_town' => $request->input('home_town'),
                    'ExpiryDate' => $request->input('ExpiryDate'),

                    'SourceOfIncome' => $request->input('SourceOfIncome'),
                    'SourceOfIncome2' => $request->input('SourceOfIncome2'),

                    'TaxResidencyDeclared' => (bool)$request->input('TaxResidencyDeclared'),
                    'AllowInformationSharing' => $request->input('AllowInformationSharing') == 1,
                    'DoNotAllowAllowInformationSharing' => $request->input('AllowInformationSharing') == 0,

                    'emp_code' => $request->input('emp_code'),
                    'employee_noCode' => $request->input('employee_noCode'),
                    'employee_noDisplay' => $request->input('employee_noDisplay'),
                    'IncomeType' => $request->input('IncomeType'),

                    'pay_code' => $pay_method_code,
                    'bank_code' => $request->input('bank_code'),
                    'bank_account_no' => $request->input('bank_account_no'),
                    'BankaccountName' => $request->input('BankaccountName'),
                    'bank_branch' => $request->input('bank_branch'),
                    'life_assuarance' => (bool) $request->input('life_assuarance'),
                    'previousClaimCheck' => (bool) $request->input('previousClaimCheck'),
                    'existing_pol_no' => $request->input('existing_pol_no'),
                    'claim_pol_no' => $request->input('claim_pol_no'),

                    'term' => $term,
                    'employee_no' => $request->input('employer_no'),
                    'paymode_code' => $paymode_code,
                    'deduction_date' => $deduction_date,
                    'Prem_rate' => $request->input('Prem_rate'),

                    'inv_premium' => $inv_prem,
                    'basic_premium' => $basic_premium,
                    'modal_premium' => $modal_premium,
                    'rider_premium' => $rider_prem,
                    'annual_premium' => $annual_premium,
                    'Vat' => $request->input('Vat'),
                    'TotalPremium' => $total_premium,
                    'Sum_Assured' => $sum_assured,
                    'pol_fee' => $policy_fee,
                    'cepa' => $request->input('cepa'),
                    'tot_protection' => $request->input('tot_protection'),
                    'transfer_charge' => $request->input('transfer_charge'),

                    'second_l_name' => $request->input('second_l_name'),
                    'second_l_address' => $request->input('second_l_address'),
                    'second_gender_code' => $request->input('second_gender_code'),
                    'second_dob' => $request->input('second_dob'),
                    'second_age' => $request->input('second_age'),

                    'proposal_date' => Carbon::now(),
                    'postal_address' => $request->input('postal_address'),
                    'residential_address' => $request->input('residential_address'), //IsPep
                    'Doyouhavesecondaryincome' => (bool)$request->input('Doyouhavesecondaryincome'),
                    'secondary_income' => (bool)$request->input('secondary_income'),
                    'IsPep' => (bool)$IsPep,
                    'politicaly_affiliated_person' => $request->input('politicaly_affiliated_person'),

                    'Life_Premium' => $request->input('life_Premium'),


                    'Date_Saved' => Carbon::now(),
                    'date_synced' => Carbon::now(),
                    'proposal_no' => $proposal_no,

                    'plan_code' => $plan_code,
                    'EntryCategory' => $EntryCategory,
                    'PackageCode' => $request->input('InsuranceType'),
                    'agent_code' => $agent_code,
                    'agent_codeSecond' => $agent_codeSecondId,

                    //telco,momo_no
                    'momo_no' => $request->input('momo_no'),
                    'id_type' => $id_type,
                    'IdNumber' => $request->input('IdNumber'),
                    'title' => $request->input('title'),
                    'MobileSecondary' => $request->input('MobileSecondary'),

                    'GuarantorBank' => $request->input('GuarantorBank'),
                    'currency' => $currency,
                    'DateFrom' => $DateFrom,
                    'DateTo' => $DateTo,
                    'DurationDays' => $DurationDays,
                    'CostOfProperty' => $request->input('CostOfProperty'),

                    'ClaimDefaultPay_method' => $request->input('ClaimDefaultPay_method'),
                    'ClaimDefaultTelcoCompany' => $request->input('ClaimDefaultTelcoCompany'),
                    'ClaimDefaultMobileWallet' => $request->input('ClaimDefaultMobileWallet'),
                    'ClaimDefaultEFTBank_code' => $request->input('ClaimDefaultEFTBank_code'),
                    'ClaimDefaultEFTBankBranchCode' => $request->input('ClaimDefaultEFTBankBranchCode'),
                    'ClaimDefaultEFTBank_account' => $request->input('ClaimDefaultEFTBank_account'),
                    'ClaimDefaultEftBankaccountName' => $request->input('ClaimDefaultEftBankaccountName'),
                    'DependantPremium' => $DependantPremium,

                    'is_med_uw_req' => $is_med_uw_req,
                    'HasBeenPicked' => $HasBeenPicked,

                    'IsSavingsAccount' => $IsSavingsAccount,
                    'IsCurrentAccount' => $IsCurrentAccount,
                    'extra_premium' => $request->input('extra_premium'),
                    'IsWebComplete' => $IsWebComplete,

                    //Height,Weight,Systolic,diastolic,ChestMeasurement,PulsePressure,PulseRate, AbdominalGirth
                    'Height' => $request->input('Height'),
                    'Weight' => $request->input('Weight'),
                    'Systolic' => $request->input('Systolic'),
                    'diastolic' => $request->input('diastolic'),
                    'ChestMeasurement' => $request->input('ChestMeasurement'),
                    'PulsePressure' => $request->input('PulsePressure'),
                    'PulseRate' => $request->input('PulseRate'),
                    'AbdominalGirth' => $request->input('AbdominalGirth'),

                    'Relationship' => $request->input('Relationship'),
                    'RelatedProposal' => $request->input('RelatedProposal'),

                    'NatureOfBusiness' => $request->input('NatureOfBusiness'),
                    'BusinessAddress' => $request->input('BusinessAddress'),
                    'JobTitle' => $request->input('JobTitle'),
                    'ApproximateAnnualincome' => $request->input('ApproximateAnnualincome'),

                    'IslandDetails' => $request->input('IslandDetails'),
                    'RegionName' => $request->input('RegionName'),
                    'Branchdetails' => $request->input('Branchdetails'),
                );



                //do a search on the mobile_no, plan_code, total_premium
                $MobProposalsArr = array();
                $mobile_number = $request->input('mobile');
                if (!empty($total_premium) && !empty($mobile_number)) {
                    $MobProposalsArr = $this->smartlife_db->table('mob_prop_info')
                        ->select('ID', 'plan_code', 'proposal_no')
                        ->where('plan_code', $plan_code)
                        ->where('mobile', $mobile_number)
                        ->where('second_l_name', $second_l_name)
                        ->where('TotalPremium', $total_premium)
                        ->orderBy('ID', 'asc')
                        ->limit(1)
                        ->get();
                }

                if (isset($record_id) && $record_id > 0) {
                    //update
                    //echo "Updating Record ID: " . $record_id;
                    $this->smartlife_db->table('mob_prop_info')
                        ->where(
                            array(
                                "ID" => $record_id
                            )
                        )
                        ->update($table_data);
                } else {

                    if (sizeof($MobProposalsArr) > 0) {
                        $record_id = $MobProposalsArr[0]->ID;
                        $proposal_no = $MobProposalsArr[0]->proposal_no;
                        //update..
                        $this->smartlife_db->table('mob_prop_info')
                            ->where(
                                array(
                                    "ID" => $record_id
                                )
                            )
                            ->update($table_data);
                    } else {
                        //insert
                        $record_id = $this->smartlife_db->table('mob_prop_info')->insertGetId($table_data);
                    }
                }

                //lets save to table PEPDetails
                $reasons_for_exposure = $request->input('reasons_for_exposure');
                //data for reasons_for_exposure looks like this: [1,2,,3,4,5] 
                //delete then insert
                if (isset($reasons_for_exposure) && sizeof($reasons_for_exposure) > 0) {
                    $this->smartlife_db->table('PEPDetails')->where('prop_id', '=', $record_id)->delete();
                }
                if (isset($reasons_for_exposure) && sizeof($reasons_for_exposure) > 0) {
                    foreach ($reasons_for_exposure as $reason) {
                        $table_data = array(
                            'prop_id' => $record_id,
                            'ReasonsForExposure' => $reason,
                            'created_on' => Carbon::now()
                        );
                        $this->smartlife_db->table('PEPDetails')
                            ->insert($table_data);
                    }
                }

                //insert into the respective tables
                $rider_array = array();
                $rider_arr = json_decode($riders_input);
                if (isset($rider_arr)) {
                    $this->smartlife_db->table('mob_rider_info')->where('prop_id', '=', $record_id)->delete();
                    for ($i = 0; $i < sizeof($rider_arr); $i++) {
                        $rider_array[$i]['prop_id'] = $record_id;
                        $rider_array[$i]['rider'] = $rider_arr[$i]->r_rider;
                        $rider_array[$i]['sa'] = $rider_arr[$i]->r_sa;
                        $rider_array[$i]['premium'] = $rider_arr[$i]->r_premium;
                        //delete then insert
                        $rider_id = $this->smartlife_db->table('mob_rider_info')->insertGetId($rider_array[$i]);
                    }
                }


                //dependants
                $dependants_array = array();
                $dependants_arr = json_decode($request->input('dependants'));
                if (isset($dependants_arr)) {
                    $this->smartlife_db->table('mob_funeralmembers')->where('prop_id', '=', $record_id)->delete();
                    for ($i = 0; $i < sizeof($dependants_arr); $i++) {
                        $dependants_array[$i]['prop_id'] = $record_id;
                        $dependants_array[$i]['names'] = $dependants_arr[$i]->dp_fullname;
                        $dependants_array[$i]['date_of_birth'] = $dependants_arr[$i]->dp_dob;
                        if ($dependants_array[$i]['date_of_birth'] == "null") {
                            $dependants_array[$i]['date_of_birth'] = null;
                        }
                        $dependants_array[$i]['age'] = $dependants_arr[$i]->dp_anb ?? 0;
                        $dependants_array[$i]['sa'] = $dependants_arr[$i]->dp_sa ?? 0;
                        $dependants_array[$i]['premium'] = $dependants_arr[$i]->dp_premium ?? 0;
                        $dependants_array[$i]['Relationship'] = $dependants_arr[$i]->dp_relationship;
                        if (isset($dependants_arr[$i]->dp_uid)) {
                            $dependants_array[$i]['dp_uid'] = $dependants_arr[$i]->dp_uid;
                        }
                        //$dependants_array[$i]['class_code'] = $dependants_arr[$i]->dp_class_code;
                        //$dependants_array[$i]['bapackage'] = $dependants_arr[$i]->dp_bapackage;
                        //$dependants_array[$i]['Hci_sum'] = $dependants_arr[$i]->dp_hci_sum;
                        if (isset($dependants_arr[$i]->dp_bapackage) && !empty($dependants_arr[$i]->dp_bapackage)) {
                            $dependants_array[$i]['PackageCode'] = $dependants_arr[$i]->dp_bapackage;
                        }
                        if (isset($dependants_arr[$i]->dp_hci_sum)  && !empty($dependants_arr[$i]->dp_hci_sum)) {
                            $dependants_array[$i]['Hci_sum'] = $dependants_arr[$i]->dp_hci_sum;
                        }
                        $dependants_array_id = $this->smartlife_db->table('mob_funeralmembers')->insertGetId($dependants_array[$i]);
                    }
                }


                //beneficiaries
                $beneficiaries_array = array();
                //beneficiaries
                $beneficiaries_embb = $request->input('beneficiaries_embb');
                $beneficiaries_arr = $request->input('beneficiaries_embb'); //json_decode($request->input('beneficiaries'));
                if (isset($beneficiaries_embb)) {
                    $this->smartlife_db->table('mob_beneficiary_info')->where('prop_id', '=', $record_id)->delete();
                    for ($i = 0; $i < sizeof($beneficiaries_embb); $i++) {
                        $beneficiaries_array[$i]['prop_id'] = $record_id;
                        $beneficiaries_array[$i]['Names'] = $beneficiaries_embb[$i]['b_name'];
                        $beneficiaries_array[$i]['relationship'] = $beneficiaries_embb[$i]['b_relationship'];
                        $beneficiaries_array[$i]['birth_date'] = $beneficiaries_embb[$i]['b_dob'];
                        if ($beneficiaries_array[$i]['birth_date'] == "null") {
                            $beneficiaries_array[$i]['birth_date'] = null;
                        }
                        $beneficiaries_array[$i]['perc_alloc'] = $beneficiaries_embb[$i]['b_percentage_allocated'];

                        $beneficiaries_array[$i]['telephone'] = $beneficiaries_embb[$i]['b_mobile_no'];
                        if (empty($beneficiaries_array[$i]['relationship'])) {
                            $beneficiaries_array[$i]['relationship'] = null;
                        }

                        $beneficiaries_id = $this->smartlife_db->table('mob_beneficiary_info')->insertGetId($beneficiaries_array[$i]);
                    }
                }


                //family health
                $family_health_array = array();
                //$family_health_arr = json_decode($request->input('family_health'));
                $family_health_arr = $request->input('family_health');
                if (isset($family_health_arr)) {
                    $this->smartlife_db->table('mob_family_healthinfo')->where('prop_id', '=', $record_id)->delete();
                    for ($i = 0; $i < sizeof($family_health_arr); $i++) {
                        $family_health_array[$i]['prop_id'] = $record_id;
                        $family_health_array[$i]['Relationship'] = $family_health_arr[$i]['Relationship'];
                        $family_health_array[$i]['state'] = $family_health_arr[$i]['state'];
                        $family_health_array[$i]['age'] = $family_health_arr[$i]['age'] ?? null;
                        $family_health_array[$i]['state_health'] = $family_health_arr[$i]['state_health'];
                        $family_health_id = $this->smartlife_db->table('mob_family_healthinfo')->insertGetId($family_health_array[$i]);
                    }
                }

                //hazard_questions
                $hazard_questions_array = array();
                $hazard_questions_arr = $request->input('hazard_questions');
                if (isset($hazard_questions_arr)) {
                    $this->smartlife_db->table('Hazard_History')->where('prop_id', '=', $record_id)->delete();

                    for ($i = 0; $i < sizeof($hazard_questions_arr); $i++) {
                        $hazard_questions_array[$i]['prop_id'] = $record_id;
                        $hazard_questions_array[$i]['Question'] = $hazard_questions_arr[$i]['Question'];
                        $hazard_questions_array[$i]['IsYes'] = $hazard_questions_arr[$i]['IsYes'];
                        $hazard_questions_array[$i]['IsNo'] = $hazard_questions_arr[$i]['IsNo'];
                        $hazard_questions_array[$i]['created_on'] = Carbon::now();
                        $hazard_questions_id = $this->smartlife_db->table('Hazard_History')->insertGetId($hazard_questions_array[$i]);

                        //lets handle the subquestions here...

                        $sub_questions_arr = $hazard_questions_arr[$i]['SubQuestions'];
                        $this->smartlife_db->table('Hazard_HistoryDetails')->where('Question', '=', $hazard_questions_id)->delete();
                        if ($hazard_questions_array[$i]['IsYes'] == 1 || $hazard_questions_array[$i]['IsYes'] == "1") {
                            if (isset($sub_questions_arr)) {
                                for ($j = 0; $j < sizeof($sub_questions_arr); $j++) {
                                    $sub_questions_array[$j]['Question'] = $hazard_questions_id;
                                    $sub_questions_array[$j]['SubQuestion'] = $sub_questions_arr[$j]['SubQuestion'];
                                    $sub_questions_array[$j]['MoreDetails'] = $sub_questions_arr[$j]['MoreDetails'];
                                    $sub_questions_array[$j]['created_on'] = Carbon::now();
                                    $sub_questions_id = $this->smartlife_db->table('Hazard_HistoryDetails')->insertGetId($sub_questions_array[$j]);
                                }
                            }
                        }
                    }
                }


                //TODO...delete first b4 inserting...mob_health_conditions && mob_health_intermediary
                $sql = "SELECT * FROM mob_health_intermediary p WHERE p.prop_id=$record_id";
                $DeleteIntermediary = DbHelper::getTableRawData($sql);
                if (sizeof($DeleteIntermediary) > 0) {
                    for ($i = 0; $i < sizeof($DeleteIntermediary); $i++) {
                        //delete each
                        $this->smartlife_db->table('mob_health_conditions')->where('intermediary_id', '=', $DeleteIntermediary[$i]->id)->delete();
                    }
                }
                //lastly, delete from mob_health_intermediary
                $this->smartlife_db->table('mob_health_intermediary')->where('prop_id', '=', $record_id)->delete();


                //handle the new implementation here
                //as you insert into mob_health_intermediary nest into table mob_health_conditions
                $checklistIntermediary = array();
                $checklistIntermediary = $request->input('checklistIntermediary'); //json_decode($request->input('checklistIntermediary')); //intermediary
                $mob_health_intermediary = array();
                $health_history_array = array();
                $health_history_arr = json_decode($request->input('family_history')); //checklist
                $mob_health_conditions = array();
                if (isset($checklistIntermediary) && sizeof($checklistIntermediary) > 0) {
                    for ($i = 0; $i < sizeof($checklistIntermediary); $i++) {
                        //insert into table mob_health_intermediary
                        $mob_health_intermediary[$i]['prop_id'] = $record_id;
                        $mob_health_intermediary[$i]['disease_id'] = $checklistIntermediary[$i]['disease_id'];
                        $mob_health_intermediary[$i]['answer'] = "N";
                        if ($checklistIntermediary[$i]['isYesChecked']) {
                            $mob_health_intermediary[$i]['answer'] = "Y";
                        }
                        $mob_health_intermediary[$i]['IsFromMproposal'] = 1;
                        $mob_health_intermediary[$i]['created_on'] = Carbon::now();
                        $mob_health_intermediary_id = $this->smartlife_db->table('mob_health_intermediary')->insertGetId($mob_health_intermediary[$i]);
                    }
                }



                $this->syncImage($request, $record_id, $proposal_no);

                $mobile_no = $request->input('mobile');
                $msg = "POLICY RECEIVED PENDING PAYMENT";
                // $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                // $client = new \GuzzleHttp\Client;
                // $smsRequest = $client->get($url_path);

                //health questionnaire
                $res = array(
                    'success' => true,
                    'edwa_proposal_no' => $edwa_proposal_no,
                    'record_id' => (int) $record_id,
                    'policy_no' => $proposal_no, //$rider_id,
                    'message' => 'Proposal Submited Successfully!!',
                    'res_msg' => $res_msg
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

    //querry proposals with no idfront & idback
    public function pushAttachements(Request $request)
    {
        try {
            //$proposal_no = $request->input('proposal_no');
            //1. Fetch the proposals with no attachments
            $sql = "SELECT p.ID,p.employee_no,p.mobile FROM mob_prop_info p 
                    INNER JOIN planinfo d ON d.plan_code=p.plan_code
                    WHERE (CAST(p.date_synced AS DATE) BETWEEN '2024-10-01' AND '2024-11-14') 
                    AND p.ClientSignature IS NULL AND d.microassurance=0";
            $EmptyAttachments = DbHelper::getTableRawData($sql);

            for ($i = 0; $i < sizeof($EmptyAttachments); $i++) {
                $prop_id = $EmptyAttachments[$i]->ID;
                $staff_no = $EmptyAttachments[$i]->employee_no;
                $mobile = $EmptyAttachments[$i]->mobile;

                //2. fetch the attachements
                $sql_files = "SELECT p.IdFrontPage,p.IdLastPage,p.ClientSignature,p.ClientPassportPhoto,
                        p.PayslipCopy FROM mob_prop_info p 
                        WHERE (p.employee_no='$staff_no' OR p.mobile='$mobile') 
                        AND p.IdFrontPage IS NOT NULL";
                $propAttachments = DbHelper::getTableRawData($sql_files);

                if (isset($propAttachments) && sizeof($propAttachments) > 0) {
                    //3. Update the columns where its null 
                    //DB::raw("0x" . bin2hex($image_binary))]
                    $this->smartlife_db->table('mob_prop_info')
                        ->where('ID', $prop_id)
                        ->update([
                            'ClientPassportPhoto' => DB::raw("0x" . bin2hex(($propAttachments[0]->ClientPassportPhoto))),
                            'IdFrontPage' => DB::raw("0x" . bin2hex(($propAttachments[0]->IdFrontPage))),
                            'IdLastPage' => DB::raw("0x" . bin2hex(($propAttachments[0]->IdLastPage))),
                            'ClientSignature' => DB::raw("0x" . bin2hex(($propAttachments[0]->ClientSignature))),
                            'PayslipCopy' => DB::raw("0x" . bin2hex(($propAttachments[0]->PayslipCopy))),
                            'IsAttachedManually' => 1
                        ]);
                }
            }


            $res = array(
                'success' => true,
                'message' => 'Attachements copied successfully'
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
    //test out the images thing
    public function fixDublicateAttachments(Request $request, $proposal_id = null)
    {
        try {

            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                //if (!isset($proposal_id)) {
                //$proposal_id = $request->input('proposal_id');
                //}
                //$policy_no = DbHelper::getColumnValue('mob_prop_info', 'ID', $proposal_id, 'proposal_no');

                //put in a transaction
                //1. Fetch the proposals with no attachments
                $sql_empty = "SELECT p.ID,p.IdNumber,proposal_no FROM mob_prop_info p 
                    WHERE (CAST(p.date_synced AS DATE) BETWEEN '2025-01-01' AND '2025-01-20') AND
                     (p.IdLastPage IS NULL AND p.IdFrontPage IS NULL) AND p.HasBeenPicked=1 AND 
                        p.LinkedProposal IS NOT NULL AND p.MicroProposal IS NULL AND p.plan_code<>45 ";
                $EmptyAttachments = DbHelper::getTableRawData($sql_empty);
                if (isset($EmptyAttachments) && sizeof($EmptyAttachments) > 0) {
                    for ($n = 0; $n < sizeof($EmptyAttachments); $n++) {

                        $proposal_id = $EmptyAttachments[$n]->ID;
                        $policy_no = $EmptyAttachments[$n]->proposal_no;


                        $propAttachments = array();
                        $ghana_card = DbHelper::getColumnValue('mob_prop_info', 'ID', $proposal_id, 'IdNumber');
                        if (isset($ghana_card)) {
                            $sql_files = "SELECT TOP 1 p.ID,p.IdFrontPage,p.IdLastPage,p.ClientSignature,p.ClientPassportPhoto,
                        p.PayslipCopy FROM mob_prop_info p 
                        WHERE (p.IdLastPage IS NOT NULL AND p.IdFrontPage IS NOT NULL AND p.ClientSignature IS NOT NULL
                        AND p.IdFrontPage IS NOT NULL) AND p.IdNumber='$ghana_card' AND p.ID != $proposal_id AND p.HasBeenPicked=1 AND 
                        p.LinkedProposal IS NOT NULL AND p.MicroProposal IS NULL";
                            $propAttachments = DbHelper::getTableRawData($sql_files);
                        }

                        if (isset($propAttachments) && sizeof($propAttachments) > 0) {
                            for ($i = 0; $i < sizeof($propAttachments); $i++) {

                                $this->smartlife_db->table('mob_prop_info')
                                    ->where('ID', $proposal_id)
                                    ->update([
                                        'ClientPassportPhoto' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientPassportPhoto)),
                                        'IdFrontPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdFrontPage)),
                                        'IdLastPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdLastPage)),
                                        'ClientSignature' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientSignature)),
                                        //'PayslipCopy' => DB::raw("0x" . bin2hex($propAttachments[$i]->PayslipCopy)),
                                        'IsDublicateFixed' => 1
                                    ]);

                                $sql_files = "SELECT p.* FROM mob_proposalFileAttachment p 
                                WHERE p.MobileProposal='" . $propAttachments[$i]->ID . "'";
                                $propFileAttachments = DbHelper::getTableRawData($sql_files);
                                if (isset($propFileAttachments) && sizeof($propFileAttachments) > 0) {
                                    for ($x = 0; $x < sizeof($propFileAttachments); $x++) {
                                        $uuid = Uuid::uuid4();
                                        $uuid = $uuid->toString();
                                        //save into mob_proposalFileAttachment
                                        $table_data = array(
                                            'created_on' => Carbon::now(),
                                            'MobileProposal' => $proposal_id,
                                            'DocumentType' => $propFileAttachments[$x]->DocumentType,
                                            'File' => $uuid,
                                            'proposal_no' => $policy_no,
                                            'Description' => $propFileAttachments[$x]->Description,
                                            'Doc_id' => $propFileAttachments[$x]->Doc_id
                                        );
                                        $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);

                                        $table_data = array(
                                            'Oid' => $uuid,
                                            'mobpolno' => $policy_no,
                                            'FileName' => $propFileAttachments[$x]->Description,
                                            'MobProposal' => $proposal_id,
                                            'Size' => 425833,
                                        );
                                        $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
                                    }
                                }
                            }
                        }
                    }
                }
                $res = array(
                    'success' => true,
                    'message' => 'Updated Successfully Successfully!!'
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

    //Image Sync
    public function syncImage(Request $request, $record_id = null, $proposal_no = null)
    {
        try {
            //for base64
            //$proposal_no = '29-2023-00056'; 
            if (!isset($proposal_no)) {
                $proposal_no = $request->input('proposal_no');
            }
            //$record_id = 18;
            //facility_letter
            $facility_letter = $request->file('facility_letter');
            $photo = $request->file('photo');
            $id_front = $request->file('id_front');
            $id_back = $request->file('id_back');
            $medical_rpt = $request->file('medical_rpt');
            $signature = $request->input('signature');
            $payslip_path = $request->file('payslip_path');
            $is_file = false;
            if (!isset($signature)) {
                $signature = $request->file('signature');
                $is_file = true;
            }
            //$category_id = 3;
            $category_id = 1;
            $policy_no = $proposal_no; //;
            $proposal_id = $record_id;
            if (!isset($proposal_id)) {
                //$proposal_id = $request->input('record_id');
                $proposal_id = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $proposal_no, 'ID');
                $record_id = $proposal_id;
            }
            if (!isset($record_id)) {
                $proposal_id = $request->input('record_id');
                $record_id = $proposal_id;
                //$proposal_id = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $proposal_no, 'ID');
                //$record_id = $proposal_id;
            }
            $fileName = $record_id . ".png"; //"signature.png";

            //TODO - fetch ghana card here..
            $propAttachments = array();
            $ghana_card = DbHelper::getColumnValue('mob_prop_info', 'ID', $proposal_id, 'IdNumber');
            if (isset($ghana_card)) {
                $sql_files = "SELECT TOP 1 p.ID,p.IdFrontPage,p.IdLastPage,p.ClientSignature,p.ClientPassportPhoto,
                        p.PayslipCopy FROM mob_prop_info p 
                        WHERE (p.IdLastPage IS NOT NULL AND p.IdFrontPage IS NOT NULL AND p.ClientSignature IS NOT NULL
                        AND p.ClientPassportPhoto IS NOT NULL) AND p.IdNumber='$ghana_card' AND P.ID != $proposal_id 
                        ";
                $propAttachments = DbHelper::getTableRawData($sql_files);
            }


            if (isset($photo)) {
                $this->savePhysicalFile($photo, $category_id, $policy_no, $proposal_id, 1);
            } else {
                if (isset($propAttachments) && sizeof($propAttachments) > 0) {
                    for ($i = 0; $i < sizeof($propAttachments); $i++) {

                        $this->smartlife_db->table('mob_prop_info')
                            ->where('ID', $proposal_id)
                            ->update([
                                'ClientPassportPhoto' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientPassportPhoto)),
                                //'IdFrontPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdFrontPage)),
                                //'IdLastPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdLastPage)),
                                //'ClientSignature' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientSignature)),
                                //'PayslipCopy' => DB::raw("0x" . bin2hex($propAttachments[$i]->PayslipCopy)),
                                'IsDublicateFixed' => 1
                            ]);

                        $sql_files = "SELECT p.* FROM mob_proposalFileAttachment p 
                        WHERE p.MobileProposal='" . $propAttachments[$i]->ID . "'";
                        $propFileAttachments = DbHelper::getTableRawData($sql_files);
                        if (isset($propFileAttachments) && sizeof($propFileAttachments) > 0) {
                            for ($x = 0; $x < sizeof($propFileAttachments); $x++) {
                                $uuid = Uuid::uuid4();
                                $uuid = $uuid->toString();
                                //save into mob_proposalFileAttachment
                                $table_data = array(
                                    'created_on' => Carbon::now(),
                                    'MobileProposal' => $proposal_id,
                                    'DocumentType' => $propFileAttachments[$x]->DocumentType,
                                    'File' => $uuid,
                                    'proposal_no' => $policy_no,
                                    'Description' => $propFileAttachments[$x]->Description,
                                    'Doc_id' => $propFileAttachments[$x]->Doc_id
                                );
                                $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);

                                $table_data = array(
                                    'Oid' => $uuid,
                                    'mobpolno' => $policy_no,
                                    'FileName' => $propFileAttachments[$x]->Description,
                                    'MobProposal' => $proposal_id,
                                    'Size' => 425833,
                                );
                                $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
                            }
                        }
                    }
                }
            }

            if (isset($id_front)) {
                $this->savePhysicalFile($id_front, $category_id, $policy_no, $proposal_id, 2);
            } else {
                if (isset($propAttachments) && sizeof($propAttachments) > 0) {
                    for ($i = 0; $i < sizeof($propAttachments); $i++) {

                        $this->smartlife_db->table('mob_prop_info')
                            ->where('ID', $proposal_id)
                            ->update([
                                //'ClientPassportPhoto' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientPassportPhoto)),
                                'IdFrontPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdFrontPage)),
                                'IdLastPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdLastPage)),
                                'ClientSignature' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientSignature)),
                                //'PayslipCopy' => DB::raw("0x" . bin2hex($propAttachments[$i]->PayslipCopy)),
                                'IsDublicateFixed' => 1
                            ]);

                        $sql_files = "SELECT p.* FROM mob_proposalFileAttachment p 
                        WHERE p.MobileProposal='" . $propAttachments[$i]->ID . "'";
                        $propFileAttachments = DbHelper::getTableRawData($sql_files);
                        if (isset($propFileAttachments) && sizeof($propFileAttachments) > 0) {
                            for ($x = 0; $x < sizeof($propFileAttachments); $x++) {
                                $uuid = Uuid::uuid4();
                                $uuid = $uuid->toString();
                                //save into mob_proposalFileAttachment
                                $table_data = array(
                                    'created_on' => Carbon::now(),
                                    'MobileProposal' => $proposal_id,
                                    'DocumentType' => $propFileAttachments[$x]->DocumentType,
                                    'File' => $uuid,
                                    'proposal_no' => $policy_no,
                                    'Description' => $propFileAttachments[$x]->Description,
                                    'Doc_id' => $propFileAttachments[$x]->Doc_id
                                );
                                $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);

                                $table_data = array(
                                    'Oid' => $uuid,
                                    'mobpolno' => $policy_no,
                                    'FileName' => $propFileAttachments[$x]->Description,
                                    'MobProposal' => $proposal_id,
                                    'Size' => 425833,
                                );
                                $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
                            }
                        }
                    }
                }
            }

            if (isset($id_back)) {
                $this->savePhysicalFile($id_back, $category_id, $policy_no, $proposal_id, 3);
            } else {
                if (isset($propAttachments) && sizeof($propAttachments) > 0) {
                    for ($i = 0; $i < sizeof($propAttachments); $i++) {

                        $this->smartlife_db->table('mob_prop_info')
                            ->where('ID', $proposal_id)
                            ->update([
                                //'ClientPassportPhoto' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientPassportPhoto)),
                                //'IdFrontPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdFrontPage)),
                                'IdLastPage' => DB::raw("0x" . bin2hex($propAttachments[$i]->IdLastPage)),
                                'ClientSignature' => DB::raw("0x" . bin2hex($propAttachments[$i]->ClientSignature)),
                                //'PayslipCopy' => DB::raw("0x" . bin2hex($propAttachments[$i]->PayslipCopy)),
                                'IsDublicateFixed' => 1
                            ]);

                        $sql_files = "SELECT p.* FROM mob_proposalFileAttachment p 
                        WHERE p.MobileProposal='" . $propAttachments[$i]->ID . "'";
                        $propFileAttachments = DbHelper::getTableRawData($sql_files);
                        if (isset($propFileAttachments) && sizeof($propFileAttachments) > 0) {
                            for ($x = 0; $x < sizeof($propFileAttachments); $x++) {
                                $uuid = Uuid::uuid4();
                                $uuid = $uuid->toString();
                                //save into mob_proposalFileAttachment
                                $table_data = array(
                                    'created_on' => Carbon::now(),
                                    'MobileProposal' => $proposal_id,
                                    'DocumentType' => $propFileAttachments[$x]->DocumentType,
                                    'File' => $uuid,
                                    'proposal_no' => $policy_no,
                                    'Description' => $propFileAttachments[$x]->Description,
                                    'Doc_id' => $propFileAttachments[$x]->Doc_id
                                );
                                $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);

                                $table_data = array(
                                    'Oid' => $uuid,
                                    'mobpolno' => $policy_no,
                                    'FileName' => $propFileAttachments[$x]->Description,
                                    'MobProposal' => $proposal_id,
                                    'Size' => 425833,
                                );
                                $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
                            }
                        }
                    }
                }
            }

            if (isset($medical_rpt))
                $this->savePhysicalFile($medical_rpt, $category_id, $policy_no, $proposal_id, 0);
            if (isset($signature) && !$is_file)
                $this->saveStringFile($signature, $category_id, $policy_no, $proposal_id, $fileName);
            if (isset($signature) && $is_file)
                $this->savePhysicalFile($signature, $category_id, $policy_no, $proposal_id, 4);
            if (isset($facility_letter))
                $this->savePhysicalFile($facility_letter, $category_id, $policy_no, $proposal_id, 5);
            if (isset($payslip_path))
                $this->savePhysicalFile($payslip_path, $category_id, $policy_no, $proposal_id, 6);


            $res = array(
                'success' => true,
                'record_id' => $record_id,
                'policy_no' => $proposal_no, //$rider_id,
                'message' => 'Data Synced Successfully!!'
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

    public function savePhysicalFile($file, $category_id, $policy_no, $proposal_id, $file_type)
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
        $destinationPath = 'C:\xampp\htdocs\SmartLifeDocuments\PolicyDocuments';
        //$destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', 1, 'FileStoreLocationPath');
        $file->move($destinationPath, $file->getClientOriginalName());
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();

        $Doc_id = "";
        if ($file_type == 0) $Doc_id = "medical_rpt";
        if ($file_type == 1) $Doc_id = "photo";
        if ($file_type == 2) $Doc_id = "id_front";
        if ($file_type == 3) $Doc_id = "id_back";
        if ($file_type == 4) $Doc_id = "signature";
        if ($file_type == 5) $Doc_id = "facility_letter";
        if ($file_type == 6) $Doc_id = "payslip_path";


        //insert into mob_proposalFileAttachment
        $table_data = array(
            'created_on' => Carbon::now(),
            'MobileProposal' => $proposal_id,
            'DocumentType' => $category_id,
            'File' => $uuid,
            'Description' => $fileName,
            'Doc_id' => $Doc_id
        );
        $record_id = $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            'mobpolno' => $policy_no,
            'FileName' => $fileName,
            'MobProposal' => $proposal_id,
            'Size' => $file_size,
        );
        $record_id = $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
        if ($file_type == 1) {
            //insert photo 
            $image_path = $destinationPath . "\\" . $fileName;
            $image_binary = file_get_contents($image_path);
            $this->smartlife_db->table('mob_prop_info')
                ->where('ID', $proposal_id)
                ->update(['ClientPassportPhoto' => DB::raw("0x" . bin2hex($image_binary))]);
        }
        if ($file_type == 2) {
            //insert id front 
            $image_path = $destinationPath . "\\" . $fileName;
            $image_binary = file_get_contents($image_path);
            $this->smartlife_db->table('mob_prop_info')
                ->where('ID', $proposal_id)
                ->update(['IdFrontPage' => DB::raw("0x" . bin2hex($image_binary))]);
        }
        if ($file_type == 3) {
            //insert id back 
            $image_path = $destinationPath . "\\" . $fileName;
            $image_binary = file_get_contents($image_path);
            $this->smartlife_db->table('mob_prop_info')
                ->where('ID', $proposal_id)
                ->update(['IdLastPage' => DB::raw("0x" . bin2hex($image_binary))]);
        }
        if ($file_type == 4) {
            //insert signature
            $image_path = $destinationPath . "\\" . $fileName;
            $image_binary = file_get_contents($image_path);
            $this->smartlife_db->table('mob_prop_info')
                ->where('ID', $proposal_id)
                ->update(['ClientSignature' => DB::raw("0x" . bin2hex($image_binary))]);
        }
        if ($file_type == 6) {
            //insert signature
            $image_path = $destinationPath . "\\" . $fileName;
            $image_binary = file_get_contents($image_path);
            $this->smartlife_db->table('mob_prop_info')
                ->where('ID', $proposal_id)
                ->update(['PayslipCopy' => DB::raw("0x" . bin2hex($image_binary))]);
        }
    }

    public function testVarBinary(Request $request)
    {
        try {
            //for base64
            //$proposal_no = '29-2023-00056'; 
            //$record_id = 18;

            $binary_char = "89504e470d0a1a0a0000000d494844520000015e000000540803000000fd19affa00000300504c54450000006f6f6f0158af0059b0055bb1095eb2165fb30f63b31364b61a66b6196ab71e6db9286eb92973bc3176bd377bc03d81c34485c54e80c34e8bc7558bc85a94cd5894d06c9acf77a5d5ea0000ea0101e80404ea0505ea0606ec0000ed0505eb0909eb0a0aeb0c0ceb0e0eee0a0aee0c0ceb1111eb1414ec1313ee1414ec1c1cf00808f21a1aed2525ed2828ee2d2dee2e2eee3232ee3535ef3c3cf22929f23333ef4040f04141f04545f64747f04a4af25151f15454f65656f15959f25c5cf25f5ff75f5ff26161f36363f26565f56767f06669f36868f36b6bf36c6cf36f6ff86d6df37171f47474f47676f47777f47979f47b7bf47d7df87d7d8787879292929f9f9f84aad788b2da88b3de8bb4dd8fb2da96b6dc91b8df9abbde9fbcdf95bbe09bc0e4a2c3e5a6c9ebabc2e6aec1e3aec2e4adc3e5adc3e6afc5e3adc4e6adc4e7accaecb1c5e7b4c6e7b1c7e8b1c9eab4c8e9b8cbe9bacceab5d1ecbbd3eab8d0edbbd5efbed1eab4d1f1b6d9f8bcdaf6bde3fbf58383f68687f68e8ef39396f69191f69696f79999f79b9bf79d9dfc9797fa9999f7a0a0f7a3a3faa3a3f8a9a9f8aaaaf8ababf8adadf8afaff4b3b5f9b1b1f9b3b3f9b7b7fdb0b0f9babaccccccdbdbdbc2d2ecc5d4eecad6efcbd9efc5dbf1c0dffacbdcf1d0dcf1c5e0f9c9e4fbcbecfed5e2f3d1ebfddbe4f4dce8f5dbedfcd7f3ffdff6fed9fafff7c1c3f4cbcffac0c0fac5c5fdc6c6fbcacafbccccfecacafad1d2fbd4d4ffd3d3ffd4d4fcd5d5fcd7d7fadedffdd9d9fcddddededede1e9f6e5e9f4e4ecf6e0ecf8e7eef8e9eff7e6f5ffe6f9ffe9f0f8ebf6feecf3faeff5faedf6fdfae5e7fce0e0ffe1e1fde2e2ffe3e3fee7e7f9edeffee9e9fdececfeededfdeeeefceff0f1f5fbf3f6fbf4f5f9f2f9fef2fcfff5f8fcfef1f1fef4f4fdf6f7f9f9f9f9fbfdfbfdfefef8f8fff9f9fefafbfcfbfcfefcfdfcfdfefffefefdfeff000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000e5e3c0e70000000174524e530040e6d86600000001624b47440088051d48000000097048597300000ec300000ec301c76fa8640000000774494d4507df0a1a062d37c6b228520000070a4944415478daed9cff5f137518c01fbf956986a6a5e38b22aba9531883d02986362d84266c0849dfb44c83965f3115532140130324314da5441da056a4288541a45684865628ba14b1c1e6bcfd17edee73b2ddbe7170488c9ef76fecb6dde7f37e3dbb7b9ee7f33900100441100441100441100441bc97685f7f1bbe0b5148cf325f648f0a85f42c311cbd7128a46759807a512fea45502fea45bd08ea45bda81741bd0f9d3bd53556aa6aa14dcfd062b9df70c94ac3250b503555d52ea9aabae3426f65fe1e1b79e7e86f6fac6df448ed35fa4d0505bb79505058ddfb7afe494adc2fe0e3edeae0102bc18ba0743d439961b1cf482b3eaf019c9205bb4196e7acd73c2f486c636286199a17ca64a11e095103d42a82f8210eb31ff98641346ea6c53d48fe726494c7a3ec87939212137fecbedeb31246ced46f6e6d4ed15a5977be7c08f3ed83df017851e4961c67bd5414e78504807d01a2ce980d902fe24b50efeb4d4c1af0cae7ddb64ba9c9c067c289f769bb29252de3d8931be03ba9db8906ee70a1770ee7856d00057e9d1a9b0390e7cf57efe4ded7bb7f40e280ee076f233138a1c8b4f55d5aef9a3afde324789701c4fb0ad4bbdb9f97de803eac1740c0a501b2c8b823e0ab15f4b56179897e29f9f227f47045e67ea281dbbd4f6fb2034b3c1e4dee81fb62ab9c19f6f8ede6ad2be8e05d55074f10bdaf027cec61da0139dea71778bdb727d9412e8ed21bbfaea28337e550c37b0399930dbf004d3344a8571026d6e022cb5112bc65f02409de71d61bba3fea15c63e92954df9fdf25ada6eeac1f3c54399733daa83d6284f130dc846bd9d61569251cfb39432c1bba618c692e07dca006726a15e619c2659596045cb7adaae767db9ee31e654433e002a56847a85a121838e3495332585b6181693e01ddd023512cf7ab3506f275c95b12585e5c31452525cb29514199e27eaef42af7936b728a6f8e88decb77ab3485116613ab98609de83862524787deaa1298413ab5119191a69a717875dd12a1bd17b1da357ac502ae7725146653bea9566e66477909329f556bd4de1a4a4f808b632c1ab2dd3fb30271a4897149c7a38b811a85699c8d7862bbd00947d37031cf586d5189d683739ea955fb71f64b3dc5bf51691213f77e397b5b4ded4f4fa7412bc23cac118c651b780ee5c66c52774a0569f76a1d7d87ad746abc951affcaaeb8170f5cab87a655eaad7184982570347c98dadcc309ae81d0bf05990fdac2495bc562ba89c28bbdf7d54a193de465e7aaff50bbd156452536a6f6da67b65a9697a1d69f40e3d02eddc9b5414c54baf3992f38286fa3feb9d4b82f72528658277b9ce3296b41b9eb6aae794147e45d614393656654fdce9ae27667d5b6fcf36ccce1183cf9ebdbd910ede94b49b3f3f424a8a4d00d11c4fe1cdce15b2df76efd3eb40b2e76eb030bdec2ac52c38ceb4d1579598d99262640b7c1fecb8ee53e1586404e4a25e4fab14c4e0f802c316466f5a590b2929062d036a1b4753c85530ce776aa7a35e4f64928947dcbdc8d6c32d4bc98dcda701ae847234a94d503909f576697303dbe8dd69f992e995add399479192e24d804f389624df824925f2f3f7f37abd0ef7ae0d0f2f73c80fa447eb3bedafcb5a520feb570f26ab143ab8abe0f65cdac1589893bb2b6bb2b7ebedb5c4ec0e5b5224c051b2b9a1d832865da56883224e3d2cdef3e033d25ed2ebfd555b859819edb4b37f6f668237adfe3059a518f60598b8c5c18ceb46a3d164a549b0de306e514c3138e90d6d32d95a12a6a650efd36b5691d1be00c7981b5b6a89e5c12a451b9ce1e660d2590ac2f440a17aa5dbb233ed48506b349af83c47bd62c52c3b1462efd35b4d0271ead7f748a377f5c53a76956225c0cbbc3aafddd1eb6e13547febf7b279ed4cf30966f93db5d890ccee5bb90535d2ded5dbff562bae9156f9d44fdbb6b01b9f6eb2ab146f032cf245bd02f78e90b421c27452cbee1d5946b2b2110df0a75c847a8565651dab14479892425b073e1d75cc4e3fd42b8cdd6436d39a7f5b4b82f7623aa987879743f30c11ea1596954d27438d371f231b9f743066e0838d4f7b8250af300a49fefacc0fb7d7d1496fca817a1ddbe83d0c46a508f50a0b5eb6ca7c1e4ad9462fd84a8a5312d42bb01e2689d7840ac3a614b2f1e93cc9ca866c04a34ae4757a9d763d6fe8a1c5a08eddd55d229c5da5b8475629b487eebf4eaebca31ae08af4bfd12be4d90a376ddcaeeb75fb6c4057b84e82777c2eb5454b1abdf5ec76f4b780cae06d57e4bfc3f97fe9383c19443fba22e2a337df97ef5983fabade04f6598aa69f56327a0f18de609fa5b8007fc8027923b6ea8d91d8a306b37222e7b9360a2a3bff39cc0668548879112491f771bd6685441a363d5c9e01c70fd0a497c1e26123ac0c1f67819a7973f932475961fde9abe2e23b58580454ae32c6c6fcbd14b46a42641e090d8eb30e6a6fec82181e44abf2fa7af422088220088220088220088220088220dec3bf03e1cd9e2aeb37e60000000049454e44ae426082";
            $sql = "UPDATE mob_prop_info SET ClientPassportPhoto=CONVERT(VARBINARY, '$binary_char') WHERE ID=15";
            //exit();
            $this->smartlife_db->raw($sql);
            //\DB::statement($sql);


            $res = array(
                'success' => true,
                'message' => 'Data Synced Successfully!!'
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

    function base64ToVarbinary($base64)
    {
        $binary = base64_decode($base64);
        return bin2hex($binary);
    }


    public function saveStringFile($file, $category_id, $policy_no, $proposal_id, $fileName)
    {
        //$destinationPath = 'C:\xampp\htdocs\SmartLifeDocuments\PolicyDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', 1, 'FileStoreLocationPath');
        file_put_contents($destinationPath . '\\' . $proposal_id . '.png', base64_decode($file));

        $image_path = $destinationPath . "\\" . $proposal_id . ".png";
        $image_binary = file_get_contents($image_path);
        $this->smartlife_db->table('mob_prop_info')
            ->where('ID', $proposal_id)
            ->update(['ClientSignature' => DB::raw("0x" . bin2hex($image_binary))]);

        //insert into mob_proposalFileAttachment
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();
        $table_data = array(
            'created_on' => Carbon::now(),
            'MobileProposal' => $proposal_id,
            'DocumentType' => $category_id,
            'File' => $uuid,
            'Description' => $fileName,
            'Doc_id' => "signature"
        );
        $record_id = $this->smartlife_db->table('mob_proposalFileAttachment')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            'mobpolno' => $policy_no,
            'FileName' => $fileName,
            'MobProposal' => $proposal_id,
            'Size' => 1480,
        );
        $record_id = $this->smartlife_db->table('Mob_ProposalStoreObject')->insertGetId($table_data);
    }

    //update term_of_policy....////
    public function updateTerm(Request $request)
    {
        try {

            $sql = "SELECT p.proposal_no,p.plan_code,p.term_of_policy,d.description,d.investment_plan,
                    d.whole_life,d.max_age,p.age FROM proposalinfo p 
                    INNER JOIN planinfo d ON d.plan_code=p.plan_code
                    WHERE p.term_of_policy=0 
                    ORDER BY p.proposal_date DESC";

            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                for ($i = 0; $i < sizeof($r_rows); $i++) {
                    $term = 0;
                    $is_investment = $r_rows[$i]->investment_plan;
                    $is_whole_life = $r_rows[$i]->whole_life;
                    //1.If its investment plan
                    if ($is_investment == 1) {
                        //2.Then, max_age - age = term
                        $term = (int)$r_rows[$i]->max_age - (int)$r_rows[$i]->age;
                    }
                    if ($is_whole_life == 1 || $is_whole_life == 3) {
                        //3.else if whole_life, 99-age = term
                        $term = 99 - (int)$r_rows[$i]->age;
                    }


                    //update proposalinfo - term and is_term_updated
                    $this->smartlife_db->table('proposalinfo')
                        ->where('proposal_no', $r_rows[$i]->proposal_no)
                        ->update([
                            'term_of_policy' => $term,
                            'is_term_updated' => 1
                        ]);
                }
            }

            $res = array(
                'success' => true,
                'message' => 'Terms are updated fully'
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

    //function to flag the duplicates and update the proposal_no they duplicated to 
    public function updateTheDuplicated(Request $request)
    {
        try {

            $DateFrom = '2024-03-10';
            $DateTo = '2024-03-21';

            $sql = "SELECT p.ID, p.proposal_no, p.plan_code, p.mobile, p.TotalPremium, p.second_l_name,
                        p.IsDuplicate, p.DuplicatePolicyNo, p.DuplicateId
                        FROM mob_prop_info p
                        WHERE (p.[date_synced] BETWEEN '$DateFrom' AND '$DateTo')
                        ORDER BY p.ID DESC";

            $r_rows = DbHelper::getTableRawData($sql);

            if (sizeof($r_rows) > 0) {
                // Initialize the loop index outside the loop
                $start_index = 0;

                for ($i = $start_index; $i < sizeof($r_rows); $i++) {
                    // Update
                    if ($r_rows[$i]->IsDuplicate != 1) {
                        $proposal_no = $r_rows[$i]->proposal_no;
                        $DuplicateId = $r_rows[$i]->ID;

                        $plan_code = $r_rows[$i]->plan_code;
                        $mobile = $r_rows[$i]->mobile;
                        $TotalPremium = $r_rows[$i]->TotalPremium;
                        $second_l_name = $r_rows[$i]->second_l_name;


                        // Perform the SQL update operation and obtain the changed IDs
                        $results = $this->smartlife_db->select("
                        UPDATE mob_prop_info
                        SET IsDuplicate = 1,
                            DuplicatePolicyNo = '$proposal_no',
                            DuplicateId = $DuplicateId
                        OUTPUT inserted.ID
                        WHERE plan_code = $plan_code
                            AND mobile = '$mobile'
                            AND TotalPremium = '$TotalPremium'
                            AND second_l_name = '$second_l_name'
                            AND IsDuplicate IS NULL
                            AND ID NOT IN ($DuplicateId)
                        ");

                        // Initialize the array to store the changed IDs
                        $changedIds = [];

                        // Extract the changed IDs from the results and store them in the $changedIds array
                        foreach ($results as $result) {
                            $changedIds[] = $result->ID;
                        }

                        // Update the IsDuplicate values in $r_rows for the changed IDs
                        foreach ($changedIds as $changedId) {
                            foreach ($r_rows as $row) {
                                if ($row->ID == $changedId) {
                                    $row->IsDuplicate = 1;
                                    break;
                                }
                            }
                        }
                    }



                    // Update the starting index for the next iteration
                    $start_index = $i + 1;
                }
            }





            //2. Search for a duplicate (ID ASC) other than self and update the isduplicated flag & the duplicate_id & the duplicate_proposal_no 

            $res = array(
                'success' => true,
                'message' => 'Duplicated successfully updated'
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

    ///handle duplicates in micro///
    public function microDuplicates(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $qry = $this->smartlife_db->table('mob_prop_info')->select('*')
                    ->where(
                        array(
                            'prop_id' => ''
                        )
                    );
                $row_arr = $qry->get();

                for ($i = 0; $i < sizeof($row_arr); $i++) {
                    //search again...
                    //TODO-Add wherenot id=$row_arr[$i]->ID
                    $qry = $this->smartlife_db->table('mob_prop_info')->select('*')
                        ->where(
                            array(
                                'proposal_no' => $row_arr[$i]->proposal_no
                            )
                        );
                    $row_policies = $qry->get();
                    if (isset($row_policies)) {
                        //check if id is greator, if so generate a new proposal no 

                    }
                }



                $res = array(
                    'success' => true,
                    'message' => 'Duplicates sorted!!'
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
