<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class reportsController extends Controller
{

    //display group post rpt only
    public function getGroupRpt(Request $request)
    {
        try {
            $res = array();

            $scheme_no = $request->input('scheme_no');
            $settings = $request->input('settings');
            $schemeId = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');


            $MemberId = $request->input('MemberId');

            $url = "http://192.168.1.248:85/api/Report/Report";
            if ($settings == "15") {
                $data = [
                    "reference" => $schemeId,
                    "settings" => $settings,
                    "SchemeID" => $schemeId,
                    "fund_year" => "2023"
                ];
            } else if ($settings == "13") {
                $data = [
                    "reference" => $MemberId,
                    "settings" => $settings,
                    "MemberId" => $MemberId,
                    "SchemeID" => $schemeId,
                    "FundYear" => "2023"
                ];
            }



            $headers = [
                'Content-Type' => 'application/json'
            ];

            $client = new \GuzzleHttp\Client;

            try {
                $response = $client->post($url, [
                    'headers' => $headers,
                    'json' => $data
                ]);

                if ($response->getStatusCode() == 200) {
                    // The POST request was successful
                    $base64Rpt = $response->getBody()->getContents();
                    // Do something with $result
                    //echo "Request was successful: " . $result;
                } else {
                    // Handle other status codes if needed
                    $res = array(
                        'success' => false,
                        'message' => $response->getStatusCode()
                    );
                    return response()->json($res);
                }
            } catch (RequestException $e) {
                // An error occurred during the request
                //echo "Request failed: " . $e->getMessage();
                $res = array(
                    'success' => false,
                    'message' => $e->getMessage()
                );
                return response()->json($res);
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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

    //save base64file
    function saveBase64PdfToPublic(string $base64String, string $fileName = 'document.pdf'): string
    {
        // Remove metadata if present (e.g., "data:application/pdf;base64,")
        if (strpos($base64String, ',') !== false) {
            [$meta, $base64String] = explode(',', $base64String, 2);
        }

        // Decode the base64 string
        $pdfData = base64_decode($base64String);

        if ($pdfData === false) {
            throw new \Exception("Invalid Base64 string.");
        }

        // Path to save PDF
        $filePath = public_path($fileName);

        // Save to file
        File::put($filePath, $pdfData);

        return asset($fileName); // Return the URL to access it
    }

    //send txt with premium statements link
    public function getPremiumStatement(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no'); //
            //$policyId = DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'id');
            $policyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
            if (!isset($policyId)) {
                $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
            }
            $settings = $request->input('settings');

            $isFromClientApp = $request->input('isFromClientApp');

            $agent_no = $request->input('agent_no');
            $period_year = $request->input('period_year');
            $period_month = $request->input('period_month');
            $FinancialAdvisorCategory = $request->input('FinancialAdvisorCategory');
            if (!isset($FinancialAdvisorCategory)) $FinancialAdvisorCategory = 1;
            $PayrollCategory = $request->input('PayrollCategory');
            if (!isset($PayrollCategory)) $PayrollCategory = 1;

            if ($settings == "12") {

                $pay_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
                $period_month = (int)$period_month + 1;
                if ($period_month == 12) {
                    $period_month = 1;
                }

                /*  dataSource.Criteria = CriteriaOperator.Parse("pay_no.AgentNoCode=? " +
                    " AND  period_year=? AND period_month=?", VAR.pay_no.AgentNoCode, 
                    Convert.ToInt32(VAR.account_year), Convert.ToInt32(VAR.account_month)); */

                //handle agents commission here..
                $url = "http://192.168.1.248:85/api/Report/Report";
                $data = [
                    "reference" => $pay_no,
                    "settings" => $settings,
                    //"FinancialAdvisorCategory" => $FinancialAdvisorCategory,
                    //"PayrollCategory" => $PayrollCategory,
                    "pay_no" => $pay_no,
                    "period_year" => $period_year,
                    "period_month" => $period_month
                ];

                $headers = [
                    'Content-Type' => 'application/json'
                ];

                $client = new \GuzzleHttp\Client;

                try {
                    $response = $client->post($url, [
                        'headers' => $headers,
                        'json' => $data
                    ]);

                    if ($response->getStatusCode() == 200) {
                        // The POST request was successful
                        $base64Rpt = $response->getBody()->getContents();
                        // Do something with $result
                        //echo "Request was successful: " . $result;
                    } else {
                        // Handle other status codes if needed
                        $res = array(
                            'success' => false,
                            'message' => $response->getStatusCode()
                        );
                        return response()->json($res);
                    }
                } catch (RequestException $e) {
                    // An error occurred during the request
                    //echo "Request failed: " . $e->getMessage();
                    $res = array(
                        'success' => false,
                        'message' => $e->getMessage()
                    );
                    return response()->json($res);
                }
            } else {
                //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
                if ($settings == "16") {
                    $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policy_no . "&settings=" . $settings;
                } else {
                    if ($settings == "11") {
                        //lets display the policy docments
                        //1. Fetch the plan_code
                        $plan_code = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'plan_code');
                        //2. fetch the report_name
                        $report_name = DbHelper::getColumnValue('planinfo', 'plan_code', $plan_code, 'PolicyScheduleReportName');
                        //3. fetch the reportId/settings
                        $settings = DbHelper::getColumnValue('Email_sms_Settings', 'EmailSubject', $report_name, 'id');

                        //$url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policyId . "&settings=" . $settings;
                    } else if($settings == "10"){
                        //from defaults info check the 
                        $UseInvestmentTable = DbHelper::getColumnValue('defaultsinfo', 'id', 0, 'UseInvestmentTable');
                        if($UseInvestmentTable == 1){
                            $settings = 10;
                        }else{
                            $settings = 31;
                        }

                        //$url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policy_no . "&settings=" . $settings;
                    }else if ($settings == "18") {
                        //is premium arreas....
                        //send the sms to client...
                        $units = $request->input('units');
                        $outstanding_premium = $request->input('outstanding_premium');
                        //$msg = "Dear Client, you owe " .$units . " months of premiums (GHS " . $outstanding_premium . ") for Policy No: " . $policy_no . ". Kindly settle the outstanding amount to avoid any issues";
                        
                        $msg = "Dear Client, Your arrears on ".$policy_no." is ".$outstanding_premium." for ".$units." months. Kindly use*713* 209 # to make payment  or contact us on 0202222113 for further enquiries.GLICO, we cushion you for life.";
                        //TODO - fetch the mobile_no of the client from clientinfo...
                        $client_no = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'client_number');
                        $mobile_no = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'mobile');

                        if (substr($mobile_no, 0, 1) == '0') {
                            $mobile_no = "233" . ltrim($mobile_no, '0');
                        }

                        $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                        $client = new \GuzzleHttp\Client;
                        $smsRequest = $client->get($url_path);
                    }

                    $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policyId . "&settings=" . $settings;
                }

                $client = new \GuzzleHttp\Client;
                $response =  $client->get($url_path);

                if ($response->getStatusCode() == 200) {
                    $base64Rpt = $response->getBody()->getContents();
                    // Process the retrieved data as needed
                    if(isset($isFromClientApp) && $isFromClientApp == "1"){
                        $fileName = $policyId . '.pdf';
                        $this->saveBase64PdfToPublic($base64Rpt, $fileName);
                        $res = array(
                            'success' => true,
                            'fileName' => $fileName,
                            'message' => 'Report saved successfully'
                        );
                        return response()->json($res);
                    }
                }
            }




            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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

    //send txt with Investment statements link
    public function getInvestmentStatement(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no');
            $settings = $request->input('settings');
            //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
            $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policy_no . "&settings=" . $settings;

            $client = new \GuzzleHttp\Client;
            $response =  $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $base64Rpt = $response->getBody()->getContents();
                // Process the retrieved data as needed
            }


            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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

    //send txt with Policy schedule link
    public function getPolicySchedule(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no');
            $settings = $request->input('settings');
            //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
            $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policy_no . "&settings=" . $settings;

            $client = new \GuzzleHttp\Client;
            $response =  $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $base64Rpt = $response->getBody()->getContents();
                // Process the retrieved data as needed
            }


            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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

    //send txt with Loan Statement link
    public function getLoanStatement(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no');
            $settings = $request->input('settings');
            //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
            $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policy_no . "&settings=" . $settings;

            $client = new \GuzzleHttp\Client;
            $response =  $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $base64Rpt = $response->getBody()->getContents();
                // Process the retrieved data as needed
            }


            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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

    //////////////get client dashboards//////////
    public function getClientDashboard(Request $request)
    {
        try {
            $res = array();

            //get total policies
            $client_no = $request->input('client_no');

            //2. Total pending proposals
            $sql = "SELECT COUNT(p.id) as total FROM MicroPolicyinfo p WHERE p.[Client]='$client_no' AND p.[Status]=10";
            $ActiveMicroPolicies = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.id) as total FROM polinfo p WHERE p.[client_number]='$client_no' AND p.[status_code]=10";
            $ActiveLifePolicies = DbHelper::getTableRawData($sql);

            $ActivePolicies = (int)$ActiveMicroPolicies[0]->total + (int)$ActiveLifePolicies[0]->total;


            /*
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode',$agent_no,'id');//
            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode',$agent_no,'BusinessChannel');

            
                //Return
                //1. Total active policies
                $sql='SELECT COUNT(p.id) as total FROM MicroPolicyinfo p WHERE p.Agent='.$agent_id.' AND p."Status"=10';
                $ActivePolicies = DbHelper::getTableRawData($sql);
                //2. Total pending proposals
                $sql="SELECT COUNT(p.ID) as total FROM mob_prop_info p WHERE p.agent_code=$agent_id AND p.HasBeenPicked=0";
                $PendingProposals = DbHelper::getTableRawData($sql);
                //3. Total Active clients
                $sql='SELECT COUNT(p.client_number) as total FROM clientinfo p INNER JOIN MicroPolicyinfo d ON p.client_number=d."Client"
                WHERE d.Agent='.$agent_id.' AND d."Status"=10';
                $ActiveClients = DbHelper::getTableRawData($sql);*/



            //health questionnaire
            $res = array(
                'success' => true,
                'ActivePolicies' => $ActivePolicies,
                //'PendingProposals' => $PendingProposals[0]->total,
                //'ActiveClients' => $ActiveClients[0]->total,
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
    //////////////end of client dashboards//////

    public function getWorksheet(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no');
            $policyId = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $policy_no, 'ID');
            $settings = $request->input('settings');
            //$url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
            $url_path = "http://192.168.1.248:85/api/Report/Report?PolicyNumber=" . $policyId . "&settings=" . $settings;

            $client = new \GuzzleHttp\Client;
            $response =  $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $base64Rpt = $response->getBody()->getContents();
                // Process the retrieved data as needed
            }


            //health questionnaire
            $res = array(
                'success' => true,
                'base64Rpt' => $base64Rpt,
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
    //get current M-proposal version
    public function getMproposalVersion(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Agents info if exists
            $MproposalVersion = DbHelper::getColumnValue('CompanyInfo', 'id', 1, 'MproposalVersion');

            $res = array(
                'success' => true,
                'MproposalVersion' => $MproposalVersion
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
    //get groups dashboard data
    //get scheme member total analysis
    public function getSchemeTotMembers(Request $request)
    {
        try {
            $res = array();

            $TotalMembers = 0;
            $TotalClaims = 0;
            $PolicyNo = "";
            $DateFrom = "";
            $class = "";
            $class_code = "";

            $scheme_no = $request->input('scheme_no');
            $schemeId = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');

            $sql = "SELECT COUNT(p.MemberId) as total FROM glmembersinfo p WHERE p.status='001' and SchemeID=$schemeId";
            $ActiveMembers = DbHelper::getTableRawData($sql);
            $TotalMembers = 0;
            if (sizeof($ActiveMembers) > 0) {
                $TotalMembers = $ActiveMembers[0]->total;
            }
            if ($TotalMembers == 0 && DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'StatusCode') == '001') {
                $TotalMembers = 1;
            }

            $sql = "SELECT COUNT(*) as total FROM glifeclaimsnotification p WHERE p.Scheme=$schemeId";
            $TotalClaims = DbHelper::getTableRawData($sql);
            $TotalClaimsVal = 0;
            if (sizeof($TotalClaims) > 0) {
                $TotalClaimsVal = $TotalClaims[0]->total;
            }
            $sql = "SELECT p.policy_no,FORMAT(p.DateFrom, 'dd/MM/yyyy') AS DateFrom,p.class_code FROM polschemeinfo p 
                    WHERE p.class_code=10 AND p.StatusCode='001' AND p.schemeID=$schemeId";
            $PolicyDetails = DbHelper::getTableRawData($sql);
            if (sizeof($PolicyDetails) > 0) {
                $PolicyNo = $PolicyDetails[0]->policy_no;
                $DateFrom = $PolicyDetails[0]->DateFrom;
                $class_code = $PolicyDetails[0]->class_code;
            } else {
                $PolicyNo = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'policy_no');
                //$DateFrom = DbHelper::getColumnValue('polschemeinfo', 'policy_no',$scheme_no,'DateFrom');
                $class_code = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'class_code');
            }
            $class = DbHelper::getColumnValue('glifeclass', 'class_code', $class_code, 'Description');

            $res = array(
                'success' => true,
                'class' => $class,
                'lblAllMembers' => $TotalMembers,
                'TotalClaims' => $TotalClaimsVal,
                'PolicyNo' => $PolicyNo,
                'DateFrom' => $DateFrom
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

    //get total broker schemes
    public function getBrokerTotSchemes(Request $request)
    {
        try {
            $res = array();

            $broker_id = $request->input('broker_id');
            //$schemeId = DbHelper::getColumnValue('polschemeinfo', 'policy_no',$scheme_no,'schemeID');

            //$sql="SELECT COUNT(p.MemberId) as total FROM glmembersinfo p WHERE p.status='001' and SchemeID=$schemeId";
            $sql = "SELECT COUNT(p.schemeID) as total FROM polschemeinfo p WHERE p.StatusCode='001' AND p.interm_ID=$broker_id";
            $ActiveMembers = DbHelper::getTableRawData($sql);


            //health questionnaire
            $res = array(
                'success' => true,
                'lblAllMembers' => $ActiveMembers[0]->total
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

    //get claims
    public function getClaimsTotals(Request $request)
    {
        try {
            $res = array();

            $n = $request->input('n');
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (!isset($date_from) || !isset($date_to)) {
                $date_from = date("Y-m-d");
                $date_to = date("Y-m-d");
            }

            if ($n == 1) {
                $sql = "SELECT COUNT(p.id) AS total FROM eClaimsEntries p 
                    WHERE p.PolicyId IS NOT NULL AND (p.RequestDate BETWEEN '$date_from' AND '$date_to')";
                $lblTotalClaimsReceived = DbHelper::getTableRawData($sql);

                $sql = "SELECT COUNT(p.id) AS total FROM claim_notificationinfo p 
                    WHERE ((p.pay_due_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment > 0) 
					OR (p.canceled_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment=0))";
                $lblTotalProcessedClaims = DbHelper::getTableRawData($sql);

                $sql = "SELECT ROUND(SUM(p.net_payment), 2) AS total FROM claim_notificationinfo p 
                    WHERE ((p.pay_due_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment > 0))";
                $lblPaidClaimsAmount = DbHelper::getTableRawData($sql);
            } else if ($n == 2) {
                $sql = "SELECT COUNT(p.id) AS total FROM eClaimsEntries p 
                    WHERE p.MicroPolicy IS NOT NULL AND 
                    (p.RequestDate BETWEEN '$date_from' AND '$date_to')";
                $lblTotalClaimsReceived = DbHelper::getTableRawData($sql);

                $sql = "SELECT COUNT(p.id) AS total FROM MicroClaimsInfo p 
                    WHERE ((p.pay_due_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment > 0) 
					OR (p.canceled_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment=0))";
                $lblTotalProcessedClaims = DbHelper::getTableRawData($sql);

                $sql = "SELECT ROUND(SUM(p.net_payment), 2) AS total FROM MicroClaimsInfo p 
                    WHERE ((p.pay_due_date BETWEEN '$date_from' AND '$date_to' AND p.net_payment > 0))";
                $lblPaidClaimsAmount = DbHelper::getTableRawData($sql);
            }

            $res = array(
                'success' => true,
                'lblTotalClaimsReceived' => number_format($lblTotalClaimsReceived[0]->total),
                'lblTotalProcessedClaims' => number_format($lblTotalProcessedClaims[0]->total),
                'lblPaidClaimsAmount' => number_format($lblPaidClaimsAmount[0]->total),
                //'lblPaidMicroClaimsAmount' => number_format($lblPaidMicroClaimsAmount[0]->total)
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

    //get policy admin
    public function getPolicyAdminTotals(Request $request)
    {
        try {
            $res = array();

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (!isset($date_from) || !isset($date_to)) {
                $date_from = date("Y-m-d");
                $date_to = date("Y-m-d");
            }


            //Return
            $sql = "SELECT COUNT(p.id) AS total FROM eEndorsmentEntries p 
                WHERE p.PolicyNumber IS NOT NULL AND 
                p.RequestDate BETWEEN '$date_from' AND '$date_to'";
            $lblReceivedILEndorsements = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.id) AS total FROM EndorsementDashBoard p 
                WHERE (p.approvaldate BETWEEN '$date_from' AND '$date_to' OR 
                p.declinedate BETWEEN '$date_from' AND '$date_to')";
            $lblProcessedILEndorsements = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.id) AS total FROM eEndorsmentEntries p 
                WHERE p.MicroPolicy IS NOT NULL AND 
                p.RequestDate BETWEEN '$date_from' AND '$date_to'";
            $lblReceivedMicroEndorsements = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.id) AS total FROM MicroPolicyEndorsement p 
                WHERE (p.ApprovalDate BETWEEN '$date_from' AND '$date_to')";
            $lblProcessedMicroEndorsements = DbHelper::getTableRawData($sql);



            $res = array(
                'success' => true,
                'lblReceivedILEndorsements' => number_format($lblReceivedILEndorsements[0]->total),
                'lblProcessedILEndorsements' => number_format($lblProcessedILEndorsements[0]->total),
                'lblReceivedMicroEndorsements' => number_format($lblReceivedMicroEndorsements[0]->total),
                'lblProcessedMicroEndorsements' => number_format($lblProcessedMicroEndorsements[0]->total)
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

    //get underwriting
    public function getUnderwritingTotals(Request $request)
    {
        try {
            $res = array();

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (!isset($date_from) || !isset($date_to)) {
                $date_from = date("Y-m-d");
                $date_to = date("Y-m-d");
            }


            //Return
            //1. Total active Individual Life policies
            $sql = "SELECT COUNT(p.proposal_no) AS total FROM proposalinfo p 
                WHERE p.AppraisalDate IS NULL AND p.proposal_date BETWEEN '$date_from' AND '$date_to'";
            $lblReceivedILProposals = DbHelper::getTableRawData($sql);
            //2. Total active Micro policies
            $sql = "SELECT COUNT(p.proposal_no) AS total FROM proposalinfo p 
                WHERE p.policy_no IS NOT NULL AND p.proposal_date BETWEEN '$date_from' AND '$date_to'";
            $lblAppraisedILPolicies = DbHelper::getTableRawData($sql);
            //3. Total Active Group Life schemes
            $sql = "SELECT COUNT(p.ProposalNumber) AS total FROM MicroProposalInfo p 
                WHERE p.AppraisalDate IS NULL AND p.ProposalDate BETWEEN '$date_from' AND '$date_to'";
            $lblMicroProposals = DbHelper::getTableRawData($sql);
            //4. Total Active Agents..
            $sql = "SELECT COUNT(p.ProposalNumber) AS total FROM MicroProposalInfo p 
                WHERE p.AppraisalDate IS NOT NULL AND p.ProposalDate BETWEEN '$date_from' AND '$date_to'";
            $lblAppraisedMicroPolicies = DbHelper::getTableRawData($sql);


            //health questionnaire
            $res = array(
                'success' => true,
                'lblReceivedILProposals' => number_format($lblReceivedILProposals[0]->total),
                'lblAppraisedILPolicies' => number_format($lblAppraisedILPolicies[0]->total),
                'lblMicroProposals' => number_format($lblMicroProposals[0]->total),
                'lblAppraisedMicroPolicies' => number_format($lblAppraisedMicroPolicies[0]->total),
                //'InActiveAgents' => $PendingProposals[0]->total,
                'PaidCliams' => 0, //$ActiveClients[0]->total,
                'PaidPremiums' => 0, //$ActiveClients[0]->total,
                //the piecharts.....
                'MainBarChart' => 0 //$PendingProposals[0]->total,
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

    //get mainDashboard
    public function getMainDashboardTotals(Request $request)
    {
        try {
            $res = array();

            //$agent_no = $request->input('agent_no');

            //Return
            //1. Total active Individual Life policies
            $sql = 'SELECT COUNT(p.id) AS total FROM polinfo p 
                    INNER JOIN planinfo d ON d.plan_code=p.plan_code
                    WHERE p.status_code=10 AND d.microassurance=0';
            $ActiveILPolicies = DbHelper::getTableRawData($sql);
            //2. Total active Micro policies
            $sql = "SELECT COUNT(d.Id) AS total FROM MicroPolicyInfo d
                INNER JOIN planinfo p ON d.[Plan]=p.plan_code 
                WHERE d.Status=10 AND p.microassurance=1";
            $ActiveMicroPolicies = DbHelper::getTableRawData($sql);
            //3. Total Active Group Life schemes
            $sql = "SELECT COUNT(p.schemeID) AS total FROM polschemeinfo p WHERE p.renewed=0 AND 
            GETDATE() BETWEEN p.DateFrom AND p.End_date";
            $ActiveGroupPolicies = DbHelper::getTableRawData($sql);
            //4. Total Active Agents..
            //$sql="SELECT COUNT(p.id) AS total FROM agents_info p WHERE p.IsActive=1";
            $sql = "SELECT count(p.id) as total,p.BusinessChannel,d.description AS AgentChannel 
                    FROM agents_info p 
                    INNER JOIN agentsChannel d on d.id=p.BusinessChannel
                    WHERE p.StatusCode=1 GROUP BY p.BusinessChannel,d.description";
            $ActiveAgents = DbHelper::getTableRawData($sql);


            //health questionnaire
            $res = array(
                'success' => true,
                'ActiveILPolicies' => number_format($ActiveILPolicies[0]->total),
                'ActiveMicroPolicies' => number_format($ActiveMicroPolicies[0]->total),
                'ActiveGroupPolicies' => number_format($ActiveGroupPolicies[0]->total),
                //'PendingProposals' => $PendingProposals[0]->total,
                'ActiveAgents' => $ActiveAgents,
                //'InActiveAgents' => $PendingProposals[0]->total,
                'PaidCliams' => 0, //$ActiveClients[0]->total,
                'PaidPremiums' => 0, //$ActiveClients[0]->total,
                //the piecharts.....
                'MainBarChart' => 0 //$PendingProposals[0]->total,
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

    //TODO - Get agents data...
    public function getAllAgentsData(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id'); //
            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');

            $sql = "SELECT COUNT(p.client_number) as total FROM clientinfo p INNER JOIN polinfo d ON p.client_number=d.client_number
                WHERE d.agent_no=$agent_id AND d.status_code=10";
            $AgentsData = DbHelper::getTableRawData($sql);


            //health questionnaire
            $res = array(
                'success' => true,
                'AgentsData' => $AgentsData
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


    //get agents dashboard data
    public function getAgentTotals(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id'); //
            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');

            $unitId = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'UnitName');
            $positionId = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'CurrentManagerLevel');

            $date_from = date("Y-m-01");
            $date_to = date("Y-m-d");

            if ($BusinessChannel == 5) { //micro
                //Return
                //1. Total active policies
                $sql = "SELECT COUNT(p.id) as total FROM MicroPolicyinfo p 
                    WHERE p.Agent=$agent_id AND p.[Status]=10 AND 
                    (CAST(p.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to')";
                $ActivePolicies = DbHelper::getTableRawData($sql);
                //2. Total pending proposals
                $sql = "SELECT COUNT(p.ID) as total FROM mob_prop_info p 
                    WHERE p.agent_code=$agent_id AND p.HasBeenPicked=0 AND 
                    (CAST(p.date_synced AS DATE) BETWEEN '$date_from' AND '$date_to')";
                $PendingProposals = DbHelper::getTableRawData($sql);
                //3. Total Active clients
                $sql = 'SELECT COUNT(p.client_number) as total FROM clientinfo p INNER JOIN MicroPolicyinfo d ON p.client_number=d."Client"
                WHERE d.Agent=' . $agent_id . ' AND d."Status"=10';
                $ActiveClients = DbHelper::getTableRawData($sql);
            } else {
                //1. Total active policies
                $UnitId = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'UnitName');
                $RegionId = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'RegionName');
                $PostionId = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'CurrentManagerLevel');
                $sql = "SELECT COUNT(p.id) as total FROM polinfo p ";
                if ($positionId == 4 || $positionId == 6) { //office manager
                    $sql .= " WHERE p.agent_no in (SELECT t2.id FROM agents_info t2 WHERE t2.UnitName=$unitId) ";
                } else if ($positionId == 7) { //sector manager
                    //TODO - add the inner joins here....
                    $sql .= " WHERE p.agent_no in (SELECT t2.id FROM agents_info t2 WHERE t2.RegionName=$RegionId) ";
                } else {
                    $sql .= " WHERE p.agent_no IN 
                    (SELECT t2.id  FROM agents_info t2 WHERE t2.RecruitedBy=$agent_id OR p.agent_no=$agent_id)";
                }
                $sql .= " AND 
                (CAST(p.issued_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
                //echo $sql;
                $ActivePolicies = DbHelper::getTableRawData($sql);
                //2. Total pending proposals
                $sql = "SELECT COUNT(p.proposal_no) as total FROM proposalinfo p ";
                if ($positionId == 4 || $positionId == 6) { //office manager
                    $sql .= " WHERE p.agent_no in (SELECT t2.id  FROM agents_info t2 WHERE t2.UnitName=$unitId)";
                } else if ($positionId == 7) { //sector manager
                    //TODO - add the inner joins here....
                    $sql .= " WHERE p.agent_no in (SELECT t2.id  FROM agents_info t2 WHERE t2.RegionName=$RegionId)";
                } else {
                    $sql .= " WHERE p.agent_no IN 
                    (SELECT t2.id  FROM agents_info t2 WHERE t2.RecruitedBy=$agent_id OR t2.id=$agent_id)";
                }
                $sql .= " AND 
                    (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to')";
                $PendingProposals = DbHelper::getTableRawData($sql);
                //3. Total Active clients
                $sql = "SELECT COUNT(p.client_number) as total FROM clientinfo p INNER JOIN polinfo d ON p.client_number=d.client_number
                WHERE d.agent_no=$agent_id AND d.status_code=10";
                $ActiveClients = DbHelper::getTableRawData($sql);

                //$RecruitedBy = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'mobile');

                //TODO - 
                $main_sql = "SELECT t1.AgentNoCode,t1.name, t1.id, t1.CurrentManagerLevel,t2.description 'il_position',t3.description 'micro_position',
                    t1.UnitName, t4.description 'office_name', t1.Ismanager, t1.StatusCode,
                    t5.Description 'status_name', FORMAT(t1.appointed_on, 'dd/MM/yyyy') AS appointed_on,
                    t6.description AS Region, t7.description 'Branch', t8.Description 'Sector'  
                    FROM agents_info t1 
                    LEFT JOIN ManagerPromotionLevel t2 ON t1.CurrentManagerLevel=t2.id
                    LEFT JOIN ManagerPromotionLevelMicro t3 ON t1.CurrentManagerLevelMicro=t3.id
                    LEFT JOIN AgentsunitsInfo t4 ON t1.UnitName=t4.id
                    LEFT JOIN AgentstatusInfo t5 ON t1.StatusCode=t5.id
                    LEFT JOIN Towns t6 ON t1.RegionName=t6.id
                    LEFT JOIN AgentsBranchInfo t7 ON t7.id=t4.AgentsBranchIdKey
                    LEFT JOIN AgentsRegionInfo t8 ON t7.AgentsRegionIdKey=t8.id";


                $RecruitedAgents = array();
                $DirectAgents = array();
                if ($PostionId == 7 || $PostionId == "7") {
                    $BranchId = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $unitId, 'AgentsBranchIdKey');
                    $unitId = DbHelper::getColumnValue('AgentsBranchInfo', 'id', $BranchId, 'AgentsRegionIdKey');
                    //$office = DbHelper::getColumnValue('AgentsRegionInfo', 'id', $unitId, 'Description');
                    //sector manager
                    $sql = $main_sql . " WHERE t8.id=$unitId";
                    $DirectAgents = DbHelper::getTableRawData($sql);
                } else if ($PostionId == 8 || $PostionId == "8") {
                    //CSO
                    $sql = $main_sql . " WHERE t1.BusinessChannel=3";
                    $DirectAgents = DbHelper::getTableRawData($sql);
                } else {
                    //1. fetch recruited by
                    $sql = $main_sql . " WHERE t1.RecruitedBy=$agent_id";
                    $RecruitedAgents = DbHelper::getTableRawData($sql);
                    //2. fetch direct agents
                    $sql = $main_sql . " WHERE t1.UnitName=$UnitId";
                    $DirectAgents = DbHelper::getTableRawData($sql);
                }
            }


            //health questionnaire
            $res = array(
                'success' => true,
                'ActivePolicies' => $ActivePolicies[0]->total,
                'PendingProposals' => $PendingProposals[0]->total,
                'ActiveClients' => $ActiveClients[0]->total,
                'RecruitedAgents' => $RecruitedAgents,
                'DirectAgents' => $DirectAgents
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
    //get sales between dates....
    public function getSalesBreakdown(Request $request)
    {
        try {
            $res = array();

            //AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $n = $request->input('n');

            if (!isset($date_from) || !isset($date_from)) {
                $date_from = '1985-01-01';
                $date_to = date("Y-m-d");
                if ($n == "1") {
                    $date_from = date("Y-m-d");
                    $date_to = date("Y-m-d");
                }
            }

            $micro_sql = "SELECT 'MICRO' AS source_type, d.description,d.PlanDesc, p.[Plan] AS plan_code, 
                COUNT(p.Id) as total,SUM(p.ModalPremium) AS TotalPremium FROM MicroPolicyInfo p
                LEFT JOIN planinfo d ON d.plan_code = p.[Plan]
                WHERE (p.[Status]=10 AND p.[Plan] IS NOT NULL AND d.microassurance=1)";
            if (isset($date_from) && isset($date_to)) {
                $micro_sql .= " AND CAST(p.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to'";
            }
            $micro_sql .= "GROUP BY d.description,d.PlanDesc, p.[Plan]";

            $MicroProducts = DbHelper::getTableRawData($micro_sql);

            $sql_micro_totals = "SELECT p.Id  FROM MicroPolicyInfo p 
                WHERE (p.[Status]=10 AND p.[Plan] IS NOT NULL) ";


            $life_sql = "SELECT 'INDIVIDUAL LIFE'AS source_type,
                        d.description,
                        d.PlanDesc,
                        p.plan_code,
                        p.pay_mode,
                        e.coverperiod,
                        COUNT(p.id)as total,
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium 
                        FROM polinfo p 
                        INNER JOIN planinfo d ON d.plan_code=p.plan_code 
                        INNER JOIN paymentmodeinfo e ON e.id=p.pay_mode";
            if (isset($date_from) && isset($date_to)) {
                $life_sql .= " AND CAST(p.issued_date AS DATE) BETWEEN '$date_from' AND '$date_to'";
            }
            $life_sql .= " GROUP BY d.description,d.PlanDesc,p.plan_code,p.pay_mode,e.coverperiod";

            $LifeProducts = DbHelper::getTableRawData($life_sql);

            $GroupProducts = [];

            $sql_life_totals = "SELECT p.id FROM polinfo p
                WHERE (p.[status_code]=10 AND p.plan_code IS NOT null) 
                AND p.issued_date BETWEEN '$date_from' AND '$date_to' ";


            //$sql = $micro_sql." UNION ALL ".$life_sql;

            $sql_totals = "SELECT COUNT(*) as totals
            FROM (" . $sql_micro_totals . " UNION " . $sql_life_totals . ") as union_query";


            $Totals = DbHelper::getTableRawData($sql_totals);

            $res = array(
                'success' => true,
                'LifeProducts' => $LifeProducts,
                'MicroProducts' => $MicroProducts,
                'GroupProducts' => $GroupProducts,
                'Totals' => $Totals
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

    //getSalesGridRange
    public function getSalesGridRange(Request $request)
    {
        try {
            $res = array();

            //AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            // if(!isset($date_from) || !isset($date_from)){
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // }

            $micro_submitted_sql = "SELECT 
                        'MICRO' AS source_type, 
                        d.description, d.PlanDesc, p.[Plan] as plan_code, 
                        COUNT(p.ProposalNumber) as total, 
                        1 AS currency,
                        ROUND(SUM(p.ModalPremium/e.coverperiod),2) AS TotalPremium, 
                        ROUND(SUM((p.ModalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium 
                        FROM MicroProposalInfo p
                INNER JOIN planinfo d ON d.plan_code = p.[Plan]
                INNER JOIN paymentmodeinfo e ON e.id=p.PayMode
                WHERE (p.[Plan] IS NOT NULL AND d.microassurance=1) ";
            if (isset($date_from) && isset($date_to)) {
                $micro_submitted_sql .= " AND CAST(p.created_on AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $micro_submitted_sql .= " GROUP BY d.description,d.PlanDesc, p.[Plan]";

            $MicroSubmittedCases = DbHelper::getTableRawData($micro_submitted_sql);

            /*$il_submitted_sql = "SELECT 
                        'INDIVIDUAL LIFE' AS source_type, 
                        d.description, d.PlanDesc, p.plan_code,
                        COUNT(p.proposal_no) as total, 
                        1 AS currency,
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium, 
                        ROUND(SUM((p.TotalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium 
                        FROM proposalinfo p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                INNER JOIN paymentmodeinfo e ON e.id=p.pay_mode
                WHERE (d.microassurance=0 AND d.IsCreditLife=0) ";
            if (isset($date_from) && isset($date_to)) {
                $il_submitted_sql .= " AND CAST(p.created_on AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }*/

            $il_submitted_sql = "SELECT 
                        'INDIVIDUAL LIFE' AS source_type, 
                        d.description, d.PlanDesc, p.plan_code,
                        COUNT(p.proposal_no) as total, 
                        1 AS currency,
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium, 
                        ROUND(SUM((p.TotalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium 
                        FROM mob_prop_info p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                LEFT JOIN paymentmodeinfo e ON e.id=p.paymode_code
                WHERE (d.microassurance=0 AND d.IsCreditLife=0) ";
            if (isset($date_from) && isset($date_to)) {
                $il_submitted_sql .= " AND CAST(p.date_synced AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $il_submitted_sql .= " GROUP BY  d.description,d.PlanDesc, p.plan_code";
            $ILSubmittedCases = DbHelper::getTableRawData($il_submitted_sql);

            $cl_il_submitted_sql = "SELECT 
                        'CREDIT LIFE' AS source_type, 
                        d.description, d.PlanDesc, p.plan_code,
                        COUNT(p.proposal_no) as total, 
                        1 AS currency,
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium,
                        ROUND(SUM((p.TotalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium 
                        FROM proposalinfo p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                INNER JOIN paymentmodeinfo e ON e.id=p.pay_mode
                WHERE (d.IsCreditLife=1) ";
            if (isset($date_from) && isset($date_to)) {
                $cl_il_submitted_sql .= " AND CAST(p.created_on AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $cl_il_submitted_sql .= " GROUP BY  d.description,d.PlanDesc, p.plan_code";
            $CLILSubmittedCases = DbHelper::getTableRawData($cl_il_submitted_sql);

            $micro_sql = "SELECT 
                            'MICRO' AS source_type, 
                            d.description,d.PlanDesc, p.[Plan] AS plan_code, 
                            COUNT(p.Id) as total, 
                            1 AS currency,
                            ROUND(SUM(p.ModalPremium),2) AS TotalPremium,
                            ROUND(SUM((p.ModalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium  
                            FROM MicroPolicyInfo p
                INNER JOIN planinfo d ON d.plan_code = p.[Plan]
                INNER JOIN paymentmodeinfo e ON e.id=p.PayMode
                WHERE (p.[Status]=10 AND p.[Plan] IS NOT NULL AND d.microassurance=1)";
            if (isset($date_from) && isset($date_to)) {
                $micro_sql .= " AND CAST(p.EffectiveDate AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $micro_sql .= " GROUP BY d.description,d.PlanDesc, p.[Plan]";
            $MicroInceptedCases = DbHelper::getTableRawData($micro_sql);


            $life_sql = "SELECT 
                        'INDIVIDUAL LIFE' AS source_type, 
                        d.description, d.PlanDesc, p.plan_code, 
                        COUNT(p.id) as total, 
                        p.currency,
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium,
                        ROUND(SUM((p.TotalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium 
                        FROM polinfo p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                INNER JOIN paymentmodeinfo e ON e.id=p.pay_mode
                WHERE (d.microassurance=0 AND d.IsCreditLife=0) ";
            if (isset($date_from) && isset($date_to)) {
                $life_sql .= " AND CAST(p.issued_date AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $life_sql .= " GROUP BY d.description, d.PlanDesc, p.plan_code,p.currency";
            $ILInceptedCases = DbHelper::getTableRawData($life_sql);

            $cl_life_sql = "SELECT 
                        'CREDIT LIFE' AS source_type, 
                        d.description, d.PlanDesc, p.plan_code, 
                        p.currency,
                        COUNT(p.id) as total, 
                        ROUND(SUM(p.TotalPremium/e.coverperiod),2) AS TotalPremium,
                        ROUND(SUM((p.TotalPremium/e.coverperiod) * 12),2) AS YearlyTotalPremium  
                        FROM polinfo p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                INNER JOIN paymentmodeinfo e ON e.id=p.pay_mode
                WHERE (d.microassurance=0 AND d.IsCreditLife=1) ";
            if (isset($date_from) && isset($date_to)) {
                $cl_life_sql .= " AND CAST(p.issued_date AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $cl_life_sql .= " GROUP BY d.description, d.PlanDesc, p.plan_code, p.currency";

            $CLILInceptedCases = DbHelper::getTableRawData($cl_life_sql);

            //GL incepted cases
            /*$gl_life_sql = "SELECT 'GROUP LIFE' AS source_type, 
                        p.class_code, 
                        p.currency_code 'currency',
                        p.pay_mode,
                        COUNT(p.schemeID) as total,
                        ROUND(SUM(p.total_prem),2) AS TotalPremium
                        ROUND(SUM(
                            CASE 
                                WHEN p.pay_mode = 3 THEN p.total_prem * 12
                                WHEN p.pay_mode = 4 THEN p.total_prem * 4
                                WHEN p.pay_mode = 5 THEN p.total_prem * 2
                                ELSE p.total_prem
                            END
                        ), 2) AS YearlyTotalPremium  
                        FROM polschemeinfo p
                INNER JOIN glifeclass d ON d.class_code=p.class_code
                WHERE 1=1 ";*/
                
            $gl_life_sql = "SELECT 
                        'GROUP LIFE' AS source_type, 
                        p.class_code, d.Description 'description',
                        p.currency_code 'currency',
                        COUNT(p.schemeID) as total,
                        ROUND(SUM(
                            CASE 
                                WHEN p.pay_mode = 3 THEN p.total_prem * 12
                                WHEN p.pay_mode = 4 THEN p.total_prem * 4
                                WHEN p.pay_mode = 5 THEN p.total_prem * 2
                                ELSE p.total_prem
                            END
                        ), 2) AS YearlyTotalPremium  
                        FROM polschemeinfo p
                        INNER JOIN glifeclass d ON d.class_code=p.class_code
                WHERE 1=1 ";
            if (isset($date_from) && isset($date_to)) {
                $gl_life_sql .= " AND CAST(p.DateFrom AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $gl_life_sql .= " GROUP BY p.class_code, p.currency_code, d.Description";

            $GLInceptedCases = DbHelper::getTableRawData($gl_life_sql);


            //GL renewed cases
            $gl_renewed_sql = "SELECT 
                        'GROUP LIFE' AS source_type, 
                        p.class_code, d.Description 'description',
                        p.currency_code 'currency',
                        COUNT(p.schemeID) as total,
                        ROUND(SUM(
                            CASE 
                                WHEN p.pay_mode = 3 THEN p.total_prem * 12
                                WHEN p.pay_mode = 4 THEN p.total_prem * 4
                                WHEN p.pay_mode = 5 THEN p.total_prem * 2
                                ELSE p.total_prem
                            END
                        ), 2) AS YearlyTotalPremium
                        FROM polschemeinfo p
                        INNER JOIN glifeclass d ON d.class_code=p.class_code
                WHERE 1=1 ";
            if (isset($date_from) && isset($date_to)) {
                $gl_renewed_sql .= " AND CAST(p.DateFrom AS DATE) 
                    BETWEEN '$date_from' AND '$date_to'";
            }
            $gl_renewed_sql .= " GROUP BY p.class_code, p.currency_code, d.Description";

            $GLRenewedCases = DbHelper::getTableRawData($gl_renewed_sql);



            $res = array(
                'success' => true,
                'ILSubmittedCases' => $ILSubmittedCases,
                'CLILSubmittedCases' => $CLILSubmittedCases,
                'ILInceptedCases' => $ILInceptedCases,
                'CLILInceptedCases' => $CLILInceptedCases,
                'MicroSubmittedCases' => $MicroSubmittedCases,
                'MicroInceptedCases' => $MicroInceptedCases,
                'GLInceptedCases' => $GLInceptedCases,
                'GLRenewedCases' => $GLRenewedCases
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

    public function getClaimEndorseCounts(Request $request)
    {
        try {
            $res = array();

            //AND (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to')
            $n = $request->input('n');
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $source_type = $request->input('source_type');

            // if(!isset($date_from) || !isset($date_from)){
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // }

            if($source_type == "1" || $source_type == "2"){
                if ($n == "1") {
                    $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.claim_type,
                            COUNT(p.id) AS total
                            FROM eClaimsEntries p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' AND 
                            p.statuscode <> 14 AND p.statuscode <> 3 ";
                    if ($source_type == "1") {
                        $sql .= " AND (p.PolicyId IS NOT NULL AND p.MicroPolicy IS NULL)";
                    }
                    if ($source_type == "2") {
                        $sql .= " AND (p.MicroPolicy IS NOT NULL AND p.PolicyId IS NULL)";
                    }
                    $sql  .= " GROUP BY p.claim_type";
                }
                if ($n == "2") {
                    if ($source_type == "1") {
                        $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.claim_type,
                            COUNT(p.id) AS total
                            FROM claim_notificationinfo p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.processed=1 
                            AND p.IsCancelled=0 
                            AND p.isdecline=0
                            GROUP BY p.claim_type";
                    }
                    if ($source_type == "2") {
                        $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.ClaimType AS claim_type,
                            COUNT(p.Id) AS total
                            FROM MicroClaimNotification p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.IsProcessed=1 
                            AND p.IsCancelled=0 
                            AND p.IsDeclined=0
                            GROUP BY p.ClaimType";
                    }
                }
                if ($n == "3") {
                    if ($source_type == "1") {
                        $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.claim_type,
                            p.currency,
                            COUNT(p.id) AS total,
                            SUM(p.net_payment) AS TotalPayment
                            FROM claim_notificationinfo p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.processed=1 
                            AND p.Approved=1
                            AND p.IsCancelled=0 
                            AND p.isdecline=0
                            GROUP BY p.claim_type,p.currency";
                    }
                    if ($source_type == "2") {
                        $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.ClaimType AS claim_type,
                            1 AS currency,
                            COUNT(p.id) AS total,
                            SUM(p.NetPayment) AS TotalPayment
                            FROM MicroClaimNotification p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.IsProcessed=1 
                            AND p.Approved=1
                            AND p.IsCancelled=0 
                            AND p.IsDeclined=0
                            GROUP BY p.ClaimType";
                    }
                }
                if ($n == "4") {
                    $sql = "SELECT 
                            'ENDORSEMENTS' AS source_type,
                            p.Endorsementtype,
                            COUNT(p.id) AS total
                            FROM eEndorsmentEntries p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.StatusDescription <> 'DRAFT' AND p.StatusDescription <> 'CANCELLED' ";
                    if ($source_type == "1") {
                        $sql .= " AND p.PolicyNumber IS NOT NULL AND p.MicroPolicy IS NULL";
                    }
                    if ($source_type == "2") {
                        $sql .= " AND p.MicroPolicy IS NOT NULL";
                    }
                    $sql .= " GROUP BY p.Endorsementtype";
                }
                if ($n == "5") {
                    $sql = "SELECT 
                            'ENDORSEMENTS' AS source_type,
                            p.Endorsementtype,
                            COUNT(p.id) AS total
                            FROM eEndorsmentEntries p 
                            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to' 
                            AND p.StatusDescription <> 'DRAFT' AND p.StatusDescription <> 'SUBMITTED'
                            GROUP BY p.Endorsementtype";
                }
    
                
            } else if($source_type == "3"){
                //display for group life
                if ($n == "1"){
                    $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.claim_type,
                            COUNT(p.id) AS total
                            FROM glifeclaimsnotification p 
                            LEFT JOIN ClaimHistoryInfo t2 ON p.id=t2.GlifeClaim_no
                            LEFT JOIN ClaimStatusInfo t3 ON t2.statuscode=t3.id
                            WHERE CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to'					
                            GROUP BY p.claim_type";
                }
                if ($n == "2"){
                    $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            p.claim_type,
                            COUNT(p.id) AS total
                            FROM glifeclaimsnotification p 
                            LEFT JOIN ClaimHistoryInfo t2 ON p.id=t2.GlifeClaim_no
                            LEFT JOIN ClaimStatusInfo t3 ON t2.statuscode=t3.id
                            WHERE CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to'
                                    AND 
                            t3.id<>6
                            GROUP BY p.claim_type";
                }
                if ($n == "3"){
                    $sql = "SELECT 
                            'CLAIMS' AS source_type,
                            1 AS currency,
                            p.claim_type,
                            COUNT(p.id) AS total,
                            SUM(p.net_payment) AS TotalPayment
                            FROM glifeclaimsnotification p 
                            LEFT JOIN ClaimHistoryInfo t2 ON p.id=t2.GlifeClaim_no
                            LEFT JOIN ClaimStatusInfo t3 ON t2.statuscode=t3.id
                            WHERE CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to'
                                    AND 
                            t3.id=6
                            GROUP BY p.claim_type";
                }
            }
            

            



            $ClaimEndorsements = DbHelper::getTableRawData($sql);



            $res = array(
                'success' => true,
                'ClaimEndorsements' => $ClaimEndorsements
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

    //TODO-MD's Main Dashboard
    public function getMDsDashboard(Request $request)
    {
        try {
            $res = array();


            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            //TODO - approvedILClaims, approvedMicroClaims, approvedGroupClaims
            //premiumIL, premiumMicro, premiumGroup

            $sql = "SELECT COUNT(p.id) AS total FROM claim_notificationinfo p WHERE 
                    (CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.processed=1 AND p.Approved=1
                    AND p.isdecline=0";
            $approvedILClaims = DbHelper::getTableRawData($sql);
            $sql = "SELECT COUNT(p.Id) AS total FROM MicroClaimNotification p WHERE 
                    (CAST(p.NotificationDate AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.IsProcessed=1 AND p.Approved=1 
                    AND p.IsDeclined=0";
            $approvedMicroClaims = DbHelper::getTableRawData($sql);
            $sql = "SELECT COUNT(t1.id) AS total FROM glifeclaimsnotification t1
                    INNER JOIN ClaimHistoryInfo t2 ON t1.id=t2.GlifeClaim_no
                    INNER JOIN ClaimStatusInfo t3 ON t2.statuscode=t3.id
                    WHERE t3.id=6 AND 
                    (CAST(t1.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
            $approvedGroupClaims = DbHelper::getTableRawData($sql);

            $sql = "SELECT SUM(t1.received) AS total FROM prmtransinfo t1 
                    WHERE (CAST(t1.payment_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
            $premiumIL = DbHelper::getTableRawData($sql);

            $sql = "SELECT SUM(t1.Received) AS total FROM MicroPremiumTransactions t1 
                    INNER JOIN MicroPolicyInfo t2 on t2.Id = t1.Policy
                    WHERE t1.PaymentStatus IN ('P','W','D','B') AND 
                    p.IsPremiumTransfer = 0 AND
                    CAST(t1.PaymentDate AS DATE) >= CAST(t2.EffectiveDate AS DATE) AND
                    (CAST(t1.PaymentDate AS DATE) BETWEEN '$date_from' AND '$date_to') ";
            $premiumMicro = DbHelper::getTableRawData($sql);

            $sql = "SELECT SUM(CASE WHEN t1.DrCr = 'CR' THEN t1.LocalAmount ELSE 0 END) AS total 
                    FROM acdetinfo t1 
                    WHERE (CAST(t1.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to')";
            $premiumGroup = DbHelper::getTableRawData($sql);


            

            $res = array(
                'success' => true,
                'approvedILClaims' => number_format($approvedILClaims[0]->total),
                'approvedMicroClaims' => number_format($approvedMicroClaims[0]->total),
                'approvedGroupClaims' => number_format($approvedGroupClaims[0]->total),
                'premiumIL' => number_format($premiumIL[0]->total),
                'premiumMicro' => number_format(abs($premiumMicro[0]->total)),
                'premiumGroup' => number_format(abs($premiumGroup[0]->total))
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


    //TODO - getActivitiesTotalsRange
    public function getActivitiesTotalsRange(Request $request)
    {
        try {
            $res = array();

            //AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $n = $request->input('n');

            // if(!isset($date_from) || !isset($date_from)){
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // } 
            if($n == "1" || $n == "2"){
                $sql = "SELECT COUNT(p.id) AS total FROM eClaimsEntries p 
                WHERE (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') 
                AND p.statuscode <> 14 ";
                if ($n == "1") {
                    $sql .= " AND (p.PolicyId IS NOT NULL AND p.MicroPolicy IS NULL)";
                }
                if ($n == "2") {
                    $sql .= " AND (p.MicroPolicy IS NOT NULL AND p.PolicyId IS NULL)";
                }
            } 
            if($n == "3"){
                $sql = "SELECT COUNT(p.id) AS total FROM glifeclaimsnotification p 
                WHERE (CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to') ";
            }
            

            $SubmittedClaims = DbHelper::getTableRawData($sql);

            //AND p.IsCancelled=0
            if ($n == "1") {
                $sql = "SELECT COUNT(p.id) AS total FROM claim_notificationinfo p WHERE 
                    (CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.processed=1 
                     AND p.isdecline=0";
            }
            //AND p.IsCancelled=0 
            if ($n == "2") {
                $sql = "SELECT COUNT(p.Id) AS total FROM MicroClaimNotification p WHERE 
                    (CAST(p.NotificationDate AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.IsProcessed=1 
                    AND p.IsDeclined=0";
            }

            if($n == "3"){
                $sql = "SELECT COUNT(t1.id) AS total FROM glifeclaimsnotification t1
                    WHERE t1.processed = 1 AND 
                    (CAST(t1.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
            }

            $ProcessedClaims = DbHelper::getTableRawData($sql);

            //AND p.IsCancelled=0 
            if ($n == "1") {
                $sql = "SELECT COUNT(p.id) AS total FROM claim_notificationinfo p WHERE 
                    (CAST(p.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.processed=1 AND p.Approved=1
                    AND p.isdecline=0";
            }
            //AND p.IsCancelled=0 
            if ($n == "2") {
                $sql = "SELECT COUNT(p.Id) AS total FROM MicroClaimNotification p WHERE 
                    (CAST(p.NotificationDate AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.IsProcessed=1 AND p.Approved=1 
                    AND p.IsDeclined=0";
            }

            if($n == "3"){
                $sql = "SELECT COUNT(t1.id) AS total FROM glifeclaimsnotification t1
                    INNER JOIN ClaimHistoryInfo t2 ON t1.id=t2.GlifeClaim_no
                    INNER JOIN ClaimStatusInfo t3 ON t2.statuscode=t3.id
                    WHERE t3.id=6 AND 
                    (CAST(t1.notification_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
            }
            $ApprovedClaims = DbHelper::getTableRawData($sql);

            //AND p.StatusDescription <> 'CANCELLED' 
            $sql = "SELECT COUNT(p.id) AS total FROM eEndorsmentEntries p WHERE 
                    (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.StatusDescription <> 'DRAFT' ";
            if ($n == "1") {
                $sql .= " AND (p.PolicyNumber IS NOT NULL AND p.MicroPolicy IS NULL)";
            }
            if ($n == "2") {
                $sql .= " AND (p.MicroPolicy IS NOT NULL)";
            }
            $RequestedEndorsement = DbHelper::getTableRawData($sql);

            //AND p.StatusDescription <> 'CANCELLED'
            $sql = "SELECT COUNT(p.id) AS total FROM eEndorsmentEntries p WHERE 
                    (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.StatusDescription <> 'DRAFT' AND p.StatusDescription <> 'SUBMITTED'";
            if ($n == "1") {
                $sql .= " AND (p.PolicyNumber IS NOT NULL AND p.MicroPolicy IS NULL)";
            }
            if ($n == "2") {
                $sql .= " AND (p.MicroPolicy IS NOT NULL)";
            }

            $ProcessedEndorsement = DbHelper::getTableRawData($sql);

            //$POSTYPE = DbHelper::getColumnValue('portal_users', 'username', $username, 'pos_type');
            $sql = "SELECT COUNT(p.id) AS total FROM pos_log p 
                    INNER JOIN portal_users d ON p.created_by=d.username
                    WHERE 
                    (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.Activity=3 ";
            if ($n == "1") {
                $sql .= " AND d.pos_type=1";
            }
            if ($n == "2") {
                $sql .= " AND d.pos_type=2";
            }
            $GeneralEnquiries = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.id) AS total FROM pos_log p  
                    INNER JOIN portal_users d ON p.created_by=d.username 
                    WHERE
                    (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') 
                    AND p.Activity=4";
            if ($n == "1") {
                $sql .= " AND d.pos_type=1";
            }
            if ($n == "2") {
                $sql .= " AND d.pos_type=2";
            }
            $ClientComplaints = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'SubmittedClaims' => number_format($SubmittedClaims[0]->total),
                'ProcessedClaims' => number_format($ProcessedClaims[0]->total),
                'ApprovedClaims' => number_format($ApprovedClaims[0]->total),
                'RequestedEndorsement' => number_format($RequestedEndorsement[0]->total),
                'ProcessedEndorsement' => number_format($ProcessedEndorsement[0]->total),
                'GeneralEnquiries' => number_format($GeneralEnquiries[0]->total),
                'ClientComplaints' => number_format($ClientComplaints[0]->total)
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


    //get sales between dates
    public function getSalesTotalsRange(Request $request)
    {
        try {
            $res = array();

            //AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $is_cso = $request->input('is_cso');

            // if(!isset($date_from) || !isset($date_from)){
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // }

            $sql = "SELECT COUNT(p.proposal_no) AS total FROM mob_prop_info p 
                INNER JOIN planinfo d ON d.plan_code=p.plan_code
                WHERE (CAST(p.date_synced AS DATE) BETWEEN '$date_from' AND '$date_to') AND 
                (d.microassurance=0 AND d.IsCreditLife=0)";
            if ($is_cso == "1") {
                $sql = "SELECT COUNT(p.proposal_no) AS total FROM mob_prop_info p 
                INNER JOIN planinfo d ON d.plan_code=p.plan_code
                WHERE (CAST(p.date_synced AS DATE) BETWEEN '$date_from' AND '$date_to') AND 
                d.microassurance=0 AND d.IsCreditLife=0";
            }
            $ILSubmittedCases = DbHelper::getTableRawData($sql);

            $cl_sql = "SELECT COUNT(p.proposal_no) AS total FROM proposalinfo p 
                INNER JOIN planinfo d ON d.plan_code=p.plan_code
                WHERE (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') AND 
                (d.IsCreditLife=1)";
            $CLILSubmittedCases = DbHelper::getTableRawData($cl_sql);

            $sql = "SELECT COUNT(p.ID) AS total FROM polinfo p 
                        WHERE CAST(p.issued_date AS DATE) BETWEEN '$date_from' AND '$date_to'";
            if ($is_cso == "1") {
                $sql = "SELECT COUNT(p.ID) AS total FROM polinfo p 
                        INNER JOIN planinfo d ON d.plan_code=p.plan_code
                        WHERE CAST(p.issued_date AS DATE) BETWEEN '$date_from' AND '$date_to' 
                        AND d.IsCreditLife=0";
            }
            $ILInceptedCases = DbHelper::getTableRawData($sql);

            //group business incepted cases...
            $sql = "SELECT COUNT(p.schemeID) AS total FROM polschemeinfo p 
                        WHERE CAST(p.DateFrom AS DATE) BETWEEN '$date_from' AND '$date_to'";
            $GLInceptedCases = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.schemeID) AS total FROM polschemeinfo p 
                        WHERE CAST(p.RenewalDate AS DATE) BETWEEN '$date_from' AND '$date_to'";
            $GLRenewalCases = DbHelper::getTableRawData($sql);


            $sql = "SELECT COUNT(p.ProposalNumber) AS total FROM MicroProposalInfo p 
                INNER JOIN planinfo d ON d.plan_code=p.[Plan]
                WHERE (CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to') AND 
                d.microassurance=1";
            $MicroSubmittedCases = DbHelper::getTableRawData($sql);

            $sql = "SELECT COUNT(p.Id) AS total FROM MicroPolicyInfo p 
                WHERE (CAST(p.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to')";
            $MicroInceptedCases = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'ILSubmittedCases' => number_format(((int)$ILSubmittedCases[0]->total + (int)$CLILSubmittedCases[0]->total)),
                'ILInceptedCases' => number_format($ILInceptedCases[0]->total),
                'MicroSubmittedCases' => number_format($MicroSubmittedCases[0]->total),
                'MicroInceptedCases' => number_format($MicroInceptedCases[0]->total),
                'GLInceptedCases' => number_format($GLInceptedCases[0]->total),
                'GLRenewalCases' => number_format($GLRenewalCases[0]->total)
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

    //fetch the accounting period
    public function fetchReinsurancePeriod(Request $request)
    {
        try {
            $Periods = array();
            
            $sql = "SELECT p.* FROM ReinsuranceAccountPeriod p ";

            $Periods = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'Periods' => $Periods
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

    //fetch all re-insurance data
    public function fetchReinsuranceData(Request $request)
    {
        try {
            $ReinsuranceData = array();
            $year = $request->input('account_year');
            $QuaterId = $request->input('QuaterId');
            $SourceType = $request->input('SourceType');

            $sql = "";
            if($SourceType == "2"){
                //creditLife
                $sql = "SELECT t2.policy_no 'POLICY NUMBER',t1.policyHolderName 'NAME',
                t1.local_sa 'SUM ASSURED',(t1.local_sa - t1.local_reins_sum_insured) 'CEDANT RETENTION', 
                t1.local_reins_sum_insured 'CEDED AMOUNT',t1.LocalReinsPremiumRates 'PREMIUM RATE', 
                t1.local_reins_premium 'R/I PREMIUM' 
                FROM polreinsmasterinfo t1 
                INNER JOIN polinfo t2 ON t1.policyId = t2.id 
                LEFT JOIN planinfo t3 ON t2.plan_code=t3.plan_code
                LEFT JOIN treatymasterinfo t4 ON t1.TreatyMasterId=t4.id
                WHERE t1.uw_year=".$year." AND t1.Quarter=".$QuaterId." AND t3.IsCreditLife=1";
            }
            if($SourceType == "1"){
                $sql = "SELECT t2.policy_no 'POLICY NUMBER',  t1.policyHolderName 'NAME',
                    t2.effective_date 'COMMENCEMENT DATE',t2.maturity_date 'MATURITY DATE',
                    t2.term_of_policy 'TERM', 
                    t1.local_sa 'SUM ASSURED',(t1.local_sa - t1.local_reins_sum_insured) 'RETENTION', 
                    t1.local_reins_sum_insured 'CEDED AMOUNT', 
                    t1.LocalReinsPremiumRates 'PREMIUM RATE',t1.local_reins_premium 'R/I PREMIUM' 
                    FROM polreinsmasterinfo t1 
                    INNER JOIN polinfo t2 ON t1.policyId = t2.id 
                    INNER JOIN planinfo t3 ON t2.plan_code=t3.plan_code
                    LEFT JOIN treatymasterinfo t4 ON t1.TreatyMasterId=t4.id 
                    WHERE t1.uw_year=".$year." AND t1.Quarter=".$QuaterId." AND t3.IsCreditLife=0";
            }
            

            // if (!isset($date_from) || !isset($date_to)) {
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // } 

           /* _myDataSet = GlobalCommonCodes.Mysql_Select_Querry("SELECT t2.policy_no 'POLICY NUMBER', " +
          " t1.policyHolderName 'NAME',t1.local_sa 'SUM ASSURED',(t1.local_sa - t1.local_reins_sum_insured) 'CEDANT RETENTION', " +
          " t1.local_reins_sum_insured 'CEDED AMOUNT',t1.LocalReinsPremiumRates 'PREMIUM RATE', t1.local_reins_premium 'R/I PREMIUM'  " +
          " FROM polreinsmasterinfo t1 " +
          " INNER JOIN polinfo t2 ON t1.policyId = t2.id  " +
          " LEFT JOIN planinfo t3 ON t2.plan_code=t3.plan_code " +
          " LEFT JOIN treatymasterinfo t4 ON t1.TreatyMasterId=t4.id " +
          " WHERE t1.ReinsurerName=" + ReinsurerVAR + " AND  t1.uw_year = " + VAR.account_year + " AND t1.Quarter =" + VAR.QuaterCode + " AND t1.currency=" + currency_code + " " +
          "  " + IndividualLifeFilterVAR + "" + CrediLifeFilterVAR + "" + BancassuranceFilterVAR + " " + CombinedVAR + "  ");

            
          _myDataSet = GlobalCommonCodes.Mysql_Select_Querry("SELECT t2.policy_no 'POLICY NUMBER',  t1.policyHolderName 'NAME',t6.[Desc] 'GENDER',t5.birthdate 'BIRTH DATE',t1.ageNextBirthday 'AGE NEXT BIRTHDAY',t7.occupation_name 'OCCUPATION', " +
         " t2.effective_date 'COMMENCEMENT DATE',t2.maturity_date 'MATURITY DATE',t2.term_of_policy 'TERM',  " +
         " t1.local_sa 'SUM ASSURED',(t1.local_sa - t1.local_reins_sum_insured) 'RETENTION', t1.local_reins_sum_insured 'CEDED AMOUNT',  " +
         " t1.LocalReinsPremiumRates 'PREMIUM RATE',t1.local_reins_premium 'R/I PREMIUM' " +
         " FROM polreinsmasterinfo t1 " +
         " INNER JOIN polinfo t2 ON t1.policyId = t2.id  " +
         " LEFT JOIN planinfo t3 ON t2.plan_code=t3.plan_code " +
         " LEFT JOIN treatymasterinfo t4 ON t1.TreatyMasterId=t4.id " +
         " LEFT JOIN clientinfo t5 ON t2.client_number=t2.client_number  " +
         " LEFT JOIN gender_info t6 ON t5.sex= t6.[Code]  " +
         " LEFT JOIN occupationinfo t7 ON t5.occupation_code= t7.occupation_code" +
         " WHERE t1.ReinsurerName=" + ReinsurerVAR + " AND  t1.uw_year = " + VAR.account_year + " AND t1.Quarter =" + VAR.QuaterCode + " AND t1.currency=" + currency_code + " " +
         "  " + IndividualLifeFilterVAR + "" + CrediLifeFilterVAR + "" + BancassuranceFilterVAR + " " + CombinedVAR + "  ");
            */

            

            if($sql != ""){
                $ReinsuranceData = DbHelper::getTableRawData($sql);
            }
            

            $res = array(
                'success' => true,
                'ReinsuranceData' => $ReinsuranceData
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

    //check if policy is micro...
    public function checkIsMicro(Request $request)
    {
        $policy_no = $request->input('policy_no');
        $isMicroObj = $this->smartlife_db->table('polinfo', 'g')
                ->join('planinfo as p', 'p.plan_code', '=', 'g.plan_code')
                ->where('g.policy_no', $policy_no)
                ->select('p.microassurance')
                ->first();
        
        
        if(!isset($isMicroObj)){
            //check micro...
            $isMicroObj = $this->smartlife_db->table('MicroPolicyInfo', 'g')
                ->join('planinfo as p', 'p.plan_code', '=', 'g.Plan')
                ->where('g.PolicyNumber', $policy_no)
                ->select('p.microassurance')
                ->first();
        }

        //check if its in mproposal
        if(!isset($isMicroObj)){
            $isMicroObj = $this->smartlife_db->table('mob_prop_info', 'g')
            ->join('planinfo as p', 'p.plan_code', '=', 'g.plan_code')
            ->where('g.proposal_no', $policy_no)
            ->select('p.microassurance')
            ->first();
        }

        return $res = array(
            'success' => true,
            'is_micro' => $isMicroObj->microassurance
        );
    }

    public function policyNoMicro(Request $request)
    {
        $policy_no = $request->input('policy_no');

        //1. Determine if its a policy...
        $PolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');       
        if(isset($PolicyId) && (int)$PolicyId > 0){
            $edwaPolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'EdwankosoPolicy');
            if(isset($edwaPolicyId) && (int)$edwaPolicyId > 0){
                $PlanId = DbHelper::getColumnValue('MicroPolicyInfo', 'Id', $edwaPolicyId, 'Plan');
                //fetch the policy_no
                if($PlanId == "13" || $PlanId == 13){
                    $policy_no = DbHelper::getColumnValue('MicroPolicyInfo', 'Id', $edwaPolicyId, 'PolicyNumber');
                }
            }
        } else{
            //its a proposal,
            $LinkedProposal = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $policy_no, 'LinkedProposal');
            if(isset($LinkedProposal)){
                $policy_no = $LinkedProposal;
            }
        }

        return $res = array(
            'success' => true,
            'policy_no' => $policy_no
        );
    }


    //fetch all premiums
    public function fetchPremiums(Request $request)
    {
        try {
            $Premiums = array();
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $period_year = $request->input('period_year');
            $period_month = $request->input('period_month');
            $SourceType = $request->input('SourceType');

            // if (!isset($date_from) || !isset($date_to)) {
            //     $date_from = date("Y-m-d");
            //     $date_to = date("Y-m-d");
            // }
            //(CAST(p.date_synced AS DATE) BETWEEN '$date_from' AND '$date_to')
            if($SourceType == "1"){
                $sql = "SELECT p.id,p.Currency_code,p.frontofficepolholder,p.EmpsumKey,p.[DeductBatch],
                p.policy_no,p.current_premiums,p.TotalPremium,p.received,p.Rider_Prem,p.investment_prem,
                p.TransferFee,p.pol_fee,p.modal_premium,p.payment_date,p.period_year,p.period_month
                FROM prmtransinfo p 
                WHERE 1=1 ";
            } else if($SourceType == "3"){
                //fetch Micro premiums...
                $sql = "SELECT p.id,p.Policy,d.PolicyNumber 'policy_no',p.ModalPremium 'TotalPremium',
                p.Received 'received',p.RiderPremium 'Rider_Prem',p.InvestmentPremium 'investment_prem',
					 '0' AS TransferFee,p.PolicyFee 'pol_fee','0' AS modal_premium,
                p.PaymentDate 'payment_date',p.PeriodYear 'period_year',p.PeriodMonth 'period_month'
                FROM MicroPremiumTransactions p 
                INNER JOIN MicroPolicyInfo d ON p.Policy=d.Id
                WHERE 1=1 ";
            }
            

            /*if(isset($period_year) && (int)$period_year > 0 ){
                if($SourceType == "1"){
                    $sql .= " AND p.period_year=".$period_year;
                } else if($SourceType == "3"){
                    $sql .= " AND p.PeriodYear=".$period_year;
                }
            }*/

            if(isset($period_month) && (int)$period_month > 0){
                if($SourceType == "1"){
                    $sql .= " AND p.period_month=".$period_month;
                } else if($SourceType == "3"){
                    $sql .= " AND p.PeriodMonth=".$period_month;
                }
            }

            if((isset($date_from) && $date_from != "NaN-NaN-NaN") && 
            (isset($date_to) && $date_to != "NaN-NaN-NaN") ){
                if($SourceType == "1"){
                    $sql .= " AND (p.payment_date BETWEEN '$date_from' AND '$date_to')";
                } else if($SourceType == "3"){
                    $sql .= " AND (p.PaymentDate BETWEEN '$date_from' AND '$date_to')";
                }
            }
            
            if($SourceType == "2"){
                /*$sql = "SELECT 
                    t3.[reference], 
                    t2.policy_no,
                    t4.[name], 
                    SUM(CASE WHEN t1.DrCr = 'DR' THEN t1.LocalAmount ELSE 0 END) AS Premium, 
                    SUM(CASE WHEN t1.DrCr = 'CR' THEN t1.LocalAmount ELSE 0 END) AS Receipts, 
                    SUM(t1.LocalAmount) AS OSAmount 
                FROM acdetinfo t1 
                INNER JOIN polschemeinfo t2 ON t1.schemeNo = t2.schemeID 
                INNER JOIN debitmastinfo t3 ON t1.Debit_ID = t3.id 
                INNER JOIN glifeclientinfo t4 ON t2.ClientNumber = t4.Id 
                WHERE 
                (CAST(t1.transDate AS DATE) BETWEEN '$date_from' AND '$date_to')
                GROUP BY t3.[reference], t1.Debit_ID, t4.[name], t2.policy_no";
                */

                $sql="SELECT   
                        t4.[name], 
                        SUM(CASE WHEN t1.DrCr = 'DR' THEN t1.LocalAmount ELSE 0 END) AS Premium, 
                        SUM(CASE WHEN t1.DrCr = 'CR' THEN t1.LocalAmount ELSE 0 END) AS Receipts, 
                        SUM(t1.LocalAmount) AS OSAmount,
                        t5.Description AS Product
                    FROM acdetinfo t1 
                    INNER JOIN polschemeinfo t2 ON t1.schemeNo = t2.schemeID 
                    INNER JOIN debitmastinfo t3 ON t1.Debit_ID = t3.id 
                    INNER JOIN glifeclientinfo t4 ON t2.ClientNumber = t4.Id 
                    INNER JOIN glifeclass t5 ON t5.class_code = t2.class_code 
                    WHERE (CAST(t1.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to')
                    GROUP BY t4.[name],t5.Description";
            } 


            $Premiums = DbHelper::getTableRawData($sql);

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
    //get All Sales for the a period per product(year & month)
    public function getAllSales(Request $request)
    {
        try {
            $res = array();

            //AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $n = $request->input('n');

            if (!isset($date_from) || !isset($date_from)) {
                $date_from = '1985-01-01';
                $date_to = date("Y-m-d");
                if ($n == "1") {
                    $date_from = date("Y-m-d");
                    $date_to = date("Y-m-d");
                }
            }

            $micro_sql = "SELECT 'MICRO' AS source_type, d.description,d.PlanDesc, p.[Plan] AS plan_code, 
                COUNT(p.Id) as total,SUM(p.ModalPremium) AS TotalPremium FROM MicroPolicyInfo p
                LEFT JOIN planinfo d ON d.plan_code = p.[Plan]
                WHERE (p.[Status]=10 AND p.[Plan] IS NOT NULL AND d.microassurance=1)";
            /*if(isset($date_from) && isset($date_to)){
                    $micro_sql .= " AND CAST(p.EffectiveDate AS DATE) BETWEEN '$date_from' AND '$date_to'";
                }*/
            $micro_sql .= "GROUP BY d.description,d.PlanDesc, p.[Plan]";

            $MicroProducts = DbHelper::getTableRawData($micro_sql);

            $sql_micro_totals = "SELECT p.Id  FROM MicroPolicyInfo p 
                WHERE (p.[Status]=10 AND p.[Plan] IS NOT NULL) ";


            $life_sql = "SELECT 'INDIVIDUAL LIFE' AS source_type, d.description, d.PlanDesc, p.plan_code, 
                COUNT(p.id) as total,SUM(p.TotalPremium) AS TotalPremium FROM polinfo p
                LEFT JOIN planinfo d ON d.plan_code = p.plan_code
                WHERE (p.[status_code]=10 AND d.microassurance=0)";
            // if(isset($date_from) && isset($date_to)){
            //     $life_sql .= " AND CAST(p.effective_date AS DATE) BETWEEN '$date_from' AND '$date_to'";
            // }
            $life_sql .= " GROUP BY d.description, d.PlanDesc, p.plan_code";
            $LifeProducts = DbHelper::getTableRawData($life_sql);

            $gl_sql = "SELECT 'GROUP LIFE' AS source_type, d.Description 'description', d.short_desc 'PlanDesc', p.class_code 'plan_code', 
                COUNT(p.schemeID) as total,SUM(p.total_prem) AS TotalPremium FROM polschemeinfo p
                LEFT JOIN glifeclass d ON d.class_code = p.class_code
                WHERE (p.renewed=0 AND (GETDATE() BETWEEN p.DateFrom AND p.End_date))
                GROUP BY d.Description, d.short_desc, p.class_code";
            $GroupProducts = DbHelper::getTableRawData($gl_sql);

            $sql_life_totals = "SELECT p.id FROM polinfo p
                WHERE (p.[status_code]=10 AND p.plan_code IS NOT null) 
                AND p.issued_date BETWEEN '$date_from' AND '$date_to' ";


            //$sql = $micro_sql." UNION ALL ".$life_sql;

            $sql_totals = "SELECT COUNT(*) as totals
            FROM (" . $sql_micro_totals . " UNION " . $sql_life_totals . ") as union_query";


            $Totals = DbHelper::getTableRawData($sql_totals);

            /////////agents data ////////////////
            $BusinessChannelId = DbHelper::getColumnValue('agentsChannel', 'IsForIndividualLife', 1, 'id');
            $sql_il_agents = "SELECT p.id,p.AgentNoCode,p.name,p.UnitName,p.CurrentManagerLevel,
                    d.description 'position', e.description 'office',
                    f.description 'Branch', g.Description 'Sector' 
                    FROM agents_info p 
                    LEFT JOIN ManagerPromotionLevel d ON d.id=p.CurrentManagerLevel
                    LEFT JOIN AgentsunitsInfo e ON e.id=p.UnitName
                    LEFT JOIN AgentsBranchInfo f ON f.id=e.AgentsBranchIdKey
                    LEFT JOIN AgentsRegionInfo g ON g.id=f.AgentsRegionIdKey 
                    WHERE p.BusinessChannel=$BusinessChannelId AND p.IsActive=1";
            $IlAgents = DbHelper::getTableRawData($sql_il_agents);

            $BusinessChannelId = DbHelper::getColumnValue('agentsChannel', 'IsforMicro', 1, 'id');
            $sql_micro_agents = "SELECT p.id,p.AgentNoCode,p.name,p.UnitNameMicro,p.CurrentManagerLevel,
                    d.description 'position', e.description 'office' 
                    FROM agents_info p 
                    LEFT JOIN ManagerPromotionLevel d ON d.id=p.CurrentManagerLevel
                    LEFT JOIN AgentsUnitsMicro e ON e.id=p.UnitNameMicro 
                    WHERE p.BusinessChannel=$BusinessChannelId AND p.IsActive=1";
            $MicroAgents = DbHelper::getTableRawData($sql_micro_agents);

            $BusinessChannelId = DbHelper::getColumnValue('agentsChannel', 'IsForBa', 1, 'id');
            $sql_il_agents = "SELECT p.id,p.AgentNoCode,p.name,p.UnitName,p.CurrentManagerLevel,
                    d.description 'position', e.description 'office' 
                    FROM agents_info p 
                    LEFT JOIN ManagerPromotionLevel d ON d.id=p.CurrentManagerLevel
                    LEFT JOIN AgentsunitsInfo e ON e.id=p.UnitName 
                    WHERE p.BusinessChannel=$BusinessChannelId AND p.IsActive=1";
            $BancaAgents = DbHelper::getTableRawData($sql_il_agents);
            /////////end of agents data////////

            $res = array(
                'success' => true,
                'LifeProducts' => $LifeProducts,
                'MicroProducts' => $MicroProducts,
                'GroupProducts' => $GroupProducts,
                'IlAgents' => $IlAgents,
                'MicroAgents' => $MicroAgents,
                'BancaAgents' => $BancaAgents,
                'Totals' => $Totals
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



    //get Unit Sales - filter(year & month)
    public function getUnitSales(Request $request)
    {
        try {
            $res = array();

            //get all totals for every agent - filter(year & month)
            $current_year = (int)date('Y');
            $current_month = (int)date('m');
            $last_day = date('t', strtotime("$current_year-$current_month"));

            $date_from = $request->input('date_from');
            if (!isset($date_from)) {
                $date_from = $current_year . '-0' . $current_month . '-01';
            }
            $date_to = $request->input('date_to');
            if (!isset($date_to)) {
                $date_to = $current_year . '-0' . $current_month . '-' . $last_day;
            }

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (!isset($date_from) || !isset($date_to)) {
                $date_from = date("Y-m-d");
                $date_to = date("Y-m-d");
            }

            //if($BusinessChannel == 5){
            //micro  AND p.[Status]=10
            //$date_from = '2023-01-01';
            //$date_to = '2023-04-07';
            /*
            $micro_sql = "SELECT 
                    t.id AS team_name,
                        t.description AS team, 
                    p.description AS product_name,
                        p.PlanDesc, 
                    COALESCE(COUNT(po.PolicyNumber), 0) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                CROSS JOIN planinfo p 
                LEFT JOIN agents_info m ON t.id = m.UnitName 
                LEFT JOIN MicroPolicyInfo po ON m.id = po.Agent AND p.plan_code = po.[Plan] AND po.[Status]=10  AND po.EffectiveDate BETWEEN '$date_from' AND '$date_to'
                WHERE p.is_active=1 AND p.microassurance=1 AND m.BusinessChannel=5 
                GROUP BY 
                    t.id, 
                    p.description,
                    t.description,
                    p.PlanDesc
                ORDER BY
                    t.id";

            $sql_micro_totals = "SELECT 
                    t.id AS team_name,
                    t.description AS team, 
                    COUNT(po.PolicyNumber) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                LEFT JOIN agents_info m ON t.id = m.UnitName 
                LEFT JOIN MicroPolicyInfo po ON m.id = po.Agent AND po.EffectiveDate BETWEEN '$date_from' AND '$date_to'
                WHERE m.BusinessChannel=5
                GROUP BY 
                    t.id,
                    t.description
                ORDER BY
                    t.id";

            $life_sql="SELECT 
                    t.id AS team_name,
                        t.description AS team, 
                    p.description AS product_name,
                        p.PlanDesc, 
                    COALESCE(COUNT(po.policy_no), 0) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                CROSS JOIN planinfo p
                LEFT JOIN agents_info m ON t.id = m.UnitName 
                LEFT JOIN polinfo po ON m.id = po.agent_no AND p.plan_code = po.plan_code AND po.status_code=10 AND po.effective_date BETWEEN '$date_from' AND '$date_to'
                WHERE p.is_active=1 AND p.microassurance=0 
                GROUP BY 
                    t.id, 
                    p.description,
                    p.PlanDesc,
                    t.description
                ORDER BY
                    t.id";

            $sql_life_totals = "SELECT 
                    t.id AS team_name,
                    t.description AS team, 
                    COUNT(po.policy_no) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                LEFT JOIN agents_info m ON t.id = m.UnitName 
                LEFT JOIN polinfo po ON m.id = po.agent_no AND po.effective_date BETWEEN '$date_from' AND '$date_to'
                WHERE m.BusinessChannel != 5
                    GROUP BY 
                    t.id,
                    t.description
                ORDER BY
                    t.id";
                    */
            $micro_sql = "SELECT 
                    t.id AS team_name,
                        t.description AS team, 
                    p.description AS product_name,
                        p.PlanDesc, 
                    COALESCE(COUNT(po.PolicyNumber), 0) AS total_sold,
                    SUM(po.ModalPremium) AS TotalPremium
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsOfficeMicro 
                ) t
                CROSS JOIN planinfo p 
                INNER JOIN agents_info m ON t.id = m.OfficeNameMicro 
                INNER JOIN MicroPolicyInfo po ON m.id = po.Agent AND p.plan_code = po.[Plan] AND po.[Status]=10  AND po.EffectiveDate BETWEEN '$date_from' AND '$date_to'
                WHERE p.is_active=1 AND p.microassurance=1 AND m.BusinessChannel=5 
                GROUP BY 
                    t.id, 
                    p.description,
                    t.description,
                    p.PlanDesc
                ORDER BY
                    t.id";

            $sql_micro_totals = "SELECT 
                    t.id AS team_name,
                    t.description AS team, 
                    COUNT(po.PolicyNumber) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                INNER JOIN agents_info m ON t.id = m.UnitName 
                INNER JOIN MicroPolicyInfo po ON m.id = po.Agent AND po.EffectiveDate BETWEEN '$date_from' AND '$date_to'
                WHERE m.BusinessChannel=5
                GROUP BY 
                    t.id,
                    t.description
                ORDER BY
                    t.id";

            $life_sql = "SELECT 
                    t.id AS team_name,
                        t.description AS team, 
                    p.description AS product_name,
                        p.PlanDesc, 
                    COALESCE(COUNT(po.policy_no), 0) AS total_sold,
                    SUM(po.TotalPremium) AS TotalPremium
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                CROSS JOIN planinfo p
                INNER JOIN agents_info m ON t.id = m.UnitName
                INNER JOIN polinfo po ON m.id = po.agent_no AND p.plan_code = po.plan_code AND po.status_code=10 AND po.issued_date BETWEEN '$date_from' AND '$date_to'
                WHERE p.is_active=1 AND p.microassurance=0 
                GROUP BY 
                    t.id, 
                    p.description,
                    p.PlanDesc,
                    t.description
                ORDER BY
                    t.id";

            $sql_life_totals = "SELECT 
                    t.id AS team_name,
                    t.description AS team, 
                    COUNT(po.policy_no) AS total_sold
                FROM (
                    SELECT DISTINCT id, description 
                    FROM AgentsUnitsInfo 
                ) t
                INNER JOIN agents_info m ON t.id = m.UnitName 
                INNER JOIN polinfo po ON m.id = po.agent_no AND po.issued_date BETWEEN '$date_from' AND '$date_to'
                WHERE m.BusinessChannel != 5
                    GROUP BY 
                    t.id,
                    t.description
                ORDER BY
                    t.id";

            $MicroUnitsTotals = DbHelper::getTableRawData($micro_sql);
            $MicroTotals = DbHelper::getTableRawData($sql_micro_totals);

            $lifeUnitsTotals = DbHelper::getTableRawData($life_sql);
            $LifeTotals = DbHelper::getTableRawData($sql_life_totals);

            $res = array(
                'success' => true,
                'MicroUnitsTotals' => $MicroUnitsTotals,
                'MicroTotals' => $MicroTotals,
                'lifeUnitsTotals' => $lifeUnitsTotals,
                'LifeTotals' => $LifeTotals
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


    //Agents products within a timeline.... 
    public function getAgentPerformance(Request $request)
    {
        try {
            $res = array();


            $current_year = (int)date('Y');
            $current_month = (int)date('m');
            $last_day = date('t', strtotime("$current_year-$current_month"));

            $date_from = $request->input('date_from');
            if (!isset($date_from)) {
                $date_from = $current_year . '-0' . $current_month . '-01';
            }
            $date_to = $request->input('date_to');
            if (!isset($date_to)) {
                $date_to = $current_year . '-0' . $current_month . '-' . $last_day;
            }

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (!isset($date_from) || !isset($date_to)) {
                $date_from = date("Y-m-d");
                $date_to = date("Y-m-d");
            }
            //if($BusinessChannel == 5){
            //micro  AND p.[Status]=10
            //$date_from = '2023-01-01';
            //$date_to = '2023-04-07';
            $sql_micro = "SELECT p.Agent AS agent_no,t.name AS agent_name,
            d.description AS plan_name, d.PlanDesc, p.[Plan] AS plan_code, 
            COUNT(p.id) as total,SUM(p.ModalPremium) AS TotalPremium FROM MicroPolicyInfo p
                INNER JOIN planinfo d ON d.plan_code = p.[Plan]
                INNER JOIN agents_info t ON p.Agent=t.id
                WHERE (p.[Status]=10) AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to'
                GROUP BY p.Agent,t.name,d.description,d.PlanDesc, p.[Plan]
                ORDER BY p.Agent";

            $sql_micro_totals = "SELECT COUNT(p.Id) AS totals  FROM MicroPolicyInfo p 
                    WHERE (p.[Status]=10) AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to' ";
            //}else{
            /*$date_from = '2000-01-01';
                $date_to = '2023-04-07';*/
            $sql_life = "SELECT p.agent_no,t.name AS agent_name,d.description AS plan_name, 
            d.PlanDesc, p.plan_code, COUNT(p.id) as total,SUM(p.TotalPremium) AS TotalPremium FROM polinfo p
                    INNER JOIN planinfo d ON d.plan_code = p.plan_code
                    INNER JOIN agents_info t ON p.agent_no=t.id
                    WHERE (p.[status_code]=10) AND p.issued_date BETWEEN '$date_from' AND '$date_to' 
                    GROUP BY p.agent_no,t.name,d.description,d.PlanDesc, p.plan_code
                    ORDER BY p.agent_no";

            $sql_life_totals = "SELECT COUNT(p.id) AS totals FROM polinfo p 
                    WHERE (p.[status_code]=10) AND p.issued_date BETWEEN '$date_from' AND '$date_to' ";
            //}
            //$Products = \DB::statement($sql, [$agent_id,$date_from,$date_to]);//DbHelper::getTableRawData($sql);

            $MicroAgents = DbHelper::getTableRawData($sql_micro);
            $MicroTotals = DbHelper::getTableRawData($sql_micro_totals);
            $LifeAgents = DbHelper::getTableRawData($sql_life);
            $LifeTotals = DbHelper::getTableRawData($sql_life_totals);

            $res = array(
                'success' => true,
                'MicroAgents' => $MicroAgents,
                'MicroTotals' => $MicroTotals,
                'LifeAgents' => $LifeAgents,
                'LifeTotals' => $LifeTotals
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


    //Agents products within a timeline.... 
    public function getAgentProducts(Request $request)
    {
        try {
            $res = array();

            //get all totals for every agent - filter(year & month)
            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'id', $agent_id, 'BusinessChannel');

            $current_year = (int)date('Y');
            $current_month = (int)date('m');
            $last_day = date('t', strtotime("$current_year-$current_month"));

            $date_from = $request->input('date_from');
            if (!isset($date_from)) {
                $date_from = $current_year . '-0' . $current_month . '-01';
            }
            $date_to = $request->input('date_to');
            if (!isset($date_to)) {
                $date_to = $current_year . '-0' . $current_month . '-' . $last_day;
            }
            //$date_from = '2022-01-01';
            //$date_to = '2023-04-07';
            /*if($current_month == 1){
                $current_month = 12;
                $current_year -= 1;
            }else{
                $current_month -= 1;
            }*/
            //
            /*
            $sql = "SELECT agent_code, PlanDesc, plan_code, COUNT(*) as total FROM mob_prop_info 
            WHERE agent_code=$agent_id AND date_synced BETWEEN '$date_from' AND '$date_to' GROUP BY agent_code,PlanDesc, plan_code";
            */
            /*$sql = "SELECT p.agent_code, d.PlanDesc, p.plan_code, COUNT(*) as total FROM mob_prop_info p
            INNER JOIN planinfo d ON d.plan_code = p.plan_code
            WHERE agent_code=$agent_id AND date_synced BETWEEN '$date_from' AND '$date_to' 
            GROUP BY p.agent_code,d.PlanDesc, p.plan_code";*/
            if ($BusinessChannel == 5) {
                //micro  AND p.[Status]=10
                /*$date_from = '2000-01-01';
                $date_to = '2023-04-07';*/
                $sql = "SELECT p.Agent AS agent_no, d.PlanDesc, p.[Plan] AS plan_code, COUNT(p.Id) as total FROM MicroPolicyInfo p
                INNER JOIN planinfo d ON d.plan_code = p.[Plan]
                WHERE (p.Agent=$agent_id AND p.[Status]=10) AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to' 
                GROUP BY p.Agent,d.PlanDesc, p.[Plan]";

                $sql_totals = "SELECT COUNT(p.Id) AS totals  FROM MicroPolicyInfo p 
                    WHERE (p.Agent=$agent_id AND p.[Status]=10) AND p.EffectiveDate BETWEEN '$date_from' AND '$date_to' ";
            } else {
                /*$date_from = '2000-01-01';
                $date_to = '2023-04-07';*/
                $sql = "SELECT p.agent_no, d.PlanDesc, p.plan_code, COUNT(p.id) as total FROM polinfo p
                INNER JOIN planinfo d ON d.plan_code = p.plan_code
                WHERE (p.agent_no=$agent_id AND p.[status_code]=10) AND p.issued_date BETWEEN '$date_from' AND '$date_to' 
                GROUP BY p.agent_no,d.PlanDesc, p.plan_code";


                $sql_totals = "SELECT COUNT(p.id) AS totals FROM polinfo p 
                    WHERE (p.agent_no=$agent_id AND p.[status_code]=10) AND p.issued_date BETWEEN '$date_from' AND '$date_to' ";
            }
            //$Products = \DB::statement($sql, [$agent_id,$date_from,$date_to]);//DbHelper::getTableRawData($sql);

            $Products = DbHelper::getTableRawData($sql);
            $AgentTotals = DbHelper::getTableRawData($sql_totals);

            $res = array(
                'success' => true,
                'Products' => $Products,
                'AgentTotals' => $AgentTotals
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


    ////Erics Reports
    //Subsidiary Accounts
    public function getSubsidiaryAccounts(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            //$agent_id = DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'id');

            $sql = "SELECT * FROM MicroProposalInfo d";
            $SubsidiaryAccounts = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'SubsidiaryAccounts' => $SubsidiaryAccounts,
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

    //Investments
    public function getInvestments(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'agent_no', $agent_no, 'id');

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $Investments = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Investments' => $Investments,
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

    //Fixed Assets
    public function getFixedAssets(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'agent_no', $agent_no, 'id');

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $FixedAssets = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'FixedAssets' => $FixedAssets,
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

    //Prepayment
    public function getPrepayment(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'agent_no', $agent_no, 'id');

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $Prepayment = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Prepayment' => $Prepayment,
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

    //Prepayment
    public function getBudgetingandForecasting(Request $request)
    {
        try {
            $res = array();

            $agent_no = $request->input('agent_no');
            $agent_id = DbHelper::getColumnValue('agents_info', 'agent_no', $agent_no, 'id');

            $sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $Prepayment = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Prepayment' => $Prepayment,
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

    //Financial Reporting
    public function getFinancialReporting(Request $request)
    {
        try {

            $account_year = $request->input('account_year');
            $account_month = $request->input('account_month');
            //$agent_id = DbHelper::getColumnValue('agents_info', 'agent_no',$agent_no,'id');

            //$sql = "SELECT * FROM MicroProposalInfo d WHERE d.Agent='$agent_id'";
            $sql = "SELECT t2.LevelName, t1.LevelSerial, t1.levelAccountYear, t1.levelAccountMonth, t1.levelSubName, t1.thisYearMonthly, t1.lastYearMonthly, t1.Monthly_variation, t1.thisYearYTD, t1.lastYearYTD, t1.YTD_variation, t1.isHeader, t1.isSubTotal 
            FROM gllevelbal t1 
            INNER JOIN gllevelheader t2 ON t1.LevelName = t2.ReportID 
            WHERE t1.levelAccountYear = $account_year AND t1.levelAccountMonth = $account_month ORDER BY t1.LevelName, t1.LevelSerial;";
            $consolidated = DbHelper::getTableRawData($sql);

            $sql = "SELECT t3.glbranch_name, t2.LevelName, t1.LevelSerial, t1.levelAccountYear, t1.levelAccountMonth, t1.levelSubName, t1.thisYearMonthly, t1.lastYearMonthly, t1.Monthly_variation, t1.thisYearYTD, t1.lastYearYTD, t1.YTD_variation, t1.isHeader, t1.isSubTotal 
            FROM gllevelbrabal t1 
            INNER JOIN gllevelheader t2 ON t1.LevelName = t2.ReportID 
            INNER JOIN glBranchInfo t3 ON t1.levelBra = t3.glBranch 
            WHERE t1.levelAccountYear = $account_year AND t1.levelAccountMonth = $account_month ORDER BY t3.glBranch, t1.LevelName, t1.LevelSerial;";
            $branch = DbHelper::getTableRawData($sql);

            $sql = "SELECT t3.CostCentreName, t2.LevelName, t1.LevelSerial, t1.levelAccountYear, t1.levelAccountMonth, t1.levelSubName, t1.thisYearMonthly, t1.lastYearMonthly, t1.Monthly_variation, t1.thisYearYTD, t1.lastYearYTD, t1.YTD_variation, t1.isHeader, t1.isSubTotal 
            FROM gllevelcostcentrebal t1 
            INNER JOIN gllevelheader t2 ON t1.LevelName = t2.ReportID 
            INNER JOIN glCostCentre t3 ON t1.levelCostCentre = t3.CostCentreCode 
            WHERE t1.levelAccountYear = $account_year AND t1.levelAccountMonth = $account_month ORDER BY t3.CostCentreCode, t1.LevelName, t1.LevelSerial;";
            $costCentre = DbHelper::getTableRawData($sql);

            $res =  array(
                'success' => true,
                "Consolidated" => $consolidated,
                "Branch" => $branch,
                "CostCentre" => $costCentre
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
