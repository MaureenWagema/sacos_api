<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Helpers\DbHelper;

class EndorsementController extends Controller
{
    public function saveEndorsement(Request $request)
    {
        try {
            Log::info('Endorsement save request started', ['request_data' => $request->all()]);
            
            // Get endorsement data directly from request or from tableData
            $endorsement_data = null;
            if ($request->has('tableData')) {
                $endorsement_data = json_decode($request->input('tableData'));
            } else {
                // Direct access to request input to avoid stdClass wrapper
                $endorsement_data = new \stdClass();
                $endorsement_data->Endorsementtype = $request->input('Endorsementtype');
                $endorsement_data->policy_no = $request->input('policy_no');
                $endorsement_data->plan_code = $request->input('plan_code');
                $endorsement_data->name = $request->input('name');
                $endorsement_data->mobile = $request->input('mobile');
                $endorsement_data->IdNumber = $request->input('IdNumber');
                $endorsement_data->client_number = $request->input('client_number');
                $endorsement_data->EffectiveDate = $request->input('EffectiveDate');
                $endorsement_data->Reason = $request->input('Reason');
                $endorsement_data->Narration = $request->input('Narration');
                $endorsement_data->narration = $request->input('narration');
                $endorsement_data->status_code = $request->input('status_code');
                $endorsement_data->ID = $request->input('ID');
            }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid JSON data: ' . json_last_error_msg()
                ], 400);
            }

            Log::info('Endorsement data parsed', ['endorsement_data' => $endorsement_data]);

            // Validate required fields
            $required_fields = ['Endorsementtype', 'policy_no', 'name', 'status_code'];
            foreach ($required_fields as $field) {
                if (!isset($endorsement_data->$field) || empty($endorsement_data->$field)) {
                    return response()->json([
                        'success' => false,
                        'message' => "Required field '{$field}' is missing or empty"
                    ], 400);
                }
            }

            Log::info('Required fields validated');

            $res = array();
            
            Log::info('Starting database transaction');
            $this->smartlife_db->transaction(function () use (&$res, $request, $endorsement_data) {
                // Extract fields from payload
                $Endorsementtype = $endorsement_data->Endorsementtype;
                $policy_no = $endorsement_data->policy_no;
                $plan_code = $endorsement_data->plan_code ?? null;
                $name = $endorsement_data->name;
                $mobile = $endorsement_data->mobile ?? null;
                $IdNumber = $endorsement_data->IdNumber ?? null;
                $client_number = $endorsement_data->client_number ?? null;
                $EffectiveDate = $endorsement_data->EffectiveDate ?? Carbon::now()->format('Y-m-d');
                $Reason = $endorsement_data->Reason ?? null;
                $status_code = $endorsement_data->status_code;
                
                Log::info('Fields extracted', ['policy_no' => $policy_no]);
                
                // Get PolicyNumber from polinfo table using policy_no
                $PolicyNumber = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'id');
                
                Log::info('Policy lookup result', ['policy_no' => $policy_no, 'PolicyNumber' => $PolicyNumber]);
                
                if (!$PolicyNumber) {
                    throw new \Exception("Policy with policy_no '{$policy_no}' not found");
                }
                
                // Prepare endorsement data
                $endorsementData = array(
                    'Endorsementtype' => $Endorsementtype,
                    'PolicyNumber' => $PolicyNumber,
                    'RequestDate' => Carbon::now(),
                    'created_on' => Carbon::now(),
                    'ClientName' => $name,
                    'Statuscode1' => $status_code,
                    'Statuscode2' => $status_code,
                    'StatusDescription' => 'endorsed',
                    'Reason' => $Reason,
                    'EffectiveDate' => $EffectiveDate
                );
                
                Log::info('Endorsement data prepared', ['endorsementData' => $endorsementData]);
                
                // Check for existing endorsement with same Endorsementtype and PolicyNumber
                $existingEndorsement = $this->smartlife_db->table('eEndorsementEntries')
                    ->where('Endorsementtype', $Endorsementtype)
                    ->where('PolicyNumber', $PolicyNumber)
                    ->first();
                
                // Check if this is an update or insert
                if (isset($endorsement_data->ID) && $endorsement_data->ID > 0) {
                    // Update existing endorsement by ID
                    $id = $endorsement_data->ID;
                    $endorsementData['dola'] = Carbon::now();
                    
                    Log::info('Updating endorsement by ID', ['id' => $id]);
                    $this->smartlife_db->table('eEndorsementEntries')
                        ->where('id', $id)
                        ->update($endorsementData);
                        
                    $message = 'Endorsement updated successfully!';
                } elseif ($existingEndorsement) {
                    // Update existing endorsement found by duplicate check
                    $id = $existingEndorsement->id;
                    $endorsementData['dola'] = Carbon::now();
                    
                    Log::info('Updating existing endorsement (duplicate found)', ['id' => $id]);
                    $this->smartlife_db->table('eEndorsementEntries')
                        ->where('id', $id)
                        ->update($endorsementData);
                        
                    $message = 'Endorsement updated successfully (existing record found)!';
                } else {
                    // Insert new endorsement
                    $endorsementData['date_synced'] = Carbon::now();
                    
                    Log::info('Inserting new endorsement');
                    $id = $this->smartlife_db->table('eEndorsementEntries')->insertGetId($endorsementData);
                    $message = 'Endorsement created successfully!';
                }
                
                Log::info('Database operation completed', ['id' => $id]);
                
                $res = array(
                    'success' => true,
                    'endorsementid' => $id,
                    'message' => $message
                );
                
                // Log endorsement activity to pos_log
                $this->logEndorsementActivity($id, $endorsementData, $policy_no);
            });
            
            Log::info('Transaction completed successfully', ['result' => $res]);
            
            return response()->json($res);
            
        } catch (\Exception $exception) {
            Log::error('Endorsement save error: ' . $exception->getMessage(), [
                'request_data' => $request->all(),
                'trace' => $exception->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $exception->getMessage()
            ], 500);
        }
    }
    
    private function logEndorsementActivity($recordId, array $endorsementData, $policy_no)
    {
        // Check if eEndorsementId already exists to ensure uniqueness
        $existingLog = $this->smartlife_db->table('Pos_Log')
            ->where('eEndorsementId', $recordId)
            ->where('Activity', 2) // Activity 2 for endorsements (matching original system)
            ->first();

        // Get endorsement type description
        $endorsementTypeDesc = $this->smartlife_db->table('LifeEndorsementTypeInfo')
            ->where('Id', $endorsementData['Endorsementtype'])
            ->value('Description');

        $logData = [
            'ClientName' => $endorsementData['ClientName'],
            'StaffNumber' => $this->getStaffNumber($policy_no),
            'Activity' => 2, // Activity 2 for endorsements 
            'Narration' => $endorsementTypeDesc . " (Policy Number: " . $policy_no . ") ",
            'eEndorsementId' => $recordId,
            'created_on' => Carbon::now(),
            'created_by' => request()->input('user_id')
        ];

        if ($existingLog) {
            // Update existing log entry
            $this->smartlife_db->table('Pos_Log')
                ->where('eEndorsementId', $recordId)
                ->where('Activity', 2)
                ->update($logData);
        } else {
            // Insert new log entry
            $this->smartlife_db->table('Pos_Log')->insert($logData);
        }
    }

    private function getStaffNumber($policyNo)
    {
        // Try regular policy first
        $staffNo = $this->smartlife_db->table('polinfo')
            ->where('policy_no', $policyNo)
            ->value('SearchReferenceNumber');

        if ($staffNo) {
            return $staffNo;
        }

    // Try micro policy
    $staffNo = $this->smartlife_db->table('MicroPolicyInfo')
        ->where('PolicyNo', $policyNo)
        ->value('SearchReferenceNumber');

    return $staffNo ?: 'Unknown';
}

    public function getEndorsementEntries(Request $request)
{
    try {
        $endorsementId = $request->input('endorsement_id');
        $policyNo = $request->input('policy_no');
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $query = $this->smartlife_db->table('eEndorsementEntries');

        // Filter by endorsement ID if provided
        if ($endorsementId) {
            $query->where('id', $endorsementId);
        }

        // Filter by policy number if provided
        if ($policyNo) {
            // Get the PolicyNumber (ID) from polinfo table
            $policyId = DbHelper::getColumnValue('polinfo', 'policy_no', $policyNo, 'id');
            if ($policyId) {
                $query->where('PolicyNumber', $policyId);
            } else {
                // If policy not found, return empty result
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total_count' => 0,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
            }
        }

        // Get endorsement entries with pagination
        $endorsements = $query
            ->orderBy('created_on', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Add endorsement type definition to each record
        foreach ($endorsements as $endorsement) {
            $endorsement->endorsDef = DbHelper::getColumnValue('LifeEndorsementTypeInfo', 'Id', $endorsement->Endorsementtype, 'Description');
        }

        // Get total count for pagination
        $totalCountQuery = $this->smartlife_db->table('eEndorsementEntries');
        
        if ($endorsementId) {
            $totalCountQuery->where('id', $endorsementId);
        }
        
        if ($policyNo && isset($policyId)) {
            $totalCountQuery->where('PolicyNumber', $policyId);
        }
        
        $totalCount = $totalCountQuery->count();

        return response()->json([
            'success' => true,
            'data' => $endorsements,
            'total_count' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);

    } catch (\Exception $exception) {
        Log::error('Error fetching endorsement entries: ' . $exception->getMessage(), [
            'endorsement_id' => $endorsementId ?? null,
            'policy_no' => $policyNo ?? null,
            'trace' => $exception->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $exception->getMessage()
        ], 500);
    }
}

}