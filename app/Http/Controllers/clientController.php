<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;

class clientController extends Controller
{
    //TODO...
    //1. Get client details
    public function getClientDetails(Request $request)
    {
        try{
            $client_no = $request->input('client_no');
            $mobile_no = $request->input('mobile_no');
            $is_micro = $request->input('is_micro');

            if(isset($mobile_no)){
                $sql = "SELECT p.Surname AS surname, p.OtherNames AS other_name,p.Address AS residential_address,p.Mobile AS mobile,
                p.Email AS email,p.BirthDate AS dob,p.Sex AS gender_code,p.Occupation AS occupation_code,p.Country AS country_code,
                p.Region AS region, p.MaritalStatus AS marital_status_code  FROM MicroClientInfo p 
                WHERE p.Mobile ='$mobile_no'";
            }else{
                $sql = "SELECT p.Surname AS surname, p.OtherNames AS other_name,p.Address AS residential_address,p.Mobile AS mobile,
                p.Email AS email,p.BirthDate AS dob,p.Sex AS gender_code,p.Occupation AS occupation_code,p.Country AS country_code,
                p.Region AS region, p.MaritalStatus AS marital_status_code  FROM MicroClientInfo p 
                WHERE p.ClientNumber ='$client_no'";
            }

            
            
            //$client_id = DbHelper::getColumnValue('clientinfo', 'client_number',$client_no,'id');
            // put in a transaction the whole process of syncing data...
            
            $Client = DbHelper::getTableRawData($sql);
            if(sizeof($Client) < 1){
                if(isset($mobile_no)){
                    $sql = "SELECT p.surname,p.other_name,p.address AS postal_address,p.Address2 AS residential_address,p.mobile,
                    p.birthdate AS dob,p.sex AS gender_code,p.marital_status AS marital_status_code,p.email,p.Country AS country_code,
                    p.occupation_code,p.occup_class AS client_class_code,p.pin_no as tin_no,p.Height as pop_height ,p.Weight as pop_weight   
                    FROM clientinfo p WHERE p.mobile='$mobile_no'";
                }else{
                    $sql = "SELECT p.surname,p.other_name,p.address AS postal_address,p.Address2 AS residential_address,p.mobile,
                    p.birthdate AS dob,p.sex AS gender_code,p.marital_status AS marital_status_code,p.email,p.Country AS country_code,
                    p.occupation_code,p.occup_class AS client_class_code,p.pin_no as tin_no,p.Height as pop_height ,p.Weight as pop_weight   
                    FROM clientinfo p WHERE p.client_number='$client_no'";
                    
                }
				$Client = DbHelper::getTableRawData($sql);
            }

            $res = array(
                'success' => true,
                'client_no' => $client_no,
                'Client' => $Client
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
    }

    //get statements
    public function getClientPolicies(Request $request)
    {
        try{
            $client_no = $request->input('client_no');
            $mobile_no = $request->input('mobile_no');
            $policy_no = $request->input('policy_no');
            $status = $request->input('status');

            //$client_id = DbHelper::getColumnValue('clientinfo', 'client_number',$client_no,'id');
            $policy_no = $request->input('policy_no');
            if(isset($policy_no) && isset($status)){
                //get the client_no
                $client_no = DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'client_number');
            }
            if(isset($mobile_no) && isset($status)){
                //get the client_no
                $client_no = DbHelper::getColumnValue('clientinfo', 'mobile',$mobile_no,'client_number');
                if(!isset($client_no)){
                    if(substr($mobile_no, 0, 1) == '0'){
                        $mobile_no = "233".ltrim($mobile_no, '0');
                    }
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile',$mobile_no,'client_number');
                }
                $mobile_no = null;
            }

            // put in a transaction the whole process of syncing data...
            if(isset($mobile_no)){
                $sql = "select t3.coverperiod,T1.*, T2.description, T2.investment_plan, T4.surname, T4.other_name, T4.mobile, T4.email,
                (DATEDIFF(month,t1.effective_date,GETDATE())) AS expected_prem from polinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo t3 on t1.plan_code = t3.plan_code and t1.pay_mode=t3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                WHERE T4.mobile ='$mobile_no'  AND T1.status_code=10";
            }
            if(isset($client_no)){//T3.description AS pay_mode, 
                $sql = "SELECT T1.Life_Prem,T1.Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                T1.maturity_date,T1.employee_no,T1.prem_units,T1.id,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                T1.status_code,T1.proposal_no,T1.policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, 
                T4.other_name, T4.mobile, T4.email,T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, 
                T4.ClaimDefaultMobileWallet, T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,
                T4.ClaimDefaultEFTBank_account, T4.ClaimDefaultEftBankaccountName, T1.pay_mode,
                (DATEDIFF(month,t1.effective_date,GETDATE()) * T3.coverperiod * t1.modal_prem) AS expected_prem,
                d.description AS [status], 
                T1.agent_no,g.name AS agent_name,h.description AS agent_office 
                FROM polinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo T3 on T1.plan_code = T3.plan_code and T1.pay_mode=T3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                INNER JOIN agents_info g ON g.id=T1.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                WHERE T1.client_number ='$client_no' AND T1.status_code=10 ";
            }
            if(isset($policy_no)){
                /*$sql = "select t3.coverperiod,T1.*, T2.description, T2.investment_plan, T4.surname, T4.other_name, T4.mobile, T4.email,
                (DATEDIFF(month,t1.effective_date,GETDATE()) * t3.coverperiod * t1.modal_prem) AS expected_prem from polinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo t3 on t1.plan_code = t3.plan_code and t1.pay_mode=t3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                WHERE T1.policy_no ='$policy_no' ";*/
                $sql="SELECT T1.Life_Prem,T1.Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                T1.maturity_date,T1.employee_no,T1.prem_units,T1.id,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                T1.status_code,T1.proposal_no,T1.policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, 
                T4.other_name, T4.mobile, T4.email,T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, 
                T4.ClaimDefaultMobileWallet, T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,
                T4.ClaimDefaultEFTBank_account, T4.ClaimDefaultEftBankaccountName, T1.pay_mode,
                (DATEDIFF(month,t1.effective_date,GETDATE()) * T3.coverperiod * t1.modal_prem) AS expected_prem,
                d.description AS [status], 
                T1.agent_no,g.name AS agent_name,h.description AS agent_office 
                FROM polinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo T3 on T1.plan_code = T3.plan_code and T1.pay_mode=T3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                INNER JOIN agents_info g ON g.id=T1.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                WHERE T1.policy_no ='$policy_no'";
            }
            $ClientPolicies = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'client_no' => $client_no,
                'ClientPolicies' => $ClientPolicies
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
    }
    
    //client premiums
    public function getClientPremiums(Request $request)
    {
        try{
            $policy_no = $request->input('policy_no');
            $ClientPremiums = array();
            // put in a transaction the whole process of syncing data... AND payment_status='P' AND received<>0
            //getClientPremiums
            $PolicyId = DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'id');
            if(isset($PolicyId)){
                $sql = "SELECT 
                p.id,
                FORMAT(p.received, 'N2') AS received,                  
                FORMAT(p.payment_date, 'dd/MM/yyyy') AS payment_date,   
                p.period_year,
                p.period_month 
                FROM prmtransinfo p WHERE p.policy_no ='".$policy_no."' 
                ORDER BY p.id DESC ";
                $ClientPremiums = DbHelper::getTableRawData($sql);
            }else{
                $MicroPolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber',$policy_no,'Id');
                $EffectiveDate = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber',$policy_no,'EffectiveDate');
                $sql = "SELECT 
                p.Id AS id,
                FORMAT(p.Received, 'N2') AS received,                  
                FORMAT(p.PaymentDate, 'dd/MM/yyyy') AS payment_date,   
                p.PeriodYear AS period_year,
                p.PeriodMonth AS period_month 
                FROM MicroPremiumTransactions p 
                WHERE p.Policy =$MicroPolicyId AND p.PaymentStatus IN ('P','W','D','B') 
                AND p.PaymentDate > '2024-10-02'
                AND p.PaymentDate >= '$EffectiveDate'
                AND p.IsPremiumTransfer = 0 AND p.IsClaimed = 0 
                ORDER BY p.PaymentDate ASC ";
                $ClientPremiums = DbHelper::getTableRawData($sql);
            }
            

            

            $res = array(
                'success' => true,
                'policy_no' => $policy_no,
                'ClientPremiums' => $ClientPremiums
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
    }

    //client investments
    public function getClientInvestment(Request $request)
    {
        try{
            $policy_no = $request->input('policy_no');
            //TODO - fix 
            $PolicyId = DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'id');
            $sql = "SELECT t1.balance_cf, t1.admin_charge,t1.fund_year, t1.total_prem,t1.prem_allocated,
            t1.interest,t1.cacv,
            t1.amt_withdrawn FROM sipfundinfo t1 "; 
            
            if(isset($PolicyId)){
                $sql .= " WHERE t1.policy_no like '$policy_no'";
            }else{
                $MicroPolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber',$policy_no,'Id');
                $sql .= " WHERE t1.Id = $MicroPolicyId";
            }
            // put in a transaction the whole process of syncing data...
            
            $ClientInvestment = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'policy_no' => $policy_no,
                'ClientInvestment' => $ClientInvestment
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
    }

    //client policies
    public function getClientnPolicies(Request $request)
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
    }
}
