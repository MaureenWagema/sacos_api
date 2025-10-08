<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class policyController extends Controller
{
    public function validatePolicyNumber(Request $request)
    {
        try {
            $isValid = false;
            $policy_no = $request->input('policy_no');

            $sql = "SELECT p.id FROM polinfo p WHERE p.policy_no='$policy_no' ";

            $MicroProducts = DbHelper::getTableRawData($sql);
            if (isset($MicroProducts) && sizeof($MicroProducts) > 0) {
                $isValid = true;
            }

            $res = array(
                'isValid' => $isValid
            );
        } catch (\Exception $exception) {
            $res = array(
                'isValid' => false,
                'message' => $exception->getMessage()
            );
            return response()->json($res);
        } catch (\Throwable $throwable) {
            $res = array(
                'isValid' => false,
                'message' => $throwable->getMessage()
            );
            return response()->json($res);
        }
        return response()->json($res);
    }

    //validate for PWD and Loan
    public function validatePWDLoan(Request $request)
    {
        try {

            $policy_no = $request->input('policy_no');
            $claim_type = $request->input('claim_type');

            //for loan just check if client has an active loan...
            if ($claim_type == "LON") {
                //fetch staffno from polinfo
                $staffno = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'SearchReferenceNumber');

                //$outstandingBalance = DbHelper::getColumnValue('loansmasterinfo', 'SearchReferenceNumber', $staffno, 'outstandingBalance');
                $outstandingBalance = $this->smartlife_db->table('loansmasterinfo')
                    ->select('outstandingBalance')
                    ->where(
                        array(
                            'SearchReferenceNumber' => $staffno
                        )
                    )->orderBy('id', 'DESC')
                    ->first()->outstandingBalance;

                if (isset($outstandingBalance) && (float)$outstandingBalance > 0) {
                    return $res = array(
                        'success' => false,
                        'OustandingBalance' => $outstandingBalance,
                        'staffno' => $staffno,
                        'message' => "Settle your Outstanding Loan First"
                    );
                }
            }

            //for partial withdrawal just check the loan interval....



            //health questionnaire
            $res = array(
                'success' => true,
                'message' => "Successfully Validated"
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

    //get requested endorsements
    public function getMicroProducts(Request $request)
    {
        try {


            $sql = "SELECT p.plan_code,p.description,p.Narration FROM planinfo p WHERE p.microassurance=1 AND p.IsForMportal=1";

            $MicroProducts = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'MicroProducts' => $MicroProducts
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
    //get all data here..
    //complete form data..
    //beneficiaries, dependants, health_info, health_history, 
    public function getProposal(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                //get the record data
                $is_dashboard = $request->input('is_dashboard');
                $record_id = $request->input('record_id');
                $agent_code = $request->input('agent_code');
                $pos_type = $request->input('pos_type');

                //TODO - write generic code for fetching your data..
                $sql_generic = "SELECT derived.agent_code, derived.HasBeenPicked, 
                derived.isWebCompleted, derived.MicroProposal, 
                derived.ProposalNumberLink, derived.ID, 
                derived.surname, derived.other_name, 
                CONCAT(derived.surname,' ',derived.other_name) AS [name], 
                derived.email, derived.mobile, derived.TotalPremium, 
                derived.term, derived.plan_code, derived.proposal_no, 
                derived.date_synced, derived.isApproved, derived.pay_code, derived.employer, 
                derived.employee_no, derived.bank_code, derived.bank_branch, 
                derived.bank_account_no, derived.BankaccountName, derived.momo_no ";


                $sql_inject = " AND planinfo.mortgage = 0 AND planinfo.is_keyman=0 AND planinfo.IsLoanProtection=0 ";
                $IsCreditLifeUser = $request->input('IsCreditLifeUser');
                if (isset($IsCreditLifeUser) && $IsCreditLifeUser == 1) {
                    $sql_inject = " AND (planinfo.mortgage = 1 OR planinfo.is_keyman=1 OR planinfo.IsLoanProtection=1) ";
                }

                //TODO get data from mob_prop_info...  ,'' AS uw_reason
                if (isset($record_id) && $record_id > 0) {
                    $qry = $this->smartlife_db->table('mob_prop_info')
                        ->select('*', DB::raw('NULL AS UwCode'), DB::raw('\'\' AS uw_name'), DB::raw('NULL AS Status'), DB::raw('\'\' AS StatusName'), DB::raw('\'\' AS uw_reason'), DB::raw('\'\' AS agent_name'))
                        ->where(
                            array(
                                'ID' => $record_id
                            )
                        );
                    $row_arr = $qry->get();
                } else if ((isset($agent_code) && !empty($agent_code)) || (isset($is_dashboard) && $is_dashboard == "1")) {
                    $date_from = $request->input('date_from');
                    $date_to = $request->input('date_to');
                    //filter by agent
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_code, 'id');
                    $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_code, 'BusinessChannel');
                    //isolate the micro business as they need a different query...
                    if ($BusinessChannel == 5 || $BusinessChannel == "5") {
                        //micro
                        $sql = "DECLARE @columnNames NVARCHAR(MAX)
                        SELECT @columnNames = COALESCE(@columnNames + ', ', '') + 'mob_prop_info.' + COLUMN_NAME
                        FROM (SELECT DISTINCT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mob_prop_info' AND COLUMN_NAME NOT IN ('ClientSignature','ClientPassportPhoto','IdFrontPage','IdLastPage','PayslipCopy')) AS subquery
                        
                        DECLARE @sql NVARCHAR(MAX)
                        SET @sql = 'SELECT ' + @columnNames + ',MicroProposalInfo.Status,statuscodeinfo.description as StatusName,NULL AS UwCode,'' AS uw_name,'' AS uw_reason 
                        CONCAT(CAST(agents_info.AgentNoCode AS NVARCHAR), ''-'', agents_info.name) AS agent_name
                        FROM mob_prop_info 
                        LEFT JOIN MicroProposalInfo ON MicroProposalInfo.ProposalNumber=mob_prop_info.proposal_no
                        LEFT JOIN statuscodeinfo ON statuscodeinfo.status_code = MicroProposalInfo.Status
                        LEFT JOIN agents_info ON agents_info.id=mob_prop_info.agent_code 
                        WHERE mob_prop_info.agent_code = $agentId
                        ORDER BY mob_prop_info.ID DESC'
                        
                        EXEC (@sql)";
                    } else {
                        //life
                        $unitId = DbHelper::getColumnValue('agents_info', 'id', $agentId, 'UnitName');
                        $positionId = DbHelper::getColumnValue('agents_info', 'id', $agentId, 'CurrentManagerLevel');


                        $sql = $sql_generic . " 
                        ,null as StatusName,
                        null as Status,proposalinfo.UwCode,uwcodesinfo.uw_name,uwcodesinfo.AllowMproposalEdit,
                        AppraisalHistory.Observation AS uw_reason,
                        CONCAT(agents_info.AgentNoCode,'-',agents_info.name) as agent_name,
                        AgentsunitsInfo.description AS agent_office,
                        AgentsBranchInfo.description 'Branch',
                        AgentsRegionInfo.Description 'Sector'
                        FROM (
                            SELECT COALESCE(t1.agent_codeSecond, t1.agent_code) AS agent_code, 
                                t1.HasBeenPicked, 
                                t1.isWebCompleted, t1.MicroProposal, t1.ProposalNumberLink, 
                                t1.ID, t1.surname, t1.other_name, t1.email, t1.mobile, 
                                t1.TotalPremium, t1.term, t1.plan_code, t1.proposal_no, 
                                t1.date_synced, t1.isApproved, t1.pay_code, t1.employer, 
                                t1.employee_no, t1.bank_code, t1.bank_branch, 
                                t1.bank_account_no, t1.BankaccountName, t1.momo_no 
                            FROM mob_prop_info t1 WHERE 1=1 ";
                        if (isset($date_from) && isset($date_to)) {
                            $sql .= " AND (CAST(t1.date_synced AS DATE) BETWEEN '$date_from' AND '$date_to')";
                        }
                        $sql .= ") AS derived
                        INNER JOIN planinfo ON planinfo.plan_code = derived.plan_code
                        INNER JOIN agents_info ON agents_info.id = derived.agent_code
                        INNER JOIN AgentsunitsInfo ON AgentsunitsInfo.id = agents_info.UnitName 
                        LEFT JOIN AgentsBranchInfo ON AgentsBranchInfo.id=AgentsunitsInfo.AgentsBranchIdKey
                        LEFT JOIN AgentsRegionInfo ON AgentsRegionInfo.id=AgentsBranchInfo.AgentsRegionIdKey
                        LEFT JOIN proposalinfo ON proposalinfo.MproposalNumber=derived.ID
                        LEFT JOIN uwcodesinfo ON uwcodesinfo.uw_code = proposalinfo.UwCode
                        LEFT JOIN AppraisalHistory ON 
                        (AppraisalHistory.proposal_no = proposalinfo.proposal_no AND AppraisalHistory.IsCurrentRecord = 1)";
                        if ($positionId == 4 || $positionId == 6) {
                            $sql .= " WHERE derived.agent_code in (SELECT t2.id  FROM agents_info t2 WHERE t2.UnitName=$unitId)";
                        } else if ($positionId == 7) {
                            $BranchId = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $unitId, 'AgentsBranchIdKey');
                            $sectorId = DbHelper::getColumnValue('AgentsBranchInfo', 'id', $BranchId, 'AgentsRegionIdKey');

                            $sql .= " WHERE AgentsRegionInfo.id=$sectorId";
                        } else if ($positionId == 8 || (isset($is_dashboard) && $is_dashboard == "1")) {
                            //do nothing..
                            $sql .= " WHERE 1=1 AND (planinfo.microassurance=0 AND planinfo.IsCreditLife=0) ";
                        } else {

                            $sql .= " WHERE derived.agent_code IN 
                            (SELECT t2.id  FROM agents_info t2 WHERE t2.RecruitedBy=$agentId OR t2.id=$agentId) ";
                        }

                        $sql .= " ORDER BY derived.ID DESC";

                        $organised_arr = DbHelper::getTableRawData($sql);
                        return $res = array(
                            'success' => true,
                            'record_id' => $record_id,
                            'policy_arr' => $organised_arr,
                            'message' => 'Data Synced Successfully!!'
                        );
                    }

                    //AND (mob_prop_info.HasBeenPicked=0 OR mob_prop_info.isWebCompleted=0)
                    $row_arr = DbHelper::getTableRawData($sql);
                } else if (isset($pos_type) && !empty($pos_type)) {
                    //logic just check if micro else display all 1-lnd life 
                    //pos_type:0-Admin;1-Individual;2-Micro;3-Group;4-Pension;5-Medical;
                    if ($pos_type == 2) {
                        //micro
                        $sql = "DECLARE @columnNames NVARCHAR(MAX)

                            SELECT @columnNames = COALESCE(@columnNames + ', ', '') + 'mob_prop_info.' + COLUMN_NAME
                            FROM (
                                SELECT DISTINCT COLUMN_NAME 
                                FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_NAME = 'mob_prop_info' 
                                AND COLUMN_NAME NOT IN ('ClientSignature','ClientPassportPhoto','IdFrontPage','IdLastPage')
                            ) AS subquery

                            DECLARE @sql NVARCHAR(MAX)

                            SET @sql = 'SELECT ' + @columnNames + ', null as StatusName, null as Status, proposalinfo.UwCode, uwcodesinfo.uw_name, '''' AS uw_reason, null AS agent_name 
                                        FROM mob_prop_info 
                                        INNER JOIN agents_info ON agents_info.id = mob_prop_info.agent_code 
                                        LEFT JOIN proposalinfo ON proposalinfo.MproposalNumber = mob_prop_info.ID
                                        LEFT JOIN uwcodesinfo ON uwcodesinfo.uw_code = proposalinfo.UwCode
                                        WHERE agents_info.BusinessChannel = 5 AND (mob_prop_info.HasBeenPicked = 0 OR mob_prop_info.isWebCompleted = 0) OR 
                                        (mob_prop_info.HasBeenPicked = 1 AND mob_prop_info.isWebCompleted = 0)
                                        ORDER BY mob_prop_info.ID DESC'

                            EXEC (@sql)
                            ";
                    } else {
                        //life,bancassuarance 
                        /*$sql = "DECLARE @columnNames NVARCHAR(MAX)
                            SELECT @columnNames = COALESCE(@columnNames + ', ', '') + 'mob_prop_info.' + COLUMN_NAME
                            FROM (SELECT DISTINCT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mob_prop_info' AND COLUMN_NAME NOT IN ('ClientSignature','ClientPassportPhoto','IdFrontPage','IdLastPage')) AS subquery
                            DECLARE @sql NVARCHAR(MAX)
                            SET @sql = 'SELECT ' + @columnNames + ',null as StatusName,null as Status,
                            proposalinfo.UwCode,uwcodesinfo.uw_name,
                            AppraisalHistory.Observation AS uw_reason,
                            null AS agent_name
                            FROM mob_prop_info 
                            INNER JOIN agents_info ON agents_info.id = mob_prop_info.agent_code 
                            INNER JOIN planinfo ON mob_prop_info.plan_code = planinfo.plan_code " .$sql_inject."
                            LEFT JOIN proposalinfo ON proposalinfo.MproposalNumber=mob_prop_info.ID
                            LEFT JOIN uwcodesinfo ON uwcodesinfo.uw_code = proposalinfo.UwCode
                            LEFT JOIN AppraisalHistory ON AppraisalHistory.proposal_no = proposalinfo.proposal_no
                            WHERE agents_info.BusinessChannel != 5 AND uwcodesinfo.uw_code != 1 AND  
                            (mob_prop_info.HasBeenPicked=0 OR mob_prop_info.isWebCompleted=0) OR 
                            (mob_prop_info.HasBeenPicked = 1 AND mob_prop_info.isWebCompleted = 0) 
                            ORDER BY AppraisalHistory.id DESC,mob_prop_info.ID DESC'
                            EXEC (@sql)";*/
                        $sql = "DECLARE @columnNames NVARCHAR(MAX)
                            SELECT @columnNames = COALESCE(@columnNames + ', ', '') + 'mob_prop_info.' + COLUMN_NAME
                            FROM (SELECT DISTINCT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mob_prop_info' AND COLUMN_NAME NOT IN ('ClientSignature','ClientPassportPhoto','IdFrontPage','IdLastPage')) AS subquery
                            DECLARE @sql NVARCHAR(MAX)
                            SET @sql = 'SELECT ' + @columnNames + ',null as StatusName,null as Status,
                            proposalinfo.UwCode,uwcodesinfo.uw_name,
                            null AS agent_name
                            FROM mob_prop_info 
                            LEFT JOIN agents_info ON agents_info.id = mob_prop_info.agent_code 
                            LEFT  JOIN planinfo ON mob_prop_info.plan_code = planinfo.plan_code " . $sql_inject . "
                            LEFT JOIN proposalinfo ON proposalinfo.MproposalNumber=mob_prop_info.ID
                            LEFT JOIN uwcodesinfo ON uwcodesinfo.uw_code = proposalinfo.UwCode
                            ORDER BY mob_prop_info.ID DESC'
                            EXEC (@sql)";
                    }
                    $row_arr = DbHelper::getTableRawData($sql);
                } else {
                    //If there is no parameters
                    $sql = "DECLARE @columnNames NVARCHAR(MAX)
                            SELECT @columnNames = COALESCE(@columnNames + ', ', '') + 'mob_prop_info.' + COLUMN_NAME
                            FROM (SELECT DISTINCT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'mob_prop_info' AND COLUMN_NAME NOT IN ('ClientSignature','ClientPassportPhoto','IdFrontPage','IdLastPage','PayslipCopy')) AS subquery

                            DECLARE @sql NVARCHAR(MAX)
                            SET @sql = 'SELECT ' + @columnNames + ',null as StatusName,null as Status,proposalinfo.UwCode,uwcodesinfo.uw_name,AppraisalHistory.Observation AS uw_reason, 
                            null AS agent_name
                            FROM mob_prop_info 
                            INNER JOIN agents_info ON agents_info.id = mob_prop_info.agent_code 
                            INNER JOIN planinfo ON mob_prop_info.plan_code = planinfo.plan_code " . $sql_inject . "
                            LEFT JOIN proposalinfo ON proposalinfo.MproposalNumber=mob_prop_info.ID
                            LEFT JOIN uwcodesinfo ON uwcodesinfo.uw_code = proposalinfo.UwCode
                            LEFT JOIN AppraisalHistory ON AppraisalHistory.proposal_no = proposalinfo.proposal_no
                            LEFT JOIN agents_info ON agents_info.id=mob_prop_info.agent_code 
                            WHERE (mob_prop_info.HasBeenPicked=0 OR mob_prop_info.isWebCompleted=0) ORDER BY AppraisalHistory.id DESC,mob_prop_info.ID DESC'
                            
                            EXEC (@sql)";

                    $row_arr = DbHelper::getTableRawData($sql);
                }



                $organised_arr = array();

                foreach ($row_arr as $results) {
                    //print_r($results);
                    $momo_no = $results->momo_no;
                    $telco = "";
                    //$Status = null;
                    if (isset($momo_no))
                        $telco = $results->employer;

                    $qry = $this->smartlife_db->table('PEPDetails')->select('*')
                        ->where(
                            array(
                                'prop_id' => $results->ID
                            )
                        );

                    $row_arr = $qry->get();

                    for ($i = 0; $i < sizeof($row_arr); $i++) {
                        $pep_details[] = $row_arr[$i]->ReasonsForExposure;
                    }
                    $organised_arr[] = array(
                        'ID' => (int)$results->ID,
                        //,
                        'HasBeenPicked' => $results->HasBeenPicked,
                        'IsWebComplete' => $results->IsWebComplete,
                        //'MicroProposal' => $results->MicroProposal,
                        'ProposalNumberLink' => $results->ProposalNumberLink,
                        'UwCode' => $results->UwCode,
                        'uw_name' => $results->uw_name,
                        //'uw_reason' => $results->uw_reason,
                        'Status' => $results->Status,
                        'StatusName' => $results->StatusName,
                        'IncomeType' => (int)$results->IncomeType ?? null,

                        'isApproved' => $results->isApproved,
                        'mobile_id' => $results->mobile_id,
                        'surname' => $results->surname,
                        'other_name' => $results->other_name,
                        'name' => $results->surname . " " . $results->other_name,

                        'employer_code' => $results->employer,
                        'emp_code' => $results->emp_code,
                        'employee_noCode' => $results->employee_noCode,
                        'employee_noDisplay' => $results->employee_noDisplay,
                        //'policy_no' => $results->policy_no,
                        //'paysource_br' => $request->input('FirstName,
                        //'paysource_br_code' => $results->paysource_br,
                        //'sort_code' => $results->sort_code,
                        'email' => $results->email,
                        //'tel_no' => $results->tel_no,
                        'mobile' => $results->mobile,
                        'marital_status_code' => $results->marital_status,
                        'gender_code' => $results->gender,
                        'plan_code' => $results->plan_code,
                        'good_health' => (bool) $results->good_health,
                        'health_condition' => $results->health_condition,

                        'country_code' => $results->Country,
                        'city' => $results->city,

                        'occupation_code' => $results->occupation,

                        'dob' => $results->Dob,
                        'anb' => $results->anb,
                        'home_town' => $results->home_town,
                        'ExpiryDate' => $results->ExpiryDate,

                        'DualCitiizenship' => $results->DualCitiizenship ? 1 : 0,
                        'Country2' => $results->Country2,

                        'GpsCode' => $results->GpsCode,
                        'SRCNumber' => $results->SRCNumber,

                        'SourceOfIncome' => $results->SourceOfIncome,
                        'SourceOfIncome2' => $results->SourceOfIncome2,

                        'date_synced' => $results->date_synced,
                        //p.TaxResidencyDeclared,p.AllowInformationSharing,p.DoNotAllowAllowInformationSharing,p.TaxResidencyDeclared
                        'TaxResidencyDeclared' => $results->TaxResidencyDeclared ? 1 : 0,
                        'AllowInformationSharing' => $results->AllowInformationSharing ? 1 : 0,
                        'DoNotAllowAllowInformationSharing' => $results->DoNotAllowAllowInformationSharing ? 1 : 0,

                        'pay_method_code' => $results->pay_code,
                        'bank_code' => $results->bank_code,
                        'bank_branch' => $results->bank_branch,
                        'bank_account_no' => $results->bank_account_no,

                        'life_assuarance' => $results->life_assuarance,
                        'existing_policy' => $results->existing_policy,
                        'existing_pol_no' => $results->existing_pol_no,
                        'claim_pol_no' => $results->claim_pol_no,

                        'term' => $results->term,
                        'employer_no' => $results->employee_no,
                        'paymode_code' => $results->paymode_code,
                        'deduction_date' => $results->deduction_date,

                        'sum_assured' => $results->Sum_Assured,
                        'inv_premium' => $results->inv_premium,
                        'basic_premium' => $results->basic_premium,
                        'modal_premium' => $results->modal_premium,
                        'TotalPremium' => $results->TotalPremium,
                        'rider_premium' => $results->rider_premium,
                        'Vat' => $results->Vat,

                        'pol_fee' => $results->pol_fee,
                        'cepa' => $results->cepa,

                        'transfer_charge' => $results->transfer_charge,

                        'proposal_date' => $results->proposal_date,

                        'postal_address' => $results->postal_address,
                        'residential_address' => $results->residential_address,
                        'Doyouhavesecondaryincome' => (bool) $results->Doyouhavesecondaryincome,
                        'secondary_income' => $results->secondary_income,
                        'IsPep' => $results->IsPep ? 1 : 0,
                        'politicaly_affiliated_person' => $results->politicaly_affiliated_person,
                        'policy_no' => $results->proposal_no,
                        'proposal_no' => $results->proposal_no,
                        'life_premium' => $results->Life_Premium,

                        'Date_Saved' => $results->Date_Saved,
                        ////bank,bank_acc_no,telco,momo_no
                        //'bank' => $results->bank,
                        //'bank_acc_no' => $results->bank_acc_no,
                        'telco' => $telco,
                        'momo_no' => $results->momo_no,
                        'id_type' => $results->id_type,
                        'IdNumber' => $results->IdNumber,
                        'title' => $results->title,
                        'MobileSecondary' => $results->MobileSecondary,
                        //'InsuranceType' => $results->InsuranceType,

                        'GuarantorBank' => $results->GuarantorBank,
                        'currency' => $results->currency,
                        'DateFrom' => $results->DateFrom,
                        'DateTo' => $results->DateTo,
                        'DurationDays' => $results->DurationDays,
                        'CostOfProperty' => $results->CostOfProperty,

                        'ClaimDefaultPay_method' => $results->ClaimDefaultPay_method,
                        'ClaimDefaultTelcoCompany' => $results->ClaimDefaultTelcoCompany,
                        'ClaimDefaultMobileWallet' => $results->ClaimDefaultMobileWallet,
                        'ClaimDefaultEFTBank_code' => $results->ClaimDefaultEFTBank_code,
                        'ClaimDefaultEFTBankBranchCode' => $results->ClaimDefaultEFTBankBranchCode,
                        'ClaimDefaultEFTBank_account' => $results->ClaimDefaultEFTBank_account,
                        'ClaimDefaultEftBankaccountName' => $results->ClaimDefaultEftBankaccountName,

                        'second_l_name' => $results->second_l_name,
                        'second_l_address' => $results->second_l_address,
                        'second_gender_code' => $results->second_gender_code,
                        'second_dob' => $results->second_dob,
                        'second_age' => $results->second_age,

                        'DependantPremium' => $results->DependantPremium,

                        'agent_code' => $results->agent_code,
                        'agent_name' => $results->agent_name,
                        'reasons_for_exposure' => $pep_details,

                        'Height' => $results->Height,
                        'Weight' => $results->Weight,
                        'Systolic' => $results->Systolic,
                        'diastolic' => $results->diastolic,
                        'ChestMeasurement' => $results->ChestMeasurement,
                        'PulsePressure' => $results->PulsePressure,
                        'PulseRate' => $results->PulseRate,
                        'AbdominalGirth' => $results->AbdominalGirth,
                    );
                }

                $rider_arr = array();
                $dependants_arr = array();
                $beneficiaries_arr = array();
                $MobIntermediary = array();
                $MobHealthConditions = array();
                $family_health_arr = array();

                if (isset($record_id) && $record_id > 0) {

                    $qry = $this->smartlife_db->table('mob_rider_info')->select('*')
                        ->where(
                            array(
                                'prop_id' => $record_id
                            )
                        );

                    $row_arr = $qry->get();

                    for ($i = 0; $i < sizeof($row_arr); $i++) {
                        $rider_arr[$i]['r_rider'] = $row_arr[$i]->rider;
                        $rider_arr[$i]['r_sa'] = $row_arr[$i]->sa;
                        $rider_arr[$i]['r_premium'] = $row_arr[$i]->premium;
                    }

                    $qry = $this->smartlife_db->table('mob_funeralmembers')->select('*')
                        ->where(
                            array(
                                'prop_id' => $record_id
                            )
                        );
                    $row_arr = $qry->get();

                    for ($i = 0; $i < sizeof($row_arr); $i++) {
                        $dependants_arr[$i]['dp_fullname'] = $row_arr[$i]->names;
                        $dependants_arr[$i]['dp_dob'] = $row_arr[$i]->date_of_birth;
                        $dependants_arr[$i]['dp_anb'] = $row_arr[$i]->age;
                        $dependants_arr[$i]['dp_sa'] = $row_arr[$i]->sa;
                        $dependants_arr[$i]['dp_premium'] = $row_arr[$i]->premium;
                        $dependants_arr[$i]['dp_relationship'] = $row_arr[$i]->Relationship;
                        //$dependants_arr[$i]['dp_class_code'] = $row_arr[$i]->class_code;
                        $dependants_arr[$i]['dp_bapackage'] = $row_arr[$i]->PackageCode;
                        $dependants_arr[$i]['dp_hci_sum'] = $row_arr[$i]->Hci_sum;
                    }

                    $qry = $this->smartlife_db->table('mob_beneficiary_info')->select('*')
                        ->where(
                            array(
                                'prop_id' => $record_id
                            )
                        );
                    $row_arr = $qry->get();

                    for ($i = 0; $i < sizeof($row_arr); $i++) {
                        $beneficiaries_arr[$i]['b_name'] = $row_arr[$i]->Names;
                        $beneficiaries_arr[$i]['b_relationship'] = $row_arr[$i]->relationship;
                        $beneficiaries_arr[$i]['b_dob'] = $row_arr[$i]->birth_date;
                        $beneficiaries_arr[$i]['b_percentage_allocated'] = $row_arr[$i]->perc_alloc;
                        $beneficiaries_arr[$i]['b_mobile_no'] = $row_arr[$i]->telephone;
                    }

                    $qry = $this->smartlife_db->table('mob_family_healthinfo')->select('*')
                        ->where(
                            array(
                                'prop_id' => $record_id
                            )
                        );
                    $row_arr = $qry->get();

                    for ($i = 0; $i < sizeof($row_arr); $i++) {
                        $family_health_arr[$i]['fh_family'] = $row_arr[$i]->Relationship;
                        $family_health_arr[$i]['fh_state'] = $row_arr[$i]->state;
                        $family_health_arr[$i]['fh_age'] = $row_arr[$i]->age;
                        $family_health_arr[$i]['fh_state_health'] = $row_arr[$i]->state_health;
                    }





                    if (isset($record_id)) {
                        //TODO-
                        //1.Just query mob_health_intermediary
                        $sql = "SELECT p.id,p.disease_id,p.DependantName,p.answer FROM mob_health_intermediary p 
                        WHERE p.prop_id=$record_id";
                        $MobIntermediary = DbHelper::getTableRawData($sql);
                        //2.Just query mob_health_conditions
                        $sql = "SELECT p.intermediary_id,p.disease_id,p.LoadingFactor,p.disease_injury,
                        p.disease_date,p.disease_duration,p.disease_result,p.disease_doc FROM mob_health_conditions p 
                        inner join mob_health_intermediary d ON p.intermediary_id=d.id 
                        WHERE d.prop_id=$record_id";
                        $MobHealthConditions = DbHelper::getTableRawData($sql);
                        //as they are...they'll bind perfectly
                        if(empty($MobIntermediary) || sizeof($MobIntermediary) == 0){
                            //query mob_health_info
                            $MobIntermediary = $this->smartlife_db->table('mob_health_info as p')
                            ->select(
                                'p.id as disease_id',
                                DB::raw('CAST(0 AS bit) as isYesChecked'),
                                DB::raw('CAST(0 AS bit) as isNoChecked'),
                                DB::raw("'' as comments")
                            )
                            ->get();
                        }
                    }
                }


                //health questionnaire
                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'policy_arr' => $organised_arr,
                    //$rider_id,
                    'beneficiaries' => $beneficiaries_arr,
                    'dependants' => $dependants_arr,
                    'riders' => $rider_arr,
                    'family_health' => $family_health_arr,
                    'MobIntermediary' => $MobIntermediary,
                    'MobHealthConditions' => $MobHealthConditions,
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

    //get policy dependants
    public function getPolicyDependants(Request $request)
    {
        try {
            $policyId = $request->input('policyId');
            $proposal_no = $request->input('proposal_no');
            $is_micro = $request->input('is_micro');

            if ($is_micro == 1) {
                $sql = "SELECT p.[Name] as Names,p.Relationship,p.SumAssured as sa,p.Premium as premium,
                CONCAT(p.[Name],' - ',d.description) as NameRelationship
                FROM MicroDependant p 
                INNER JOIN relationship_mainteinance d ON p.Relationship=d.code ";
                if (isset($proposal_no)) {
                    $proposalId = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $proposal_no, 'Id');
                    $sql .= " WHERE p.Proposal='$proposalId'";
                } else {
                    $sql .= " WHERE p.Policy=$policyId";
                }
            } else {
                $sql = "SELECT p.*, CONCAT(p.[Names],' - ',d.description) as NameRelationship FROM funeralmembers p 
                    INNER JOIN relationship_mainteinance d ON p.Relationship=d.code";
                if (isset($proposal_no)) {
                    $sql .= " WHERE p.proposal_no='$proposal_no'"; //p.ClaimNotified=0 AND 
                } else {
                    $sql .= " WHERE p.Policy_no=$policyId"; //p.ClaimNotified=0 AND
                }
            }
            $FuneralMembers = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'FuneralMembers' => $FuneralMembers
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
    //getPropDepInfo
    public function getPropDepInfo(Request $request)
    {
        try {
            $proposal_no = $request->input('proposal_no');

            //raw
            $sql = "SELECT * FROM propdepinfo p WHERE p.proposal_no='$proposal_no'";

            $PropDep = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'PropDep' => $PropDep
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

    //get ClientAccount ... Suspence.....
    public function getClientAccount(Request $request)
    {
        try {
            $client_number = $request->input('client_number');

            //raw
            $sql = "SELECT p.*,d.policy_no FROM ClientAccount p 
                INNER JOIN polinfo d ON d.id=p.PolicyIdKey
                WHERE p.IsRefunded=0 AND p.IsAllocated=0 AND p.IsFromMainsuspense=0 AND p.client_number='$client_number'";

            $ClientAccount = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'ClientAccount' => $ClientAccount
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

    //update policybeneficiaries
    public function UpdateBeneficiaries(Request $request)
    {
        try {
            $id = $request->input('id');
            $beneficiaries = $request->input('beneficiaries');
            unset($beneficiaries['id']);
            unset($beneficiaries['age']);

            //update
            $this->smartlife_db->table('beneficiary_info')
                ->where(
                    array(
                        "id" => $id
                    )
                )
                ->update($beneficiaries);

            //health questionnaire
            $res = array(
                'success' => true
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

    //get policy riders
    public function getPolicyRiders(Request $request)
    {
        try {
            $policyId = $request->input('policyId');
            $proposal_no = $request->input('proposal_no');
            $is_micro = $request->input('is_micro');

            if ($is_micro == 1) {
                $sql = "SELECT p.Rider AS RiderCode,p.SumAssured as sum_assured,p.Premium as premium
                FROM MicroPolicyRiderInfo p ";
                if (isset($proposal_no)) {
                    $proposalId = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $proposal_no, 'Id');
                    $sql .= " WHERE p.Proposal='$proposalId'";
                } else {
                    $sql .= " WHERE p.Policy=$policyId";
                }
            } else {
                $sql = "SELECT p.* FROM pol_rider_info p ";
                if (isset($proposal_no)) {

                    $sql .= " WHERE p.proposal_no='$proposal_no'";
                } else {
                    $sql .= " WHERE p.policy_no=$policyId";
                }
            }
            $Riders = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Riders' => $Riders
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

    //get client loans
    public function getClientLoan(Request $request)
    {
        try {
            $client_no = $request->input('client_no');
            $staff_no = $request->input('staff_no');
            $is_micro = $request->input('is_micro');
            if (!isset($is_micro)) {
                $is_micro = 0;
            }
            $current_year = (int) date('Y');
            if (isset($client_no)) {
                $sql = "SELECT p.* FROM loansmasterinfo p 
                where p.IsMicro=$is_micro AND p.Client='$client_no' AND p.IsCurrent=1 and p.LoanYear=$current_year";
            } else if (isset($staff_no)) {
                $sql = "SELECT p.*,d.name FROM loansmasterinfo p
                INNER JOIN clientinfo d ON d.client_number=p.[Client] 
                WHERE p.IsMicro=$is_micro AND p.IsCurrent=1 AND  p.SearchReferenceNumber='$staff_no'";
            }

            $ClientLoan = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'ClientLoan' => $ClientLoan
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

    function GetMonthDifference($date1, $date2)
    {
        $carbon1 = Carbon::parse($date1);
        $carbon2 = Carbon::parse($date2);

        $months = $carbon1->diffInMonths($carbon2);

        return $months;
    }

    function addMonthsToDate($date, $months)
    {
        // Parse the provided date using Carbon
        $parsedDate = Carbon::parse($date);

        // Add the given number of months to the date
        $newDate = $parsedDate->addMonths($months);

        // Return the new date in the format of your choice
        return $newDate->toDateString(); // or you can use ->format('Y-m-d') for custom format
    }

    /*public function UpdateInvPremInPrmTrans($intYear, $PolicyID)
    {
        try {
            
            //The commented section is how am querrying the server in my laravel code...
            //for select queries..
            // $endorse_sql = "SELECT * FROM polendorseinfo p 
            //     WHERE p.PolicyId=$policyId AND p.IsReversed=0 ORDER BY p.Endorse_Date ASC";

            // $polEndorse = DbHelper::getTableRawData($endorse_sql);
            // if (sizeof($polEndorse) > 0) {

            // }
            //for insert queries...
            //$id = $this->smartlife_db->table('eEndorsmentEntries')->insertGetId($endorsementData);
            //for update queries..
            //$this->smartlife_db->table('eEndorsmentEntries')->where(array("id" => $id))->update($table_data);


        } catch (\Exception $exception) {
            echo $exception->getMessage();
        } 
    }*/
    /*

    public function RecalculatePremUnitsFuntion($FirstPolicyPAR, $LastPolicyPAR, $FirstEffectivedateVAR, $LastEffectivedateVAR, $ShowMessagePAR)
    {
        //$GlobalCommonCodes = new GlobalCommonCodes();
        //$SystemMessaging = new SystemMessaging();
        $sQuery = "";
        $dueDate = null;
        $PaymentDateVAR = null;
        $TotalReceivedAmt = 0;
        $paidUnits = 0;
        $dblCurrentPremUnits = 0;
        $BulkPremRebuildVAR = 0;
        $PolIdVAR = 0;
        $IsOverPaymentVAR = 0;
        $PrmTransIdVAR = 0;

        if (trim($FirstPolicyPAR) == trim($LastPolicyPAR)) {
            $BulkPremRebuildVAR = 0;
            $sQuery = "SELECT id FROM polinfo WHERE TotalPremium > 0 AND policy_no = '$FirstPolicyPAR'";
        } else {
            $QuerryPart1VAR = "";
            if ($FirstEffectivedateVAR && !$LastEffectivedateVAR) {
                $QuerryPart1VAR = " AND (t1.effective_date BETWEEN '$FirstEffectivedateVAR' AND '$LastEffectivedateVAR')";
            }

            $BulkPremRebuildVAR = 1;
            $sQuery = "SELECT t1.id FROM polinfo t1 INNER JOIN statuscodeinfo t2 ON t1.status_code = t2.status_code
                        WHERE t1.BulkPremRebuild = 0 AND t2.isActive = 1 $QuerryPart1VAR AND t1.TotalPremium > 0
                        AND (t1.policy_no BETWEEN '$FirstPolicyPAR' AND '$LastPolicyPAR')";
        }

        $polinfoRecords = $this->smartlife_db->select($sQuery);
        if (!empty($polinfoRecords)) {
            $totalRec = count($polinfoRecords);
            foreach ($polinfoRecords as $transRow) {
                $PolIdVAR = $transRow->id;
                //$PolVAR = $session->FindObject('polinfo', $PolIdVAR);
                //$PolicyId = DbHelper::getColumnValue('polinfo', 'id', $PolIdVAR, 'policy_no');
                $dblCurrentPremUnits = 0;
                $TotalReceivedAmt = 0;

                // Reset Main Suspense
                $this->smartlife_db->table('polinfo')
                    ->where('id', $PolIdVAR)
                    ->update(['prem_units' => 0, 'main_suspense' => 0]);
                $this->smartlife_db->table('ClientAccount')
                    ->where('PolicyIdKey', $PolIdVAR)
                    ->where('IsRefunded', 0)
                    ->where('IsFromMainsuspense', 1)
                    ->delete();

                $sQuery = "SELECT t2.plan_code, t1.current_premiums, t2.pay_mode, t1.*
                        FROM prmtransinfo t1
                        INNER JOIN polinfo t2 ON t1.PolicyId = t2.id
                        WHERE t1.received > 0 AND t1.PolicyId = '$PolIdVAR' AND t1.payment_status = 'P'
                        AND t1.IsRefunded = 0 AND t1.Disable = 0 AND t1.trans_type <> 'HBC'
                        ORDER BY t1.payment_date ASC";

                $prmtransinfoRecords = $this->smartlife_db->select($sQuery);

                $TotalPremium = DbHelper::getColumnValue('polinfo', 'id', $PolIdVAR, 'policy_no');

                if (!empty($prmtransinfoRecords)) {
                    foreach ($prmtransinfoRecords as $nrow) {
                        $paidUnits = 0;
                        $PrmTransIdVAR = $nrow->id;
                        $PaymentDateVAR = !empty($nrow->payment_date) ? Carbon::parse($nrow->payment_date) : Carbon::now();
                        $YearVAR = Carbon::parse($PaymentDateVAR)->year;
                        $TotalReceivedAmt += $nrow->received;

                        $ModalpremiumtoUse = $TotalPremium;
                        if ($this->CheckEndorseMent($nrow->PolicyId, $PaymentDateVAR, $ModalpremiumtoUse, $session)) {
                            if ($this->dblModePrem > 0) {
                                $ModalpremiumtoUse = $this->dblModePrem;
                            }
                        }

                        if ($Increment2 == 1) {
                            $this->smartlife_db->table('polinfo')
                                ->where('id', $PolIdVAR)
                                ->update(['main_suspense' => 0]);

                            $this->smartlife_db->table('ClientAccount')
                                ->where('PolicyIdKey', $PolIdVAR)
                                ->where('IsRefunded', 0)
                                ->where('IsFromMainsuspense', 1)
                                ->delete();
                        }

                        $previousSuspense = $this->smartlife_db->table('polinfo')
                            ->select('main_suspense')
                            ->where('id', $PolIdVAR)
                            ->first();

                        $this->calc_prem_units($PolVAR, $nrow->received, $ModalpremiumtoUse);
                        $paidUnits = $this->globalPremUnits;

                        $Main_SuspenseVAR = 0;
                        if ($this->globalShortFall_Amt > 0) {
                            $this->smartlife_db->table('polinfo')
                                ->where('id', $nrow->PolicyId)
                                ->decrement('main_suspense', $this->globalShortFall_Amt);
                            $Main_SuspenseVAR = $this->globalShortFall_Amt;
                        } else {
                            $this->smartlife_db->table('polinfo')
                                ->where('id', $nrow->PolicyId)
                                ->increment('main_suspense', $this->globalTempSuspense);
                            $Main_SuspenseVAR = $this->globalTempSuspense;
                        }

                        if ($this->globalPremUnits > 0 && $Main_SuspenseVAR > 0) {
                            $IsOverPaymentVAR = 1;
                        }

                        $ReceiptNoOLD = !empty($nrow->ReceiptNoOLD) ? $nrow->ReceiptNoOLD : null;

                        $existingClientAccount = $this->smartlife_db->table('clientaccount')
                            ->where('IsRefunded', 0)
                            ->where('PolicyIdKey', $PolVAR->id)
                            ->where('IsFromMainsuspense', 1)
                            ->first();

                        if (is_null($existingClientAccount)) {
                            $this->smartlife_db->table('clientaccount')->insert([
                                'PaymentDate' => $PaymentDateVAR->format('Y-m-d'),
                                'client_number' => $PolVAR->client_number->client_number,
                                'Cr' => $Main_SuspenseVAR,
                                'created_by' => $SystemMessaging->CleanString(auth()->user()->name),
                                'created_on' => now()->format('Y-m-d'),
                                'PolicyIdKey' => $PolVAR->id,
                                'IsFromMainsuspense' => 1,
                                'PrmIdKey' => $PrmTransIdVAR,
                                'IsOverPayment' => $IsOverPaymentVAR,
                                'ReceiptNoOLD' => $ReceiptNoOLD
                            ]);
                        } else {
                            $Main_SuspenseVAR = 0;
                            $suspenseAmount = $existingClientAccount->Cr;

                            if ($this->globalShortFall_Amt > 0) {
                                $Main_SuspenseVAR = $suspenseAmount - $this->globalShortFall_Amt;
                            } else {
                                $Main_SuspenseVAR = $suspenseAmount + $this->globalTempSuspense;
                            }

                            $this->smartlife_db->table('clientaccount')
                                ->where('id', $existingClientAccount->id)
                                ->update([
                                    'IsFromMainsuspense' => 1,
                                    'IsOverPayment' => $IsOverPaymentVAR,
                                    'PrmIdKey' => $PrmTransIdVAR,
                                    'PaymentDate' => $PaymentDateVAR->format('Y-m-d'),
                                    'Cr' => $Main_SuspenseVAR,
                                    'ReceiptNoOLD' => $ReceiptNoOLD
                                ]);
                        }

                        $this->smartlife_db->table('ClientAccount')
                            ->where('PolicyIdKey', $PolVAR->id)
                            ->where('IsRefunded', 0)
                            ->where('IsFromMainsuspense', 1)
                            ->where('Cr', '<=', 0)
                            ->delete();

                        $dblCurrentPremUnits += $paidUnits;
                        $dueDate = Carbon::parse($PolVAR->effective_date)->addMonths($dblCurrentPremUnits);

                        $this->smartlife_db->table('prmtransinfo')
                            ->where('id', $nrow->id)
                            ->update([
                                'current_premiums' => $dblCurrentPremUnits,
                                'prem_due_date' => $dueDate->format('Y-m-d'),
                                'PremUnitReComputed' => 1,
                                'modal_premium' => $ModalpremiumtoUse,
                                'prem_units' => $paidUnits,
                                'prev_prem_units' => $dblCurrentPremUnits,
                                'Prev_main_susp' => round($previousSuspense->main_suspense, 2),
                                'main_susp' => round($Main_SuspenseVAR, 2)
                            ]);
                    }

                    $this->smartlife_db->table('polinfo')
                        ->where('id', $PolIdVAR)
                        ->update([
                            'received' => $TotalReceivedAmt,
                            'prem_due_Date' => $dueDate->format('Y-m-d'),
                            'prem_units' => $dblCurrentPremUnits,
                            'last_prem_date' => $PaymentDateVAR->format('Y-m-d'),
                            'BulkPremRebuild' => $BulkPremRebuildVAR
                        ]);
                }
            }

            if ($ShowMessagePAR) {
                $SystemMessaging->globalShowSlideNotification('Premium Units Rebuild Completed!', true);
            }
        } else {
            $SystemMessaging->globalShowSlideNotification('No Record Found!', true);
            return;
        }
    }



    public function UpdateInvPremInPrmTransCEPA_WAIVEROfPremium($session, $PolicyID)
    {
        try {

            $policy_no = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'policy_no');
            //$GlobalCommonCodes = new GlobalCommonCodes();
            //$SystemMessaging = new SystemMessaging();
            $ServerDateVAR = Carbon::now();//$GlobalCommonCodes->get_current_Server_Date($uow);
            $ServerDateYear = Carbon::parse($ServerDateVAR)->year;

            $StartDateVAR = null;
            $EndDateVAR = null;
            $payment_dateVAR = null;
            $DurationVAR = 0;
            $InvestPremVAR = 0;
            $ReceivedVAR = 0;
            $XFactorVAR = 1;
            $PolicyCoverPeriodVAR = 1;

            //$InvestPremVAR = $PolicyID->investment_prem;
            //$ReceivedVAR = $PolicyID->TotalPremium;
            $InvestPremVAR = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'investment_prem');
            $ReceivedVAR = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'TotalPremium');

            //$UnitOfWorkVAR = new UnitOfWork($session->ServiceProvider, $session->DataLayer);
            //$rsPolinf = $UnitOfWorkVAR->FindObject('polinfo', ['id' => $PolicyID->id]);

            
            //$paymentmodeinfo = $PolicyID->pay_mode;
            $paymentmodeinfo = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'pay_mode');

            $coverperiod = DbHelper::getColumnValue('paymentmodeinfo', 'id', $paymentmodeinfo, 'coverperiod');
            if ((float)$coverperiod > 0) {
                $PolicyCoverPeriodVAR = $coverperiod;
            }

            $WaiverOfPremActivated = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'WaiverOfPremActivated');
            $WaiverOfPremActivationDate = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'WaiverOfPremActivationDate');
            $maturity_date = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'maturity_date');
            $last_premium_date = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'last_premium_date');
            if ($WaiverOfPremActivated) {
                if (isset($WaiverOfPremActivationDate)) {
                    $this->smartlife_db->table('prmtransinfo')
                        ->where('IsForWaiverOfPremium', 1)
                        ->where('policyid', $PolicyID)
                        ->delete();

                    $StartDateVAR = Carbon::parse($WaiverOfPremActivationDate);
                    if ($ServerDateVAR >= Carbon::parse($maturity_date)) {
                        $EndDateVAR = Carbon::parse($last_premium_date);
                    } else {
                        //TODO.. php version of get months
                        $EndDateVAR = $ServerDateVAR->subMonths($PolicyCoverPeriodVAR);
                    }

                    //TODO .. php version of add the months and return date..
                    $payment_dateVAR = $StartDateVAR->copy()->addMonths($PolicyCoverPeriodVAR);

                    $policy_no = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'policy_no');
                    $TotalPremium = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'TotalPremium');

                    $period_year = Carbon::parse($payment_dateVAR)->year;
                    $period_month = Carbon::parse($payment_dateVAR)->month;
                    $Deduct_Year = Carbon::parse($payment_dateVAR)->year;
                    $Deduct_Month = Carbon::parse($payment_dateVAR)->month;

                    if ($StartDateVAR < $EndDateVAR) {
                        $DurationVAR = $this->GetMonthDifference($StartDateVAR, $EndDateVAR) / $PolicyCoverPeriodVAR;

                        

                        while ($XFactorVAR <= $DurationVAR) {
                            $prmtrans = [
                                'policy_no' => $policy_no,
                                'PolicyId' => $PolicyID,
                                'payment_date' => $payment_dateVAR,
                                'payment_status' => 'P',
                                'ReceiptNoOLD' => 'WAIVER PREM ENTRY ' . $XFactorVAR,
                                'received' => $ReceivedVAR,
                                'TotalPremium' => $TotalPremium,
                                'trans_type' => 'AUTO',
                                'Disable' => false,
                                'IsRefunded' => false,
                                'investment_prem' => $InvestPremVAR,
                                'IsForWaiverOfPremium' => true,
                                'period_year' => $period_year,
                                'period_month' => $period_month,
                                'Deduct_Year' => $Deduct_Year,
                                'Deduct_Month' => $Deduct_Month,
                            ];

                            $this->smartlife_db->table('prmtransinfo')->insert($prmtrans);

                            $XFactorVAR++;
                            //TODO - figure this out
                            $payment_dateVAR->addMonth();
                        }
                    }
                }
            }

            $this->RecalculatePremUnitsFuntion($policy_no, $policy_no, null, null, false);

        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }



    public function globalRebuildInvestmentStatement(Request $request)
    {
        try {

            //TODO - fetch stuff proff polinfo from policy_no...
            $PolicyNumberPAR = $request->input('policy_no');
            $CurrentYearPAR = Date('Y');

            // Check if the policy should be excluded from cash values rebuild
            if ($PolicyNumberPAR->status_code->ExcludeFromCashValuesRebuild == true) {
                return; // stop
            }

            //$GlobalCommonCodes = new GlobalCommonCodes();
            //$uow = new UnitOfWork(); // Assuming UnitOfWork is correctly set up in Laravel
            $ServerDateVAR = Carbon::now();//$GlobalCommonCodes->get_current_Server_Date($uow);
            $ServerDateYear = Carbon::parse($ServerDateVAR)->year;

            $globalCashValue = 0;
            //$df = $uow->findObject('defaultsinfo', [1]);
            $LastInvestInfoDate = DbHelper::getColumnValue('defaultsinfo', 'id', 1, 'LastInvestInfoDate');

            // Delete records from investinfo and sipfundinfo
            $PolicyId = DbHelper::getColumnValue('polinfo', 'id', $PolicyNumberPAR, 'LastInvestInfoDate');
            $this->smartlife_db->table('investinfo')->where('policyId', $PolicyId)->delete();
            $this->smartlife_db->table('sipfundinfo')->where('policyId', $PolicyId)->delete();

            // Update investment premium transactions with CEPA and waiver of premiums
            $this->UpdateInvPremInPrmTransCEPA_WAIVEROfPremium($PolicyNumberPAR);

            // Generate investment records
            $this->GenInvestRecs($PolicyNumberPAR, Carbon::parse($LastInvestInfoDate)->format('Y-m-d'));

            $effective_date = DbHelper::getColumnValue('polinfo', 'policy_no', $PolicyNumberPAR, 'effective_date');
            $startYear = Carbon::parse($effective_date)->year;
            $clientNumber = DbHelper::getColumnValue('polinfo', 'policy_no', $PolicyNumberPAR, 'client_number');

            $totalRec = (int)$CurrentYearPAR - $startYear;
            $this->genSipFund($PolicyId, $PolicyNumberPAR, $startYear);

            if ($totalRec == 0) {
                $totalRec = 1;
            }

            for ($i = $startYear; $i <= (int)$CurrentYearPAR; $i++) {
                $this->UpdateInvPremInPrmTrans($i, $PolicyNumberPAR);
                $this->InitializeFund($startYear, $PolicyId);
                $this->UpdateFundStatement($startYear, $ServerDateYear, $PolicyNumberPAR);
                $startYear++;
            }

            // Update polinfo with the latest cash value
            $sQuery = "SELECT t.cacv 
                    FROM sipfundinfo t 
                    WHERE policyId = " . $PolicyId . " 
                    ORDER BY fund_year DESC 
                    LIMIT 1";

            $rsD = DbHelper::getTableRawData($sQuery);

            if ($rsD != null && count($rsD) > 0) {
                $cacv = $rsD[0]->cacv ?? null;
                if (!is_null($cacv)) {
                    $this->smartlife_db->table('polinfo')
                        ->where('policy_no', $PolicyNumberPAR)
                        ->update(['cashvalue' => $cacv]);
                }
            }
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }



    public function UpdateInvPremInPrmTrans($intYear, $PolicyID)
    {
        try {
            $sQuery = "";
            $wPayMentDate = "";
            $dblInvestPrem = 0;
            $prem_units = 0;

            if ($intYear > 0) {
                // Is Year Has Value
                $sQuery = "SELECT id, payment_date, prem_units 
                        FROM prmtransinfo 
                        WHERE investment_prem = 0 AND received > 0 
                        AND period_year = $intYear 
                        AND policyid = $PolicyID
                        AND IsRefunded = 0 AND payment_status = 'P'";
            } else {
                // If No Value On Year ...
                $sQuery = "SELECT id, payment_date, prem_units 
                        FROM prmtransinfo 
                        WHERE investment_prem = 0 AND received > 0 
                        AND policyid = $PolicyID
                        AND IsRefunded = 0 AND payment_status = 'P'";
            }

            $ds = DbHelper::getTableRawData($sQuery);

            if ($ds != null && count($ds) > 0) {
                foreach ($ds as $nrow) {
                    $sQuery = "";
                    $prem_units = !is_null($nrow->prem_units) ? (int)$nrow->prem_units : 0;
                    $prem_units = $prem_units == 0 ? 1 : $prem_units;
                    $wPayMentDate = Carbon::parse($nrow->payment_date)->format('Y-m-d');

                    $sQuery = "SELECT endorse_date, new_prem, prev_prem, prev_sa, new_sa, InvestmentPrem, NewInvPrem 
                            FROM polendorseinfo 
                            WHERE IsReversed = 0 AND prev_prem <> 0 AND new_prem <> 0 
                            AND policyid = $PolicyID
                            AND endorse_date >= '$wPayMentDate' 
                            AND endorse_date IS NOT NULL 
                            ORDER BY endorse_date ASC LIMIT 1";

                    $ds1 = DbHelper::getTableRawData($sQuery);

                    $investment_prem = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'investment_prem');

                    if ($ds1 != null && count($ds1) > 0) {
                        $dblInvestPrem = !is_null($ds1[0]->InvestmentPrem) ? (double)$ds1[0]->InvestmentPrem : $investment_prem;
                        $dblInvestPrem = $dblInvestPrem == 0 ? $investment_prem : $dblInvestPrem;
                    } else {
                        $dblInvestPrem = $investment_prem;
                    }

                    if (Carbon::parse($wPayMentDate)->year >= 2023) {
                        // Multiply Here to get exact unit...
                        $dblInvestPrem = $dblInvestPrem * $prem_units;
                    }

                    // Update query
                    $this->smartlife_db->table('prmtransinfo')
                        ->where('id', $nrow->id)
                        ->update(['investment_prem' => $dblInvestPrem]);
                }
            }

            // Check For CEPA Activation Or Waiver Of premiums
            // PolicyID
            $TotalPremium = DbHelper::getColumnValue('polinfo', 'id', $PolicyID, 'TotalPremium');
            $StartDateVAR = null;
            $EndDateVAR = null;
            $payment_dateVAR = null;
            $DurationVAR = 0;
            $InvestPremVAR = $investment_prem; // pass Current investment premium
            $ReceivedVAR = $TotalPremium;
            $XFactorVAR = 0;

            //$UnitOfWorkVAR = new UnitOfWork();
            $prmtrans = null;
            //$rsPolinf = $UnitOfWorkVAR->findObject('polinfo', ['id' => $PolicyID->id]);

        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }
    }




    public function reBuildCashValue(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $CashValue = 0;
            
            //The commented section is how am querrying the server
            // $endorse_sql = "SELECT * FROM polendorseinfo p 
            //     WHERE p.PolicyId=$policyId AND p.IsReversed=0 ORDER BY p.Endorse_Date ASC";

            // $polEndorse = DbHelper::getTableRawData($endorse_sql);
            // if (sizeof($polEndorse) > 0) {

            // }
            

            $res = array(
                'success' => true,
                'CashValue' => $CashValue
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
*/

    public function getLifeCashValue(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            //192.168.1.248
            $url_path = "http://192.168.1.248:85/api/Report/CashValue?PolicyNumber=" . $policy_no;

            $client = new \GuzzleHttp\Client;
            $response = $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $CashValue = $response->getBody()->getContents();
                //echo $is_correct;
                // Process the retrieved data as needed
                if (isset($CashValue) && (float)$CashValue > -1) {
                } else {
                    //health questionnaire
                    $res = array(
                        'success' => false,
                        'msg' => "Unable to fetch Amount Available"
                    );
                    return $res;
                }
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'CashValue' => $CashValue
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

    public function getMicroCashValue(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $RebuildCashValue = $request->input('RebuildCashValue');
            $url_path = "http://192.168.1.248:85/api/Report/CashValue?PolicyNumber=" . $policy_no . "&RebuildCashValue=false";
            if ($RebuildCashValue === 1 || $RebuildCashValue === "1") {
                $url_path = "http://192.168.1.248:85/api/Report/CashValue?PolicyNumber=" . $policy_no . "&RebuildCashValue=true";
            } else {
                $url_path = "http://192.168.1.248:85/api/Report/CashValue?PolicyNumber=" . $policy_no . "&RebuildCashValue=false";
            }
            //192.168.1.248


            $client = new \GuzzleHttp\Client;
            $response = $client->get($url_path);

            if ($response->getStatusCode() == 200) {
                $CashValue = $response->getBody()->getContents();
                //echo $is_correct;
                // Process the retrieved data as needed
                if (isset($CashValue) && (float)$CashValue > -1) {
                } else {
                    //health questionnaire
                    $res = array(
                        'success' => false,
                        'msg' => "Unable to fetch Amount Available"
                    );
                    return $res;
                }
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'CashValue' => $CashValue
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

    //get client loans
    public function getClientLifeLoan(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
            //$client_no = $request->input('client_no');
            $client_no = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'client_number');
            $drtn = 0;
            $escCount = 0;
            $drtnYrs = 0;
            $drtnMths = 0;
            $drtnMths2 = 0;
            $drtnMthsEndorse = 0;
            $polDrtn = 0;

            $_dblWithdawalAmt = 0;
            $PreviousloanAmount = 0;
            $dblSurrValue = 0;
            $dblSurrValueAfterEndorse = 0;
            $dblTotalSurrValue = 0;
            $dblTempLoanAmt = 0;
            $dblTotalLoanAmt = 0;
            $dblTotalAmountAvailable = 0;
            $dblTotalLoanAvailable = 0;
            $dtPrevEscalationDate = null;

            //get all policies for the client that are loan applicable
            /*$sql = "SELECT * FROM polinfo p 
            INNER JOIN planinfo d ON p.plan_code=d.plan_code
            INNER JOIN statuscodeinfo e ON e.status_code=p.status_code 
            WHERE (p.client_number='$client_no' AND d.investment_plan=1) AND 
            (e.isActive=1 OR e.printPolicy=1)";*/
            $sql = "SELECT * FROM polinfo p 
            INNER JOIN planinfo d ON p.plan_code=d.plan_code
            INNER JOIN statuscodeinfo e ON e.status_code=p.status_code 
            WHERE (p.policy_no='$policy_no' AND d.loan_applicable=1) AND 
            (e.isActive=1 OR e.printPolicy=1)";

            $polColl = DbHelper::getTableRawData($sql);
            if (sizeof($polColl) > 0) {
                foreach ($polColl as $PolicVAR) {

                    $dtrn = $this->GetMonthDifference($PolicVAR->effective_date, $PolicVAR->prem_due_date);

                    if ($dtrn > (float) $PolicVAR->min_surrender_period) {
                        $escCount = 0;
                        $dtPrevEscalationDate = null;
                        $dblSurrValue = 0;
                        $dblSurrValueAfterEndorse = 0;
                        if ($PolicVAR->investment_plan == true) {

                            $endorse_sql = "SELECT * FROM polendorseinfo p 
                            WHERE p.PolicyId=$policyId AND p.IsReversed=0 ORDER BY p.Endorse_Date ASC";

                            $polEndorse = DbHelper::getTableRawData($endorse_sql);
                            if (sizeof($polEndorse) > 0) {

                                foreach ($polEndorse as $escRec) {
                                    if ($escCount == 0) {
                                        //strPrevEscalationDate = rs4.Fields("escalation_effective_date")
                                        $dtPrevEscalationDate = $escRec->EscEffectiveDate;
                                        $drtnMths = $this->GetMonthDifference($PolicVAR->effective_date, $escRec->EscEffectiveDate);
                                        //If l_duration_mths < 0 Then
                                        if ($drtnMths < 0) {
                                            $drtnMths = 12 + $drtnMths;
                                            //l_duration_yrs = l_duration_yrs - 1
                                            $drtnYrs = $drtnYrs - 1;
                                        }
                                        //W_DurationMths = ((l_duration_yrs * 12) + l_duration_mths)
                                        $drtnMths2 = ($drtnYrs * 12) + $drtnMths;
                                        //W_Surrender_Value = W_Surrender_Value + (CDbl(rs4.Fields("investment_prem")) * W_DurationMths)
                                        $dblSurrValue = $dblSurrValue + ((float)$escRec->InvestmentPrem * $drtnMths2);
                                    } else {
                                        // If rs4.Fields("escalation_effective_date") > strPrevEscalationDate Then
                                        if ($escRec->EscEffectiveDate > $dtPrevEscalationDate) {
                                            $drtnMths = $this->GetMonthDifference($dtPrevEscalationDate, $escRec->EscEffectiveDate);
                                            $dblSurrValue = $dblSurrValue + ((float)$escRec->InvestmentPrem * $drtnMths);
                                        }
                                    }
                                    $escCount = $escCount + 1;
                                    $dtPrevEscalationDate = $escRec->EscEffectiveDate;
                                }
                            } else {
                                //if no endorsement
                                $drtnMths = $this->GetMonthDifference($PolicVAR->effective_date, $PolicVAR->prem_due_date);

                                $dblSurrValue = (float)$PolicVAR->investment_prem * $drtnMths;
                            }

                            if ($escCount != 0) {
                                //'get the number of months from the last escalation to present
                                //'only if premdue date is greater than escalation date
                                //If strPrevEscalationDate < rs2.Fields("prem_due_date") Then
                                if ($dtPrevEscalationDate < $PolicVAR->prem_due_date) {

                                    $drtnMths = $this->GetMonthDifference($dtPrevEscalationDate, $PolicVAR->prem_due_date);
                                    $drtnMthsEndorse = $drtnMths; // (drtnYrs * 12) + drtnMths;
                                    $dblSurrValueAfterEndorse = (float)$PolicVAR->investment_prem * $drtnMthsEndorse;
                                }
                                //dblTotalSurrValue = dblTotalSurrValue + W_Surrender_Value + W_SurrValueAftEndorse

                            }
                            $dblTotalSurrValue = $dblTotalSurrValue + $dblSurrValue + $dblSurrValueAfterEndorse;
                            $dblTempLoanAmt = ($dblTotalSurrValue) * ((float)$PolicVAR->loanrate / 100);
                            $dblTotalAmountAvailable = $dblTempLoanAmt;
                            $dblTotalLoanAvailable = $dblTotalSurrValue;
                            $dblTotalLoanAmt = $dblTempLoanAmt;
                        } else {

                            //drtnYrs = PolicVAR.prem_due_date.Subtract(PolicVAR.effective_date).Days / 365;
                            $drtnMths = $this->GetMonthDifference($PolicVAR->effective_date, $PolicVAR->prem_due_date);
                            //echo "\n";
                            $polDrtn = (int)($drtnMths / 12);
                            if ((int)$polDrtn > (int)$PolicVAR->term_of_policy) {
                                $sum_assured = $PolicVAR->sa;
                                $dblTotalLoanAmt = (float)$sum_assured;

                                $sq = "select COALESCE(SUM(t1.total_proceeds),0) AS dblPwd  
                                FROM  claimsinfo t1 INNER JOIN claims_types t2 ON t1.claim_type=t2.claim_type 
                                WHERE t1.policy_no ='$policy_no' AND t2.IsPwd=1 and t1.processed=1";
                                $ds1 = DbHelper::getTableRawData($sq);
                                if (sizeof($ds1) > 0) {
                                    $_dblWithdawalAmt = $ds1[0]->dblPwd;
                                }
                                $dblTotalLoanAmt = $dblTotalLoanAmt - $_dblWithdawalAmt;

                                //return the sum assured
                                $res = array(
                                    'success' => true,
                                    'TotalLoanAmount' => number_format((float) $PolicVAR->sa, 2, '.', ''),
                                    'TotalLoanAvailable' => number_format((float)$PolicVAR->sa, 2, '.', ''),
                                    'TotalWithdrawal' => number_format((float)$_dblWithdawalAmt, 2, '.', ''),
                                    'AmountAvailable' => number_format((float)$dblTotalLoanAmt, 2, '.', ''),
                                    'PreviousloanAmount' => number_format((float)$PreviousloanAmount, 2, '.', '')
                                );
                                return $res;
                            }



                            //'multiply no of years the policy has completed by sum assured
                            if ($PolicVAR->SurrenderRateTable != null) {
                                //$surrRate = uow.FindObject<sur_rates_info>(CriteriaOperator.Parse("sur_table=? and duration=? and term=?", PolicVAR.plan_code.SurrenderRateTable.id, polDrtn, PolicVAR.term_of_policy));
                                $SurrenderRateTableId = $PolicVAR->SurrenderRateTable; //DbHelper::getColumnValue('PremRateTableCode', 'PlanOldName',$plan_code,'id'); 
                                $term = $PolicVAR->term_of_policy;
                                $surrRate_sql = "SELECT * FROM sur_rates_info p 
                                    WHERE p.sur_table=$SurrenderRateTableId AND p.duration=$polDrtn AND p.term=$term";


                                $surrRate = DbHelper::getTableRawData($surrRate_sql);
                                if ($surrRate != null) {
                                    $dblSurrValue = (float)$PolicVAR->sa * ((float)$surrRate[0]->sur_factor / 100);
                                } else {
                                    //Use Manual Rate ...
                                    $dblSurrValue = (float)$PolicVAR->sa * ((float)$PolicVAR->surrenderpercent / 100);
                                }
                                $dblTotalLoanAvailable = $dblSurrValue;
                                $dblTempLoanAmt = $dblSurrValue * ($PolicVAR->loanrate / 100);
                                //$dblTotalSurrValue = $dblTotalSurrValue + $dblSurrValue;
                                $dblTotalAmountAvailable = $dblTempLoanAmt;
                                $dblTotalLoanAmt =  $dblTempLoanAmt;
                            }
                        }
                        //check for claims
                        //check for claims Here 
                        //total claims 

                        $sq = "select COALESCE(SUM(t1.total_proceeds),0) AS dblPwd  
                        FROM  claimsinfo t1 INNER JOIN claims_types t2 ON t1.claim_type=t2.claim_type 
                        WHERE t1.policy_no ='$policy_no' AND t2.IsPwd=1 and t1.processed=1";
                        $ds1 = DbHelper::getTableRawData($sq);
                        if (sizeof($ds1) > 0) {
                            $_dblWithdawalAmt = $ds1[0]->dblPwd;
                        }
                        $dblTotalLoanAmt = $dblTotalLoanAmt - $_dblWithdawalAmt; //deduct previous PWD claims Here ...

                        //echo 
                        if ($dblTotalLoanAmt < 0) {
                            $dblTotalLoanAmt = 0;
                        }


                        //get Previous Loan Amount
                        $currentYear = (int)date('Y');
                        $sql = "SELECT * FROM loansmasterinfo p WHERE p.Client='$client_no' AND p.LoanYear=$currentYear";
                        $results_sql = DbHelper::getTableRawData($sql);
                        if (sizeof($results_sql) > 0) {
                            $PreviousloanAmount = (float)$results_sql[0]->outstandingBalance;
                        }
                    }
                }
            }




            $res = array(
                'success' => true,
                'TotalLoanAmount' => number_format((float) $dblTotalLoanAvailable, 2, '.', ''),
                'TotalLoanAvailable' => number_format((float)$dblTotalAmountAvailable, 2, '.', ''),
                'TotalWithdrawal' => number_format((float)$_dblWithdawalAmt, 2, '.', ''),
                'AmountAvailable' => number_format((float)$dblTotalLoanAmt, 2, '.', ''),
                'PreviousloanAmount' => number_format((float)$PreviousloanAmount, 2, '.', '')
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

    //get client micro loan - get amount available
    public function getAmountAvailable(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $AmountAvailable = 0;
            $current_year = (int) date('Y');

            //TODO-
            $plan_code = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Plan');
            //1. if edwa get cashvalue from micropolicyinfo
            if ((int) $plan_code == 13) {
                $AmountAvailable = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'CashValue');
            } //2. else if anidaso go to sipfundinfo and get the cacv for the current year
            else {
                $PolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
                $sql = "SELECT * FROM sipfundinfo p WHERE p.fund_year=$current_year AND MicroPolicy='$PolicyId'";
                $Results = DbHelper::getTableRawData($sql);
                if (sizeof($Results) > 0) {
                    $AmountAvailable = $Results[0]->cacv;
                } else {
                    $AmountAvailable = 0;
                }
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'AmountAvailable' => $AmountAvailable
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


    //get client micro loan - get amount available
    public function getMicroLoanAvailable(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $AmountAvailable = 0;
            $current_year = (int) date('Y');

            //TODO-
            $plan_code = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Plan');
            //1. if edwa get cashvalue from micropolicyinfo
            if ((int) $plan_code == 13) {
                $AmountAvailable = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'CashValue');
            } //2. else if anidaso go to sipfundinfo and get the cacv for the current year
            else {
                $PolicyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
                $sql = "SELECT * FROM sipfundinfo p WHERE p.fund_year=$current_year AND MicroPolicy='$PolicyId'";
                if (empty($PolicyId)) {
                    $PolicyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
                    $sql = "SELECT * FROM sipfundinfo p WHERE p.fund_year=$current_year AND policyId='$PolicyId'";
                }

                $Results = DbHelper::getTableRawData($sql);
                if (sizeof($Results) > 0) {
                    $AmountAvailable = $Results[0]->cacv;
                } else {
                    $AmountAvailable = 0;
                }
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'AmountAvailable' => $AmountAvailable
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


    //get policy beneficiaries
    public function getPolicyBeneficiaries(Request $request)
    {
        try {
            $policyId = $request->input('policyId');
            $proposal_no = $request->input('proposal_no');
            $is_micro = $request->input('is_micro');

            $policy_no = $request->input('policy_no');
            if (isset($policy_no)) {
                $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
            }

            if ($is_micro == 1) {
                $sql = "SELECT p.Names,p.AllocationPercentage AS perc_alloc,p.BirthDate as birth_date,
                p.Mobile as mobile,p.Relationship AS relationship, t2.description AS relationship_name
                FROM MicroBeneficiaryInfo p 
                INNER JOIN relationship_mainteinance t2 ON t2.code = p.Relationship";
                if (isset($proposal_no)) {
                    $proposalId = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $proposal_no, 'Id');
                    $sql .= " WHERE p.Proposal='$proposalId'";
                } else {
                    $sql .= " WHERE p.Policy=$policyId";
                }
            } else {
                $sql = "SELECT p.*,t2.description AS relationship_name FROM beneficiary_info p 
                INNER JOIN relationship_mainteinance t2 ON t2.code = p.relationship";
                if (isset($proposal_no)) {
                    $sql .= " WHERE p.proposal_no='$proposal_no'";
                } else {
                    $sql .= " WHERE p.policy_no=$policyId";
                }
            }

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

    //get micro proposal details
    public function getMicroProposalDetails(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $proposal_no = $request->input('proposal_no');
            $policyId = $request->input('policyId');
            $search_entry = $request->input('search_entry');
            $mobile_no = $request->input('mobile_no');
            $criteria = $request->input('criteria');
            if (isset($proposal_no)) {
                $policyId = DbHelper::getColumnValue('MicroProposalInfo', 'ProposalNumber', $proposal_no, 'Id');
            }

            if (isset($policyId)) {
                //effective_date, maturity_date, last_premium_date,term,payment_mode
                $sql = "SELECT T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                T1.EdwankosoProposal,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,                   
            T1.PolicyTerm as term_of_policy,T1.PayMode as pay_mode,
            T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa,
            T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no, 
                  T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, 
                  T4.surname, T4.other_name, T4.mobile, T4.email,
            d.description AS status, d.status_code,
            T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
            from MicroProposalInfo T1 
            left join planinfo T2 on T1.[Plan] = T2.plan_code left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id 
            left join clientinfo T4 ON T1.[Client] = T4.client_number
            INNER JOIN statuscodeinfo d ON d.status_code=T1.[Status]
            INNER JOIN agents_info g ON g.id=T1.Agent
            INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                WHERE T1.Id=$policyId";
            } else {
                /*$sql = "SELECT p.id,p.policy_no,d.mobile,p.effective_date,d.name,d.email,p.plan_code,
                p.modal_prem,p.status_code,e.description AS pay_mode,p.maturity_date FROM polinfo p 
                INNER JOIN clientinfo d ON p.client_number=d.client_number 
                INNER JOIN paymentmodeinfo e ON p.pay_mode=e.id";*/
                $sql = "SELECT T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                T1.EdwankosoProposal,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,                   
            T1.PolicyTerm as term_of_policy,T1.PayMode as pay_mode,
            T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa,
            T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no, 
                  T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, 
                  T4.surname, T4.other_name, T4.mobile, T4.email,
            d.description AS status, d.status_code,
            T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
            from MicroProposalInfo T1 
            left join planinfo T2 on T1.[Plan] = T2.plan_code left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id 
            left join clientinfo T4 ON T1.[Client] = T4.client_number
            INNER JOIN statuscodeinfo d ON d.status_code=T1.[Status]
            INNER JOIN agents_info g ON g.id=T1.Agent
            INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id";
                if (isset($criteria) && $criteria == '1') { //name
                    $sql .= " WHERE T4.name LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '2') { //policy number
                    $sql .= " WHERE T1.ProposalNumber LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '3') { //mobile no
                    $sql .= " WHERE T4.mobile LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '4') { //agent_no
                    $sql .= " WHERE T1.EmployeeNumber LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '5') { //staff_no
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $search_entry, 'id');
                    $sql .= " WHERE T1.Agent = $agentId";
                    // $sql .= " WHERE T1.Agent LIKE '%$search_entry%'";
                } else {
                    if (isset($mobile_no)) {
                        $sql .= " WHERE T4.mobile = '$mobile_no'";
                    } else {
                        //display the last 10,000 records - by default
                        $sql = 'SELECT TOP 100 T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                        T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                        T1.EdwankosoProposal,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,                   
                    T1.PolicyTerm as term_of_policy,T1.PayMode as pay_mode,
                    T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa,
                    T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no, 
						  T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, 
						  T4.surname, T4.other_name, T4.mobile, T4.email,
                    d.description AS status, d.status_code,
                    T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
                    from MicroProposalInfo T1 
                    left join planinfo T2 on T1.[Plan] = T2.plan_code left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id 
                    left join clientinfo T4 ON T1.[Client] = T4.client_number
                    INNER JOIN statuscodeinfo d ON d.status_code=T1.[Status]
                    INNER JOIN agents_info g ON g.id=T1.Agent
                    INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                    ORDER BY T1.Id DESC';
                    }
                }
            }
            $PolicyDetails = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'PolicyDetails' => $PolicyDetails,
                'ClientPolicies' => $PolicyDetails
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

    //get micro policy details
    public function getMicroPolicyDetails(Request $request)
    {
        try {
            $policy_no = $request->input('policy_no');
            $policyId = $request->input('policyId');
            $search_entry = $request->input('search_entry');
            $mobile_no = $request->input('mobile_no');
            $client_no = $request->input('client_no');
            $criteria = $request->input('criteria');

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            if (isset($policy_no)) {
                $policyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');
            }

            if (isset($policyId) && $criteria != "7") {
                //effective_date, maturity_date, last_premium_date,term,payment_mode
                $sql = "SELECT '1' AS is_micro,T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                T5.PolicyNumber AS EdwankosoPolicy,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,
                T1.EffectiveDate as effective_date,T1.MaturityDate as maturity_date,T1.LastPremiumDate as last_premium_date,
                T1.PolicyTerm as term_of_policy,T1.CashValue as cashvalue,
                T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa, T3.description AS pay_mode, T1.PremiumUnits as prem_units,
                T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no,T1.PolicyNumber AS policy_no, T2.description,
                 T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, 
                 T4.email,T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, T4.ClaimDefaultMobileWallet, 
                 T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,T4.ClaimDefaultEFTBank_account, 
                 T4.ClaimDefaultEftBankaccountName,
                (DATEDIFF(MONTH,T1.EffectiveDate,GETDATE()) * t3.coverperiod * T1.ModalPremium) AS expected_prem,
                d.description AS status, d.status_code,
                T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
                FROM MicroPolicyInfo T1 
                left join planinfo T2 on T1.[Plan] = T2.plan_code 
                left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id 
                left join clientinfo T4 ON T1.[Client] = T4.client_number
                LEFT JOIN statuscodeinfo d ON d.status_code=T1.[Status] 
                LEFT JOIN agents_info g ON g.id=T1.Agent
                LEFT JOIN AgentsunitsInfo h ON g.UnitName=h.id
                LEFT JOIN MicroPolicyInfo T5 ON T5.Id = T1.EdwankosoPolicy
                WHERE T1.Id=$policyId";
            } else {
                /*$sql = "SELECT p.id,p.policy_no,d.mobile,p.effective_date,d.name,d.email,p.plan_code,
                p.modal_prem,p.status_code,e.description AS pay_mode,p.maturity_date FROM polinfo p 
                INNER JOIN clientinfo d ON p.client_number=d.client_number 
                INNER JOIN paymentmodeinfo e ON p.pay_mode=e.id";*/
                $sql = "SELECT '1' AS is_micro,T1.Id as id, T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                T5.PolicyNumber AS EdwankosoPolicy,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,
                T1.EffectiveDate as effective_date,T1.MaturityDate as maturity_date,T1.LastPremiumDate as last_premium_date,
                T1.PolicyTerm as term_of_policy, T3.description AS pay_mode,
                T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa, T1.PremiumUnits as prem_units,
                T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no,T1.PolicyNumber AS policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, T4.ClaimDefaultMobileWallet, T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,T4.ClaimDefaultEFTBank_account, T4.ClaimDefaultEftBankaccountName,
                (DATEDIFF(MONTH,T1.EffectiveDate,GETDATE()) * t3.coverperiod * T1.ModalPremium) AS expected_prem,d.description AS status, d.status_code,
                T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
                from MicroPolicyInfo T1 
                left join planinfo T2 on T1.[Plan] = T2.plan_code 
                left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id 
                left join clientinfo T4 ON T1.[Client] = T4.client_number
                LEFT JOIN statuscodeinfo d ON d.status_code=T1.[Status]
                LEFT JOIN agents_info g ON g.id=T1.Agent
                LEFT JOIN AgentsunitsInfo h ON g.UnitName=h.id
                LEFT JOIN MicroPolicyInfo T5 ON T5.Id = T1.EdwankosoPolicy";
                if (isset($criteria) && $criteria == '1') { //name
                    $sql .= " WHERE T4.name LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '2') { //policy number
                    $sql .= " WHERE T1.PolicyNumber = '$search_entry'";
                } else if (isset($criteria) && $criteria == '3') { //mobile no
                    $sql .= " WHERE T4.mobile LIKE '%$search_entry%'";

                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $search_entry, 'client_number');
                    if (!isset($search_entry)) {
                        if (substr($search_entry, 0, 1) == '0') {
                            $search_entry = "233" . ltrim($search_entry, '0');
                        }
                        $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $search_entry, 'client_number');
                    }
                } else if (isset($criteria) && $criteria == '4') { //agent_no
                    $sql .= " WHERE T1.EmployeeNumber LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '5') { //staff_no
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $search_entry, 'id');
                    $sql .= " WHERE T1.Agent = $agentId AND T1.[Status]=10";
                    // $sql .= " WHERE T1.Agent LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '6') { //client_no

                    //staff_no
                    //perform a search and get all the client_nos...
                    $client_momo = DbHelper::getColumnValue('clientinfo', 'client_number', $search_entry, 'mobile');
                    //TODO-1. remove all white spaces $client_momo
                    $client_momo = str_replace(' ', '', $client_momo);
                    //2. if starts with + - remove the first 4 characters
                    if (substr($client_momo, 0, 1) == '+') {
                        $client_momo = substr($client_momo, 4);
                    }
                    //3. if starts with 2 - remove the first 3 characters
                    if (substr($client_momo, 0, 1) == '2') {
                        $client_momo = substr($client_momo, 3);
                    }
                    //4. if starts with 0 - remove the first 1 character
                    if (substr($client_momo, 0, 1) == '0') {
                        $client_momo = substr($client_momo, 1);
                    }

                    $sql_client_no = "SELECT
                    p.client_number,
                    p.name,
                    REPLACE(REPLACE(p.mobile, ' ', ''), '+', '') AS cleaned_mobile,
                    p.id_type
                FROM
                    clientinfo p
                WHERE
                    REPLACE(REPLACE(p.mobile, ' ', ''), '+', '') LIKE '%$client_momo%' ";
                    $ClientNos = DbHelper::getTableRawData($sql_client_no);
                    $where_string = " WHERE (";
                    for ($i = 0; $i < sizeof($ClientNos); $i++) {
                        //build a where string...
                        $where_string .= " T1.[Client] = '" . $ClientNos[$i]->client_number . "'";
                        if ($i < (sizeof($ClientNos) - 1)) {
                            $where_string .= " OR ";
                        }
                    }


                    $sql .= $where_string . ") AND (T1.[Status]=10 OR T1.[Status]=15 OR T1.[Plan] = 13)";


                    //$sql .= " WHERE T1.[Client] = '$search_entry'";



                } else if (isset($criteria) && $criteria == '6') { //staff_no
                    if (!isset($date_from) || !isset($date_to)) {
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }
                    $sql .= " WHERE T1.created_on  BETWEEN '$date_from' AND '$date_to'";
                } else if (isset($criteria) && $criteria == '7') {
                    //specific policy search..
                    if (!isset($policyId)) $policyId = 0;
                    $sql .= " WHERE T1.Id=$policyId ";
                } else {
                    if (isset($mobile_no)) {
                        $sql .= " WHERE T4.mobile = '$mobile_no'";
                    } else {
                        //display the last 10,000 records - by default
                        $sql = 'SELECT TOP 100 T1.LifePremium AS Life_Prem,T1.InvestmentPremium AS investment_prem,
                        T1.BasicPremium AS basic_prem,T1.RiderPremium AS total_rider_prem,T1.PolicyFee AS policyFee,T1.ModalPremium AS modal_prem,
                        T1.EdwankosoPolicy,T1.id,T1.[Plan] as plan_code,T1.PayMethod AS pay_method,T1.Employer AS employer_name,
                    T1.EffectiveDate as effective_date,T1.MaturityDate as maturity_date,T1.LastPremiumDate as last_premium_date,
                    T1.PolicyTerm as term_of_policy, T3.description AS pay_mode,
                    T1.EmployeeNumber AS employee_no,t3.coverperiod,T1.SumAssured AS sa, T1.PremiumUnits as prem_units,
                    T1.ModalPremium AS modal_prem, T1.ModalPremium AS TotalPremium,T1.ProposalNumber AS proposal_no,T1.PolicyNumber AS policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                    (DATEDIFF(MONTH,T1.EffectiveDate,GETDATE()) * t3.coverperiod * T1.ModalPremium) AS expected_prem,d.description AS status, d.status_code,
                    T1.Agent AS agent_no,g.name AS agent_name,h.description AS agent_office 
                    from MicroPolicyInfo T1 
                    left join planinfo T2 on T1.[Plan] = T2.plan_code 
                    left join paymentmodeinfo t3 ON T1.[Plan] = t3.plan_code AND T1.PayMode=t3.id                    
                    left join clientinfo T4 ON T1.[Client] = T4.client_number
                    INNER JOIN statuscodeinfo d ON d.status_code=T1.[Status]
                    INNER JOIN agents_info g ON g.id=T1.Agent
                    INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                    ORDER BY T1.id DESC';
                    }
                }
            }
            //echo $sql;
            //exit();
            $PolicyDetails = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'PolicyDetails' => $PolicyDetails,
                'ClientPolicies' => $PolicyDetails
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

    //get proposal details
    public function getProposalDetails(Request $request)
    {
        try {
            //proposal_no
            $proposal_no = $request->input('proposal_no');
            $policy_no = $request->input('policy_no');
            $policyId = $request->input('policyId');

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            /*if (isset($proposal_no) && !isset($policyId)) {
                //get the policy ID
                $policyId = DbHelper::getColumnValue('proposalinfo', 'proposal_no', $proposal_no, 'id');
            }*/
            $search_entry = $request->input('search_entry');
            $criteria = $request->input('criteria');

            if (isset($proposal_no)) {
                $sql = "SELECT T1.LifePrem as Life_Prem,T1.CepaPrem as Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                T1.employee_no,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                T1.status_code,T1.proposal_no,T1.policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                d.description AS [status], T3.description AS pay_mode,
                T1.agent_no,g.name AS agent_name,h.description AS agent_office 
                FROM proposalinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo T3 on t1.plan_code = T3.plan_code and t1.pay_mode=T3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                INNER JOIN agents_info g ON g.id=T1.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                where T1.proposal_no='$proposal_no'";
            } else {
                $sql = "SELECT T1.LifePrem as Life_Prem,T1.CepaPrem as Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                T1.employee_no,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                T1.status_code,T1.proposal_no,T1.policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                d.description AS [status], T3.description AS pay_mode,
                T1.agent_no,g.name AS agent_name,h.description AS agent_office 
                FROM proposalinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo T3 on t1.plan_code = T3.plan_code and t1.pay_mode=T3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                INNER JOIN agents_info g ON g.id=T1.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id";
                if (isset($criteria) && $criteria == '1') { //name
                    $sql .= " WHERE T4.name LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '2') { //policy number
                    $sql .= " WHERE T1.proposal_no = '$search_entry'";
                } else if (isset($criteria) && $criteria == '3') { //mobile no
                    $sql .= " WHERE T4.mobile LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '4') { //staff_no
                    $sql .= " WHERE T1.employee_no LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '5') { //agent_no
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $search_entry, 'id');
                    $sql .= " WHERE T1.agent_no = $agentId";
                } else if (isset($criteria) && $criteria == '6') { //staff_no
                    if (!isset($date_from) || !isset($date_to)) {
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }
                    $sql .= " WHERE T1.proposal_date  BETWEEN '$date_from' AND '$date_to'";
                } else {
                    //display the last 10,000 records - by default
                    $sql = "SELECT TOP 100 T1.LifePrem as Life_Prem,T1.CepaPrem as Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                    T1.employee_no,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                    T1.status_code,T1.policy_no, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                    d.description AS [status], T3.description AS pay_mode,
                    T1.agent_no,g.name AS agent_name,h.description AS agent_office 
                    FROM proposalinfo T1 
                    left join planinfo T2 on T1.plan_code = T2.plan_code 
                    left join paymentmodeinfo T3 on t1.plan_code = T3.plan_code and t1.pay_mode=T3.id 
                    left join clientinfo T4 on T1.client_number = T4.client_number
                    INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                    INNER JOIN agents_info g ON g.id=T1.agent_no
                    INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id";
                }
            }
            $PolicyDetails = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'PolicyDetails' => $PolicyDetails
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

    //get policy details
    public function getPolicyDetails(Request $request)
    {
        try {
            $policy_no = urldecode($request->input('policy_no'));
            $policyId = $request->input('policyId');

            $is_dashboard = $request->input('is_dashboard');

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');

            if (isset($policy_no) && !isset($policyId)) {
                //get the policy ID
                $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
            }
            $search_entry = $request->input('search_entry');
            $criteria = $request->input('criteria');

            $sql_inject = " AND T2.mortgage = 0 AND T2.is_keyman=0 AND T2.IsLoanProtection=0 ";
            $IsCreditLifeUser = $request->input('IsCreditLifeUser');
            if (isset($IsCreditLifeUser) && $IsCreditLifeUser == 1) {
                $sql_inject = " AND (T2.mortgage = 1 OR T2.is_keyman=1 OR T2.IsLoanProtection=1) ";
            }

            if (isset($policyId) && $criteria != "7") {

                $CurrentPremUnits = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'prem_units');
                $prem_due_dateVAR = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'prem_due_date');
                $pay_mode = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'pay_mode');
                $coverperiod = DbHelper::getColumnValue('paymentmodeinfo', 'id', $pay_mode, 'coverperiod');
                $prem_due_dateVARX = $this->addMonthsToDate($prem_due_dateVAR, $coverperiod);
                $effective_dateVAR = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'effective_date');
                $MonthDateUsedVAR = date('Y-m-d');
                $MonthBetween1 = $this->GetMonthDifference($effective_dateVAR, $MonthDateUsedVAR);
                $ExpectedPremUnits = (int)(($MonthBetween1 / $coverperiod) * $coverperiod);
                //if (Carbon::parse($prem_due_dateVARX) < Carbon::parse($MonthDateUsedVAR)) {
                $MissingPremUnits = (($ExpectedPremUnits) - $CurrentPremUnits);
                if ($MissingPremUnits < 0) {
                    $MissingPremUnits = 0;
                }
                //} else {

                //}
                // you can now multiply by the total premium * Missing Units to get total Premiums Expected
                $TotalPremium = DbHelper::getColumnValue('polinfo', 'id', $policyId, 'TotalPremium');
                $ExpectedPremiums = $ExpectedPremUnits * $TotalPremium;
                $PaidPremiums = $CurrentPremUnits * $TotalPremium;
                $OutstandingPremiums = $MissingPremUnits * $TotalPremium;

                $sql = "SELECT p.*,'0' AS is_micro,
                '$ExpectedPremUnits' AS ExpectedCount,
                '$CurrentPremUnits' AS PaidCount,
                '$MissingPremUnits' AS UnPaidCount,
                '$PaidPremiums' AS PaidPremiums,
                '$ExpectedPremiums' AS ExpectedPremiums,
                '$OutstandingPremiums' AS OutstandingPremiums,
                ROUND(p.TotalPremium, 2) AS Total_Premium,c.description AS status_name,d.[Name] AS employer_name,
                e.bank_code,f.id AS bank_branch_id,d.emp_code,
                e.description AS bank_name,f.bankBranchName AS bank_branch,T4.id_type,T4.IdNumber,T4.occupation_code,
                T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, t2.PercentageValue,
                T4.other_name, T4.mobile, T4.email,T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, 
                T4.ClaimDefaultMobileWallet, T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,
                T4.ClaimDefaultEFTBank_account, T4.ClaimDefaultEftBankaccountName,g.AgentNoCode AS agent_no,
                g.name AS agent_name,h.description AS agent_office,t3.description,t3.microassurance,t3.investment_plan
                FROM polinfo p
                INNER JOIN planinfo t3 ON t3.plan_code=p.plan_code
                INNER JOIN statuscodeinfo c ON c.status_code=p.status_code
                INNER JOIN clientinfo T4 ON p.client_number = T4.client_number
                INNER JOIN agents_info g ON g.id=p.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                left JOIN pay_source_mainteinance d ON p.emp_code=d.emp_code
                left JOIN bankcodesinfo e ON e.bank_code=p.EFTBank_code
                left JOIN bankmasterinfo f ON f.id = p.EFTBankBranchCode
                LEFT JOIN  PremiumIncrementPercentage t2 ON t2.id=p.escalator_rate
                where p.id=$policyId";

                //Do the salary areas thing...


            } else {
                /*$sql = "SELECT p.id,p.policy_no,d.mobile,p.effective_date,d.name,d.email,p.plan_code,
                p.modal_prem,p.status_code,e.description AS pay_mode,p.maturity_date FROM polinfo p 
                INNER JOIN clientinfo d ON p.client_number=d.client_number 
                INNER JOIN paymentmodeinfo e ON p.pay_mode=e.id";*/
                $sql = "SELECT '0' AS is_micro, T1.id,T1.Life_Prem,T1.Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                T1.maturity_date,T1.SearchReferenceNumber,T1.prem_units,T1.id,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,ROUND(T1.TotalPremium, 2) AS TotalPremium,
                T1.status_code,T1.proposal_no,T1.policy_no,T1.term_of_policy,T1.last_premium_date,
                T2.ShowSecondLifeDetails, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,
                T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, 
                T1.pay_method,T1.EFTBank_code,T1.EFTBankBranchCode,T1.EftBankaccountName,
                T1.EFTBank_account,T1.emp_code,T1.MobileWallet,T1.last_prem_date,T1.issued_date,
                T4.other_name, T4.mobile, T4.email,T4.ClaimDefaultPay_method, T4.ClaimDefaultTelcoCompany, 
                T4.ClaimDefaultMobileWallet, T4.ClaimDefaultEFTBank_code, T4.ClaimDefaultEFTBankBranchCode,
                T4.ClaimDefaultEFTBank_account, T4.ClaimDefaultEftBankaccountName, 
                (DATEDIFF(month,t1.effective_date,GETDATE()) * T3.coverperiod * t1.modal_prem) AS expected_prem,
                d.description AS [status], T3.description AS pay_mode, 
                g.AgentNoCode AS agent_no,g.name AS agent_name,h.description AS agent_office,
                g.RecruitedBy, t5.description 'Branch',t6.Description 'Sector',T2.microassurance  
                FROM polinfo T1 
                INNER JOIN planinfo T2 on T1.plan_code = T2.plan_code 
                INNER JOIN paymentmodeinfo T3 on t1.plan_code = T3.plan_code and t1.pay_mode=T3.id 
                INNER JOIN clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                INNER JOIN agents_info g ON g.id=T1.agent_no
                INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                LEFT JOIN AgentsBranchInfo t5 ON t5.id=h.AgentsBranchIdKey
                LEFT JOIN AgentsRegionInfo t6 ON t6.id=t5.AgentsRegionIdKey";

                if (isset($criteria) && $criteria == '1') { //name
                    $sql .= " WHERE T4.name LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '2') { //policy number
                    $sql .= " WHERE T1.policy_no LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '3') { //mobile no
                    //get the client number first....to know the momo number to use
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $search_entry, 'client_number');
                    if (!isset($search_entry)) {
                        if (substr($search_entry, 0, 1) == '0') {
                            $search_entry = "233" . ltrim($search_entry, '0');
                        }
                        $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $search_entry, 'client_number');
                    }

                    $sql .= " WHERE T4.mobile LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '4') { //staff_no
                    $sql .= " WHERE T1.SearchReferenceNumber LIKE '%$search_entry%'";
                } else if (isset($criteria) && $criteria == '5') { //agent_no
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $search_entry, 'id');
                    //get_position
                    $positionId = DbHelper::getColumnValue('agents_info', 'id', $agentId, 'CurrentManagerLevel');
                    $UnitNameId = DbHelper::getColumnValue('agents_info', 'id', $agentId, 'UnitName');

                    //$sql .= " LEFT JOIN agents_info recruiter ON g.RecruitedBy = $agentId WHERE T1.agent_no = $agentId";
                    if ($positionId == 4 || $positionId == 6) {
                        $sql .= " WHERE T1.agent_no IN (SELECT t8.id  FROM agents_info t8 WHERE t8.UnitName=$UnitNameId)";
                    } else if ($positionId == 8 || (isset($is_dashboard)) && $is_dashboard == "1") {
                        $sql .= " WHERE 1=1 ";
                    } else if ($positionId == 7) {
                        $BranchId = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $UnitNameId, 'AgentsBranchIdKey');
                        $sectorId = DbHelper::getColumnValue('AgentsBranchInfo', 'id', $BranchId, 'AgentsRegionIdKey');

                        $sql .= " WHERE t6.id=$sectorId";
                    } else {
                        $sql .= " WHERE T1.agent_no IN 
                        (SELECT t8.id  FROM agents_info t8 WHERE t8.RecruitedBy=$agentId OR t8.id=$agentId)";
                    }

                    if (isset($date_from) && isset($date_to)) {
                        $sql .= " AND (CAST(T1.issued_date AS DATE) BETWEEN '$date_from' AND '$date_to')";
                    }
                } else if (isset($criteria) && $criteria == '6') {
                    //staff_no
                    //perform a search and get all the client_nos...
                    $client_momo = DbHelper::getColumnValue('clientinfo', 'client_number', $search_entry, 'mobile');
                    //TODO-1. remove all white spaces $client_momo
                    $client_momo = str_replace(' ', '', $client_momo);
                    //2. if starts with + - remove the first 4 characters
                    if (substr($client_momo, 0, 1) == '+') {
                        $client_momo = substr($client_momo, 4);
                    }
                    //3. if starts with 2 - remove the first 3 characters
                    if (substr($client_momo, 0, 1) == '2') {
                        $client_momo = substr($client_momo, 3);
                    }
                    //4. if starts with 0 - remove the first 1 character
                    if (substr($client_momo, 0, 1) == '0') {
                        $client_momo = substr($client_momo, 1);
                    }

                    $sql_client_no = "SELECT
                    p.client_number,
                    p.name,
                    REPLACE(REPLACE(p.mobile, ' ', ''), '+', '') AS cleaned_mobile,
                    p.id_type
                FROM
                    clientinfo p
                WHERE
                    REPLACE(REPLACE(p.mobile, ' ', ''), '+', '') LIKE '%$client_momo%' ";
                    $ClientNos = DbHelper::getTableRawData($sql_client_no);
                    $where_string = " WHERE (";
                    for ($i = 0; $i < sizeof($ClientNos); $i++) {
                        //build a where string...
                        $where_string .= " T1.client_number = '" . $ClientNos[$i]->client_number . "'";
                        if ($i < (sizeof($ClientNos) - 1)) {
                            $where_string .= " OR ";
                        }
                    }


                    $sql .= $where_string . ") AND (T1.status_code=10 OR T1.status_code=15) ";

                    //TODO- pass the search to the micro bit as well and return the array and mearge with
                    //the results from this one...


                } else if (isset($criteria) && $criteria == '7') {
                    //specific policy search..
                    if (!isset($policyId)) $policyId = 0;
                    $sql .= " WHERE T1.id = $policyId";
                } else {
                    //display the last 10,000 records - by default
                    $sql = "SELECT TOP 100 T1.Life_Prem,T1.Cepa_Prem,T1.investment_prem,T1.basic_prem,T1.extra_prem,T1.total_rider_prem,T1.policyFee,T1.modal_prem,
                    T1.maturity_date,T1.employee_no AS SearchReferenceNumber,T1.prem_units,T1.id,T1.plan_code,T3.coverperiod,T1.sa,T1.modal_prem,T1.TotalPremium,
                    T1.status_code,T1.proposal_no,T1.policy_no, T2.ShowSecondLifeDetails, T2.description, T2.investment_plan,T4.id_type,T4.IdNumber,T4.client_number,T4.birthdate,T4.sex,T4.name, T4.surname, T4.other_name, T4.mobile, T4.email,
                    (DATEDIFF(month,t1.effective_date,GETDATE()) * T3.coverperiod * t1.modal_prem) AS expected_prem,d.description AS [status], T3.description AS pay_mode,
                    T1.agent_no,g.name AS agent_name,h.description AS agent_office,g.RecruitedBy  
                    FROM polinfo T1 
                    INNER JOIN planinfo T2 ON T1.plan_code = T2.plan_code " . $sql_inject . "
                    left join paymentmodeinfo T3 on t1.plan_code = T3.plan_code and t1.pay_mode=T3.id 
                    left join clientinfo T4 on T1.client_number = T4.client_number
                    INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code
                    INNER JOIN agents_info g ON g.id=T1.agent_no
                    INNER JOIN AgentsunitsInfo h ON g.UnitName=h.id
                    ORDER BY T1.id DESC";
                }
            }

            $PolicyDetails = DbHelper::getTableRawData($sql);
            if (isset($policyId) && $policyId > 0) {
            }

            //if search criteria is policy_no then merge with search from micro
            if (isset($criteria) && $criteria == '6') {
                $microData = json_encode($this->getMicroPolicyDetails($request));
                $microData = json_decode($microData);
                $microData = $microData->original;
                $MicroPolicyDetails = $microData->PolicyDetails;
                //print_r(json_decode($MicroPolicyDetails));
                //exit();

                //TODO merge $PolicyDetails & $MicroPolicyDetails
                $PolicyDetails = array_merge($PolicyDetails, $MicroPolicyDetails);
            }

            //health questionnaire
            $res = array(
                'success' => true,
                'PolicyDetails' => $PolicyDetails
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


    //get history of endorsements
    public function getHistoryEndorsements(Request $request)
    {
        try {
            $res = array();

            $policy_no = $request->input('policy_no');
            $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');

            $is_dashboard = $request->input('is_dashboard');
            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            $is_micro = $request->input('is_micro');


            if (isset($is_micro) && $is_micro == "1") {
                $sql = "SELECT '' AS branch_name,p.EndorsementNumber,'' AS Reason,
                p.created_on AS EndorsementDate,p.EffectiveDate,p.Endorsementtype,
                p.created_on,p.created_by,p.altered_by FROM MicroPolicyEndorsement p ";
                if (isset($client_no)) {
                    $sql .= " WHERE p.Client='$client_no' ";
                } else if (isset($policy_no)) {
                    $policyId = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Id');

                    $sql .= " WHERE p.Policy=" . $policyId;
                } else if (isset($id)) {
                    $sql .= " WHERE d.Id=$id";
                } else if (isset($is_dashboard) && $is_dashboard == "1") {
                    if (!isset($date_from) || !isset($date_to)) {
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }
                    $sql .= " WHERE p.EffectiveDate BETWEEN '$date_from' AND '$date_to'";
                }
            } else {
                $sql = "SELECT p.branch_id,g.glbranch_name AS branch_name,p.EndorsementNumber,p.Reason,
                        p.EndorsementDate,p.EffectiveDate,p.Endorsementtype,p.created_on,p.created_by,
                        p.altered_by FROM EndorsementDashBoard p 
                        INNER JOIN glBranchInfo g ON p.branch_id=g.glBranch";
                if (isset($client_no)) {
                    $sql .= " WHERE p.client_number='$client_no'";
                } else if (isset($policy_no)) {
                    $sql .= " WHERE p.PolicyNumber1=" . $policyId;
                } else if (isset($id)) {
                    $sql .= " WHERE d.id=$id";
                } else if (isset($is_dashboard) && $is_dashboard == "1") {
                    if (!isset($date_from) || !isset($date_to)) {
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    }
                    $sql .= " WHERE p.EffectiveDate BETWEEN '$date_from' AND '$date_to'";
                }
            }

            $EndorsementHistory = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'EndorsementHistory' => $EndorsementHistory
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

    //get requested endorsements
    public function getRequestedEndorsements(Request $request)
    {
        try {
            //$policyId = $request->input('policyId'); 
            $client_no = $request->input('client_no');
            $policy_no = $request->input('policy_no');
            $id = $request->input('id');
            $is_micro = $request->input('is_micro');
            $is_dashboard = $request->input('is_dashboard');

            $date_from = $request->input('date_from');
            $date_to = $request->input('date_to');
            //$policyId = DbHelper::getColumnValue('polinfo', 'policy_no',$policy_no,'id');
            //EndorsementNumber

            $sql = "SELECT p.id,p.Reason,p.NewEffectiveDate,p.NewPreviousWidthDate,p.escalator_rateOLD,
            p.esc_due_dateOLD,p.escalator_rateNEW,p.esc_due_dateNEW,p.pay_methodOLD,p.MobileWalletOLD,
            p.emp_codeOLD,p.employee_noOLDCodeOLD,p.employee_noOLD,p.EFTBank_codeOLD,p.EFTBankBranchCodeOLD,
            p.EFTBank_accountOLD,p.EftBankaccountNameOLD,p.pay_methodNEW,p.MobileWalletNEW,p.emp_codeNEW,
            p.employee_noNEWCodeNEW,p.employee_noNEW,p.EFTBank_codeNEW,p.EFTBankBranchCodeNEW,p.EFTBank_accountNEW,
            p.EFTBank_accountNoDisplayNEW,p.EftBankaccountNameNEW,p.StatusCode1,p.StatusCode2,p.DurationDays,
            p.StatusDescription,p.EffectiveDate,p.CheckOffDate,p.HoldDuration,p.HoldReinstatementDate,
            p.PolicyNumber,p.Endorsementtype,p.RequestDate,p.created_by,p.created_on,p.branch_id,d.policy_no,
            e.glbranch_name as branch_name  
            FROM eEndorsmentEntries p 
            INNER JOIN polinfo d ON p.PolicyNumber=d.id
            LEFT JOIN glBranchInfo e ON p.branch_id=e.glBranch";

            if (isset($client_no)) {
                $sql .= " WHERE d.client_number='$client_no' AND p.StatusDescription <> 'DRAFT'";
            } else if (isset($id)) {
                $sql .= " WHERE p.id=$id";
            } /*else if(isset($policyId)){
               $sql .= " WHERE p.PolicyNumber='$policyId'";
           }*/ else if (isset($policy_no)) {
                $sql .= " WHERE d.policy_no='$policy_no' AND p.StatusDescription <> 'DRAFT'";
            } else if (isset($date_from) || (isset($is_dashboard) && $is_dashboard == "1")) {
                if (!isset($date_from) || !isset($date_to)) {
                    $date_from = date('Y-m-d');
                    $date_to = date('Y-m-d');
                } //RequestDate
                $sql .= " WHERE (p.RequestDate BETWEEN '$date_from' AND '$date_to') AND p.StatusDescription <> 'DRAFT'";
            }


            if (isset($is_micro) && $is_micro == "1") {
                $sql = "SELECT p.id,p.Reason,p.NewEffectiveDate,p.NewPreviousWidthDate,p.escalator_rateOLD,
                p.esc_due_dateOLD,p.escalator_rateNEW,p.esc_due_dateNEW,p.pay_methodOLD,p.MobileWalletOLD,
                p.emp_codeOLD,p.employee_noOLDCodeOLD,p.employee_noOLD,p.EFTBank_codeOLD,p.EFTBankBranchCodeOLD,
                p.EFTBank_accountOLD,p.EftBankaccountNameOLD,p.pay_methodNEW,p.MobileWalletNEW,p.emp_codeNEW,
                p.employee_noNEWCodeNEW,p.employee_noNEW,p.EFTBank_codeNEW,p.EFTBankBranchCodeNEW,p.EFTBank_accountNEW,
                p.EFTBank_accountNoDisplayNEW,p.EftBankaccountNameNEW,p.StatusCode1,p.StatusCode2,p.DurationDays,
                p.StatusDescription,p.EffectiveDate,p.CheckOffDate,p.HoldDuration,p.HoldReinstatementDate,
                p.PolicyNumber,p.Endorsementtype,p.RequestDate,p.created_by,p.created_on,p.branch_id,
                d.PolicyNumber AS policy_no,e.glbranch_name as branch_name 
                FROM eEndorsmentEntries p 
                INNER JOIN MicroPolicyInfo d ON p.MicroPolicy=d.Id
                LEFT JOIN glBranchInfo e ON p.branch_id=e.glBranch ";

                if (isset($client_no)) {
                    $sql .= " WHERE p.Client='$client_no' AND p.StatusDescription <> 'DRAFT'";
                } else if (isset($id)) {
                    $sql .= " WHERE d.id='$id'";
                } else if (isset($policy_no)) {
                    $sql .= " WHERE d.PolicyNumber='$policy_no' AND p.StatusDescription <> 'DRAFT'";
                } else if (isset($is_dashboard) && $is_dashboard == "1") {
                    if (!isset($date_from) || !isset($date_to)) {
                        $date_from = date('Y-m-d');
                        $date_to = date('Y-m-d');
                    } //RequestDate
                    $sql .= " WHERE (p.RequestDate BETWEEN '$date_from' AND '$date_to') AND p.StatusDescription <> 'DRAFT'";
                }
            }

            $Endorsements = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'Endorsements' => $Endorsements
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
    ////TODO-agents to view paysource data//////
    public function getAgentPaySourceData(Request $request)
    {
        try {
            $PaySourceRawData = array();

            $type = $request->input('type'); //1 is employer, 2 is bank
            $agent_no = $request->input('agent_no');
            $Period_year = $request->input('Period_year');
            $Period_month = $request->input('Period_month');
            $Category = $request->input('Category'); //2 is policy, 1 is proposal

            $agent_no = $request->input('agent_no');
            $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $UnitName = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'UnitName');
            $PostionId = DbHelper::getColumnValue('agents_info', 'id', $agentId, 'CurrentManagerLevel');

            if ($type == "1") {
                $sql = "SELECT t1.name,t1.Policy_no,t1.plan_code,t1.status_code,t2.policy_no,t1.Emp_code,
                    t1.Premium,t1.ExpectedAmount,t1.ExcessAmount,t1.AllocatedAmount,
                    t1.AllocatedAmountActual,t1.IsAllocated,t1.Staff_no,t1.Period_year,t1.Period_month,
                    t1.payment_date,t3.AgentNoCode,t3.name 'agent_name'
                    FROM checkoffinfo t1
                    INNER JOIN polinfo t2 ON t2.id=t1.Policy_no
                    INNER JOIN agents_info t3 ON t3.id=t2.agent_no
                    WHERE (t1.Period_year=$Period_year AND t1.Period_month=$Period_month 
                    AND t1.Category=$Category AND t1.IsRefunded=0)";
                if ($PostionId != 8) {
                    $sql .= " AND t3.UnitName=$UnitName";
                }
            } else {
                $sql = "SELECT t1.name,t1.plan_code,t1.status_code,t2.policy_no,t1.Bank_code,
                    t1.Premium,t1.ExpectedAmount,t1.ExcessAmount,t1.AllocatedAmount,
                    t1.AllocatedAmountActual,t1.IsAllocated,t1.BankAccountNumber 'Staff_no',t1.Period_year,
                    t1.Period_month,t1.payment_date,t3.AgentNoCode,t3.name AS agent_name, 
                    t5.description 'Branch',
                    t6.Description 'Sector'
                    FROM Deduct t1
                    INNER JOIN polinfo t2 ON t2.id=t1.Policy_no
                    INNER JOIN agents_info t3 ON t3.id=t2.agent_no
                    INNER JOIN AgentsunitsInfo ON t4.id = t3.UnitName 
                    LEFT JOIN AgentsBranchInfo ON t5.id=t4.AgentsBranchIdKey
                    LEFT JOIN AgentsRegionInfo ON t6.id=t5.AgentsRegionIdKey
                    WHERE (t1.Period_year=$Period_year AND t1.Period_month=$Period_month 
                    AND t1.Category=$Category AND t1.IsRefunded=0) ";
                if ($PostionId != 8) {
                    if ($PostionId == 7) {
                        $BranchId = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $UnitName, 'AgentsBranchIdKey');
                        $sectorId = DbHelper::getColumnValue('AgentsBranchInfo', 'id', $BranchId, 'AgentsRegionIdKey');

                        $sql .= " AND t6.id=$sectorId";
                    } else {
                        $sql .= " AND t3.UnitName=$UnitName";
                    }
                }
            }

            $PaySourceRawData = DbHelper::getTableRawData($sql);

            $res = array(
                'success' => true,
                'PaySourceRawData' => $PaySourceRawData
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

    ////end of agents to view paysource data////

    ///get the paysource raw data (Employees)
    public function getPaySourceRawData(Request $request)
    {
        try {
            //
            $bank_employer = $request->input('bank_employer');
            $staff_no = $request->input('staff_no');
            $source_type = $request->input('source_type');
            $emp_type = $request->input('emp_type');
            $is_wrongful = $request->input('is_wrongful');

            //p.Is_unknown=1 AND 
            // if ($source_type == 2) {
            //allocated
            // AND p.Emp_code='$bank_employer'
            // $sql = "SELECT *,p.Emp_code as EmployerName,p.Staff_no as ReferenceNumber 
            // FROM checkoffinfo p                                            
            // INNER JOIN empsuminfo d ON p.EmpsumKey=d.IdKey                 
            // WHERE p.IsRefunded=0 AND p.IsAllocated=1 ";
            // if (isset($staff_no)) {
            //     $sql .= " AND p.Staff_no='$staff_no'";
            // }
            if ($is_wrongful === "1") {
                $sql = "SELECT IIF(p.[Reference] IS NOT NULL,t4.[Name] ,IIF(p.DeductBatch IS NOT NULL,t5.[description] ,t3.SourceName ) ) 'SOURCE',
                IIF(p.[Reference] IS NOT NULL,'EMPLOYER PREMIUM' ,IIF(p.DeductBatch IS NOT NULL,'BANK PREMIUM' ,'LOAN DEDUCTION' ) ) 'TYPE', p.* 
					 FROM GlobalUnallocated p
                LEFT JOIN Loancheckoffinfo d ON p.ReferenceLoan=d.id
                LEFT JOIN checkoffinfo e ON p.Reference=e.id
                LEFT JOIN Deduct f ON p.ReferenceDDI=f.Id 
                LEFT JOIN empsuminfo t1 ON e.EmpsumKey= t1.IdKey
                LEFT JOIN DeductBatch t2 ON f.DeductBatch=t2.IdKey
                LEFT JOIN LoanReceipts t3 ON d.LoanReceiptKey=t3.IdKey
                LEFT JOIN pay_source_mainteinance t4 ON t1.EmpCode=t4.emp_code
                LEFT JOIN bankcodesinfo t5 ON t2.Bank=t5.bank_code
                WHERE p.IsRefunded=0 AND p.IsReversed=0 AND p.IsAllocated=0  AND  p.ReferenceNumber='$staff_no'";
            } else {
                //1. fetch client no
                //$client_no = DbHelper::getColumnValue('polinfo', 'client_number', $staff_no, 'client_number');
                $sql = "
                    SELECT t1.name,t1.payment_date, t1.Premium AS premium,t1.Year_paid AS Period_year,
                    t1.month_paid AS Period_month,t1.Emp_code AS EmployerName,
                    t1.OLDReferenceNumber AS ReferenceNumber, t1.client_number    
                    FROM checkoffinfo t1  
                    WHERE  t1.IsRefunded=0 AND t1.IsReversed=0 AND t1.GlobalUnallocatedIdKey IS NULL AND
                    (t1.Emp_code = '$bank_employer' AND  t1.OLDReferenceNumber = '$staff_no' )  
                    GROUP BY t1.name,t1.payment_date,t1.Year_paid,t1.month_paid,t1.OLDReferenceNumber,t1.Premium,t1.Emp_code,t1.client_number  
                ";
            }

            $PaySourceRawData = DbHelper::getTableRawData($sql);
            $DeductRawData = [];
            $client_number = null;
            if ($is_wrongful !== "1") {
                $client_number = $PaySourceRawData[0]->client_number;
            }


            if ($is_wrongful !== "1" && isset($client_number)) {
                //$clientNo = DbHelper::getColumnValue('Deduct', 'id', $BranchId, 'AgentsRegionIdKey');
                //query bank also with client number....
                $sql_deduct = "
                    WITH CTE AS (
                        SELECT 
                            p.name, 
                            p.payment_date, 
                            p.premium, 
                            p.Period_year, 
                            p.Period_month, 
                            p.Bank_code as BankName,
                            p.OLDReferenceNumber AS ReferenceNumber,
                            p.Id as id,
                            p.client_number,
                            ROW_NUMBER() OVER (PARTITION BY p.Period_year, p.Period_month ORDER BY p.Period_year, p.Period_month, p.client_number) AS rn
                        FROM 
                            Deduct p
                        INNER JOIN 
                            DeductBatch d ON p.DeductBatch=d.IdKey   
                        WHERE 
                            p.IsRefunded = 0 
                            AND p.IsAllocated = 1 
                            AND p.client_number = '$client_number'
                    )
                    SELECT 
                        name, 
                        payment_date, 
                        premium, 
                        Period_year, 
                        Period_month, 
                        BankName,
                        ReferenceNumber,
                        id,
                        client_number
                    FROM 
                        CTE
                    WHERE 
                        rn = 1
                ";
                ///query from deduct and merge both json arrays...
                $DeductRawData = DbHelper::getTableRawData($sql_deduct);
                if (sizeof($DeductRawData) > 0) {
                    $PaySourceRawData = $PaySourceRawData + $DeductRawData;
                }
            }



            //health questionnaire
            $res = array(
                'success' => true,
                'PaySourceRawData' => $PaySourceRawData
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

    ///get the banks data (Banks)
    public function getBanksRawData(Request $request)
    {
        try {
            //$policyId = $request->input('policyId'); 
            $bank_employer = $request->input('bank_employer');
            $staff_no = $request->input('staff_no');
            $source_type = $request->input('source_type');
            $emp_type = $request->input('emp_type');

            /*$sql = "SELECT p.PeriodYear AS Period_year, p.PeriodMonth AS Period_month, 
            p.ReceiptNoOLD AS ReferenceNumber, p.AmountPaid AS AllocatedAmount, 
            p.Allocated AS RecordMatched  
            FROM empsuminfo p
            WHERE p.Allocated=0";*/

            //p.Is_unknown=1 AND
            //if ($source_type == 2) {
            //for allocated
            //AND p.Bank_code='$bank_employer'
            /*$sql = "SELECT *,p.Id as id,p.Bank_code as BankName,p.BankAccountNumber as ReferenceNumber FROM Deduct p                                            
                INNER JOIN DeductBatch d ON p.DeductBatch=d.IdKey                 
                WHERE  p.IsRefunded=0 AND p.IsAllocated=1 ";
            if (isset($staff_no)) {
                $sql .= " AND p.BankAccountNumber='$staff_no'";
            }
            */
            $sql = "
                WITH CTE AS (
                    SELECT 
                        p.name, 
                        p.payment_date, 
                        p.premium, 
                        p.Period_year, 
                        p.Period_month, 
                        p.Bank_code as BankName,
                        p.OLDReferenceNumber AS ReferenceNumber,
                        p.Id as id,
                        p.client_number,
                        ROW_NUMBER() OVER (PARTITION BY p.Period_year, p.Period_month ORDER BY p.Period_year, p.Period_month,p.client_number) AS rn
                    FROM 
                        Deduct p
                    INNER JOIN 
                        DeductBatch d ON p.DeductBatch=d.IdKey   
                    WHERE 
                        p.IsRefunded = 0 
                        AND p.IsAllocated = 1 
                        AND p.OLDReferenceNumber = '$staff_no'
                        AND p.Bank_code = '$bank_employer'
                )
                SELECT 
                    name, 
                    payment_date, 
                    premium, 
                    Period_year, 
                    Period_month, 
                    BankName,
                    ReferenceNumber,
                    id,
                    client_number
                FROM 
                    CTE
                WHERE 
                    rn = 1
                ";
            // } else if ($source_type == 1) {
            //     //raw data
            //     $sql = "SELECT *,p.ID as id FROM PaysourcerawdataDetails p 
            //     INNER JOIN loadpaysourcerawdata d ON p.LoadedData=d.ID 
            //     WHERE p.RecordMatched=0 AND d.IsForBanks=1";
            // }

            $PaySourceRawData = DbHelper::getTableRawData($sql);
            $DeductRawData = [];
            $client_number = $PaySourceRawData[0]->client_number;

            if (isset($client_number)) {
                $sql_checkoff = "
                    SELECT t1.name,t1.payment_date, t1.Premium AS premium,t1.Year_paid AS Period_year,
                    t1.month_paid AS Period_month,t1.Emp_code AS EmployerName,
                    t1.OLDReferenceNumber AS ReferenceNumber, t1.client_number    
                    FROM checkoffinfo t1  
                    WHERE  t1.IsRefunded=0 AND t1.IsReversed=0 AND t1.GlobalUnallocatedIdKey IS NULL AND
                    (t1.OLDReferenceNumber = '$client_number' )  
                    GROUP BY t1.name,t1.payment_date,t1.Year_paid,t1.month_paid,t1.OLDReferenceNumber,t1.Premium,t1.Emp_code,t1.client_number  
                ";

                $DeductRawData = DbHelper::getTableRawData($sql_checkoff);
                if (sizeof($DeductRawData) > 0) {
                    $PaySourceRawData = $PaySourceRawData + $DeductRawData;
                }
            }



            //health questionnaire
            $res = array(
                'success' => true,
                'PaySourceRawData' => $PaySourceRawData
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

    //1. add get activities endpoint
    public function getActivitiesData(Request $request)
    {
        try {
            //$policyId = $request->input('policyId'); 
            $n = $request->input('n');
            $is_micro = $request->input('is_micro');
            $date_from = $request->input('date_from');
            $source_type = $request->input('source_type');
            if (!isset($date_from) || $date_from == "NaN-NaN-NaN") {
                $date_from = Date('Y-m-d');
            }
            $date_to = $request->input('date_to');
            if (!isset($date_to) || $date_to == "NaN-NaN-NaN") {
                $date_to = Date('Y-m-d');
            }
            $created_by = $request->input('created_by');

            //$branch_id = DbHelper::getColumnValue('PermissionPolicyUser', 
            //'UserName', $username, 'Branch');
            //$sql = "SELECT * FROM pos_log p";  INNER JOIN portal_users e ON p.created_by=e.username
            $sql = "SELECT p.id,p.ClientName,p.staff_no,p.Activity,p.ComplaintType,p.Narration,
             p.created_on,p.created_by,d.Branch,g.glbranch_name as branch_name 
             FROM pos_log p 
            
             LEFT JOIN PermissionPolicyUser d ON d.UserName = p.created_by
             LEFT JOIN glBranchInfo g ON d.Branch=g.glBranch 
             WHERE ";

            if ($n == "6") {
                $sql .= " p.Activity=3 AND ";
            }

            if ($n == "7") {
                $sql .= " p.Activity=4 AND ";
            }

            if ($source_type == "1" || $is_micro == "0") {
                $sql .= " d.Module='IL' AND ";
            }
            if ($source_type == "2" || $is_micro == "1") {
                $sql .= " d.Module='MI' AND ";
            }

            $sql .= " CAST(p.created_on AS DATE)  BETWEEN '$date_from' AND '$date_to' 
            GROUP BY p.id,p.ClientName,p.staff_no,p.Activity,p.Narration,p.created_on,
            p.created_by,d.Branch,g.glbranch_name,p.ComplaintType
            ORDER BY p.id DESC";

            $Activities = DbHelper::getTableRawData($sql);

            //TODO - Display the Summaries..
            $sql_summ = "SELECT COUNT(DISTINCT p.staff_no) AS total, d.Branch, 
            g.glbranch_name 'branch_name' 
            FROM pos_log p 
            LEFT JOIN PermissionPolicyUser d ON d.UserName = p.created_by 
            LEFT JOIN glBranchInfo g ON d.Branch=g.glBranch 
            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to'
            GROUP BY d.Branch, g.glbranch_name";

            $ClientSummary = DbHelper::getTableRawData($sql_summ);

            /*$sql_summ = "SELECT COUNT(DISTINCT p.staff_no) AS total, 
            FROM pos_log p 
            WHERE CAST(p.created_on AS DATE) BETWEEN '$date_from' AND '$date_to'
            GROUP BY d.Branch, g.glbranch_name";

            $ActivitiesSummary = DbHelper::getTableRawData($sql_summ);*/

            //health questionnaire
            $res = array(
                'success' => true,
                'Activities' => $Activities,
                'ClientSummary' => $ClientSummary
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

    //2. add post activities endpoint
    public function postPOSActivities(Request $request)
    {
        try {
            $pos_log_data = array(
                'ClientName' => $request->input('ClientName'),
                'staff_no' => $request->input('staff_no'),
                'Activity' => $request->input('Activity'),
                'Narration' => $request->input('Narration'),
                'eClaimId' => null,
                'eEndorsementId' => null,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => $request->input('created_by'),
                'ComplaintType' => $request->input('ComplaintType')
            );
            $pos_log_id = $this->smartlife_db->table('pos_log')->insertGetId($pos_log_data);
            //health questionnaire
            $res = array(
                'success' => true,
                'record_id' => $pos_log_id
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


    //save endorsement
    //post agent registration
    public function saveEndorsement(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $table_data = json_decode($request->input('tableData'));
                $Endorsementtype = $table_data->Endorsementtype;
                if (isset($table_data->id)) {
                    $id = $table_data->id;
                    unset($table_data->id);
                }
                $PolicyNumber = $table_data->PolicyNumber;

                if (isset($table_data->MicroPolicy) && $table_data->MicroPolicy > 0) {
                    $MicroPolicy = $table_data->MicroPolicy;
                    $PolicyNumber = null;
                } //

                if (isset($table_data->emp_codeOLD)) {
                    $table_data->emp_codeOLD = DbHelper::getColumnValue('pay_source_mainteinance', 'Name', $table_data->emp_codeOLD, 'emp_code');
                } //telco
                if (isset($table_data->telco)) {
                    $table_data->emp_codeOLD = $table_data->telco;
                    unset($table_data->telco);
                }
                if (isset($table_data->relationship_name)) { //relationship_name
                    unset($table_data->relationship_name);
                }
                if (isset($table_data->statuscode)) { //statuscode, policy_no
                    unset($table_data->statuscode);
                }
                if (isset($table_data->policy_no)) { //statuscode, policy_no
                    unset($table_data->policy_no);
                }
                if (isset($table_data->branch_name)) { //statuscode, policy_no
                    unset($table_data->branch_name);
                }

                $ClientName = "";
                if (isset($table_data->name)) { //name
                    $ClientName = $table_data->name;
                    $table_data->ClientName = $table_data->name;
                    unset($table_data->name);
                }
                if (isset($table_data->mobile) || empty($table_data->mobile)) { //mobile
                    unset($table_data->mobile);
                } //
                $username = "";
                if (isset($table_data->user_id)) { //user_id
                    $user_id = $table_data->user_id;
                    unset($table_data->user_id);
                    //get the Branch id here
                    $username = $user_id; //DbHelper::getColumnValue('portal_users', 'id',$user_id,'username');
                    $branch_id = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'Branch');
                    $table_data->branch_id = $branch_id;
                }

                $table_data->RequestDate = date('Y-m-d H:i:s');
                $table_data->date_synced = date('Y-m-d H:i:s');
                //branch_id

                $is_from_claims = 0;
                if (isset($table_data->is_from_claims) && (int) $table_data->is_from_claims > 0) {
                    $is_from_claims = 1;
                    unset($table_data->is_from_claims);
                }

                $beneficiaries_obj = $request->input('beneficiaries');
                if (isset($beneficiaries_obj)) {
                    $beneficiaries = json_decode($beneficiaries_obj);
                    if ($is_from_claims == 1) {
                        $endorsementData = array(
                            'Endorsementtype' => 6,
                            'RequestDate' => Carbon::now(),
                            'PolicyNumber' => $PolicyNumber,
                            'StatusDescription' => 'SUBMITTED'
                        );
                        $id = $this->smartlife_db->table('eEndorsmentEntries')->insertGetId($endorsementData);
                    }

                    if (isset($id)) {
                        $this->smartlife_db->table('beneficiary_infoEndorse')->where('EndorseRequestID', '=', $id)->delete();
                        for ($i = 0; $i < sizeof($beneficiaries); $i++) { //
                            $beneficiaries[$i]->EndorseRequestID = $id;
                            unset($beneficiaries[$i]->proposal_no);
                            unset($beneficiaries[$i]->policy_no);
                            unset($beneficiaries[$i]->OldPolicyNo);
                            unset($beneficiaries[$i]->OldRelation);
                            unset($beneficiaries[$i]->isMigrated); //
                            unset($beneficiaries[$i]->telephone);
                            unset($beneficiaries[$i]->IsAuto);
                            unset($beneficiaries[$i]->relationship_name);
                            $beneficiaries[$i]->GuardianSurname = $beneficiaries[$i]->GuardianSurname . ' ' . $beneficiaries[$i]->GuardianOtherNames;
                            unset($beneficiaries[$i]->GuardianOtherNames);
                            if (isset($beneficiaries[$i]->id)) {
                                $beneficiaries[$i]->OLDIdKey = $beneficiaries[$i]->id;
                                unset($beneficiaries[$i]->id);
                            }
                            $beneficiaries_id = $this->smartlife_db->table('beneficiary_infoEndorse')->insertGetId(json_decode(json_encode($beneficiaries[$i]), true));
                        }
                    }
                }
                //$table_data->date_synced = date('Y-m-d H:i:s');
                //$table_data->created_on = date('Y-m-d H:i:s');
                //$table_data->RequestDate = date('Y-m-d H:i:s');
                //$table_data->status = 1;
                if (isset($table_data->signature)) {
                    $signature = $table_data->signature;
                    unset($table_data->signature);
                }



                //save here

                if (isset($id) && (int) $id > 0) {
                    $table_data->altered_by = $username;
                    $table_data->dola = date('Y-m-d H:i:s');
                    //update
                    $table_data = json_decode(json_encode($table_data), true);
                    $this->smartlife_db->table('eEndorsmentEntries')
                        ->where(
                            array(
                                "id" => $id
                            )
                        )
                        ->update($table_data);
                    $record_id = $id;
                } else {
                    $table_data->created_by = $username;
                    $table_data->created_on = date('Y-m-d H:i:s');
                    //insert
                    $table_data = json_decode(json_encode($table_data), true);
                    $record_id = $this->smartlife_db->table('eEndorsmentEntries')->insertGetId($table_data);
                }

                //if(isset($signature)) $this->saveStringFile($signature,$category_id,$req_code,$eEndorsementId,$fileName);
                if (isset($signature)) {
                    if (isset($signature))
                        $this->saveStringFile($signature, 3, 5, $record_id, $record_id . '.png');
                }

                //insert into pos_log
                $pos_log_e_id = DbHelper::getColumnValue('pos_log', 'eEndorsementId', $record_id, 'id');
                if (!isset($pos_log_e_id)) {

                    if (isset($PolicyNumber)) {
                        $staff_no = DbHelper::getColumnValue('polinfo', 'id', $PolicyNumber, 'SearchReferenceNumber');
                    }
                    if (isset($MicroPolicy)) {
                        $staff_no = DbHelper::getColumnValue('MicroPolicyInfo', 'Id', $MicroPolicy, 'EmployeeNumber');
                    }
                    //narration is the claim_type
                    $narration = DbHelper::getColumnValue('LifeEndorsementTypeInfo', 'Id', $Endorsementtype, 'Description');
                    $policy_number = DbHelper::getColumnValue('polinfo', 'id', $PolicyNumber, 'policy_no');
                    $narration .= " (Policy Number: " . $policy_number . ")";

                    $pos_log_data = array(
                        'ClientName' => $ClientName,
                        'staff_no' => $staff_no,
                        'Activity' => 2,
                        'Narration' => $narration,
                        'eClaimId' => null,
                        'eEndorsementId' => $record_id,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $user_id
                    );
                    $pos_log_id = $this->smartlife_db->table('pos_log')->insertGetId($pos_log_data);
                }

                $res = array(
                    'success' => true,
                    'record_id' => $record_id,
                    'message' => 'Endorsement Successfully!!'
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

    ////Save Endorsements Files/////////
    //Image Sync
    public function syncEndorsementsImage(Request $request)
    {
        try {
            //for base64
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $myFile = $request->file('myFile');
                $req_code = $request->input('req_code');
                $Description = $request->input('Description'); //DbHelper::getColumnValue('claim_requirement', 'reg_code',$req_code,'description');
                $eEndorsementId = $request->input('eEndorsementId');
                //$signature = $request->input('signature');
                $category_id = 3;

                $fileName = $eEndorsementId . ".png"; //"signature.png";

                if (isset($myFile))
                    $this->savePhysicalFile($myFile, $category_id, $req_code, $eEndorsementId, $Description);
                //if(isset($signature)) $this->saveStringFile($signature,$category_id,$req_code,$eEndorsementId,$fileName);

                $res = array(
                    'success' => true,
                    'record_id' => $eEndorsementId,
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

    public function savePhysicalFile($file, $category_id, $req_code, $eEndorsementId, $Description)
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
        //$destinationPath = 'C:\Users\kgach\Documents\SmartLife\EndorsementDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');
        $file->move($destinationPath, $file->getClientOriginalName());
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();

        //insert into mob_proposalFileAttachment
        //claim_no,code,received_flag,date_received,MicroClaim,eClaimNumber,File,Description
        $table_data = array(
            'Oid' => $uuid,
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            //'code' => $req_code,
            //'received_flag' => 0,
            'created_on' => Carbon::now(),
            //'MicroClaim' => 0,
            'Endorsement' => $eEndorsementId,
            'File' => $uuid,
            'Description' => $fileName //$Description,
        );
        $record_id = $this->smartlife_db->table('eEndorsementAttachment')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'EndorsementRequest' => $eEndorsementId,
            'Size' => $file_size,
        );
        $record_id = $this->smartlife_db->table('EndorsementStoreObject')->insertGetId($table_data);
    }

    function base64ToVarbinary($base64)
    {
        $binary = base64_decode($base64);
        return bin2hex($binary);
    }

    public function saveStringFile($file, $category_id, $req_code, $eEndorsementId, $fileName)
    {
        //$destinationPath = 'C:\Users\kgach\Documents\SmartLife\EndorsementDocuments';
        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');
        file_put_contents($destinationPath . '\\' . $fileName, base64_decode($file));

        $image_path = $destinationPath . "\\" . $eEndorsementId . ".png";
        $image_binary = file_get_contents($image_path);
        $this->smartlife_db->table('eClaimsEntries')
            ->where('id', $eEndorsementId)
            ->update([
                'ClientSignature' => DB::raw("0x" . bin2hex($image_binary)),
                'StatusDescription' => "SUBMITTED"
            ]);
        /*$this->smartlife_db->table('eClaimsEntries')
            ->where('id', $eClaimId)
            ->update(['ClientSignature' => DB::raw("0x" . bin2hex($image_binary))]);*/

        //insert into mob_proposalFileAttachment
        $uuid = Uuid::uuid4();
        $uuid = $uuid->toString();
        $table_data = array(
            'Oid' => $uuid,
            'created_on' => Carbon::now(),
            //'claim_no' => $claim_id,
            //'code' => $req_code,
            //'received_flag' => 0,
            'created_on' => Carbon::now(),
            //'MicroClaim' => 0,
            'Endorsement' => $eEndorsementId,
            'File' => $uuid,
            'Description' => $fileName //$Description,
        );
        $record_id = $this->smartlife_db->table('eEndorsementAttachment')->insertGetId($table_data);
        //insert into Mob_ProposalStoreObject
        $table_data = array(
            'Oid' => $uuid,
            //'claimno' => $eClaimId,
            'FileName' => $fileName,
            'EndorsementRequest' => $eEndorsementId,
            'Size' => 570,
        );
        $record_id = $this->smartlife_db->table('EndorsementStoreObject')->insertGetId($table_data);
    }
    //////////end of Endorsements Files///

    //get Endorsement Attached files
    public function getEndorsementFiles(Request $request)
    {
        try {
            //get files for eClaim
            $rcd_id = $request->input('rcd_id');

            $sql = "SELECT p.* from eEndorsementAttachment p WHERE p.Endorsement=$rcd_id";
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

    public function getPolicyFiles(Request $request)
    {
        try {
            //get files for eClaim
            $rcd_id = $request->input('rcd_id');

            $sql = "SELECT p.* from mob_proposalFileAttachment p WHERE p.MobileProposal=$rcd_id";
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


    //////////////////////migrate////////////////////

    function reduceRemoteImageOrPDFSizeTo150KB($sourceFileUrl)
    {
        error_reporting(E_ERROR | E_PARSE);
        $fileData = file_get_contents($sourceFileUrl);

        if ($fileData === false) {
            // Error handling: Unable to fetch the remote file
            return $fileData;
        }

        // Determine the file type based on the first few bytes of the file data
        $fileType = $this->determineFileType($fileData);

        if ($fileType === 'image/png') {
            // PNG image
            $quality = 9; // Starting quality (valid range: 0-9)
            while (strlen($fileData) > 150 * 1024 && $quality >= 0) {
                $image = imagecreatefromstring($fileData);
                ob_start();
                imagepng($image, null, $quality);
                $compressedData = ob_get_clean();
                imagedestroy($image);

                if ($compressedData === false) {
                    // Error handling: Unable to compress the image
                    return $fileData;
                }

                $fileData = $compressedData;
                $quality--; // Reduce quality
            }
        } elseif ($fileType === 'image/jpeg') {
            // JPEG image
            $quality = 80; // Starting quality
            while (strlen($fileData) > 150 * 1024 && $quality >= 10) {
                $compressedData = imagejpeg(imagecreatefromstring($fileData), null, $quality);

                if ($compressedData === false) {
                    // Error handling: Unable to compress the image
                    return $fileData;
                }

                $fileData = $compressedData;
                $quality -= 10;
            }
        } elseif ($fileType === 'application/pdf') {
            // PDF file
            // You can implement PDF compression logic here (e.g., using Ghostscript).
            // For simplicity, this example does not perform PDF compression.
            return $fileData;
        } else {
            // Unsupported file type or unable to determine file type
            return $fileData; // Return the original file data
        }

        return $fileData; // Return the compressed image or PDF data
    }


    // Function to determine file type based on file data
    function determineFileType($fileData)
    {
        if (strncmp($fileData, "\x89PNG", 4) === 0) {
            return 'image/png';
        } elseif (strncmp($fileData, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        } elseif (strncmp($fileData, "%PDF-", 5) === 0) {
            return 'application/pdf';
        }

        return false; // Unable to determine file type
    }

    //migrate files to proposalinfo
    public function migrateDeleteFiles(Request $request)
    {
        try {

            $res = array();
            $this->smartlife_db->table('mob_prop_info')
                ->update([
                    'IdFrontPage' => null
                ]);

            $res = array(
                'success' => true,
                'message' => 'Ids Deleted Successfully'
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

    //migrate files to proposalinfo
    public function migratProposalFiles(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $mproposal_data = json_decode($request->input('mproposal_data'));

                $MobPropArr = json_decode(json_encode($mproposal_data), true);
                //$destinationPath = 'file://///192.168.1.5/assets';
                $destinationPath = 'http://192.168.1.5/mprop_api/assets';

                for ($i = 0; $i < sizeof($MobPropArr); $i++) {

                    /*$proposal_no = $MobPropArr[$i]['proposal_no'];
                    $id_path = $MobPropArr[$i]['id_file'];
                    if(!isset($id_path) || empty($id_path) || $id_path == null || $id_path == ''){
                        $this->smartlife_db->table('mob_prop_info')
                        ->where('proposal_no', $proposal_no)
                        ->update([
                            'IdFrontPage' => null
                        ]);
                    }*/
                    $proposal_no = $MobPropArr[$i]['proposal_no'];
                    $is_file_migrated = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $proposal_no, 'is_file_migrated');
                    $sig_binary = null;
                    $id_binary = null;

                    if ($is_file_migrated == 0) {
                        $id_name = $MobPropArr[$i]['id_file'];
                        $sig_name = $MobPropArr[$i]['sig'];
                        $sig_path = $destinationPath . "/files/" . $MobPropArr[$i]['sig'];
                        $id_path = $destinationPath . "/attachments/" . $MobPropArr[$i]['id_file'];



                        $headers_sig = get_headers($sig_path);
                        if (!isset($sig_name) || empty($sig_name) || $sig_name == null || $sig_name == '') {
                            $sig_binary = null;
                        } else {
                            if ($headers_sig && strpos($headers_sig[0], '200 OK') !== false) {
                                $tmp_sig_binary = file_get_contents($sig_path);
                                //$tmp_sig_binary = $this->reduceRemoteImageSizeTo150KB($sig_path);
                                $sig_binary = DB::raw("0x" . bin2hex($tmp_sig_binary));
                            }
                        }


                        $headers_id = get_headers($id_path);
                        if (!isset($id_name) || empty($id_name) || $id_name == null || $id_name == '') {
                            $id_binary = null;
                        } else {
                            if ($headers_id && strpos($headers_id[0], '200 OK') !== false) {
                                $tmp_id_binary = file_get_contents($id_path);
                                //$tmp_id_binary = $this->reduceRemoteImageOrPDFSizeTo150KB($id_path);
                                $id_binary = DB::raw("0x" . bin2hex($tmp_id_binary));
                            }
                        }
                    }

                    $this->smartlife_db->table('mob_prop_info')
                        ->where('proposal_no', $proposal_no)
                        ->update([
                            'ClientSignature' => $sig_binary,
                            'IdFrontPage' => $id_binary,
                            'is_file_migrated' => 1
                        ]);
                }

                $res = array(
                    'success' => true,
                    'message' => 'Migrated Successfully!!'
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

    //migrate missed Proposals
    public function migrateMissedFiles(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {
                $n = $request->input('n');

                $mproposal_data = json_decode($request->input('mproposal_data'));
                $MobPropArr = json_decode(json_encode($mproposal_data), true);


                $total_affected = 0;
                /*for($i=0;$i<sizeof($MobPropArr);$i++){
                    //check if proposal no exists....
                    $proposal_no = $MobPropArr[$i]['proposal_no'];
                    $proposal_no = DbHelper::getColumnValue('mob_prop_info', 'proposal_no', $proposal_no, 'proposal_no');
                    if(!isset($proposal_no)){
                        $this->smartlife_db->table('mob_prop_info')->insert($MobPropArr[$i]);
                        $total_affected++;
                    }
                }*/
                if ($n == 2) {
                    //move the riders

                    for ($i = 0; $i < sizeof($MobPropArr); $i++) {
                        $mobile_id = $MobPropArr[$i]['mobile_id'];
                        $rider = $MobPropArr[$i]['rider_copy'];
                        $sql = "SELECT * FROM mob_rider_info p 
                        WHERE p.mobile_id IS NOT NULL AND p.mobile_id='$mobile_id' AND p.rider_copy='$rider'";
                        $Riders = DbHelper::getTableRawData($sql);
                        if (sizeof($Riders) > 0) {
                            //do nothing...
                        } else {
                            //insert
                            $this->smartlife_db->table('mob_rider_info')->insert($MobPropArr[$i]);
                            $total_affected++;
                        }
                    }
                }

                if ($n == 3) {
                    //move the funeral members

                    for ($i = 0; $i < sizeof($MobPropArr); $i++) {
                        $mobile_id = $MobPropArr[$i]['mobile_id'];
                        $Relationship_copy = $MobPropArr[$i]['Relationship_copy'];
                        $sql = "SELECT * FROM mob_funeralmembers p 
                        WHERE p.mobile_id IS NOT NULL AND p.mobile_id='$mobile_id' AND p.Relationship_copy='$Relationship_copy'";
                        $Dependants = DbHelper::getTableRawData($sql);
                        if (sizeof($Dependants) > 0) {
                            //do nothing...
                        } else {
                            //insert
                            $this->smartlife_db->table('mob_funeralmembers')->insert($MobPropArr[$i]);
                            $total_affected++;
                        }
                    }
                }

                if ($n == 4) {
                    //move the beneficiaries

                    for ($i = 0; $i < sizeof($MobPropArr); $i++) {
                        $mobile_id = $MobPropArr[$i]['mobile_id'];
                        $relationship_copy = $MobPropArr[$i]['relationship_copy'];
                        $sql = "SELECT * FROM mob_beneficiary_info p 
                        WHERE p.mobile_id IS NOT NULL AND p.mobile_id='$mobile_id' AND p.relationship_copy='$relationship_copy'";
                        $Beneficiaries = DbHelper::getTableRawData($sql);
                        if (sizeof($Beneficiaries) > 0) {
                            //do nothing...
                        } else {
                            //insert
                            $this->smartlife_db->table('mob_beneficiary_info')->insert($MobPropArr[$i]);
                            $total_affected++;
                        }
                    }
                }

                $res = array(
                    'success' => true,
                    'message' => 'Missed Migrated Successfully!!',
                    'Total Affected' => $total_affected
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

    //migrate proposal
    public function migrateMproposal(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                $mproposal_data = json_decode($request->input('mproposal_data'));
                $n = $request->input('n');

                if ($n == 1) { //mob_prop_info
                    $this->smartlife_db->table('mob_prop_info')->insert(json_decode(json_encode($mproposal_data), true));
                } else if ($n == 2) { //mob_rider_info
                    $data = json_decode(json_encode($mproposal_data), true); // Your data to be inserted

                    // Define the batch size (e.g., 100 rows per batch)
                    $batchSize = 100;
                    //$this->smartlife_db->table('mob_rider_info')->insert($data);

                    // Use chunk to insert in batches
                    collect($data)->chunk($batchSize)->each(function ($batch) {
                        //YourModel::insert($batch->toArray());
                        $this->smartlife_db->table('mob_rider_info')->insert($batch->toArray());
                    });
                } else if ($n == 3) { //mob_funeralmembers
                    $data = json_decode(json_encode($mproposal_data), true); // Your data to be inserted

                    // Define the batch size (e.g., 100 rows per batch)
                    $batchSize = 100;
                    //$this->smartlife_db->table('mob_rider_info')->insert($data);

                    // Use chunk to insert in batches
                    collect($data)->chunk($batchSize)->each(function ($batch) {
                        //YourModel::insert($batch->toArray());
                        $this->smartlife_db->table('mob_funeralmembers')->insert($batch->toArray());
                    });
                } else if ($n == 4) { //mob_beneficiary_info
                    $data = json_decode(json_encode($mproposal_data), true); // Your data to be inserted

                    // Define the batch size (e.g., 100 rows per batch)
                    $batchSize = 100;
                    //$this->smartlife_db->table('mob_rider_info')->insert($data);

                    // Use chunk to insert in batches
                    collect($data)->chunk($batchSize)->each(function ($batch) {
                        //YourModel::insert($batch->toArray());
                        $this->smartlife_db->table('mob_beneficiary_info')->insert($batch->toArray());
                    });
                }


                $res = array(
                    'success' => true,
                    'message' => 'Migrated Successfully!!'
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
    //////end of mproposal migrate//////


}
