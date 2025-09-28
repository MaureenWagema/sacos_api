<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\loginController;
use App\Http\Controllers\parametersController;
use App\Http\Controllers\syncController;
use App\Http\Controllers\premCalController;
use App\Http\Controllers\policyController;
use App\Http\Controllers\collectionsController;
use App\Http\Controllers\clientController;
use App\Http\Controllers\emailController;
use App\Http\Controllers\agentController;
use App\Http\Controllers\quotationController;
use App\Http\Controllers\claimController;
use App\Http\Controllers\reportsController;
use App\Http\Controllers\groupController;
use App\Http\Controllers\USSDController;
use App\Http\Controllers\NsanoController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('auth/tokenRequest', [AuthController::class, 'clientCredentialsAccessToken']);

Route::post('auth/apitokenRequest', [AuthController::class, 'APIclientCredentialsAccessToken']); //api token request

Route::post('auth/getTest', [loginController::class, 'getTest']); //AgentRegistration

Route::post('auth/AgentRegistration', [loginController::class, 'AgentRegistration']);
Route::post('auth/AgentLogin', [loginController::class, 'AgentLogin']);
Route::post('auth/ClientRegistration', [loginController::class, 'ClientRegistration']); //
Route::post('auth/ClientLogin', [loginController::class, 'ClientLogin']);
Route::post('auth/POSLogin', [loginController::class, 'POSLogin']);
Route::post('auth/POSRegistration', [loginController::class, 'POSRegistration']); //BrokerLogin
Route::post('auth/GroupLogin', [loginController::class, 'GroupLogin']);
Route::post('auth/BrokerLogin', [loginController::class, 'BrokerLogin']);
Route::post('auth/SendAgentOTP', [loginController::class, 'SendAgentOTP']);
Route::post('auth/ChangePassword', [loginController::class, 'ChangePassword']);
Route::post('auth/ResetAgentPassword', [loginController::class, 'ResetAgentPassword']);
Route::post('auth/GroupRegistration', [loginController::class, 'GroupRegistration']);
Route::post('auth/BrokerRegistration', [loginController::class, 'BrokerRegistration']);
Route::post('auth/SendClientOTP', [loginController::class, 'SendClientOTP']);
Route::post('auth/SendBrokerOTP', [loginController::class, 'SendBrokerOTP']);
Route::post('auth/SendGroupOTP', [loginController::class, 'SendGroupOTP']);

Route::get('params/getParams', [parametersController::class, 'getCommonParams']);
Route::get('params/getBankPlans', [parametersController::class, 'getBankPlans']);
Route::get('params/getBankProducts', [parametersController::class, 'getBankProducts']);
Route::get('params/getInsuranceCoverTypes', [parametersController::class, 'getInsuranceCoverTypes']);

Route::post('sync/synProposal', [syncController::class, 'synProposal']);
Route::post('sync/updateTheDuplicated', [syncController::class, 'updateTheDuplicated']);
Route::post('sync/syncImage', [syncController::class, 'syncImage']); //
Route::post('sync/testVarBinary', [syncController::class, 'testVarBinary']);//
Route::post('sync/microDuplicates', [syncController::class, 'microDuplicates']);
Route::get('sync/updateTerm', [syncController::class, 'updateTerm']);
Route::get('sync/pushAttachements', [syncController::class, 'pushAttachements']);
Route::get('sync/fixDublicateAttachments', [syncController::class, 'fixDublicateAttachments']);

//reports
Route::get('reports/getWorksheet', [reportsController::class, 'getWorksheet']);
Route::get('reports/getAgentProducts', [reportsController::class, 'getAgentProducts']);
Route::get('reports/getSubsidiaryAccounts', [reportsController::class, 'getSubsidiaryAccounts']);
Route::get('reports/getInvestments', [reportsController::class, 'getInvestments']);
Route::get('reports/getFixedAssets', [reportsController::class, 'getFixedAssets']);
Route::get('reports/getPrepayment', [reportsController::class, 'getPrepayment']);
Route::get('reports/getFinancialReporting', [reportsController::class, 'getFinancialReporting']);
Route::get('reports/getPremiumStatement', [reportsController::class, 'getPremiumStatement']);
Route::get('reports/getInvestmentStatement', [reportsController::class, 'getInvestmentStatement']);
Route::get('reports/getPolicySchedule', [reportsController::class, 'getPolicySchedule']); //
Route::get('reports/getLoanStatement', [reportsController::class, 'getLoanStatement']);
Route::get('reports/getAgentTotals', [reportsController::class, 'getAgentTotals']);
Route::get('reports/getAllSales', [reportsController::class, 'getAllSales']);
Route::get('reports/getUnitSales', [reportsController::class, 'getUnitSales']);
Route::get('reports/getAgentPerformance', [reportsController::class, 'getAgentPerformance']); //
Route::get('reports/getSchemeTotMembers', [reportsController::class, 'getSchemeTotMembers']);
Route::get('reports/getMproposalVersion', [reportsController::class, 'getMproposalVersion']);
Route::get('reports/getBrokerTotSchemes', [reportsController::class, 'getBrokerTotSchemes']);
Route::get('reports/getClientDashboard', [reportsController::class, 'getClientDashboard']);
Route::get('reports/getMainDashboardTotals', [reportsController::class, 'getMainDashboardTotals']);
//getClaimsTotals, getUnderwritingTotals, getPolicyAdminTotals
Route::get('reports/getClaimsTotals', [reportsController::class, 'getClaimsTotals']);
Route::get('reports/getUnderwritingTotals', [reportsController::class, 'getUnderwritingTotals']);
Route::get('reports/getPolicyAdminTotals', [reportsController::class, 'getPolicyAdminTotals']);
Route::get('reports/getGroupRpt', [reportsController::class, 'getGroupRpt']);

Route::get('reports/getSalesBreakdown', [reportsController::class, 'getSalesBreakdown']);
Route::get('reports/getSalesTotalsRange', [reportsController::class, 'getSalesTotalsRange']);
Route::get('reports/getSalesGridRange', [reportsController::class, 'getSalesGridRange']);
Route::get('reports/getActivitiesTotalsRange', [reportsController::class, 'getActivitiesTotalsRange']);
Route::get('reports/getClaimEndorseCounts', [reportsController::class, 'getClaimEndorseCounts']);
Route::get('reports/fetchPremiums', [reportsController::class, 'fetchPremiums']);
Route::get('reports/fetchReinsurancePeriod', [reportsController::class, 'fetchReinsurancePeriod']);
Route::get('reports/fetchReinsuranceData', [reportsController::class, 'fetchReinsuranceData']);
Route::get('reports/checkIsMicro', [reportsController::class, 'checkIsMicro']);
Route::get('reports/getMDsDashboard', [reportsController::class, 'getMDsDashboard']);
Route::get('reports/policyNoMicro', [reportsController::class, 'policyNoMicro']);

//policy getSalesGridRange getActivitiesTotalsRange 
Route::get('policy/getMicroProducts', [policyController::class, 'getMicroProducts']);
Route::get('policy/getProposal', [policyController::class, 'getProposal']);
Route::get('policy/getPolicyDependants', [policyController::class, 'getPolicyDependants']);
Route::get('policy/getPolicyBeneficiaries', [policyController::class, 'getPolicyBeneficiaries']);
Route::get('policy/getPolicyDetails', [policyController::class, 'getPolicyDetails']);
Route::get('policy/getRequestedEndorsements', [policyController::class, 'getRequestedEndorsements']);
Route::post('policy/saveEndorsement', [policyController::class, 'saveEndorsement']); //
Route::post('policy/syncEndorsementsImage', [policyController::class, 'syncEndorsementsImage']);
Route::get('policy/getMicroPolicyDetails', [policyController::class, 'getMicroPolicyDetails']);
Route::post('policy/UpdateBeneficiaries', [policyController::class, 'UpdateBeneficiaries']);
Route::get('policy/getPolicyRiders', [policyController::class, 'getPolicyRiders']); //
Route::get('policy/getClientLoan', [policyController::class, 'getClientLoan']);
Route::get('policy/getMicroLoanAvailable', [policyController::class, 'getMicroLoanAvailable']);
Route::get('policy/getClientLifeLoan', [policyController::class, 'getClientLifeLoan']);
Route::get('policy/getPaySourceRawData', [policyController::class, 'getPaySourceRawData']);
Route::get('policy/getBanksRawData', [policyController::class, 'getBanksRawData']);
Route::get('policy/getEndorsementFiles', [policyController::class, 'getEndorsementFiles']);
Route::get('policy/getPolicyFiles', [policyController::class, 'getPolicyFiles']);
//getMicroCashValue
Route::get('policy/getMicroCashValue', [policyController::class, 'getMicroCashValue']);
Route::get('policy/getLifeCashValue', [policyController::class, 'getLifeCashValue']);
Route::get('policy/getClientAccount', [policyController::class, 'getClientAccount']);
Route::get('policy/getPropDepInfo', [policyController::class, 'getPropDepInfo']);

Route::get('policy/getProposalDetails', [policyController::class, 'getProposalDetails']);
Route::get('policy/getMicroProposalDetails', [policyController::class, 'getMicroProposalDetails']);
//getHistoryEndorsements
Route::get('policy/getHistoryEndorsements', [policyController::class, 'getHistoryEndorsements']);
//getInvCashValue
Route::get('policy/reBuildCashValue', [policyController::class, 'reBuildCashValue']);

Route::get('policy/getActivitiesData', [policyController::class, 'getActivitiesData']);
Route::post('policy/postPOSActivities', [policyController::class, 'postPOSActivities']);
//
Route::get('policy/getAgentPaySourceData', [policyController::class, 'getAgentPaySourceData']);
Route::get('policy/validatePWDLoan', [policyController::class, 'validatePWDLoan']);
//validatePolicyNumber
Route::post('policy/validatePolicyNumber', [policyController::class, 'validatePolicyNumber']);

//migrateMproposal,migratProposalFiles,migrateMissedFiles validatePWDLoan
Route::post('migrate/migrateMproposal', [policyController::class, 'migrateMproposal']);
Route::post('migrate/migratProposalFiles', [policyController::class, 'migratProposalFiles']);
Route::post('migrate/migrateDeleteFiles', [policyController::class, 'migrateDeleteFiles']);
Route::post('migrate/migrateMissedFiles', [policyController::class, 'migrateMissedFiles']);


Route::get('group/getSchemeMembers', [groupController::class, 'getSchemeMembers']);
Route::post('group/addMemberToScheme', [groupController::class, 'addMemberToScheme']);
Route::get('group/getGroupQuotations', [groupController::class, 'getGroupQuotations']);
Route::post('group/saveQuoteGroup', [groupController::class, 'saveQuoteGroup']); //
Route::get('group/getMemberStatement', [groupController::class, 'getMemberStatement']);
Route::post('group/syncClaimGroupImage', [groupController::class, 'syncClaimGroupImage']);

//ComputePremiumCreditLife, OrdinaryPolicies
Route::post('calc/ESB', [premCalController::class, 'esbcalculation']);
Route::post('calc/IdealFuneralPlan', [premCalController::class, 'IdealFuneralPlan']);
Route::post('calc/PremiumFuneralPlan', [premCalController::class, 'PremiumFuneralPlan']);
Route::post('calc/GEEP', [premCalController::class, 'GEEP']);
Route::post('calc/lifeSavingsPlan', [premCalController::class, 'lifeSavingsPlan']);
Route::post('calc/DepAnidaso', [premCalController::class, 'DepAnidaso']);
Route::post('calc/LifeAnidaso', [premCalController::class, 'LifeAnidaso']);
Route::post('calc/ITCAnidaso', [premCalController::class, 'ITCAnidaso']);
Route::post('calc/PersonalAccidentPlan', [premCalController::class, 'PersonalAccidentPlan']);
Route::post('calc/FamilyComprehensionPlan', [premCalController::class, 'FamilyComprehensionPlan']);
Route::post('calc/HCIPlan', [premCalController::class, 'HCIPlan']);
Route::post('calc/ComputePremiumCreditLife', [premCalController::class, 'ComputePremiumCreditLife']);
Route::post('calc/esb_manual_rider', [premCalController::class, 'esb_manual_rider']);
Route::post('calc/OrdinaryPolicies', [premCalController::class, 'OrdinaryPolicies']);

//
Route::post('collections/getClientnPolicies', [collectionsController::class, 'getClientnPolicies']);
Route::post('collections/sendOTP', [collectionsController::class, 'sendOTP']);
Route::post('collections/receiveOTP', [collectionsController::class, 'receiveOTP']);
Route::post('collections/Remit', [collectionsController::class, 'Remit']);
Route::post('collections/updateRemit', [collectionsController::class, 'updateRemit']);
Route::post('collections/updateHubtel', [collectionsController::class, 'updateHubtel']);
Route::get('collections/getPaymentHistory', [collectionsController::class, 'getPaymentHistory']);
Route::post('collections/deductionHubtel', [collectionsController::class, 'deductionHubtel']);
Route::get('collections/sendNewBusinessSMS', [collectionsController::class, 'sendNewBusinessSMS']);
Route::get('collections/getAgentPrompts', [collectionsController::class, 'getAgentPrompts']);

Route::get('sms/SMS', [collectionsController::class, 'SMS']);

Route::post('quotation/saveQuote', [quotationController::class, 'saveQuote']);

//client
Route::get('client/getClientPolicies', [clientController::class, 'getClientPolicies']);
Route::get('client/getClientPremiums', [clientController::class, 'getClientPremiums']);
Route::get('client/getClientInvestment', [clientController::class, 'getClientInvestment']);
Route::get('client/getClientDetails', [clientController::class, 'getClientDetails']);

//agents  
Route::get('agents/getAgentsPaymentMethods', [agentController::class, 'getAgentsPaymentMethods']);
Route::get('agents/getAgentsRegions', [agentController::class, 'getAgentsRegions']);
Route::get('agents/getAgentsBranches', [agentController::class, 'getAgentsBranches']);
Route::get('agents/getAgentsUnits', [agentController::class, 'getAgentsUnits']);
Route::get('agents/getAgentsTeams', [agentController::class, 'getAgentsTeams']); //
Route::get('agents/getAgentsChannel', [agentController::class, 'getAgentsChannel']);
Route::get('agents/getAgentsEducationLevel', [agentController::class, 'getAgentsEducationLevel']);
Route::get('agents/getAgentsFileChecklist', [agentController::class, 'getAgentsFileChecklist']);
Route::get('agents/getAgentsComplianceLicense', [agentController::class, 'getAgentsComplianceLicense']);
Route::post('agents/AgentsRegistration', [agentController::class, 'AgentsRegistration']); //
Route::post('agents/syncAgentImage', [agentController::class, 'syncAgentImage']); //
Route::get('agents/getAgentsEmploymentType', [agentController::class, 'getAgentsEmploymentType']);
Route::post('agents/saveAgentLoanRequest', [agentController::class, 'saveAgentLoanRequest']);
Route::get('agents/getAgentLoans', [agentController::class, 'getAgentLoans']);

Route::get('agents/getRegions', [agentController::class, 'getRegions']);
Route::get('agents/getBanks', [agentController::class, 'getBanks']); //
Route::get('agents/getBankBranches', [agentController::class, 'getBankBranches']);
Route::get('agents/getRecruitedBy', [agentController::class, 'getRecruitedBy']);
Route::get('agents/getIdTypes', [agentController::class, 'getIdTypes']);
Route::get('agents/getGender', [agentController::class, 'getGender']);
Route::post('agents/getMaritalStatus', [agentController::class, 'getMaritalStatus']);
Route::get('agents/getExprienceSector', [agentController::class, 'getExprienceSector']);
Route::get('agents/getRelationships', [agentController::class, 'getRelationships']);
Route::get('agents/getAgentCommission', [agentController::class, 'getAgentCommission']); //
Route::get('agents/getTelcos', [agentController::class, 'getTelcos']); //
Route::get('agents/getAgentPeriod', [agentController::class, 'getAgentPeriod']);
Route::get('agents/getAgentDetails', [agentController::class, 'getAgentDetails']);
Route::post('agents/editAgentsDetails', [agentController::class, 'editAgentsDetails']);
Route::post('agents/migrateAgentImage', [agentController::class, 'migrateAgentImage']);
Route::post('agents/migrateAgentNICDocs', [agentController::class, 'migrateAgentNICDocs']);

//claims:   
Route::post('claims/insertClaimEntries', [claimController::class, 'insertClaimEntries']);
Route::get('claims/getClientClaims', [claimController::class, 'getClientClaims']);
Route::get('claims/getClaimAttachments', [claimController::class, 'getClaimAttachments']);
Route::get('claims/getClaimFiles', [claimController::class, 'getClaimFiles']);
Route::post('claims/syncClaimImage', [claimController::class, 'syncClaimImage']);
Route::post('claims/insertGroupClaimEntries', [claimController::class, 'insertGroupClaimEntries']);
Route::get('claims/getClaimTypeGroup', [claimController::class, 'getClaimTypeGroup']);
Route::get('claims/getHistoryClaims', [claimController::class, 'getHistoryClaims']);
Route::get('claims/getGroupClaims', [claimController::class, 'getGroupClaims']);
Route::get('claims/getClaimsToSign', [claimController::class, 'getClaimsToSign']);
Route::get('claims/getGroupHistoryClaims', [claimController::class, 'getGroupHistoryClaims']);
Route::post('claims/insertSLAMSWrongful', [claimController::class, 'insertSLAMSWrongful']);

//ussd 
Route::post('itc/setAllBeneficiaries', [USSDController::class, 'setAllBeneficiaries']);
Route::get('itc/getAllBeneficiaries', [USSDController::class, 'getAllBeneficiaries']);
Route::post('itc/makeAClaim', [USSDController::class, 'makeAClaim']);
Route::get('itc/getAClaim', [USSDController::class, 'getAClaim']);

Route::post('email/sendLink', [emailController::class, 'sendLink']);
Route::post('email/sendEmail', [emailController::class, 'sendEmail']);
Route::post('email/smsPOST', [emailController::class, 'smsPOST']);

//nsano API
Route::post('nsano/NsanoUpload', [NsanoController::class, 'NsanoUpload']);
Route::post('nsano/NsanoUploadStatus', [NsanoController::class, 'NsanoUploadStatus']);
Route::post('nsano/NsanoTransactionStatus', [NsanoController::class, 'NsanoTransactionStatus']);

Route::group(['middleware' => ['client']], function () {



});

Route::get('/orders', function (Request $request) {
    echo "here";
    return "mister";
});