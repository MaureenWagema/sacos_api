<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;
use PDO;

class claimController extends Controller

{

    //wrongful from slams.............
    //claims entries

    public function insertSLAMSWrongful(Request $request)
    {
        try {
            $res = array();
            // put in a transaction the whole process of syncing data...
            $this->smartlife_db->transaction(function () use (&$res, $request) {

                //StaffNumber, FullName, IsForBanks, BankName, IsForEmployer, Employer_TelcoName, PaymentDate

                $InsertData = array(

                    "StaffNumber" => $request->input('StaffNumber'),
                    "FullName" => $request->input('FullName'),
                    "IsForBanks" => $request->input('IsForBanks'),
                    "BankName" => $request->input('BankName'),
                    "IsForEmployer" => $request->input('IsForEmployer'),
                    "Employer_TelcoName" => $request->input('Employer_TelcoName'),
                    "PaymentDate" => $request->input('PaymentDate'),
                    "Reason" => $request->input('Reason'),
                    "created_on" => Carbon::now(),
                    "created_by" => $request->input('user_id')
                );

                $record_id = $this->smartlife_db->table('MissingPwdClaimUpload')->insertGetId($InsertData);

                $pos_log_data = array(
                    'ClientName' => $request->input('FullName'),
                    'staff_no' => $request->input('StaffNumber'),
                    'Activity' => 1,
                    'Narration' => $request->input('Reason'),
                    'eClaimId' => null,
                    'eEndorsementId' => null,
                    'created_on' => Carbon::now(),
                    'created_by' => $request->input('user_id')
                );
                $pos_log_id = $this->smartlife_db->table('pos_log')->insertGetId($pos_log_data);

                $res = array(
                    'success' => true,
                    'message' => 'Wrongful SLAMS Claim Successfully Submitted'
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

    public function insertClaimEntries(Request $request)
    {
        try {
            $res = [];
            if (!$this->checkDatabaseConnection()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database connection is currently unavailable. Please try again.'
                ], 503);
            }
            try {
                $claimData = $this->extractClaimData($request);
                $paymentData = $this->extractPaymentData($request);
                $attachmentData = $this->extractAttachmentData($request);
                
                \Log::info('Raw Request Data: ' . json_encode($request->all()));
                \Log::info('Extracted Payment Data: ' . json_encode($paymentData));
                
                $this->validateClaimData($claimData);

                if ($claimData['IsWebComplete'] == 0) {
                    // Use minimal client info for draft claims
                    $clientInfo = [
                        'name' => $claimData['ClaimantName'],
                        'client_number' => 'DRAFT-' . time() 
                    ];
                    $policyInfo = [
                        'id' => null,
                        'client_number' => $clientInfo['client_number'] ?? 'TEMP-' . time(),
                        'type' => 'draft'
                    ];
                } else {
                    $policyInfo = $this->getPolicyInfo($claimData);
                    $clientInfo = $this->getClientInfo($policyInfo);
                }
                
                $statusCode = $claimData['IsWebComplete'] == 1 ? 14 : 13; 
                $statusText = $claimData['IsWebComplete'] == 1 ? 'submitted' : 'draft';
                
                $tableData = array_merge([
                    'created_on' => Carbon::now(),
                    'RequestDate' => Carbon::now(),
                    'statuscode' => $statusCode,
                    'branch_id' => $claimData['IsWebComplete'] == 1 ? $this->getBranchId($request->input('user_id')) : null,
                    'ClientName' => $clientInfo['name'],
                    'client_number' => $clientInfo['client_number'] ?? 'TEMP-' . time(),
                ], array_merge($claimData, $paymentData));

                // Remove array fields that shouldn't be inserted into database
                unset($tableData['attachments']);
                unset($tableData['documents']);
                
                // Remove fields that don't exist in eClaimsEntries table
                unset($tableData['claim_type_description']);
                unset($tableData['plan_code']);
                unset($tableData['plan_description']);
                unset($tableData['ID']);
                unset($tableData['IsWebComplete']);
                unset($tableData['ClaimCause']);
                unset($tableData['DoctorName']);
                unset($tableData['eventdate']);
                unset($tableData['ClaimDefaultEFTBank_accountName']);
                unset($tableData['ClaimDefaultCashRecipient']);
                unset($tableData['ClaimDefaultCashContact']);

                // Map policy_no to PolicyId for eClaimsEntries table
                if (isset($tableData['policy_no'])) {
                    // For complete claims, get the actual policy ID from database
                    if ($claimData['IsWebComplete'] == 1) {
                        try {
                            $policyInfo = $this->getPolicyInfo(['policy_no' => $tableData['policy_no']]);
                            $tableData['PolicyId'] = $policyInfo['id'];
                        } catch (\Exception $e) {
                            $tableData['PolicyId'] = null; // Fallback if policy not found
                        }
                    } else {
                        $tableData['PolicyId'] = null; // Draft claims don't have policy ID yet
                    }
                    unset($tableData['policy_no']);
                }

                $stepParam = $request->input('step', 1); 
                $isStep2 = $stepParam == 2; 
                $isStep3 = $stepParam == 3;

                \Log::info('Payment Data: ' . json_encode($paymentData));
                \Log::info('Is Step 2: ' . ($isStep2 ? 'true' : 'false'));
                \Log::info('Is Step 3: ' . ($isStep3 ? 'true' : 'false'));
                \Log::info('IsWebComplete: ' . $claimData['IsWebComplete']);

                if ($claimData['IsWebComplete'] == 0) {
                    if ($isStep2) {
                        \Log::info('Processing Step 2 - Database save for draft claim');

                        try {
                            $policyInfo = $this->getPolicyInfo($claimData);
                            $realClientInfo = $this->getClientInfo($policyInfo);
                            $policyId = $policyInfo['id']; 

                            $essentialData = [
                                'claim_type' => $claimData['claim_type'],
                                'PolicyId' => $policyId,
                                'ClaimantName' => $claimData['ClaimantName'],
                                'ClaimantMobile' => $claimData['ClaimantMobile'],
                                'IdNumber' => $claimData['IdNumber'] ?? null,
                                'id_type' => $claimData['id_type'] ?? null,
                                'client_number' => $realClientInfo['client_number'],
                                'created_on' => Carbon::now(),
                                'created_by' => request()->input('user_id'),
                                'statuscode' => $claimData['statuscode'] ?? 13,
                                'ClaimDefaultPay_method' => $paymentData['ClaimDefaultPay_method'] ?? null,
                                'ClaimDefaultEFTBank_code' => $paymentData['ClaimDefaultEFTBank_code'] ?? null,
                                'ClaimDefaultEFTBankBranchCode' => $paymentData['ClaimDefaultEFTBankBranchCode'] ?? null,
                                'ClaimDefaultEFTBank_account' => $paymentData['ClaimDefaultEFTBank_account'] ?? null,
                                'dola' => $claimData['eventdate'] ?? null,
                                'isApproved' => 0,
                            ];

                        } catch (\Exception $e) {
                            $realClientInfo = [
                                'name' => $claimData['ClaimantName'],
                                'client_number' => $claimData['client_number'] ?? 'TEMP-' . time() 
                            ];

                            $essentialData = [
                                'claim_type' => $claimData['claim_type'],
                                'PolicyId' => null,
                                'ClaimantName' => $claimData['ClaimantName'],
                                'ClaimantMobile' => $claimData['ClaimantMobile'],
                                'IdNumber' => $claimData['IdNumber'] ?? null,
                                'id_type' => $claimData['id_type'] ?? null,
                                'client_number' => $realClientInfo['client_number'],
                                'created_on' => Carbon::now(),
                                'created_by' => request()->input('user_id'),
                                'statuscode' => $claimData['statuscode'] ?? 13,
                                'ClaimDefaultPay_method' => $paymentData['ClaimDefaultPay_method'] ?? null,
                                'ClaimDefaultEFTBank_code' => $paymentData['ClaimDefaultEFTBank_code'] ?? null,
                                'ClaimDefaultEFTBankBranchCode' => $paymentData['ClaimDefaultEFTBankBranchCode'] ?? null,
                                'ClaimDefaultEFTBank_account' => $paymentData['ClaimDefaultEFTBank_account'] ?? null,
                                'dola' => $claimData['eventdate'] ?? null,
                                'isApproved' => 0,
                            ];

                            $policyId = null;
                        }
                        
                        \Log::info('Final essential data for database: ' . json_encode($essentialData));
                        \Log::info('About to call saveClaimEntryWithTimeout with ' . count($essentialData) . ' fields');
                        
                        $recordId = $this->saveClaimEntryWithTimeout($essentialData);
                        
                        \Log::info('saveClaimEntryWithTimeout returned record ID: ' . $recordId);
                        
                        // Process attachments
                        
                        try {
                            $this->logClaimActivity($recordId, $claimData, $realClientInfo, 'draft');
                            \Log::info('Draft claim activity logged successfully');
                        } catch (\Exception $e) {
                            \Log::error('Failed to log draft claim activity: ' . $e->getMessage());
                            // Don't fail the entire operation if logging fails
                        }
                        
                        try {
                            $sessionKey = null;
                            foreach (session()->all() as $key => $value) {
                                if (strpos($key, 'draft_claim_') === 0 && is_array($value) && isset($value['claimData']['policy_no']) && $value['claimData']['policy_no'] === $claimData['policy_no']) {
                                    $sessionKey = $key;
                                    break;
                                }
                            }

                            if ($sessionKey) {
                                session([$sessionKey => [
                                    'claimData' => $claimData,
                                    'paymentData' => $paymentData,
                                    'attachmentData' => $attachmentData,
                                    'clientInfo' => $realClientInfo,
                                    'databaseRecordId' => $recordId,
                                    'isSavedToDatabase' => true 
                                ]]);
                                \Log::info('Updated session with real database record ID: ' . $recordId . ' for session key: ' . $sessionKey);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Failed to update session with real record ID: ' . $e->getMessage());
                            // Don't fail the operation if session update fails
                        }
                    } elseif ($isStep3) {
                        \Log::info('Processing Step 3 - Update existing draft claim');
                        
                        // Find existing record ID from session
                        $existingRecordId = null;
                        foreach (session()->all() as $key => $value) {
                            if (strpos($key, 'draft_claim_') === 0 && is_array($value) && isset($value['claimData']['policy_no']) && $value['claimData']['policy_no'] === $claimData['policy_no']) {
                                $existingRecordId = $value['databaseRecordId'] ?? null;
                                if ($existingRecordId) {
                                    break;
                                }
                            }
                        }
                        
                        if (!$existingRecordId) {
                            return response()->json([
                                'success' => false,
                                'message' => 'No existing draft claim found to update'
                            ], 400);
                        }
                        
                        try {
                            $policyInfo = $this->getPolicyInfo($claimData);
                            $realClientInfo = $this->getClientInfo($policyInfo);
                            $policyId = $policyInfo['id']; 

                            $updateData = [
                                'claim_type' => $claimData['claim_type'],
                                'PolicyId' => $policyId,
                                'ClaimantName' => $claimData['ClaimantName'],
                                'ClaimantMobile' => $claimData['ClaimantMobile'],
                                'IdNumber' => $claimData['IdNumber'] ?? null,
                                'id_type' => $claimData['id_type'] ?? null,
                                'client_number' => $realClientInfo['client_number'],
                                'statuscode' => $claimData['statuscode'] ?? 13,
                                'ClaimDefaultPay_method' => $paymentData['ClaimDefaultPay_method'] ?? null,
                                'ClaimDefaultEFTBank_code' => $paymentData['ClaimDefaultEFTBank_code'] ?? null,
                                'ClaimDefaultEFTBankBranchCode' => $paymentData['ClaimDefaultEFTBankBranchCode'] ?? null,
                                'ClaimDefaultEFTBank_account' => $paymentData['ClaimDefaultEFTBank_account'] ?? null,
                                'dola' => $claimData['eventdate'] ?? null,
                            ];

                        } catch (\Exception $e) {
                            $updateData = [
                                'claim_type' => $claimData['claim_type'],
                                'PolicyId' => null,
                                'ClaimantName' => $claimData['ClaimantName'],
                                'ClaimantMobile' => $claimData['ClaimantMobile'],
                                'IdNumber' => $claimData['IdNumber'] ?? null,
                                'id_type' => $claimData['id_type'] ?? null,
                                'client_number' => $claimData['client_number'] ?? 'TEMP-' . time(),
                                'statuscode' => $claimData['statuscode'] ?? 13,
                                'ClaimDefaultPay_method' => $paymentData['ClaimDefaultPay_method'] ?? null,
                                'ClaimDefaultEFTBank_code' => $paymentData['ClaimDefaultEFTBank_code'] ?? null,
                                'ClaimDefaultEFTBankBranchCode' => $paymentData['ClaimDefaultEFTBankBranchCode'] ?? null,
                                'ClaimDefaultEFTBank_account' => $paymentData['ClaimDefaultEFTBank_account'] ?? null,
                                'dola' => $claimData['eventdate'] ?? null,
                            ];
                            $realClientInfo = [
                                'name' => $claimData['ClaimantName'],
                                'client_number' => $claimData['client_number'] ?? 'TEMP-' . time()
                            ];
                        }
                        
                        \Log::info('Updating existing record ID: ' . $existingRecordId . ' with data: ' . json_encode($updateData));
                        
                        // Update the existing record
                        $updated = DB::table('eClaimsEntries')
                            ->where('id', $existingRecordId)
                            ->update($updateData);
                            
                        if ($updated) {
                            \Log::info('Record updated successfully');
                            $recordId = $existingRecordId;
                            
                            // Skip attachment processing on Step 3 - only update claim data
                            
                            try {
                                $this->logClaimActivity($recordId, $claimData, $realClientInfo, 'updated');
                                \Log::info('Claim update activity logged successfully');
                            } catch (\Exception $e) {
                                \Log::error('Failed to log claim update activity: ' . $e->getMessage());
                            }
                        } else {
                            \Log::error('Failed to update record');
                            return response()->json([
                                'success' => false,
                                'message' => 'Failed to update existing claim'
                            ], 400);
                        }
                    } else {
                        $recordId = 'DRAFT-' . time() . '-' . rand(1000, 9999);
                        session(['draft_claim_' . $recordId => [
                            'claimData' => $claimData,
                            'paymentData' => $paymentData,
                            'attachmentData' => $attachmentData,
                            'clientInfo' => $clientInfo
                        ]]);
                    }
                } else {
                    $recordId = DB::transaction(function () use ($tableData, $claimData, $attachmentData) {
                        $claimId = $this->saveClaimEntry($tableData, $claimData['ID'] ?? null);
                        
                        // Process attachments for complete claims
                        //$this->processAttachments($attachmentData, $claimId);
                        
                        return $claimId;
                        $this->updateClientPaymentDetails($clientInfo['client_number'] ?? 'TEMP-' . time(), $paymentData);
                    });
                }
                
                if ($claimData['IsWebComplete'] == 1) {
                    DB::transaction(function () use ($recordId, $claimData, $clientInfo, $statusText) {
                        $this->logClaimActivity($recordId, $claimData, $clientInfo, $statusText);
                    });
                }
                
                $res = [
                    'success' => true,
                    'record_id' => $recordId,
                    'statuscode' => $statusCode,
                    'status' => $statusText,
                    'message' => "Claim saved successfully as {$statusText}"
                ];
            } catch (\Exception $exception) {
                $res = [
                    'success' => false,
                    'message' => $exception->getMessage()
                ];
                return response()->json($res, 400);
            }
            return response()->json($res);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 400);
        }
    }

    private function extractClaimData(Request $request)
    {
        return [
            'ID' => $request->input('ID'),
            'claim_type' => $request->input('claim_type'),
            'claim_type_description' => $request->input('claim_type_description'),
            'policy_no' => $request->input('policy_no'),
            'client_number' => $request->input('client_number') ?? 'TEMP-' . time(),
            'plan_code' => $request->input('plan_code'),
            'plan_description' => $request->input('plan_description'),
            'ClaimantName' => $request->input('ClaimantName') ?: $request->input('name'),
            'ClaimantMobile' => $request->input('ClaimantMobile') ?: $request->input('mobile'),
            'IdNumber' => $request->input('IdNumber') ?: $request->input('IdNumber'),
            'id_type' => $request->input('id_type'),
            'IsWebComplete' => $request->input('IsWebComplete', 0),
            'ClaimCause' => $request->input('ClaimCause'),
            'DoctorName' => $request->input('DoctorName'),
            'eventdate' => $request->input('event_date') ?: $request->input('eventdate'),
        ];
    }

    private function extractPaymentData(Request $request)
    {
        return [
            'ClaimDefaultPay_method' => $request->input('ClaimDefaultPay_method'),
            'ClaimDefaultEFTBank_code' => $request->input('ClaimDefaultEFTBank_code'),
            'ClaimDefaultEFTBankBranchCode' => $request->input('ClaimDefaultEFTBankBranchCode'),
            'ClaimDefaultEFTBank_accountName' => $request->input('ClaimDefaultEFTBank_accountName'),
            'ClaimDefaultEFTBank_account' => $request->input('ClaimDefaultEFTBank_account'),
            'ClaimDefaultCashRecipient' => $request->input('ClaimDefaultCashRecipient'),
            'ClaimDefaultCashContact' => $request->input('ClaimDefaultCashContact'),
        ];
    }
    
    private function extractAttachmentData(Request $request)
    {
        return [
            'attachments' => $request->input('attachments', []),
            'documents' => $request->input('documents', []),
        ];
    }
    
    private function validateClaimData(array $data)
    {
        $required = ['claim_type', 'policy_no', 'ClaimantName']; 
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field '{$field}' is required");
            }
        }
        
        if (!empty($data['ClaimDefaultPay_method'])) {
            $validMethods = ['01', '02']; 
            if (!in_array($data['ClaimDefaultPay_method'], $validMethods)) {

                throw new \Exception("Invalid payment method");

            }

            

            if ($data['ClaimDefaultPay_method'] === '01') {

                $eftRequired = ['ClaimDefaultEFTBank_code', 'ClaimDefaultEFTBank_accountName', 'ClaimDefaultEFTBank_account'];

                foreach ($eftRequired as $field) {

                    if (empty($data[$field])) {

                        throw new \Exception("EFT payment requires bank details");

                    }

                }

            }

            

            // Validate Cheque fields if Cheque is selected

            if ($data['ClaimDefaultPay_method'] === '02') {

                $chequeRequired = ['ClaimDefaultCashRecipient', 'ClaimDefaultCashContact'];

                foreach ($chequeRequired as $field) {

                    if (empty($data[$field])) {

                        throw new \Exception("Cheque payment requires recipient details");

                    }

                }

            }

        }

    }

    

    private function getPolicyInfo(array $claimData)

    {

        $policyNo = $claimData['policy_no'];

        

        $policy = DB::table('polinfo')

            ->where('policy_no', $policyNo)

            ->first(); 

            

        if ($policy) {

            return [

                'id' => $policy->id,

                'type' => 'micro',

                'client_number' => $policy->client_number

            ];

        }

        

        throw new \Exception("Policy not found: {$policyNo}");

    }

    

    private function getClientInfo(array $policyInfo)

    {

        $client = DB::table('clientinfo')

            ->where('client_number', $policyInfo['client_number'] ?? 'TEMP-' . time())

            ->first();

            

        if (!$client) {

            throw new \Exception("Client not found: " . ($policyInfo['client_number'] ?? 'unknown'));

        }

        

        return [

            'name' => $client->name,

            'client_number' => $client->client_number

        ];

    }

    

    private function getBranchId($userId)

    {

        // Since portal_users table doesn't exist and we need to avoid foreign key constraints,
        // return null for branch_id to allow the claim to be saved without branch validation

        try {

            // Return null instead of hardcoded branch ID to avoid FK constraint violations
            return null;

        } catch (\Exception $e) {

            \Log::error('Error getting branch ID: ' . $e->getMessage());

            return null; // Return null on error to avoid FK issues

        }

    }

    

    private function checkDatabaseConnection()

    {

        try {

            // Simple connection test using the default connection

            DB::connection()->getPdo();

            return true;

        } catch (\Exception $e) {

            \Log::error('Database connection check failed: ' . $e->getMessage());

            // Don't fail immediately - try to proceed with the operation

            // Let the actual database operations handle connection issues

            return true; // Allow operation to proceed, let individual operations handle failures

        }

    }



    private function saveClaimEntryWithTimeout(array $tableData)

    {

        // Force SQL Server connection with different timeout settings

        try {

            \Log::info("Attempting SQL Server insert with custom settings...");

            

            // Create a new database connection with custom timeout settings

            $config = [

                'driver' => 'sqlsrv',

                'host' => env('DB_HOST'),

                'port' => env('DB_PORT', '1433'),

                'database' => env('DB_DATABASE'),

                'username' => env('DB_USERNAME'),

                'password' => env('DB_PASSWORD'),

                'trust_server_certificate' => true,

                'timeout' => 30, // 30 seconds timeout

                'options' => [

                    PDO::ATTR_EMULATE_PREPARES => true,

                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_TIMEOUT => 30,

                ],

            ];

            

            // Create new PDO connection

            $dsn = "sqlsrv:Server=" . $config['host'] . "," . $config['port'] . ";Database=" . $config['database'] . ";LoginTimeout=30";

            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);

            

            // Build the insert query manually

            $columns = array_keys($tableData);

            $values = array_values($tableData);

            $placeholders = str_repeat('?,', count($columns) - 1) . '?';

            

            $sql = "INSERT INTO eClaimsEntries (" . implode(',', $columns) . ") VALUES ({$placeholders})";

            

            \Log::info("Executing SQL: " . $sql);

            \Log::info("With data: " . json_encode($tableData));

            

            $stmt = $pdo->prepare($sql);

            $stmt->execute($values);

            

            // Get the last insert ID

            $id = $pdo->lastInsertId();

            

            \Log::info("SQL Server insert successful! ID: " . $id);

            return $id;

            

        } catch (\Exception $e) {

            \Log::error("Custom SQL Server connection failed: " . $e->getMessage());

            

            // Try with Laravel's DB but with different approach

            try {

                DB::transaction(function () use ($tableData, &$result) {

                    $result = DB::table('eClaimsEntries')->insertGetId($tableData);

                });

                

                \Log::info("Laravel transaction successful! ID: " . $result);

                return $result;

                

            } catch (\Exception $e2) {

                \Log::error("Laravel transaction also failed: " . $e2->getMessage());

                

                // Final attempt - try without any special settings

                try {

                    \Log::info("Final attempt - basic insert...");

                    $result = DB::table('eClaimsEntries')->insertGetId($tableData);

                    \Log::info("Basic insert successful! ID: " . $result);

                    return $result;

                    

                } catch (\Exception $e3) {

                    \Log::error("All SQL Server attempts failed: " . $e3->getMessage());

                    throw new \Exception("Unable to save to SQL Server: " . $e3->getMessage());

                }

            }

        }

    }

    

    private function createSqliteBackup(array $backupData)

    {

        // Create a SQLite database as backup

        $sqliteDb = storage_path('app/claims_backup.sqlite');

        

        // Create SQLite table if it doesn't exist

        $pdo = new \PDO('sqlite:' . $sqliteDb);

        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        

        $pdo->exec("

            CREATE TABLE IF NOT EXISTS eClaimsEntries (

                id INTEGER PRIMARY KEY AUTOINCREMENT,

                backup_id TEXT UNIQUE,

                claim_type TEXT,

                policy_no TEXT,

                ClaimantName TEXT,

                ClaimantMobile TEXT,

                IdNumber TEXT,

                statuscode INTEGER,

                created_on TEXT,

                RequestDate TEXT,

                ClientName TEXT,

                client_number TEXT,

                created_by TEXT,

                ClaimDefaultPay_method TEXT,

                ClaimDefaultEFTBank_code TEXT,

                ClaimDefaultEFTBankBranchCode TEXT,

                ClaimDefaultEFTBank_accountName TEXT,

                ClaimDefaultEFTBank_account TEXT,

                ClaimDefaultCashRecipient TEXT,

                ClaimDefaultCashContact TEXT,

                backup_timestamp TEXT,

                sync_status TEXT DEFAULT 'pending'

            )

        ");

        

        // Insert the data

        $stmt = $pdo->prepare("

            INSERT INTO eClaimsEntries (

                backup_id, claim_type, policy_no, ClaimantName, ClaimantMobile, IdNumber,

                statuscode, created_on, RequestDate, ClientName, client_number, created_by,

                ClaimDefaultPay_method, ClaimDefaultEFTBank_code, ClaimDefaultEFTBankBranchCode,

                ClaimDefaultEFTBank_accountName, ClaimDefaultEFTBank_account, ClaimDefaultCashRecipient,

                ClaimDefaultCashContact, backup_timestamp, sync_status

            ) VALUES (

                :backup_id, :claim_type, :policy_no, :ClaimantName, :ClaimantMobile, :IdNumber,

                :statuscode, :created_on, :RequestDate, :ClientName, :client_number, :created_by,

                :ClaimDefaultPay_method, :ClaimDefaultEFTBank_code, :ClaimDefaultEFTBankBranchCode,

                :ClaimDefaultEFTBank_accountName, :ClaimDefaultEFTBank_account, :ClaimDefaultCashRecipient,

                :ClaimDefaultCashContact, :backup_timestamp, :sync_status

            )

        ");

        

        $data = $backupData['data'];

        $stmt->execute([

            ':backup_id' => $backupData['id'],

            ':claim_type' => $data['claim_type'] ?? '',

            ':PolicyId' => $data['PolicyId'] ?? '',

            ':ClaimantName' => $data['ClaimantName'] ?? '',

            ':ClaimantMobile' => $data['ClaimantMobile'] ?? '',

            ':IdNumber' => $data['IdNumber'] ?? '',

            ':statuscode' => $data['statuscode'] ?? 13,

            ':created_on' => $data['created_on'] ?? '',

            ':RequestDate' => $data['RequestDate'] ?? '',

            ':ClientName' => $data['ClientName'] ?? '',

            ':client_number' => $data['client_number'] ?? '',

            ':created_by' => $data['created_by'] ?? '',

            ':ClaimDefaultPay_method' => $data['ClaimDefaultPay_method'] ?? '',

            ':ClaimDefaultEFTBank_code' => $data['ClaimDefaultEFTBank_code'] ?? '',

            ':ClaimDefaultEFTBankBranchCode' => $data['ClaimDefaultEFTBankBranchCode'] ?? '',

            ':ClaimDefaultEFTBank_accountName' => $data['ClaimDefaultEFTBank_accountName'] ?? '',

            ':ClaimDefaultEFTBank_account' => $data['ClaimDefaultEFTBank_account'] ?? '',

            ':ClaimDefaultCashRecipient' => $data['ClaimDefaultCashRecipient'] ?? '',

            ':ClaimDefaultCashContact' => $data['ClaimDefaultCashContact'] ?? '',

            ':backup_timestamp' => $backupData['timestamp'],

            ':sync_status' => 'pending'

        ]);

    }



    private function saveClaimEntry(array $tableData, $existingId = null)

    {

        $maxRetries = 3;

        $retryCount = 0;

        

        while ($retryCount < $maxRetries) {

            try {

                // Set a shorter timeout for the database operation

                DB::statement('SET LOCK_TIMEOUT 5000'); // 5 seconds

                

                if ($existingId) {

                    // Update existing claim

                    $updated = DB::table('eClaimsEntries')

                        ->where('id', $existingId)

                        ->update(array_merge($tableData, [

                            'altered_by' => request()->input('user_id'),

                            'dola' => Carbon::now()

                        ]));

                    

                    if ($updated) {

                        return $existingId;

                    } else {

                        throw new \Exception("Failed to update claim record");

                    }

                } else {

                    // Insert new claim with timeout handling

                    return DB::table('eClaimsEntries')->insertGetId(array_merge($tableData, [

                        'created_by' => request()->input('user_id'),

                        'created_on' => Carbon::now()

                    ]));

                }

                

            } catch (\Exception $e) {

                $retryCount++;

                

                // Check if it's a timeout error

                if (strpos($e->getMessage(), 'timeout') !== false || strpos($e->getMessage(), 'timed out') !== false) {

                    if ($retryCount < $maxRetries) {

                        // Wait before retrying (exponential backoff)

                        usleep(500000 * $retryCount); // 0.5s, 1s, 1.5s

                        continue;

                    }

                }

                

                // Re-throw the exception if it's not a timeout or we've exhausted retries

                throw $e;

            }

        }

        

        throw new \Exception("Database operation failed after {$maxRetries} attempts due to timeout");

    }

    

    private function updateClientPaymentDetails($clientNumber, array $paymentData)

    {

        // Disabled: clientinfo table doesn't have payment detail columns
        // Payment details are stored in eClaimsEntries table, not clientinfo
        
        \Log::info('updateClientPaymentDetails called but disabled - payment details are stored in eClaimsEntries');
        
        return;

    }

    



    /// TODO: add username and createdby as created_by both as the username ya login

    private function logClaimActivity($recordId, array $claimData, array $clientInfo, $statusText)

    {

        // Check if eClaimId already exists to ensure uniqueness

        $existingLog = DB::table('pos_log')

            ->where('eClaimId', $recordId)

            ->where('Activity', 1) // Claim activity

            ->first();

            

        if ($existingLog) {

            return; // eClaimId already exists, do not save duplicate

        }

        

        // Get claim type description

        $claimTypeDesc = DB::table('claims_types')

            ->where('claim_type', $claimData['claim_type'])

            ->value('Description');

            

        // Log the activity with status

        DB::table('pos_log')->insert([

            'ClientName' => $claimData['ClaimantName'] ?? $clientInfo['name'],

            'StaffNumber' => $this->getStaffNumber($claimData['policy_no']),

            'Activity' => 1,

            'Narration' => $claimTypeDesc . " (Policy Number: " . $claimData['policy_no'] . ") - Status: {$statusText}",

            'eClaimId' => $recordId,

            'created_on' => Carbon::now(),

            'created_by' => request()->input('user_id')

        ]);

    }

    

    private function getStaffNumber($policyNo)

    {

        // Try regular policy first

        $staffNo = DB::table('polinfo')

            ->where('policy_no', $policyNo)

            ->value('SearchReferenceNumber');

            

        if ($staffNo) {

            return $staffNo;

        }

        

        // Try micro policy

        return DB::table('MicroPolicyInfo')

            ->where('ProposalNumber', $policyNo)

            ->value('EmployeeNumber') ?? '';

    }



    public function removeDuplicateClaimTypes(array $objects) {

        // Use an associative array to store unique claim_type values

        $uniqueClaimTypes = array();

    

        // Filter out duplicate claim_type values

        $filteredArray = array_filter($objects, function ($item) use (&$uniqueClaimTypes) {

            $claimType = $item->claim_type;

    

            // Check if claim_type is already in the uniqueClaimTypes array

            if (!isset($uniqueClaimTypes[$claimType])) {

                // Add claim_type to the uniqueClaimTypes array

                $uniqueClaimTypes[$claimType] = true;

                return true; // Include the item in the filtered array

            }

    

            return false; // Exclude the item from the filtered array

        });

    

        return array_values($filteredArray);

    }



    //get Claims Attached files

    public function getClaimTypeGroup(Request $request)

    {

        try {

            //get files for eClaim

            //$rcd_id = $request->input('rcd_id');



            $sql = "SELECT p.*,d.Description AS SchemeBenefit FROM claims_types p 

                    INNER JOIN SchemeBenefitConfig d ON p.claim_type = d.ClaimType";

            $ClaimType = DbHelper::getTableRawData($sql);



            $objects = [

                (object)["claim_type" => "0054", "OldCode" => null, "Description" => "MARRIAGE BENEFIT"],

                (object)["claim_type" => "0054", "OldCode" => null, "Description" => "MARRIAGE BENEFIT"],

                (object)["claim_type" => "010", "OldCode" => null, "Description" => "SPOUSAL COVER"],

                (object)["claim_type" => "010", "OldCode" => null, "Description" => "SPOUSAL COVER"],

                (object)["claim_type" => "011", "OldCode" => null, "Description" => "ANOTHER CLAIM TYPE"],

                // Add more objects with different claim_types

            ];

            

            // Call the function to remove duplicates based on claim_type

            $result = $this->removeDuplicateClaimTypes($ClaimType);



            $res = array(

                'success' => true,

                'ClaimType' => $result//$this->removeDuplicateClaimTypes($ClaimType)

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



    //insert group eClaims

    public function insertGroupClaimEntries(Request $request)

    {

        try {

            $res = array();

            // put in a transaction the whole process of syncing data...

            $this->smartlife_db->transaction(function () use (&$res, $request) {



                $id = $request->input('id');

                $claim_type = $request->input('claim_type');

                $EventDate = $request->input('event_date');

                $member_no = $request->input('member_no');

                $PayMethod = $request->input('PayMethod');

                $LoanType = $request->input('LoanType');



                if(isset($claim_type)){

                    $SchemeBenefit = DbHelper::getColumnValue('SchemeBenefitConfig', 'ClaimType', $claim_type, 'id');

                }

                



                $PayBeneficiary = 0;

                $PayScheme = 0;

                $PayMember = 0;

                if (!isset($PayMethod)) {

                    $PayScheme = 1;

                } else {

                    if (isset($PayMethod) && $PayMethod == "1")

                        $PayScheme = 1;

                    if (isset($PayMethod) && $PayMethod == "2")

                        $PayBeneficiary = 1;

                    if (isset($PayMethod) && $PayMethod == "3")

                        $PayMember = 1;

                }

                //get MemberID

                $MemberID = DbHelper::getColumnValue('glmembersinfo', 'member_no', $member_no, 'MemberId');

                //get SchemeID

                $SchemeID = DbHelper::getColumnValue('glmembersinfo', 'member_no', $member_no, 'SchemeID');







                $table_data = array(

                    'claim_type' => $claim_type,

                    'SchemeID' => $SchemeID,

                    'MemberID' => $MemberID,

                    'EventDate' => $EventDate,

                    'NoticeDate' => Carbon::now(),

                    'PayBeneficiary' => $PayBeneficiary,

                    'PayScheme' => $PayScheme,

                    'PayMember' => $PayMember,

                    'LoanType' => $LoanType,

                    'SchemeBenefit' => $SchemeBenefit

                );



                //insert into 

                if (isset($id) && (int) $id > 0) {

                    //$table_data['AlteredBy'] = $user_id;

                    $table_data['Dola'] = date('Y-m-d H:i:s');

                    //update

                    $this->smartlife_db->table('eClaimEntriesGroup')

                        ->where(

                            array(

                                "ID" => $id

                            )

                        )

                        ->update($table_data);

                    $record_id = $id;

                } else {

                    //$table_data['CreatedBy'] = $user_id;

                    $table_data['CreatedOn'] = date('Y-m-d H:i:s');

                    //insert

                    $record_id = $this->smartlife_db->table('eClaimEntriesGroup')->insertGetId($table_data);

                }



                //health questionnaire

                $res = array(

                    'success' => true,

                    'claim_id' => $record_id,

                    'message' => 'Claim Successfully Saved!'

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



    //get Claims Attached files

    public function getClaimFiles(Request $request)

    {

        try {

            //get files for eClaim

            $rcd_id = $request->input('rcd_id');

            $is_group = $request->input('is_group');



            if(!isset($is_group) || $is_group == 0){

                $sql = "SELECT p.*,d.description AS file_desc from claimsreqinfo p 

                inner join claim_requirement d on d.reg_code=p.code 

                where p.eClaimNumber=$rcd_id";           

            }else{

                $sql = "SELECT p.*,d.description AS file_desc from glifeclaimsreqinfo p 

                inner join claim_requirement d on d.reg_code=p.code 

                where p.EclaimsEntrieId=$rcd_id"; 

            }

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





    ////Save Claim Files/////////

    //Image Sync

    public function syncClaimImage(Request $request)

    {

        try {

            //for base64

            $res = array();

            // put in a transaction the whole process of syncing data...

            $this->smartlife_db->transaction(function () use (&$res, $request) {

                // Debug logging - Show all request data

                \Log::info('syncClaimImage - All request data:', [

                    'all_inputs' => $request->all(),

                    'all_files' => $request->allFiles(),

                    'hasFile' => $request->hasFile('myFile'),

                    'hasFile_any' => $request->hasFile('doc_id'),

                    'headers' => $request->headers->all()

                ]);

                

                $myFile = $request->file('myFile');



                $req_code = $request->input('req_code') ?: $request->input('doc_id');

                $eClaimId = $request->input('eClaimId') ?: $request->input('record_id');

                

                // Debug logging

                \Log::info('syncClaimImage - Parameters:', [

                    'req_code' => $req_code,

                    'eClaimId' => $eClaimId,

                    'myFile_exists' => isset($myFile),

                    'myFile_name' => $myFile ? $myFile->getClientOriginalName() : 'null'

                ]);

                

                $Description = DbHelper::getColumnValue('claim_requirement', 'reg_code', $req_code, 'description') ?: "Document Upload";

                

                // Debug description

                \Log::info('syncClaimImage - Description:', ['Description' => $Description]);

                

                // Check if req_code exists in claim_requirement table

                $codeExists = DbHelper::getTableRawData("SELECT COUNT(*) as count FROM claim_requirement WHERE reg_code = '$req_code'");

                \Log::info('syncClaimImage - Code exists check:', ['req_code' => $req_code, 'exists' => $codeExists[0]->count > 0]);

                

                // If code doesn't exist, use a default one

                if ($codeExists[0]->count == 0) {

                    $req_code = '001'; // Use DEATH CERTIFICATE as default

                    \Log::info('syncClaimImage - Using fallback code:', ['new_req_code' => $req_code]);

                }

                

                // Check if eClaimId exists in eClaimsEntries table

                $eClaimExists = DbHelper::getTableRawData("SELECT COUNT(*) as count FROM eClaimsEntries WHERE id = '$eClaimId'");

                \Log::info('syncClaimImage - eClaimId exists check:', ['eClaimId' => $eClaimId, 'exists' => $eClaimExists[0]->count > 0]);

                

                // If eClaimId doesn't exist, create a basic record

                if ($eClaimExists[0]->count == 0) {

                    \Log::info('syncClaimImage - Creating eClaimEntry:', ['eClaimId' => $eClaimId]);

                    try {

                        $this->smartlife_db->table('claimsinfo')->insert([

                            'id' => $eClaimId,

                            'claim_type' => '0003',

                            'created_on' => Carbon::now(),

                            'created_by' => request()->input('user_id') ?: 1,

                            'statuscode' => 13

                        ]);

                        \Log::info('syncClaimImage - eClaimEntry created successfully');

                    } catch (\Exception $e) {

                        \Log::error('syncClaimImage - eClaimEntry creation failed:', ['error' => $e->getMessage()]);

                        throw $e;

                    }

                }

                

                $signature = $request->input('signature');

                $IsClientSigned = $request->input('IsClientSigned');

                $category_id = 2;



                $fileName = $eClaimId . ".png"; 



                if (isset($myFile))

                    $this->savePhysicalFile($myFile, $category_id, $req_code, $eClaimId, $Description);

                if (isset($signature))

                    $this->saveStringFile($signature, $category_id, $req_code, $eClaimId, $fileName,$IsClientSigned);



                $res = array(

                    'success' => true,

                    'record_id' => $eClaimId,

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



    public function savePhysicalFile($file, $category_id, $req_code, $eClaimId, $Description)

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

        

        // Debug logging

        \Log::info('savePhysicalFile - Starting:', [

            'fileName' => $fileName,

            'file_size' => $file_size,

            'req_code' => $req_code,

            'eClaimId' => $eClaimId,

            'Description' => $Description

        ]);

        

        //Move Uploaded File

        //FileCategoriesStore

        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');

        

        // Fallback to default path if database lookup fails

        if (!$destinationPath) {

            $destinationPath = 'C:\xampp\htdocs\sacos_api\storage\app\claims_documents';

            // Create directory if it doesn't exist

            if (!is_dir($destinationPath)) {

                mkdir($destinationPath, 0777, true);

            }

        }

        

        \Log::info('savePhysicalFile - Destination path:', ['destinationPath' => $destinationPath]);

        

        $file->move($destinationPath, $file->getClientOriginalName());

        $uuid = Uuid::uuid4();

        $uuid = $uuid->toString();



        //insert into mob_proposalFileAttachment

        //claim_no,code,received_flag,date_received,MicroClaim,eClaimNumber,File,Description



        //check if file already exists

        $sql = "SELECT p.* FROM claimsreqinfo p WHERE p.eClaimNumber=$eClaimId AND p.code='$req_code'";

        $claimsreqinfoArr = DbHelper::getTableRawData($sql);

        

        \Log::info('savePhysicalFile - Existing records check:', [

            'sql' => $sql,

            'existing_count' => sizeof($claimsreqinfoArr)

        ]);

        

        $table_data = array(

            'created_on' => Carbon::now(),

            //'claim_no' => $claim_id,

            'code' => $req_code,

            'received_flag' => 0,

            'date_received' => Carbon::now(),

            //'MicroClaim' => 0,

            'eClaimNumber' => $eClaimId,

            'File' => $uuid,

            'Description' => $fileName //$Description,

        );



        if (sizeof($claimsreqinfoArr) > 0) {

            //update..

            $record_id = $claimsreqinfoArr[0]->id;

            \Log::info('savePhysicalFile - Updating existing record:', ['record_id' => $record_id]);

            $this->smartlife_db->table('claimsreqinfo')

            ->where(

                array(

                    "id" => $record_id

                )

            )

            ->update($table_data);

        }else{

            \Log::info('savePhysicalFile - Inserting new record');

            try {

                $record_id = $this->smartlife_db->table('claimsreqinfo')->insertGetId($table_data);

                \Log::info('savePhysicalFile - New record ID:', ['record_id' => $record_id]);

            } catch (\Exception $e) {

                \Log::error('savePhysicalFile - Insert failed:', ['error' => $e->getMessage()]);

                throw $e;

            }

        }



        



        //insert into Mob_ProposalStoreObject

        $table_data = array(

            'Oid' => $uuid,

            //'claimno' => $eClaimId,

            'FileName' => $fileName,

            'RequestedClaim' => $eClaimId,

            'Size' => $file_size,

        );

        

        \Log::info('savePhysicalFile - Inserting into ClaimsStoreObject:', ['uuid' => $uuid]);

        $record_id = $this->smartlife_db->table('ClaimsStoreObject')->insertGetId($table_data);

        \Log::info('savePhysicalFile - ClaimsStoreObject record ID:', ['record_id' => $record_id]);

    }



    function base64ToVarbinary($base64)

    {

        $binary = base64_decode($base64);

        return bin2hex($binary);

    }



    public function saveStringFile($file, $category_id, $req_code, $eClaimId, $fileName,$IsClientSigned=null)

    {

        // $prefix = "data:image/png;base64,";

        // if (strpos($file, $prefix) === 0) {

        //     return str_replace($prefix, '', $file);

        // }

        //echo $file;

        //echo "am here";

        $file = substr($file, 22);

        //echo $file;

        //$destinationPath = 'C:\xampp\htdocs\SmartLifeDocuments\ClaimsDocuments';

        $destinationPath = DbHelper::getColumnValue('FileCategoriesStore', 'ID', $category_id, 'FileStoreLocationPath');



        file_put_contents($destinationPath . '\\' . $eClaimId . '.png', base64_decode($file));

        $image_path = $destinationPath . "\\" . $eClaimId . ".png";

        $image_binary = file_get_contents($image_path);



        $this->smartlife_db->table('eClaimsEntries')

            ->where('id', $eClaimId)

            ->update([

                'ClientSignature' => DB::raw("0x" . bin2hex($image_binary)),

                'statuscode' => 13,

                'IsClientSigned' => $IsClientSigned

            ]);



        //insert into mob_proposalFileAttachment

        $sql = "SELECT p.* FROM claimsreqinfo p WHERE p.eClaimNumber=$eClaimId AND p.code='$req_code'";

        $claimsreqinfoArr = DbHelper::getTableRawData($sql);

        $uuid = Uuid::uuid4();

        $uuid = $uuid->toString();

        $table_data = array(

            'created_on' => Carbon::now(),

            //'claim_no' => $claim_id,

            'code' => $req_code,

            'received_flag' => 0,

            'date_received' => Carbon::now(),

            //'MicroClaim' => 0,

            'eClaimNumber' => $eClaimId,

            'File' => $uuid,

            'Description' => $fileName //$Description,

        );

        if (sizeof($claimsreqinfoArr) > 0) {

            //update..

            $record_id = $claimsreqinfoArr[0]->id;

            $this->smartlife_db->table('claimsreqinfo')

            ->where(

                array(

                    "id" => $record_id

                )

            )

            ->update($table_data);

        }else{

            $record_id = $this->smartlife_db->table('claimsreqinfo')->insertGetId($table_data);

        }

        //insert into Mob_ProposalStoreObject

        $table_data = array(

            'Oid' => $uuid,

            //'claimno' => $eClaimId,

            'FileName' => $fileName,

            'RequestedClaim' => $eClaimId,

            'Size' => 570,

        );

        $record_id = $this->smartlife_db->table('ClaimsStoreObject')->insertGetId($table_data);

    }

    //////////end of Claim Files///



    //fetch claims to sign... 

    public function getClaimsToSign(Request $request)

    {

        try {

            $res = array();

            //created_by, is_micro

            $created_by = $request->input('created_by');

            $is_micro = $request->input('is_micro');

            if($is_micro == "1"){

                $sql = "SELECT p.Id 'id',d.PolicyNumber 'policy_no',p.claim_type,p.ClaimantName,

                p.ClaimantMobile,p.created_on,p.IsClientSigned 

                FROM eClaimsEntries p 

                INNER JOIN MicroPolicyInfo d ON p.MicroPolicy=d.id

                WHERE p.created_on > '2024-07-20' AND

                (p.IsClientSigned=0 OR p.IsClientSigned IS NULL) AND 

                p.created_by = '$created_by' 

                ORDER BY p.id DESC";

            }else{

                $sql = "SELECT p.id,d.policy_no,p.PolicyId,p.claim_type,p.ClaimantName,

                p.ClaimantMobile,p.created_on,p.IsClientSigned 

                FROM eClaimsEntries p 

                INNER JOIN polinfo d ON p.PolicyId=d.id

                WHERE p.created_on > '2024-07-20' AND

                (p.IsClientSigned=0 OR p.IsClientSigned IS NULL) AND 

                p.created_by = '$created_by'

                ORDER BY p.id DESC";

            }

            

            $Claims = DbHelper::getTableRawData($sql);

            //health questionnaire

            $res = array(

                'success' => true,

                'Claims' => $Claims

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



    //get history of group claims

    public function getGroupHistoryClaims(Request $request)

    {

        try {

            $res = array();



            //

            $date_from = $request->input('date_from');

            $date_to = $request->input('date_to');

            $source_type = $request->input('source_type');



            $filter_array = array();//t3.id=6

            if($source_type == "2"){

                $filter_array = array(

                    "p.processed" => 1

                );

            } else if($source_type == "3"){

                $filter_array = array(

                    "s.id" => 6

                );

            }



            $results = $this->smartlife_db->table('glifeclaimsnotification as p')

                ->select('p.*', 's.description as claim_status', 'g.member_no as Emp_code')

                ->join('polschemeinfo as i', 'i.schemeID', '=', 'p.Scheme')

                ->join('ClaimHistoryInfo as h', 'p.id', '=', 'h.GlifeClaim_no')

                ->join('ClaimStatusInfo as s', 'h.statuscode', '=', 's.id')

                ->join('glmembersinfo as g', 'g.MemberId', '=', 'p.MemberIdKey')

                ->where($filter_array)

                ->whereBetween('p.notification_date', [$date_from, $date_to]) 

                ->orderBy('p.notification_date', 'DESC')

                ->get();

           

            $res = array(

                'success' => true,

                'Claims' => $results

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





    //get history of claims

    public function getHistoryClaims(Request $request)

    {

        try {

            $res = array();



            $client_no = $request->input('client_no');

            $policy_no = $request->input('policy_no');



            $ReferenceNumber = $request->input('staff_no');

            $id = $request->input('id');

            $is_micro = $request->input('is_micro');

            $is_dashboard = $request->input('is_dashboard');

            $is_md_coo = $request->input('is_md_coo');





            $date_from = $request->input('date_from');

            $date_to = $request->input('date_to');



            $criteria = $request->input('criteria');

            if(isset($criteria)) {

                if($criteria == "1"){

                    $policy_no = $request->input('search_entry');

                } else if($criteria == "2"){

                    $ReferenceNumber = $request->input('search_entry');

                }

            }





            $sql = "SELECT d.NarrationForRefunds,d.id,d.PolicyId,d.event_date,d.ClaimCause,d.claim_type,d.claim_no,

            d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,

            d.AmountAppliedFor,d.claimant AS ClaimantName,d.IsCancelled,d.processed,d.Cancel_narration,

            d.Pay_method,d.PaymentOptions,d.total_proceeds, d.total_deductions, d.net_payment, d.pay_due_date,

            d.created_by,d.created_on,d.branch_id,d.statusCode, 

            p.policy_no, n.description as ClaimStatus,

            f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 

            f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,

            f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,

            i.Pay_method, h.decription AS payment_method, i.Bank, j.description AS bank_name, 

            i.BankBranch, k.bankBranchName AS bank_branch_name, i.BankAccount, i.MobileNumber, 

            i.TelcoCompany, i.cheque_no,  q.Names 'Deceased' 

            FROM claim_notificationinfo d 

            LEFT JOIN eClaimsEntries m ON m.id = d.RequestedClaim

            LEFT JOIN ClaimStatusInfo n ON n.id = m.statuscode 

            LEFT JOIN claimsinfo i ON i.claim_no = d.id

            LEFT JOIN polinfo p ON d.PolicyId=p.id 

            LEFT JOIN clientinfo f ON f.client_number=p.client_number 

            LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch

            LEFT JOIN payment_type h ON h.payment_mode=i.Pay_method

            LEFT JOIN bankcodesinfo j ON j.bank_code=i.Bank

            LEFT JOIN bankmasterinfo k ON k.id=i.BankBranch

            LEFT JOIN funeralmembers q ON q.id=d.FuneralMembersInfo";

            if (isset($client_no) && $client_no != 'undefined') {

                $sql .= " WHERE p.client_number='$client_no'";

            } else if (isset($policy_no) && $policy_no != 'undefined') {

                $sql .= " WHERE p.policy_no='$policy_no'";

            } else if (isset($id)) {

                $sql .= " WHERE d.id=$id";

            } else if (isset($is_dashboard) && $is_dashboard == "1") {

                if(!isset($date_from) || !isset($date_to)){

                    $date_from = date('Y-m-d');

                    $date_to = date('Y-m-d');

                }//RequestDate

                $sql .= " WHERE d.pay_due_date BETWEEN '$date_from' AND '$date_to'";

                

            } else if (isset($is_md_coo)) {

                if(!isset($date_from) || !isset($date_to)){

                    $date_from = date('Y-m-d');

                    $date_to = date('Y-m-d');

                }//RequestDate

                $sql .= " WHERE d.created_on BETWEEN '$date_from' AND '$date_to'";

                

            } else if (isset($ReferenceNumber)) {

                $sql .= " WHERE p.SearchReferenceNumber='$ReferenceNumber'";

            }



            if(isset($criteria) || isset($is_md_coo)) {

                //TODO-Add the ones at enquiry stage only

                $sql_enquiry = "SELECT p.policy_no,d.id,d.claim_type,d.created_on,d.created_by,d.claim_no,

                        p.status_code, 

                        n.description AS ClaimStatus,d.IsCancelled,d.Cancel_narration,

                        g.glbranch_name as branch_name, i.Names 'Deceased'   

                        FROM eClaimsEntries d 

                        INNER JOIN polinfo p ON p.id=d.PolicyId

                        LEFT JOIN ClaimStatusInfo n ON n.id = d.statuscode 

                        LEFT JOIN claim_notificationinfo e ON e.RequestedClaim=d.id

                        LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch 

                        LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo

                        WHERE e.RequestedClaim IS NULL AND d.statuscode <> 14 ";

                if (isset($ReferenceNumber)) {

                    $sql_enquiry .= " AND p.SearchReferenceNumber='gli10213'";

                }

                if (isset($policy_no)) {

                    $sql_enquiry .= " AND p.policy_no='$policy_no'";

                }

                if (isset($is_md_coo)) {

                    if(!isset($date_from) || !isset($date_to)){

                        $date_from = date('Y-m-d');

                        $date_to = date('Y-m-d');

                    }

                    $sql .= " AND d.created_on BETWEEN '$date_from' AND '$date_to'";

                }



            }





            if (isset($is_micro) && $is_micro == 1) {

                $sql = "SELECT d.Id,d.Policy,h.EventDate AS event_date,h.ClaimCause,d.claim_type,h.ClaimNumber AS claim_no,

                h.[PartialWidthrawalReasons] AS PartialWithdPurpose,h.CurrentCashValue,

                h.AmountAppliedFor,d.claimant AS ClaimantName,d.IsCancelled,d.processed,d.Cancel_narration,

                d.Pay_method,h.PaymentOptions,d.total_proceeds, d.total_deductions, d.net_payment, d.pay_due_date,

                d.created_by,d.created_on,h.Branch AS branch_id,p.ProposalNumber AS policy_no,

                d.payment_flag AS statuscode, n.description as ClaimStatus,

                f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 

                f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,

                f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name  

                FROM MicroClaimsInfo d 

                INNER JOIN MicroPolicyInfo p ON d.Policy=p.Id 

                INNER JOIN clientinfo f ON f.client_number=p.Client 

                INNER JOIN MicroClaimNotification h ON h.Id=d.MicroClaim 

                LEFT JOIN eClaimsEntries m ON m.id = h.RequestedClaim

                LEFT JOIN ClaimStatusInfo n ON n.id = m.statuscode 

                LEFT JOIN glBranchInfo g ON h.Branch=g.glBranch";

                if (isset($client_no) && $client_no != 'undefined') {

                    $sql .= " WHERE p.Client='$client_no' ";

                } else if (isset($policy_no) && $policy_no != 'undefined') {

                    $sql .= " WHERE p.PolicyNumber='$policy_no' ";

                } else if (isset($id)) {

                    $sql .= " WHERE d.Id=$id";

                } else if (isset($is_dashboard) && $is_dashboard == "1") {

                    if(!isset($date_from) || !isset($date_to)){

                        $date_from = date('Y-m-d');

                        $date_to = date('Y-m-d');

                    }//RequestDate

                    $sql .= " WHERE d.pay_due_date BETWEEN '$date_from' AND '$date_to'";

                }



                if(isset($criteria)) {

                    //TODO-Add the ones at enquiry stage only

                    $sql_enquiry = "SELECT p.PolicyNumber,d.id,d.claim_type,d.created_on,d.created_by,d.claim_no,p.status_code,

                            n.description AS ClaimStatus,d.IsCancelled,d.Cancel_narration,

                            g.glbranch_name as branch_name  

                            FROM eClaimsEntries d 

                            INNER JOIN MicroPolicyInfo p ON d.Micro=p.Id 

                            LEFT JOIN ClaimStatusInfo n ON n.id = d.statuscode 

                            LEFT JOIN claim_notificationinfo e ON e.RequestedClaim=d.id

                            LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch

                            WHERE e.RequestedClaim IS NULL AND d.statuscode <> 14 ";

                    if (isset($ReferenceNumber)) {

                        $sql_enquiry .= " AND p.SearchReferenceNumber='gli10213'";

                    }

                    if (isset($policy_no)) {

                        $sql_enquiry .= " AND p.PolicyNumber='$policy_no'";

                    }

                }

            }



            

            $Claims = DbHelper::getTableRawData($sql);

            if(isset($criteria)){

                $ClaimsE = DbHelper::getTableRawData($sql_enquiry);

                $Claims = array_merge($Claims, $ClaimsE);

            }



            //health questionnaire

            $res = array(

                'success' => true,

                'Claims' => $Claims

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



    //get group claims........

    public function getGroupClaims(Request $request)

    {

        try {

            $res = array();



            $scheme_no = $request->input('scheme_no');

            $SchemeID = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');

            

            /*$sql = "SELECT

            p.id,p.Scheme,p.claim_no,p.branch_id,p.notification_date,p.total_proceeds,p.total_deductions,p.net_payment,

            CASE 

                WHEN p.IsCancelled = 'True' THEN 1

                WHEN p.IsCancelled = 'False' THEN 0

                ELSE NULL -- Handle other cases if needed

            END AS IsCancelled,

            -- Map 'True' to 1 and 'False' to 0 for the 'Approved' column

            CASE 

                WHEN p.Approved = 'True' THEN 1

                WHEN p.Approved = 'False' THEN 0

                ELSE NULL -- Handle other cases if needed

            END AS Approved,

            e.name AS client_name,

            g.glbranch_name,

            q.Description AS ClaimName

        FROM glifeclaimsnotification p 

            INNER JOIN polschemeinfo d ON d.schemeID=p.Scheme

            INNER JOIN glifeclientinfo e ON e.Id=d.schemeID

            INNER JOIN claims_types q ON q.claim_type=p.claim_type

            LEFT JOIN glBranchInfo g ON p.branch_id=g.glBranch

            WHERE d.schemeID=$SchemeID 

            ORDER BY p.id DESC";*/

            //INNER JOIN glmembersinfo j ON j.SchemeID=p.Scheme

            //, j.MemberId, j.Names, j.member_no



            $sql = "SELECT

            p.id,p.Scheme,p.claim_no,p.branch_id,p.notification_date,p.total_proceeds,p.total_deductions,p.net_payment,

            p.IsCancelled, p.Approved, e.name AS client_name,

            g.glbranch_name,

            q.Description AS ClaimName

            FROM glifeclaimsnotification p 

            INNER JOIN polschemeinfo d ON d.schemeID=p.Scheme

            INNER JOIN glifeclientinfo e ON e.Id=d.schemeID

            INNER JOIN claims_types q ON q.claim_type=p.claim_type

            

            LEFT JOIN glBranchInfo g ON p.branch_id=g.glBranch

            WHERE d.schemeID=$SchemeID 

            ORDER BY p.id DESC";



            //echo $sql;

            $Claims = DbHelper::getTableRawData($sql);



            //health questionnaire

            $res = array(

                'success' => true,

                'Claims' => $Claims

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



    //get claims details

    public function getClientClaims(Request $request)

    {

        try {

            $res = array();



            $client_no = $request->input('client_no');

            $policy_no = $request->input('policy_no');

            $proposal_no = $request->input('proposal_no');

            $id = $request->input('id');

            $is_micro = $request->input('is_micro');



            $is_dashboard = $request->input('is_dashboard');



            $date_from = $request->input('date_from');

            $date_to = $request->input('date_to');



            $sql = "SELECT h.NarrationForRefunds,d.ProposalNumber,d.id,d.PolicyId,d.event_date,d.ClaimCause,d.RequestDate,d.claim_type,d.claim_no,

            d.ClientName,d.mobile_id,d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,

            d.AmountAppliedFor,d.ClaimantName,d.ClaimantMobile, d.Cancel_narration,

            d.Pay_method,d.PaymentOptions,d.statuscode,d.StatusDescription,d.HasBeenPicked,d.IsFromClientPortal,

            d.IsFromPosPortal,d.IsFromSmartLife,d.created_by,d.created_on,d.branch_id,p.policy_no,

            e.description AS statuscode,

            f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 

            f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,

            f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,

            d.requisition_no,d.pv_no,d.SpecificReason,q.Description AS claim_name,

            i.Names 'Deceased'

            FROM eClaimsEntries d 

            INNER JOIN claims_types q ON q.claim_type=d.claim_type";

            if(isset($proposal_no) && !isset($policy_no)){

                $sql .= " INNER JOIN proposalinfo p ON d.ProposalNumber=p.proposal_no 

                INNER JOIN clientinfo f ON f.client_number=p.client_number 

                INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 

                LEFT JOIN claim_notificationinfo h ON h.RequestedClaim=d.id 

                LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 

                LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";

            }else{

                $sql .= " INNER JOIN polinfo p ON d.PolicyId=p.id 

                INNER JOIN clientinfo f ON f.client_number=p.client_number 

                INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 

                LEFT JOIN claim_notificationinfo h ON h.RequestedClaim=d.id 

                LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 

                LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";

            }

            

            if (isset($client_no)) {

                $sql .= " WHERE p.client_number='$client_no' AND d.statuscode <> 14";

            } else if (isset($policy_no)) {

                $sql .= " WHERE p.policy_no='$policy_no' AND d.statuscode <> 14";

            } else if (isset($proposal_no)) {

                $sql .= " WHERE d.ProposalNumber='$proposal_no' AND d.statuscode <> 14";

            } else if (isset($id)) {

                $sql .= " WHERE d.id=$id";

            } else if (isset($date_from) || (isset($is_dashboard) && $is_dashboard == "1")) {

                if(!isset($date_from) || !isset($date_to)){

                    $date_from = date('Y-m-d');

                    $date_to = date('Y-m-d');

                }//RequestDate

                $sql .= " WHERE (d.RequestDate BETWEEN '$date_from' AND '$date_to') AND d.statuscode <> 14";

            }



            if (isset($is_micro) && $is_micro == 1) {

                $sql = "SELECT d.id,d.MicroPolicy as PolicyId,d.event_date,d.ClaimCause,d.RequestDate,d.claim_type,d.claim_no,

                d.ClientName,d.mobile_id,d.PartialWithdPurpose,d.CurrentCashValue,d.PreviousloanAmount,

                d.AmountAppliedFor,d.ClaimantName,d.ClaimantMobile,d.Pay_method,d.PaymentOptions,d.statuscode,

                d.StatusDescription,d.HasBeenPicked, d.Cancel_narration,

                d.IsFromClientPortal,d.IsFromPosPortal,d.IsFromSmartLife,d.created_by,d.created_on,d.branch_id,

                p.ProposalNumber AS policy_no,

                e.description AS statuscode,

                f.ClaimDefaultPay_method,f.ClaimDefaultTelcoCompany,f.ClaimDefaultMobileWallet, 

                f.ClaimDefaultEFTBank_code,f.ClaimDefaultEFTBankBranchCode,f.ClaimDefaultEFTBank_account,

                f.ClaimDefaultEftBankaccountName,g.glbranch_name as branch_name,

                d.requisition_no,d.pv_no,d.SpecificReason,q.Description AS claim_name     

                FROM eClaimsEntries d 

                INNER JOIN claims_types q ON q.claim_type=d.claim_type ";

                if(isset($proposal_no) && !isset($policy_no)){

                    $sql .= "LEFT JOIN MicroProposalInfo p ON d.ProposalNumber=p.ProposalNumber 

                    INNER JOIN clientinfo f ON f.client_number=p.Client 

                    INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 

                    LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";

                }else{

                    $sql .= "LEFT JOIN MicroPolicyInfo p ON d.MicroPolicy=p.Id 

                    INNER JOIN clientinfo f ON f.client_number=p.Client 

                    INNER JOIN ClaimStatusInfo e ON e.id=d.statuscode 

                    LEFT JOIN funeralmembers i ON i.id=d.FuneralMembersInfo 

                    LEFT JOIN glBranchInfo g ON d.branch_id=g.glBranch";

                }

                if (isset($client_no)) {

                    $sql .= " WHERE p.Client='$client_no' AND d.statuscode <> 14";

                } else if (isset($policy_no)) {

                    $sql .= " WHERE p.PolicyNumber='$policy_no' AND d.statuscode <> 14";

                } else if (isset($proposal_no)) {

                    $sql .= " WHERE d.ProposalNumber='$proposal_no' AND d.statuscode <> 14";

                } else if (isset($id)) {

                    $sql .= " WHERE d.id=$id";

                } else if (isset($date_from) || (isset($is_dashboard) && $is_dashboard == "1")) {

                    if(!isset($date_from) || !isset($date_to)){

                        $date_from = date('Y-m-d');

                        $date_to = date('Y-m-d');

                    }//RequestDate

                    $sql .= " WHERE (d.RequestDate BETWEEN '$date_from' AND '$date_to') AND d.statuscode <> 14";

                }

            }

            //echo $sql;

            $Claims = DbHelper::getTableRawData($sql);



            //health questionnaire

            $res = array(

                'success' => true,

                'Claims' => $Claims

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



    //get claims details

    public function getClaimAttachments(Request $request)

    {

        try {

            $res = array();



            $claim_type = $request->input('claim_type');



            $sql = "SELECT p.id,p.req_code,d.description,p.IsMandatory FROM claimtyperequirementinfo p 

            INNER JOIN claim_requirement d  ON p.req_code=d.reg_code WHERE p.claim_type='$claim_type'";

            $Attachments = DbHelper::getTableRawData($sql);



            //health questionnaire

            $res = array(

                'success' => true,

                'Attachments' => $Attachments

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