<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'auth/apitokenRequest',
        'auth/tokenRequest',
        'auth/getTest',
        'auth/AgentRegistration',
        'auth/AgentLogin',
        'auth/ClientRegistration',
        'auth/ClientLogin',
        'auth/POSLogin',
        'auth/POSRegistration',
        'auth/GroupLogin',
        'auth/BrokerLogin',
        'auth/SendAgentOTP',
        'auth/ChangePassword',
        'auth/ResetAgentPassword',
        'auth/GroupRegistration',
        'auth/BrokerRegistration',
        'auth/SendClientOTP',
        'auth/SendBrokerOTP',
        'auth/SendGroupOTP',

        'reports/getAgentProducts',

        'params/getParams',
        'params/getBankProducts',

        'sync/synProposal',
        'sync/syncImage',
        'sync/testVarBinary',
        'sync/updateTheDuplicated',
        'sync/microDuplicates',
        'sync/updateTerm',
        'sync/pushAttachements',

        'calc/ESB',
        'calc/IdealFuneralPlan',
        'calc/PremiumFuneralPlan',
        'calc/GEEP',
        'calc/lifeSavingsPlan',
        'calc/DepAnidaso',
        'calc/LifeAnidaso',
        'calc/PersonalAccidentPlan',
        'calc/FamilyComprehensionPlan',
        'calc/HCIPlan',
        'calc/ComputePremiumCreditLife',
        'calc/ITCAnidaso',
        'calc/esb_manual_rider',
        'calc/OrdinaryPolicies',
        'calc/FuneralPolicies',

        'policy/getProposal',
        'policy/getPolicyDependants',
        'policy/getPolicyBeneficiaries',
        'policy/getPolicyDetails',
        'policy/getRequestedEndorsements',
        'policy/saveEndorsement',
        'policy/getMicroProducts',
        'policy/getMicroPolicyDetails',
        'policy/UpdateBeneficiaries',
        'policy/getPolicyRiders',
        'policy/getClientLoan',
        'policy/syncEndorsementsImage',
        'policy/getClientLifeLoan',
        'policy/getPaySourceRawData',
        'policy/getBanksRawData',
        'policy/getEndorsementFiles',
        'policy/getProposalDetails',
        'policy/getMicroProposalDetails',
        'policy/getMicroCashValue',
        'policy/getLifeCashValue',
        'policy/getHistoryEndorsements',
        'policy/getClientAccount',
        'policy/getPropDepInfo',

        'policy/reBuildCashValue',

        'policy/getActivitiesData',
        'policy/postPOSActivities',

        'policy/validatePWDLoan',

        'policy/validatePolicyNumber',

        'proposal/relatedProposals',

        'migrate/migrateMproposal',
        'migrate/migratProposalFiles',
        'migrate/migrateDeleteFiles',
        'migrate/migrateMissedFiles',


        'group/getSchemeMembers',
        'group/addMemberToScheme',
        'group/getGroupQuotations',
        'group/saveQuoteGroup',
        'group/getMemberStatement',
        'group/syncClaimGroupImage',


        'collections/getClientnPolicies',
        'collections/sendOTP',
        'collections/receiveOTP',
        'collections/Remit',
        'collections/updateRemit',
        'collections/updateHubtel',
        'collections/deductionHubtel',
        'collections/sendNewBusinessSMS',

        'collections/getAgentPrompts',

        'client/getClientPolicies',
        'client/getClientPremiums',
        'client/getClientInvestment',
        'client/getClientDetails',

        'claims/insertClaimEntries',
        'claims/insertGroupClaimEntries',
        'claims/getClientClaims',
        'claims/getClaimAttachments',
        'claims/syncClaimImage',
        'claims/getClaimTypeGroup',
        'claims/getHistoryClaims',
        'claims/getGroupClaims',

        'claims/getClaimsToSign',
        'claims/getGroupHistoryClaims',
        'claims/insertSLAMSWrongful',

        'agents/getAgentsPaymentMethods',
        'agents/getAgentsRegions',
        'agents/getAgentsBranches',
        'agents/getAgentsUnits',
        'agents/getAgentsTeams',
        'agents/getAgentsChannel',
        'agents/getAgentsEducationLevel',
        'agents/getAgentsFileChecklist',
        'agents/AgentsRegistration',
        'agents/syncAgentImage',
        'agents/getAgentsComplianceLicense',
        'agents/getAgentsEmploymentType',
        'agents/saveAgentLoanRequest',
        'agents/getAgentLoans',

        'agents/getRegions',
        'agents/getBanks',
        'agents/getBankBranches',
        'agents/getRecruitedBy',
        'agents/getIdTypes',
        'agents/getGender',
        'agents/getMaritalStatus',

        'agents/getExprienceSector',
        'agents/getRelationships',
        'agents/getAgentCommission',
        'agents/getTelcos',
        'agents/getAgentPeriod',

        'agents/getAgentDetails',
        'agents/editAgentsDetails',
        'agents/migrateAgentImage',
        'agents/migrateAgentNICDocs',

        'reports/getAgentProducts',
        'reports/getSubsidiaryAccounts',
        'reports/getInvestments',
        'reports/getFixedAssets',
        'reports/getPrepayment',
        'reports/getFinancialReporting',
        'reports/getWorksheet',

        'reports/getPremiumStatement',
        'reports/getInvestmentStatement',
        'reports/getPolicySchedule',
        'reports/getLoanStatement',
        'reports/getAgentTotals',
        'reports/getAllSales',
        'reports/getUnitSales',
        'reports/getAgentPerformance',
        'reports/getSchemeTotMembers',
        'reports/getBrokerTotSchemes',
        'reports/getMproposalVersion',
        'reports/getClientDashboard',

        'reports/getMainDashboardTotals',
        'reports/getClaimsTotals',
        'reports/getUnderwritingTotals',
        'reports/getGroupRpt',

        'reports/getSalesTotalsRange',
        'reports/getSalesGridRange',
        'reports/getActivitiesTotalsRange',
        'reports/getClaimEndorseCounts',

        'reports/fetchPremiums',
        'reports/fetchReinsurancePeriod',
        'reports/fetchReinsuranceData',
        'reports/checkIsMicro',

        'reports/getMDsDashboard',
        'reports/policyNoMicro',

        'email/sendEmail',
        'email/smsPOST',
        'email/sendLink',

        'nsano/NsanoUpload',
        'nsano/NsanoUploadStatus',
        'nsano/NsanoTransactionStatus',

        'itc/setAllBeneficiaries',
        'itc/getAllBeneficiaries',
        'itc/makeAClaim',
        'itc/getAClaim',

        'quotation/saveQuote',
        'sms/SMS',

        'orders',

    ];
}