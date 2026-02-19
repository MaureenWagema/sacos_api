<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class parametersController extends Controller
{
    //get Insurance Cover Types
    public function getInsuranceCoverTypes(Request $request)
    {
        try {
            $Plan_code = $request->input('Plan_code');
            /*$sql = "SELECT p.InsuranceType,p.PremiumAmount,d.description FROM ProductBenefitsConfig p 
            INNER JOIN InsuranceCoverTypes d ON d.id=p.InsuranceType 
            WHERE p.Plan_code='$Plan_code' GROUP BY p.InsuranceType,p.PremiumAmount,d.description";*/
            $sql = "SELECT p.id AS InsuranceType,p.PremiumAmount,p.description FROM bapackages p 
                    WHERE p.Plan_code=$Plan_code";
            $InsuranceCoverTypes = DbHelper::getTableRawData($sql);

            //health questionnaire
            $res = array(
                'success' => true,
                'InsuranceCoverTypes' => $InsuranceCoverTypes
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

    public function getCommonParams(Request $request)
    {
        try {


            $agent_no = $request->input('agent_no');
            if(isset($agent_no)){
                $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');
            }
            $n = $request->input('n');

            if(isset($n) && (int)$n == 1){
                //TODO - Create a new field with concanteneted name and policy no
                //if (isset($_GET['agent_no']) && isset($_GET['n']) && (int) $_GET['n'] == 1) {
                $sql = "SELECT  t3.coverperiod,T1.term_of_policy,T1.plan_code,T1.id,T1.sa,T1.modal_prem,T1.TotalPremium,
                T1.status_code,T1.policy_no, T2.description, T2.investment_plan, T4.surname, T4.other_name, 
                T4.mobile, T4.email, CONCAT(T4.[name],' - ',T1.policy_no) AS name_policy,
                (DATEDIFF(month,t1.effective_date,GETDATE()) * t3.coverperiod * t1.modal_prem) AS expected_prem,
                d.description AS status, d.status_code,t3.description AS paymode from polinfo T1 
                left join planinfo T2 on T1.plan_code = T2.plan_code 
                left join paymentmodeinfo t3 on t1.plan_code = t3.plan_code and t1.pay_mode=t3.id 
                left join clientinfo T4 on T1.client_number = T4.client_number
                INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code";
                
                $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
                $sql .= " WHERE T1.agent_no=$agentId";
                $Policies = DbHelper::getTableRawData($sql);
            //}

                return response()->json(
                    array(
                        "success" => true,
                        "Policies" => $Policies
                    )
                );

            }else{

                if (isset($_GET['is_micro']) && $_GET['is_micro'] == "1") {
                    $sql = "SELECT p.*,p.plan_code as plan_id,p.maxMatAge AS maturity_age,p.MinAgeParents AS min_age_parents,
                    p.MaxAgeParents AS max_age_parents,p.isBancAssurance AS isbancassurance,0 AS istr,p.MinSum AS min_sum,p.CategoryCode+1 as CategoryCode,
                    p.PlanOldName as plan_code from planinfo p 
                    LEFT JOIN plan_prop_category d 
                        ON (p.CategoryCode=d.prop_code) WHERE isForMportal = 1";
                } else {
                    if (isset($_GET['n']) && $_GET['n'] == "1") {
                        /*$sql = "SELECT p.*,p.OrdinaryLife AS ordinary_life,p.plan_code as plan_id,p.maxMatAge AS maturity_age,p.MinAgeParents AS min_age_parents,
                        p.MaxAgeParents AS max_age_parents,p.isBancAssurance AS isbancassurance,0 AS istr,p.MinSum AS min_sum,p.CategoryCode+1 as CategoryCode,
                        p.PlanOldName as plan_code from planinfo p 
                        LEFT JOIN plan_prop_category d 
                        ON (p.CategoryCode=d.prop_code) WHERE p.is_active=1 AND ( (p.investment_plan=1 AND p.microassurance=1) 
                        OR p.OrdinaryLife=1) AND p.isBancAssurance = 0 AND isForMportal = 1";*/
                        $sql = "SELECT p.*,p.plan_code as plan_id,p.maxMatAge AS maturity_age,p.MinAgeParents AS min_age_parents,
                        p.MaxAgeParents AS max_age_parents,0 AS istr,p.MinSum AS min_sum,p.CategoryCode+1 as CategoryCode,
                        p.PlanOldName as plan_code from planinfo p 
                        LEFT JOIN plan_prop_category d 
                        ON (p.CategoryCode=d.prop_code)";
                    } else {
                        $sql = "SELECT p.*,p.plan_code as plan_id,p.maxMatAge AS maturity_age,p.MinAgeParents AS min_age_parents,
                        p.MaxAgeParents AS max_age_parents,0 AS istr,p.MinSum AS min_sum,p.CategoryCode+1 as CategoryCode,
                        p.PlanOldName as plan_code from planinfo p 
                        LEFT JOIN plan_prop_category d 
                        ON (p.CategoryCode=d.prop_code)";
                    }
                }
                $plan_info_rows = DbHelper::getTableRawData($sql);

                $sql = "select * from rider_info WHERE [description] IS NOT NULL";
                $rider_info_rows = DbHelper::getTableRawData($sql);

                $sql = "select p.id AS plan_rider_id,p.plan_code,p.rider_code,p.use_flat_rate, p.rate_basis, p.rate, p.rate2, p.apply_comm, p.gl_premium_account,
                d.description AS plan_description, e.description AS rider_description, e.short_description as short_desc 
                from plan_rider_config p 
                LEFT JOIN planinfo d ON (p.plan_code=d.plan_code) 
                LEFT JOIN rider_info e ON (p.rider_code=e.rider_code)
                WHERE p.is_active = 1";
                $plan_rider_info_rows = DbHelper::getTableRawData($sql);

                //$sql = "select p.code,p.description,d.code as category from relationship_mainteinance p left JOIN funeralcateginfo d ON p.Categ=d.id";
                $sql = "select * from relationship_mainteinance WHERE [description] IS NOT NULL";
                $relationship_info_rows = DbHelper::getTableRawData($sql);

                $sql = "SELECT * from maritalstatusinfo WHERE description IS NOT NULL";
                $maritalinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select * from gender_info p WHERE p.[Desc] IS NOT NULL";
                $gender_info_rows = DbHelper::getTableRawData($sql);

                if (isset($_GET['is_micro']) && $_GET['is_micro'] == "1") {
                    $sql = "SELECT emp_code,[Name],transfer_rate FROM pay_source_mainteinance 
                    WHERE [Disable]=0 AND [Name] IS NOT NULL";
                } else {
                    if (isset($BusinessChannel) && $BusinessChannel == "3") {
                        $sql = "SELECT emp_code,[Name],transfer_rate,IsForMicroInsurance FROM pay_source_mainteinance 
                        WHERE [Disable]=0 AND (IsForMicroInsurance IS NULL OR IsForMicroInsurance = 0) AND [Name] IS NOT NULL";
                    } else {
                        $sql = "SELECT emp_code,[Name],transfer_rate FROM pay_source_mainteinance WHERE [Disable]=0 AND [Name] IS NOT NULL";
                    }
                }
                $employer_info_rows = DbHelper::getTableRawData($sql);

                //return gender, marital status, occupation and paymethod
                if (isset($_GET['is_micro']) && $_GET['is_micro'] == "1") {
                    $sql = "SELECT id,CONCAT(AgentNoCode, ' - ', name) AS agent_no_name, AgentNoCode as agent_no, [name]
                    FROM agents_info
                    WHERE IsActive = 1;
                    ";
                } else {
                    $sql = "SELECT id,CONCAT(AgentNoCode, ' - ', name) AS agent_no_name, AgentNoCode  as agent_no, [name]
                    FROM agents_info
                    WHERE IsActive = 1;
                    ";
                }
                $life_agents_rows = DbHelper::getTableRawData($sql);

                //$sql = "select account_no,branchName, branchCode, sort_code from paysourcebranches";
                $paysourcebr_rows = array(); // DbHelper::getTableRawData($sql);

                $sql = "select class_code,Description,rate from paclass 
                WHERE [Description] IS NOT NULL";
                $paclass_info_rows = DbHelper::getTableRawData($sql);

                $sql = "select occupation_code,occupation_name from occupationinfo 
                WHERE [occupation_name] IS NOT NULL";
                $Occupationinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select Code,Name from countryinfo WHERE [Name] IS NOT NULL";
                $countryinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select p.*,p.id as disease_id FROM mob_health_info p 
                WHERE p.[description] IS NOT NULL";
                $healthinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select *,disease_id as qn_id,name as qn_description FROM mob_family_disease 
                where disease_id>13 AND [name] IS NOT NULL";
                $familydisease_rows = DbHelper::getTableRawData($sql);

                ///////
                //$sql = "select payment_mode AS paymethod,decription AS paymethodDescription FROM payment_type";
                $sql = "select * FROM payment_type WHERE [decription] IS NOT NULL";
                $paymentmeth_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM AgentsPaymethodInfo WHERE description IS NOT NULL";
                $AgentsPaymethodInfo = DbHelper::getTableRawData($sql);

                $sql = "select bank_code,description,BeneficiaryBankCode FROM bankcodesinfo 
                WHERE [description] IS NOT NULL";
                $bankinfo_rows = DbHelper::getTableRawData($sql);

                //$sql = "select bank_code,description,BeneficiaryBankCode FROM bankcodesinfo";
                //$bankcodes_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM paymentmodeinfo WHERE [description] IS NOT NULL";
                $paymentmode_rows = DbHelper::getTableRawData($sql);

                //$sql = "select p.OldPlanCode as plan_code,p.OldPlanCode as plan,p.oldPayMode as payment_mode,p.description,p.premyr,p.loadingfactor,p.coverperiod,p.singleprem FROM paymentmodeinfo p";
                $sql = "select * FROM paymentmodeinfo WHERE [description] IS NOT NULL";
                $paymentmodeinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM premdistinfo WHERE [PlanCode] IS NOT NULL";
                $premdistribinfo_rows = DbHelper::getTableRawData($sql);

                //////
                $sql = "select * FROM defaultsinfo";
                $defaultsinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select p.* FROM premium_rate_setup p WHERE p.plan_code IS NOT NULL";
                $premrateinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM rider_premuim_rates WHERE PlanCode IS NOT NULL";
                $riderpremuimrate_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM funeralratesinfo 
                WHERE plan_code IS NOT NULL ORDER BY id DESC";
                $Funeralratesinfo_rows = DbHelper::getTableRawData($sql);

                $sql = "select * FROM bapackages WHERE description IS NOT NULL";
                $bapackages_rows = DbHelper::getTableRawData($sql);

                //$sql = "SELECT p.id, p.plan_code,p.Description,p.code,p.Min_age AS min_age,p.Max_age AS max_age,p.Min_sa AS min_Sa,p.Max_sa AS max_Sa,p.created_by,p.created_on,p.altered_by,p.dola FROM funeralcateginfo p";
                $sql = "select * from funeralcateginfo WHERE RelationCategory IS NOT NULL";
                $funeralcat_rows = DbHelper::getTableRawData($sql);

                //$sql = "select * FROM parentspremratesinfo";
                $parentspremrates_rows = array(); //DbHelper::getTableRawData($sql);

                //$sql = "SELECT * FROM clientinfo p INNER JOIN MicroProposalInfo d ON p.client_number=d.Client WHERE d.Agent='15'";
                $Clients = array();//DbHelper::getTableRawData($sql);

                //$sql = 'SELECT d.*,p.Name,p.Mobile,e.description AS plan_name FROM MicroProposalInfo d INNER JOIN MicroClientInfo p ON p.Id=d.Client INNER JOIN planinfo e ON d."Plan"=e.plan_code WHERE d.Agent=15';
                $Policies = array();
                //TODO - Create a new field with concanteneted name and policy no
                if (isset($_GET['agent_no']) && isset($_GET['n']) && (int) $_GET['n'] == 1) {
                    $sql = "SELECT  t3.coverperiod,T1.plan_code,T1.id,T1.sa,T1.modal_prem,T1.TotalPremium,
                    T1.status_code,T1.policy_no, T2.description, T2.investment_plan, T4.surname, T4.other_name, 
                    T4.mobile, T4.email, CONCAT(T4.[name],' - ',T1.policy_no) AS name_policy,
                    (DATEDIFF(month,t1.effective_date,GETDATE()) * t3.coverperiod * t1.modal_prem) AS expected_prem,d.description AS status, d.status_code from polinfo T1 
                    left join planinfo T2 on T1.plan_code = T2.plan_code 
                    left join paymentmodeinfo t3 on t1.plan_code = t3.plan_code and t1.pay_mode=t3.id 
                    left join clientinfo T4 on T1.client_number = T4.client_number
                    INNER JOIN statuscodeinfo d ON d.status_code=T1.status_code";
                    
                    $agentId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
                    $sql .= " WHERE T1.agent_no=$agentId ORDER BY T1.id DESC";
                    $Policies = array();//DbHelper::getTableRawData($sql);
                }


                //ClientPolicies, ClaimType, PartialWithdrawalPurposes, ClaimCause

                //$sql = "SELECT * FROM polinfo d WHERE d.client_number='C00300142'";
                $ClientPolicies = array();//DbHelper::getTableRawData($sql);

                //$sql = "SELECT * FROM claims_types d WHERE d.ShowInClientPortal=1"; 
                //d.AffectGroupLife=0 and WHERE 
                $sql = "SELECT * FROM claims_types d WHERE d.Deactivate=0 AND d.[Description] IS NOT NULL";
                $ClaimType = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM PartialWidthrawalReasons WHERE [Description] IS NOT NULL";
                $PartialWithdrawalPurposes = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM claimcausesinfo WHERE [Description] IS NOT NULL";
                $ClaimCause = DbHelper::getTableRawData($sql);
                //EndorsementTypes
                $sql = "SELECT * FROM LifeEndorsementTypeInfo  where 
                ShowInPortal=1 AND Description IS NOT NULL";
                $EndorsementTypes = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM Towns WHERE Description IS NOT NULL";
                $Region = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM bankcodesinfo  WHERE [Disable]=0";
                $Banks = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM bankmasterinfo WHERE bankBranchName IS NOT NULL";
                $BanksBranches = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM identity_types WHERE [description] IS NOT NULL";
                $IDTypes = DbHelper::getTableRawData($sql);
                //
                $sql = "SELECT * FROM pay_source_mainteinance WHERE TelcoCompany=1 and [Disable]=0";
                $Telcos = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM PremiumIncrementPercentage p WHERE PercentageValue IS NOT NULL";
                $PremiumIncrementPercentage = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM titleInfo WHERE [description] IS NOT NULL";
                $titleInfo = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM statuscodeinfo WHERE [description] IS NOT NULL";
                $Statuses = DbHelper::getTableRawData($sql);

                //
                $sql = "SELECT * FROM claimcausesinfo WHERE [Description] IS NOT NULL";
                $ClaimCause = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM ClaimPaymentOptions WHERE [description] IS NOT NULL";
                $ClaimPaymentOptions = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM PaySourceNoLengthInfo WHERE EmpCode IS NOT NULL";
                $Validations = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM currency_mainteinance WHERE [description] IS NOT NULL";
                $Currency = DbHelper::getTableRawData($sql);

                //$sql = "SELECT * FROM pyparainfo p where p.IsForItemIssuing=1";
                $sql = "SELECT * FROM pyparainfo WHERE para_name IS NOT NULL";
                $ParaCode = DbHelper::getTableRawData($sql);

                //$sql = "SELECT * FROM mob_DiseaseGroup p";
                $DiseaseGroup = array(); //DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM GlifeLoanTypesinfo WHERE LoanTypeDesc IS NOT NULL";
                $GlifeLoanTypesinfo = [];//DbHelper::getTableRawData($sql);

                $sql = "SELECT id,[name] FROM Intermediaryinfo WHERE [name] IS NOT NULL";
                $Brokers = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM glifeclass t WHERE (t.IsGroupLifeCover=1 OR t.IsTravelInsurance=1 OR t.IsWelfare=1) AND t.IsActive=1";
                $GLPlan = [];//DbHelper::getTableRawData($sql);

                $sql = "SELECT doctor_code,[name] FROM doctor_info WHERE [name] IS NOT NULL";
                $Doctors = DbHelper::getTableRawData($sql);

                $sql = "SELECT * from glifeOccupClassInfo t WHERE t.IsGrp=0 AND [Industry] IS NOT NULL";
                $GLOccup = [];//DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM glifetravelcategories WHERE [Description] IS NOT NULL";
                $GLTravelCat = [];//DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM PyPayrollCategory WHERE [Description] IS NOT NULL";
                $PyPayrollCategory = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM POSComplaintType WHERE [description] IS NOT NULL";
                $POSComplaintType = [];//DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM AMLSourceOfIncomeInfo WHERE [Description] IS NOT NULL";
                $AMLSourceOfIncomeInfo = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM ReasonsForExposure WHERE [description] IS NOT NULL";
                $ReasonsForExposure = DbHelper::getTableRawData($sql);

                //query mob_health_info
                $MobIntermediary = $this->smartlife_db->table('mob_health_info as p')
                ->select(
                    'p.id as disease_id',
                    DB::raw('CAST(0 AS bit) as isYesChecked'),
                    DB::raw('CAST(0 AS bit) as isNoChecked'),
                    DB::raw("'' as comments")
                )
                ->get();

                $sql = "SELECT * FROM mob_HazardQuestions WHERE [description] IS NOT NULL";
                $HazardQuestions = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM mob_HazardQuestionsSub WHERE [description] IS NOT NULL";
                $HazardQuestionsSub = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM MortgageOptions WHERE [Description] IS NOT NULL";
                $MortgageOptions = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM IslandsDetails WHERE [Description] IS NOT NULL";
                $IslandsDetails = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM ClientNatureOfBusiness WHERE [Description] IS NOT NULL";
                $NatureOfBusiness = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM Towns WHERE [Description] IS NOT NULL";
                $Towns = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM ClientBranch WHERE [Description] IS NOT NULL";
                $ClientBranch = DbHelper::getTableRawData($sql);

                //PepStatus
                $sql = "SELECT * FROM PepStatus WHERE [Description] IS NOT NULL";
                $PepStatus = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM ClientAverageIncome WHERE [Description] IS NOT NULL";
                $ClientAverageIncome = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM PEPClassification WHERE [Description] IS NOT NULL";
                $PEPClassification = DbHelper::getTableRawData($sql);

                $sql = "SELECT * FROM SourceOfFunds WHERE [Description] IS NOT NULL";
                $SourceOfFunds = DbHelper::getTableRawData($sql);

                //FamilyStateInfo
                $sql = "SELECT * FROM FamilyStateInfo WHERE [Desc] IS NOT NULL";
                $FamilyStateInfo = DbHelper::getTableRawData($sql);

                //YesNoOptions
                $sql = "SELECT * FROM YesNoOptions WHERE [Description] IS NOT NULL";
                $YesNoOptions = DbHelper::getTableRawData($sql);

                //clientDocuments
                $sql = "SELECT id,Description as name,IsMandatory  FROM clientDocuments WHERE [Description] IS NOT NULL";
                $clientDocuments = DbHelper::getTableRawData($sql);

                //Client Risk Rating
                $sql = "SELECT * FROM ClientRiskRating WHERE [Description] IS NOT NULL";
                $ClientRiskRating = DbHelper::getTableRawData($sql);

                return response()->json(
                    array(
                        "success" => true,
                        "Planinfo" => $plan_info_rows,
                        "Riderinfo" => $rider_info_rows,
                        "PlanRiderinfo" => $plan_rider_info_rows
                        ,
                        "Relationshipinfo" => $relationship_info_rows,
                        "Maritalinfo" => $maritalinfo_rows,
                        "Genderinfo" => $gender_info_rows
                        ,
                        "Employerinfo" => $employer_info_rows,
                        "Paclassinfo" => $paclass_info_rows,
                        "Occupationinfo" => $Occupationinfo_rows
                        ,
                        "Countryinfo" => $countryinfo_rows,
                        "Healthinfo" => $healthinfo_rows,
                        "Bankinfo" => $bankinfo_rows,
                        //"BankCodes" => $bankcodes_rows,
                        "Paymentinfo" => $paymentmeth_rows,
                        "AgentsPaymethodInfo"=> $AgentsPaymethodInfo 
                        ,
                        "Paymentmodeinfo" => $paymentmodeinfo_rows,
                        "Defaultsinfo" => $defaultsinfo_rows,
                        "Premrateinfo" => $premrateinfo_rows,
                        "Paymentmode" => $paymentmode_rows
                        ,
                        "Riderpremuimrate" => $riderpremuimrate_rows,
                        "Funeralratesinfo" => $Funeralratesinfo_rows,
                        "Paysourcebr" => $paysourcebr_rows,
                        "LifeAgents" => $life_agents_rows,
                        "Premdistribinfo" => $premdistribinfo_rows,
                        "BaPackages" => $bapackages_rows
                        ,
                        "FuneralCat" => $funeralcat_rows,
                        "ParentsPrem" => $parentspremrates_rows,
                        "FamDisease" => $familydisease_rows
                        ,
                        "Clients" => $Clients,
                        "Policies" => $Policies,
                        "ClientPolicies" => $ClientPolicies,
                        "ClaimType" => $ClaimType,
                        "PartialWithdrawalPurposes" => $PartialWithdrawalPurposes,
                        "ClaimCause" => $ClaimCause,
                        "EndorsementTypes" => $EndorsementTypes,
                        "Region" => $Region,
                        "Banks" => $Banks,
                        "BanksBranches" => $BanksBranches,
                        "IDTypes" => $IDTypes,
                        "Telcos" => $Telcos,
                        "PremiumIncrementPercentage" => $PremiumIncrementPercentage,
                        "titleInfo" => $titleInfo,
                        "Statuses" => $Statuses,
                        "ClaimCause" => $ClaimCause,
                        "ClaimPaymentOptions" => $ClaimPaymentOptions,
                        "Validations" => $Validations,
                        "Currency" => $Currency,
                        "ParaCode" => $ParaCode,
                        "DiseaseGroup" => $DiseaseGroup,
                        "GlifeLoanTypesinfo" => $GlifeLoanTypesinfo,
                        "Brokers" => $Brokers,
                        "GLPlan" => $GLPlan,
                        "Doctors" => $Doctors,
                        "GLOccup" => $GLOccup,
                        "GLTravelCat" => $GLTravelCat,
                        "PyPayrollCategory" => $PyPayrollCategory,
                        "POSComplaintType" => $POSComplaintType,
                        "AMLSourceOfIncomeInfo" => $AMLSourceOfIncomeInfo,
                        "ReasonsForExposure" => $ReasonsForExposure,
                        "HazardQuestions" => $HazardQuestions,
                        "HazardQuestionsSub" => $HazardQuestionsSub,
                        "MortgageOptions" => $MortgageOptions,
                        "IslandsDetails" => $IslandsDetails,
                        "NatureOfBusiness" => $NatureOfBusiness,
                        "Towns" => $Towns,
                        "ClientBranch" => $ClientBranch,
                        "PepStatus" => $PepStatus,
                        "ClientAverageIncome" => $ClientAverageIncome,
                        "PEPClassification" => $PEPClassification,
                        "SourceOfFunds" => $SourceOfFunds,
                        "FamilyStateInfo" => $FamilyStateInfo,
                        "YesNoOptions" => $YesNoOptions,
                        "clientDocuments" => $clientDocuments,
                        "ClientRiskRating" => $ClientRiskRating
                    )
                );

            }

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
        //return response()->json($res);
    
    }

    public function getBankPlans(Request $request)
    {
        try {
            $res = array();
            //we need to use the agentcode to get the bank code
            $agent_code = $request->input('agent_code');

            //from the agentcode get the bank code
            $bank_code = "NIB";//DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_code, 'BancassuranceBankLink');

            $where_arr = array(
                'BankCode' => $bank_code
            );
            $query = $this->smartlife_db->table('BankPlanSetUp')
                ->join('planinfo', 'BankPlanSetUp.plan_code', '=', 'planinfo.plan_code')
                ->select('planinfo.*', 'planinfo.plan_code as plan_id')
                ->where($where_arr);

            $results = $query->get();

            $res = array(
                'success' => true,
                'Plans' => $results
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

    //lets fetch bank products
    public function getBankProducts(Request $request)
    {
        try {
            $res = array();
            //we need to use the agentcode to get the bank code
            $agent_code = $request->input('agent_code');

            //from the agentcode get the bank code
            $bank_code = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_code, 'BancassuranceBankLink');

            $where_arr = array(
                'BankCode' => $bank_code
            );
            $query = $this->smartlife_db->table('BankPlanSetUp')
                ->join('planinfo', 'BankPlanSetUp.plan_code', '=', 'planinfo.plan_code')
                ->select('planinfo.*', 'planinfo.plan_code as plan_id')
                ->where($where_arr);

            $results = $query->get();

            $res = array(
                'success' => true,
                'Plans' => $results
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