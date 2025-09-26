<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class loginController extends Controller
{

    public function getTest(Request $request)
    {
        try {
            /*$FirstName = $request->input('FirstName');
            $results = $this->smartlife_db->table('tblContact as p')
            ->select('*')->get();*/
            // ->where(array('p.FirstName' => $FirstName));
            //$sql_query = "SELECT id, FirstName, LastName, Email FROM tblContact";
            //$results = $this->smartlife_db->select($this->smartlife_db->raw($sql_query));

            /*$user_id = 1;//\Auth::user()->id;
            $table_data = $request->all();
            $table_name = $table_data['table_name'];
            unset($table_data['table_name']);
            $res = DbHelper::insertRecord($table_name, $table_data, $user_id);*/

            $sql = "select * from gender_info";
            $res = DbHelper::getTableRawData($sql);
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

    //change password (Agent & Client) - Send client a link.....

    function generateRandomFileName($length = 6, $level = 2)
    {
        list($usec, $sec) = explode(' ', microtime());
        srand((float) $sec + ((float) $usec * 100000));
        $validchars[1] = "0123456789abcdefghijklmnopqrstuvwxyz";
        $validchars[2] = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        //$validchars[3]="0123456789_!@#$%&*()-=+/abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_!@#$%&*()-=+/";
        $random_filename = "";
        $counter = 0;
        while ($counter < $length) {
            $actChar = substr($validchars[$level], rand(0, strlen($validchars[$level]) - 1), 1);
            if (!strstr($random_filename, $actChar)) {
                $random_filename .= $actChar;
                $counter++;
            }
        }
        return $random_filename;
    }

    //Register Group
    public function GroupRegistration(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Group info if exists
            $scheme_no = $request->input('scheme_no');
            $is_forgot = $request->input('is_forgot');

            //check if agent_no exists
            $user_id = DbHelper::getColumnValue('PortalUsers', 'scheme_no', $scheme_no, 'id');
            if (isset($user_id) && (int) $user_id > 0) {
                if (isset($is_forgot) && $is_forgot == 1) {
                    //change password here
                    /*$password = $this->generateRandomFileName();
                    $table_data = array(
                        "password" => $password
                    );
                    $this->smartlife_db->table('PortalUsers')
                            ->where(array(
                                "agent_no" => $agent_no
                            ))
                            ->update($table_data);
                    $msg = 'Your login credentials across all Agent systems shall be; Agent No: '.$agent_no.' and Password: '.$password;
                
                    $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode',$agent_no,'mobile');
                    if(substr($mobile_no, 0, 1) == '0'){
                        $mobile_no = "233".ltrim($mobile_no, '0');
                    }
                    $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
                
                    $client = new \GuzzleHttp\Client;
                    $smsRequest =  $client->get($url_path);*/

                    $res = array(
                        'success' => true,
                        'msg' => "Group Confirmed Sucessfully",
                    );
                    return $res;
                } else {
                    $res = array(
                        'success' => false,
                        'msg' => "Group is already Registered",
                    );
                    return $res;
                }
            }

            //check if Broker is active..
            /*$isActive = DbHelper::getColumnValue('glifeclientinfo', 'mobile', $mobile_no, 'isActive');
            if (!$isActive) {
                //check if Broker is active..
                $res = array(
                    'success' => false,
                    'msg' => "Broker is inactive",
                );
                return $res;
            }*/

            $mobile_no = DbHelper::getColumnValue('glifeclientinfo', 'PolicyNumber', $scheme_no, 'mobile');
            if (empty($mobile_no)) {
                $res = array(
                    'success' => false,
                    'msg' => "Contact Number not found",
                );
                return $res;
            }
            //check if scheme is inactive
            $isActive = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'StatusCode');
            if ($isActive != '001') {
                //check if Agent is active..
                $res = array(
                    'success' => false,
                    'msg' => "Scheme is inactive",
                );
                return $res;
            }


            $sql = "SELECT p.Id,p.name,p.client_number,p.mobile,p.PolicyNumber,p.email FROM glifeclientinfo p 
                    WHERE p.PolicyNumber='$scheme_no'";
            $Group = DbHelper::getTableRawData($sql);


            if (sizeof($Group) > 0) {
                //2. If true, Insert details and send sms & email with the default password
                //agent_no, password,mobile_no,email,created_on
                $password = $this->generateRandomFileName();
                $table_data = array(
                    'scheme_no' => $scheme_no,
                    'password' => md5($password),
                    'mobile_no' => $Group[0]->mobile,
                    'client_no' => $Group[0]->client_number,
                    'email' => $Group[0]->email,
                    'created_on' => Carbon::now(),
                    'pos_type' => 0,
                    'report_group' => 0,
                    'is_first_time' => 0
                );

                //insert or update..

                $record_id = $this->smartlife_db->table('PortalUsers')->insertGetId($table_data);
                $msg = 'You have been successfully onboarded. Your login credentials across all Group systems shall be; Name: ' . $Group[0]->name . ' and Password: ' . $password;

                $mobile_no = $Group[0]->mobile;

                if (substr($mobile_no, 0, 1) == '0') {
                    $mobile_no = "233" . ltrim($mobile_no, '0');
                }
                $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                $client = new \GuzzleHttp\Client;
                $smsRequest = $client->get($url_path);
            } else {
                //terminate (agent no doesn't exist)
                $res = array(
                    'success' => false,
                    'msg' => "Contact no not Registered as a Group Scheme",
                );
                return $res;
            }
            //3. 
            //health questionnaire
            $res = array(
                'success' => true,
                'user_id' => $record_id,
                'mobile_no' => $mobile_no,
                'password' => $password
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

    //Register Broker
    public function BrokerRegistration(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Agents info if exists
            $mobile_no = $request->input('mobile_no');
            $is_forgot = $request->input('is_forgot');

            //check if agent_no exists
            $user_id = DbHelper::getColumnValue('PortalUsers', 'mobile_no', $mobile_no, 'id');
            if (isset($user_id) && (int) $user_id > 0) {
                if (isset($is_forgot) && $is_forgot == 1) {
                    //change password here
                    /*$password = $this->generateRandomFileName();
                    $table_data = array(
                        "password" => $password
                    );
                    $this->smartlife_db->table('PortalUsers')
                            ->where(array(
                                "agent_no" => $agent_no
                            ))
                            ->update($table_data);
                    $msg = 'Your login credentials across all Agent systems shall be; Agent No: '.$agent_no.' and Password: '.$password;
                
                    $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode',$agent_no,'mobile');
                    if(substr($mobile_no, 0, 1) == '0'){
                        $mobile_no = "233".ltrim($mobile_no, '0');
                    }
                    $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
                
                    $client = new \GuzzleHttp\Client;
                    $smsRequest =  $client->get($url_path);*/

                    $res = array(
                        'success' => true,
                        'msg' => "Broker Confirmed Sucessfully",
                    );
                    return $res;
                } else {
                    $res = array(
                        'success' => false,
                        'msg' => "Broker is already Registered",
                    );
                    return $res;
                }
            }

            //check if contact number exists
            $broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'id');
            if (empty($broker_id)) {
                $res = array(
                    'success' => false,
                    'msg' => "Contact Number not found",
                );
                return $res;
            }

            //check if Broker is active..
            $isActive = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'isActive');
            if (!$isActive) {
                //check if Broker is active..
                $res = array(
                    'success' => false,
                    'msg' => "Broker is inactive",
                );
                return $res;
            }

            

            $sql = "SELECT p.id,p.name,p.Telephone,p.IsActive,p.email FROM Intermediaryinfo p WHERE p.IsActive=1 AND p.Telephone LIKE '$mobile_no'";
            $Broker = DbHelper::getTableRawData($sql);


            if (sizeof($Broker) > 0) {
                //2. If true, Insert details and send sms & email with the default password
                //agent_no, password,mobile_no,email,created_on
                $password = $this->generateRandomFileName();
                $table_data = array(
                    'broker_id' => $Broker[0]->id,
                    'password' => md5($password),
                    'mobile_no' => $Broker[0]->Telephone,
                    'email' => $Broker[0]->email,
                    'created_on' => Carbon::now(),
                    'pos_type' => 0,
                    'report_group' => 0,
                    'is_first_time' => 0
                );

                //insert or update..

                $record_id = $this->smartlife_db->table('PortalUsers')->insertGetId($table_data);
                $msg = 'You have been successfully onboarded. Your login credentials for Name: ' . $Broker[0]->name . ' and Password: ' . $password;

                $mobile_no = "0545412010";//$Broker[0]->Telephone;



                if (substr($mobile_no, 0, 1) == '0') {
                    $mobile_no = "233" . ltrim($mobile_no, '0');
                }
                $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                $client = new \GuzzleHttp\Client;
                $smsRequest = $client->get($url_path);
            } else {
                //terminate (agent no doesn't exist)
                $res = array(
                    'success' => false,
                    'msg' => "Contact no not Registered as a Broker",
                );
                return $res;
            }
            //3. 
            //health questionnaire
            $res = array(
                'success' => true,
                'user_id' => $record_id,
                'mobile_no' => $mobile_no,
                'password' => $password
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

    //Register Agent 
    public function AgentRegistration(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Agents info if exists
            $agent_no = $request->input('agent_no');
            $is_forgot = $request->input('is_forgot');

            //check if agent_no exists
            $user_id = DbHelper::getColumnValue('PortalUsers', 'agent_no', $agent_no, 'id');
            if (isset($user_id) && (int) $user_id > 0) {
                if (isset($is_forgot) && $is_forgot == 1) {
                    //change password here
                    /*$password = $this->generateRandomFileName();
                    $table_data = array(
                        "password" => $password
                    );
                    $this->smartlife_db->table('PortalUsers')
                            ->where(array(
                                "agent_no" => $agent_no
                            ))
                            ->update($table_data);
                    $msg = 'Your login credentials across all Agent systems shall be; Agent No: '.$agent_no.' and Password: '.$password;
                
                    $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode',$agent_no,'mobile');
                    if(substr($mobile_no, 0, 1) == '0'){
                        $mobile_no = "233".ltrim($mobile_no, '0');
                    }
                    $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=".$msg."&GSM=".$mobile_no;
                
                    $client = new \GuzzleHttp\Client;
                    $smsRequest =  $client->get($url_path);*/

                    $res = array(
                        'success' => true,
                        'msg' => "Agent Confirmed Sucessfully",
                    );
                    return $res;
                } else {
                    $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'mobile');
                    $msg = 'You are already Registered. Access forgot Password on Mproposal / Agents Portal ';
                    if (substr($mobile_no, 0, 1) == '0') {
                        $mobile_no = "233" . ltrim($mobile_no, '0');
                    }
                    $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                    $client = new \GuzzleHttp\Client;
                    $smsRequest = $client->get($url_path);
                    $res = array(
                        'success' => false,
                        'msg' => "Agent is already Registered",
                    );
                    return $res;
                }
            }

            //check if Agent is active..
            $isActive = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'IsActive');
            if (!$isActive) {
                //check if Agent is active..
                $res = array(
                    'success' => false,
                    'msg' => "Agent is inactive",
                );
                return $res;
            }

            $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'mobile');
            if (empty($mobile_no)) {
                $res = array(
                    'success' => false,
                    'msg' => "Share your Mobile Number with the Administrator",
                );
                return $res;
            }

            $sql = "SELECT p.AgentNoCode,p.mobile,p.Ismanager,p.BusinessChannel,p.IsActive,p.Emailaddress FROM agents_info p WHERE p.IsActive=1 AND AgentNoCode='$agent_no'";
            $Agent = DbHelper::getTableRawData($sql);


            if (sizeof($Agent) > 0) {
                //2. If true, Insert details and send sms & email with the default password
                //agent_no, password,mobile_no,email,created_on
                $password = $this->generateRandomFileName();
                $table_data = array(
                    'agent_no' => $Agent[0]->AgentNoCode,
                    'password' => md5($password),
                    'mobile_no' => $Agent[0]->mobile,
                    'email' => $Agent[0]->Emailaddress,
                    'created_on' => Carbon::now(),
                    'pos_type' => 0,
                    'report_group' => 0,
                    'is_first_time' => 0
                );

                //insert or update..

                $record_id = $this->smartlife_db->table('PortalUsers')->insertGetId($table_data);
                $msg = 'You have been successfully onboarded as a Glico Life agent. Your login credentials across all Agent systems shall be; Agent No: ' . $Agent[0]->AgentNoCode . ' and Password: ' . $password;

                $mobile_no = $Agent[0]->mobile;



                if (substr($mobile_no, 0, 1) == '0') {
                    $mobile_no = "233" . ltrim($mobile_no, '0');
                }
                $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                $client = new \GuzzleHttp\Client;
                $smsRequest = $client->get($url_path);
            } else {
                //terminate (agent no doesn't exist)
                $res = array(
                    'success' => false,
                    'msg' => "Provide a valid Agent no or the Agent is deactivated",
                );
                return $res;
            }
            //3. 
            //health questionnaire
            $res = array(
                'success' => true,
                'user_id' => $record_id,
                'mobile_no' => $mobile_no,
                'password' => $password
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
    //add the forgort password logid
    //TODO..send OTP to the nigha..
    public function SendAgentOTP(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $agent_no = $request->input('agent_no');
            //check if agent_no exists
            $agents_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            if (isset($agents_id) && (int) $agents_id > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'IsActive');
                if (!$isActive) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Agent is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Agent does not exist",
                );
                return $res;
            }


            $msg = "OTP: ";
            $mobile_no = DbHelper::getColumnValue('agents_info', 'id', $agents_id, 'mobile');
            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }

            $otp = mt_rand(1000, 9999);
            $msg .= "\nKindly provide security code: " . $otp . " to the Agent";
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);

            $res = array(
                'success' => true,
                'otp' => $otp
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

    //Group OTP
    public function SendGroupOTP(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $scheme_no = $request->input('scheme_no');
            //check if agent_no exists
            $scheme_id = DbHelper::getColumnValue('glifeclientinfo', 'PolicyNumber', $scheme_no, 'Id');
            if (isset($scheme_id) && (int) $scheme_id > 0) {
                //check if Agent is active..
                /*$isActive = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'IsActive');
                if (!$isActive) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Agent is inactive",
                    );
                    return $res;
                }*/
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Scheme No does not exist",
                );
                return $res;
            }


            $msg = "OTP: ";
            $mobile_no = DbHelper::getColumnValue('glifeclientinfo', 'Id', $scheme_id, 'mobile');
            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }

            $otp = mt_rand(1000, 9999);
            $msg .= "\nsecurity code: " . $otp;
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);

            $res = array(
                'success' => true,
                'otp' => $otp
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

    //Client OTP
    public function SendClientOTP(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $policy_no_mobile_no = $request->input('policy_no_mobile_no');
            
            //check if client exists
            if (isset($policy_no_mobile_no)) {
                //use policy
                $client_no = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no_mobile_no, 'client_number');
                if (!isset($client_no)) {
                    $client_no = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no_mobile_no, 'Client');
                    //$client_no = DbHelper::getColumnValue('clientinfo', 'client_number',$clientId,'ClientNumber');
                }

                //use mobile number..
                if(empty($client_no)){
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                }
                //check if start is 0 or  2
                if (empty($client_no)) {
                    if (substr($policy_no_mobile_no, 0, 1) == '0') {
                        $policy_no_mobile_no = "233" . ltrim($policy_no_mobile_no, '0');
                    }
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                }
                if (empty($client_no)) {
                    if (substr($policy_no_mobile_no, 0, 1) === '2') {
                        // Remove the first 3 characters and replace them with '0'
                        $policy_no_mobile_no = '0' . substr($policy_no_mobile_no, 3);
                    }
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                }
                
            }
            if (empty($client_no)) {
                $res = array(
                    'success' => false,
                    'msg' => "Mobile No/Policy No Not Registered. Contact GLICO to register",
                );
                return $res;
            } 

            //check if client has an account
            /*$id = DbHelper::getColumnValue('PortalUsers', 'client_no', $client_no, 'id');
            if (empty($id)) {
                $res = array(
                    'success' => false,
                    'msg' => "Client is not Registered. Kindly Register first",
                );
                return $res;
            }*/

            $msg = "OTP: ";
            $mobile_no = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'mobile');
            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }

            $otp = mt_rand(1000, 9999);
            $msg .= "\nsecurity code: " . $otp;
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);

            $res = array(
                'success' => true,
                'otp' => $otp,
                'mobile_no' => $mobile_no
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

    //Broker OTP
    public function SendBrokerOTP(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Broker is active..
            $mobile_no = $request->input('mobile_no');
            //check if agent_no exists
            $broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'id');
            if (isset($broker_id) && (int) $broker_id > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('Intermediaryinfo', 'id', $broker_id, 'IsActive');
                if (!$isActive) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Broker is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Broker does not exist",
                );
                return $res;
            }


            $msg = "OTP: ";
            //$mobile_no = DbHelper::getColumnValue('agents_info', 'id', $broker_id, 'mobile');
            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }

            $otp = mt_rand(1000, 9999);
            $msg .= "\nSecurity code: " . $otp;
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);

            $res = array(
                'success' => true,
                'otp' => $otp
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

    //RestAgentPassword
    public function ResetAgentPassword(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $agent_no = $request->input('agent_no');
            //$password = md5($request->input('password'));

            //check if agent_no exists
            $agents_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            $mobile_no = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'mobile');
            if (isset($agents_id) && (int) $agents_id > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'IsActive');
                if (!$isActive) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Agent is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Agent does not exist",
                );
                return $res;
            }

            //2. check if agent_no exists in PortalUsers
            $agents_id = DbHelper::getColumnValue('PortalUsers', 'agent_no', $agent_no, 'id');
            if (isset($agents_id) && (int) $agents_id > 0) {
                //do nothing
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Agent is not registered to use Mproposal",
                );
                return $res;
            }

            //3. update Agents password
            $password = $this->generateRandomFileName();
            $table_data = array(
                "password" => md5($password)
            );
            $this->smartlife_db->table('PortalUsers')
                ->where(
                    array(
                        "agent_no" => $agent_no
                    )
                )
                ->update($table_data);


            $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');
            $name = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'name');

            $msg = 'Your login credentials across all Agent systems shall be; Agent No: ' . $agent_no . ' and Password: ' . $password;

            if (substr($mobile_no, 0, 1) == '0') {
                $mobile_no = "233" . ltrim($mobile_no, '0');
            }
            $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

            $client = new \GuzzleHttp\Client;
            $smsRequest = $client->get($url_path);

            $res = array(
                'success' => true,
                'agent_no' => $agent_no,
                'BusinessChannel' => $BusinessChannel,
                'name' => $name,
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

    //if first time login, then, changed password - isfirsttime
    public function ChangePassword(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $column_key = "agent_no";
            $column_val = "";
            $agent_no = $request->input('agent_no');
            if(isset($agent_no)) $column_val = $agent_no;
            $mobile_no = $request->input('mobile_no');
            if(isset($mobile_no)) $column_val = $mobile_no;
            $scheme_no = $request->input('scheme_no');
            if(isset($scheme_no)) $column_val = $scheme_no;
            $n = $request->input('n');

            $policy_no_mobile_no = $request->input('policy_no_mobile_no');
            //do a search to fetch the mobile_no
            if(isset($policy_no_mobile_no) && !empty($policy_no_mobile_no)){
                $mobile_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'mobile');
                if(!isset($mobile_no)){
                    $tmp_policy_no_mobile_no = $policy_no_mobile_no;
                    if (substr($tmp_policy_no_mobile_no, 0, 1) == '2') {
                        $tmp_policy_no_mobile_no = "0" . substr($tmp_policy_no_mobile_no, 3);
                    }
                    $mobile_no = DbHelper::getColumnValue('clientinfo', 'mobile', $tmp_policy_no_mobile_no, 'mobile');
                    if(isset($mobile_no) && !empty($mobile_no)){
                        $policy_no_mobile_no = $tmp_policy_no_mobile_no;
                    }
                }
                if(!isset($mobile_no)){
                    //fetch mobile no from policy no
                    //life
                    $client_no = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no_mobile_no, 'client_number');
                    if(!isset($client_no)){
                        $client_no = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no_mobile_no, 'Client');
                    }
                    $mobile_no = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'mobile');
                }
                $column_key = "mobile_no";
                $column_val = $mobile_no;
            }/*else{
                //get client number
                $broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'id');
                $column_key = "broker_id";
                $column_val = $broker_id;
            }*/

            /*if(isset($n) && $n == 1 && isset($mobile_no)){
                //get client number
                $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $mobile_no, 'client_number');
                $column_key = "client_no";
                $column_val = $client_no;
            } else if(!isset($is_client) && isset($mobile_no)){
                //get client number
                $broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'id');
                $column_key = "broker_id";
                $column_val = $broker_id;
            }*/


            $password = md5($request->input('password'));

            //2. update Agents password
            $table_data = array(
                "password" => $password
            );
            $this->smartlife_db->table('PortalUsers')
                ->where(
                    array(
                        $column_key => $column_val
                    )
                )
                ->update($table_data);
            /*echo $column_key;
            echo $column_val;
            echo $password;
            exit();
            
            
            // Retrieve the executed SQL query
            $query = \DB::getQueryLog();
            $lastQuery = end($query);
            echo $sql = $lastQuery['query'];
            exit();*/


            //$BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');
            //$name = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'name');

            $res = array(
                'success' => true,
                'msg' => "Password successfully Changed"
                //'agent_no' => $agent_no,
                //'BusinessChannel' => $BusinessChannel,
                //'name' => $name,
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

    //login Agent (know whether: micro, life, bancassurance, manager)
    public function AgentLogin(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Agent is active..
            $agent_no = $request->input('agent_no');
            $password = md5($request->input('password'));

            //check if agent_no exists
            $agents_id = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'id');
            if (isset($agents_id) && (int) $agents_id > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'IsActive');
                if (!$isActive) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Agent is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Agent does not exist",
                );
                return $res;
            }

            //2. Login with agent_no and password
            $sql = "SELECT * FROM PortalUsers p WHERE p.agent_no='$agent_no' AND p.password='$password'";
         
            $Agent = DbHelper::getTableRawData($sql);


            if (sizeof($Agent) > 0) {
                //get the business Channel of Agent
                $BusinessChannel = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'BusinessChannel');
                $name = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'name');
                $IsManager = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'Ismanager');
                $unitId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'UnitName');
                $office = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $unitId, 'description');
                $positionId = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'CurrentManagerLevel');
                $Position = DbHelper::getColumnValue('ManagerPromotionLevel', 'id', $positionId, 'description');

                ///if position is sector manager..
                //assign unitId to sectorId and office to sector name..
                if($positionId == "7"){
                    $BranchId = DbHelper::getColumnValue('AgentsunitsInfo', 'id', $unitId, 'AgentsBranchIdKey');
                    $unitId = DbHelper::getColumnValue('AgentsBranchInfo', 'id', $BranchId, 'AgentsRegionIdKey');
                    $office = DbHelper::getColumnValue('AgentsRegionInfo', 'id', $unitId, 'Description');
                }

            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Wrong Password",
                );
                return $res;
            }

            //3. return, agentsChannel 

            //fetch the passport photo..
            $photo = '';
            $photo_binaray = DbHelper::getColumnValue('agents_info', 'AgentNoCode', $agent_no, 'photo');
            if(isset($photo)){
                $photo = base64_encode($photo_binaray);
            }

            $res = array(
                'success' => true,
                'agent_no' => $agent_no,
                'BusinessChannel' => $BusinessChannel,
                'PositionId' => $positionId,
                'Position' => $Position,
                'Office' => $office,
                'IsManager' => $IsManager,
                'name' => $name,
                'photo' => $photo
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

    //group login
    public function GroupLogin(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Client is active..
            $scheme_no = $request->input('scheme_no');
            $password = md5($request->input('password'));

            //check if agent_no exists
            $schemeId = DbHelper::getColumnValue('polschemeinfo', 'policy_no', $scheme_no, 'schemeID');
            if (isset($schemeId) && (int) $schemeId > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('polschemeinfo', 'schemeID', $schemeId, 'StatusCode');
                if ($isActive != '001') {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Scheme is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Scheme does not exist",
                );
                return $res;
            }

            //2. Login with member_no and password
            $sql = "SELECT * FROM PortalUsers p WHERE p.scheme_no='$scheme_no' AND p.password='$password'";
            $Scheme = DbHelper::getTableRawData($sql);


            if (sizeof($Scheme) > 0) {
                //get the name of the scheme
                $clientId = DbHelper::getColumnValue('polschemeinfo', 'schemeID', $schemeId, 'ClientNumber');
                $name = DbHelper::getColumnValue('glifeclientinfo', 'Id', $clientId, 'name');
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Wrong Password",
                );
                return $res;
            }


            $res = array(
                'success' => true,
                'scheme_no' => $scheme_no,
                'scheme_name' => $name,
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


    //broker login
    public function BrokerLogin(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. check if Broker is active..
            $mobile_no = $request->input('mobile_no');
            $password = md5($request->input('password'));

            //check if Broker exists
            $broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'Telephone', $mobile_no, 'id');
            if (isset($broker_id) && (int) $broker_id > 0) {
                //check if Agent is active..
                $isActive = DbHelper::getColumnValue('Intermediaryinfo', 'id', $broker_id, 'status');
                if ((int) $isActive != 1) {
                    //check if Agent is active..
                    $res = array(
                        'success' => false,
                        'msg' => "Broker is inactive",
                    );
                    return $res;
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Broker does not exist",
                );
                return $res;
            }

            //2. Login with member_no and password
            $sql = "SELECT * FROM PortalUsers p WHERE p.mobile_no='$mobile_no' AND p.password='$password'";
            $Broker = DbHelper::getTableRawData($sql);


            if (sizeof($Broker) > 0) {
                //get the name of the scheme
                //$broker_id = DbHelper::getColumnValue('Intermediaryinfo', 'schemeID',$schemeId,'ClientNumber');
                $broker_name = DbHelper::getColumnValue('Intermediaryinfo', 'id', $broker_id, 'name');
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Wrong Password",
                );
                return $res;
            }


            $res = array(
                'success' => true,
                'broker_id' => $broker_id,
                'broker_name' => $broker_name,
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

    //client Registration
    public function ClientRegistration(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Check if Client exists(policy_no & mobile_no)
            //
            $policy_no_mobile_no = $request->input('policy_no_mobile_no');
            //$policy_no = $request->input('policy_no');
            //$mobile_no = $request->input('mobile_no');
            $policy_no = "";
            $mobile_no = "";
            $client_no = "";
            $is_policy = false;
            $is_mobile_no = false;

            //check if client exists
            if (isset($policy_no_mobile_no)) {
                //get client_number
                $client_no = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no_mobile_no, 'client_number');
                if (!isset($client_no)) {
                    $client_no = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no_mobile_no, 'Client');
                    //$client_no = DbHelper::getColumnValue('clientinfo', 'client_number',$clientId,'ClientNumber');
                }
                $user_id = DbHelper::getColumnValue('PortalUsers', 'client_no', $client_no, 'id');
            }

            if (empty($client_no)) {
                $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                if(!empty($client_no)){
                    $is_mobile_no = true;
                }else{
                    //check if start is 0 or  2
                    if (empty($client_no)) {
                        if (substr($policy_no_mobile_no, 0, 1) == '0') {
                            $policy_no_mobile_no = "233" . ltrim($policy_no_mobile_no, '0');
                        }
                        $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                        if(!empty($client_no)) $is_mobile_no = true;
                    }
                    if (empty($client_no)) {
                        if (substr($policy_no_mobile_no, 0, 1) === '2') {
                            // Remove the first 3 characters and replace them with '0'
                            $policy_no_mobile_no = '0' . substr($policy_no_mobile_no, 3);
                        }
                        $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $policy_no_mobile_no, 'client_number');
                        if(!empty($client_no)) $is_mobile_no = true;
                    }
                }
                $user_id = DbHelper::getColumnValue('PortalUsers', 'client_no', $client_no, 'id');
            }else{
                $is_policy = true;
            } 
            /*else {
                $res = array(
                    'success' => false,
                    'message' => "Fill Policy Number or Mobile Number",
                );
                return $res;
            }*/
            if(empty($client_no)){

                $res = array(
                    'success' => false,
                    'message' => "Client's Policy Number / Mobile Number is Not Found",
                );
                return $res;
            }

            if (isset($user_id) && (int) $user_id > 0) {
                $res = array(
                    'success' => false,
                    'id' => $user_id,
                    'message' => "Client is already Registered",
                );
                return $res;
            }

            //check if there is a mobile no
            $mobile_no = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'mobile');
            //exit();
            if(empty($mobile_no)){
                $res = array(
                    'success' => false,
                    'message' => "Client's Mobile Number is not registered. Kindly Contact GLICO to Register your mobile number"
                );
                return $res;
            }



            $Client = array();
            if (isset($policy_no_mobile_no)) {
                $sql = "SELECT p.policy_no,d.client_number,d.mobile,d.email FROM polinfo p INNER JOIN clientinfo d ON d.client_number=p.client_number WHERE d.mobile='$policy_no_mobile_no' ";
                $Client = DbHelper::getTableRawData($sql);
                if (sizeof($Client) == 0) {
                    $sql = "SELECT p.PolicyNumber AS policy_no,d.client_number,d.mobile,d.email FROM MicroPolicyInfo p 
                    INNER JOIN clientinfo d ON d.client_number=p.Client WHERE d.mobile='$policy_no_mobile_no' ";
                    $Client = DbHelper::getTableRawData($sql);
                }
            }

            if (sizeof($Client) == 0) {
                $sql = "SELECT p.policy_no,d.client_number,d.mobile,d.email FROM polinfo p INNER JOIN clientinfo d ON d.client_number=p.client_number WHERE p.policy_no='$policy_no_mobile_no' ";
                $Client = DbHelper::getTableRawData($sql);
                if (sizeof($Client) == 0) {
                    //it could be micro made by the genius himself
                    $sql = "SELECT p.PolicyNumber AS policy_no,d.client_number,d.mobile,d.email FROM MicroPolicyInfo p 
                    INNER JOIN clientinfo d ON d.client_number=p.Client WHERE p.PolicyNumber='$policy_no_mobile_no' ";
                    $Client = DbHelper::getTableRawData($sql);
                }
            }

            if (sizeof($Client) == 0) {
                $res = array(
                    'success' => false,
                    'client_no' => $client_no,
                    'message' => "Client Does not exist",
                );
                return $res;
            }


            if (sizeof($Client) > 0) {
                //2. If true, Insert details and send sms & email with the default password
                //agent_no, password,mobile_no,email,created_on
                $password = $this->generateRandomFileName();
                $client_no = $Client[0]->client_number;
                $mobile_no = $Client[0]->mobile;
                $table_data = array(
                    'client_no' => $Client[0]->client_number,
                    'password' => md5($password),
                    'mobile_no' => $Client[0]->mobile,
                    'email' => $Client[0]->email,
                    'report_group' => 0,
                    'pos_type' => 0,
                    'created_on' => Carbon::now()
                );
                $record_id = $this->smartlife_db->table('PortalUsers')->insertGetId($table_data);

                $msg = 'Your login credentials across all GLICO apps and portals shall be; Password: ' . $password;

                if (substr($mobile_no, 0, 1) == '0') {
                    $mobile_no = "233" . ltrim($mobile_no, '0');
                }
                $url_path = "http://193.105.74.59/api/sendsms/plain?user=Glico2018&password=glicosmpp&sender=GLICO&SMSText=" . $msg . "&GSM=" . $mobile_no;

                $client = new \GuzzleHttp\Client;
                $smsRequest = $client->get($url_path);

            } else {
                //terminate (agent no doesn't exist)
                $res = array(
                    'success' => false,
                    'message' => "Provide a valid Policy No or registered Mobile No",
                );
                return $res;
            }
            //3. 
            //health questionnaire
            $res = array(
                'success' => true,
                'user_id' => $record_id,
                'client_no' => $client_no,
                'mobile_no' => $mobile_no,
                'password' => $password
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

    //client Login
    public function ClientLogin(Request $request)
    {
        try {
            $res = array();
            //TODO
            $mobile_no = $request->input('policy_no_mobile_no');
            $policy_no = $request->input('policy_no_mobile_no');
            $password = md5($request->input('password'));
            $client_no = null;

            //get the client_no
            if (isset($policy_no)) {
                //get client_number
                $client_no = DbHelper::getColumnValue('polinfo', 'policy_no', $policy_no, 'client_number');
                if (!isset($client_no)) {
                    $client_no = DbHelper::getColumnValue('MicroPolicyInfo', 'PolicyNumber', $policy_no, 'Client');
                }
                if (!isset($client_no)) {
                    $client_no = DbHelper::getColumnValue('clientinfo', 'mobile', $mobile_no, 'client_number');
                    $user_id = DbHelper::getColumnValue('PortalUsers', 'client_no', $client_no, 'id');
                }else{
                    $user_id = DbHelper::getColumnValue('PortalUsers', 'client_no', $client_no, 'id');              
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Fill Policy Number or Mobile Number",
                );
                return $res;
            }

            if (!isset($client_no)) {
                //could be micro: The genius himself
                $res = array(
                    'success' => false,
                    'msg' => "InValid Policy No or Mobile No is not registered in the system",
                );
                return $res;
            }

            //2. Login with agent_no and password
            $sql = "SELECT * FROM PortalUsers p WHERE p.client_no='$client_no' AND p.password='$password'  AND p.client_no IS NOT NULL";
            $Client = DbHelper::getTableRawData($sql);
            if (sizeof($Client) == 0) {
                $sql = "SELECT * FROM PortalUsers p WHERE p.mobile_no='$mobile_no' AND p.password='$password'  AND p.client_no IS NOT NULL";
                $Client = DbHelper::getTableRawData($sql);
                if (sizeof($Client) == 0) {
                    if (substr($mobile_no, 0, 1) == '0') {
                        $mobile_no = "233" . ltrim($mobile_no, '0');
                    }
                    //echo $mobile_no;
                    $sql = "SELECT * FROM PortalUsers p WHERE p.mobile_no='$mobile_no' AND p.password='$password'  AND p.client_no IS NOT NULL";
                    $Client = DbHelper::getTableRawData($sql);
                }
            }


            if (sizeof($Client) > 0) {
                $client_no = $Client[0]->client_no;
                //get the name of Client
                $client_name = DbHelper::getColumnValue('clientinfo', 'client_number', $client_no, 'name');
                if (!isset($client_name)) {
                    //then its micro from the genius himself
                    $client_name = DbHelper::getColumnValue('MicroClientInfo', 'ClientNumber', $client_no, 'Name');
                }
            } else {
                $res = array(
                    'success' => false,
                    'msg' => "Wrong Password",
                );
                return $res;
            }

            //3. return, agentsChannel 

            $res = array(
                'success' => true,
                'client_no' => $client_no,
                'client_name' => $client_name
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

    //POS login
    public function POSLogin(Request $request)
    {
        try {
            $res = array();
            //TODO
            $username = $request->input('username');
            $pass = $request->input('password');
            $password = md5($request->input('password'));

            //2. Login with agent_no and password
            $sql = "SELECT * FROM PortalUsers p WHERE p.Username='$username' AND p.Password='$password'";
            $POS = DbHelper::getTableRawData($sql);

            if (sizeof($POS) == 0) {

                //http://192.168.1.248:85/api/Report/Authentication?Username=User&password=Delivered%2C15
                //TODO - If not successfull then, use Dante's endpoint first
                $url_path = "http://192.168.1.248:85/api/Report/Authentication?Username=" . $username . "&password=" . $pass;

                $client = new \GuzzleHttp\Client;
                $response = $client->get($url_path);

                if ($response->getStatusCode() == 200) {
                    $is_correct = $response->getBody()->getContents();
                    //echo $is_correct;
                    // Process the retrieved data as needed
                    if ($is_correct == "true") {
                        //echo "Its here";
                        $POSID = DbHelper::getColumnValue('PortalUsers', 'Username', $username, 'id');
                        // if(!isset($POSID)){
                        //     $POSID = DbHelper::getColumnValue('PortalUsers', 'agent_no', $username, 'id');
                        // }
                        $POSTYPE = DbHelper::getColumnValue('PortalUsers', 'Username', $username, 'PosType');
                        if(!isset($POSTYPE)){
                            $Module = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'Module');
                            if($Module == "MI"){
                                $POSTYPE = 2;
                            }else if($Module == "IL"){
                                $POSTYPE = 1;
                            }
                        }

                        $IsCreditLifeUser = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'IsCreditLifeUser');
                        $isMicroManager = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'isMicroManager');
                        $res = array(
                            'success' => true,
                            'user_id' => $POSID,
                            'pos_type' => $POSTYPE,
                            'IsCreditLifeUser' => $IsCreditLifeUser,
                            'isMicroManager' => $isMicroManager
                        );
                        return $res;
                    } else {
                        //get the name of Client
                        $res = array(
                            'success' => false,
                            'msg' => "Wrong Password",
                        );
                        return $res;
                    }
                } else {
                    //get the name of Client
                    
                    $res = array(
                        'success' => false,
                        'msg' => "Wrong Password",
                    );
                    return $res;
                }
            }

            $IsCreditLifeUser = DbHelper::getColumnValue('PermissionPolicyUser', 'UserName', $username, 'IsCreditLifeUser');
            $res = array(
                'success' => true,
                'user_id' => $POS[0]->id,
                'pos_type' => $POS[0]->PosType,
                'IsCreditLifeUser' => $IsCreditLifeUser
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

    //POS Registration
    public function POSRegistration(Request $request)
    {
        try {
            $res = array();
            //TODO
            //1. Search in Agents info if exists
            $username = $request->input('username');
            $mobile_no = $request->input('mobile_no');
            $email = $request->input('email');
            $password = $request->input('password');
            $report_group = $request->input('report_group');
            $pos_type = $request->input('pos_type');
            $table_data = array(
                'username' => $username,
                'password' => md5($password),
                'mobile_no' => $mobile_no,
                'email' => $email,
                'report_group' => $report_group,
                'pos_type' => $pos_type,
                'created_on' => Carbon::now()
            );
            //check if username exists
            $user_id = DbHelper::getColumnValue('PortalUsers', 'username', $username, 'id');
            if (isset($user_id) && (int) $user_id > 0) {
                //update
                $this->smartlife_db->table('PortalUsers')
                    ->where(
                        array(
                            "id" => $user_id
                        )
                    )
                    ->update($table_data);
            } else {
                //insert
                $user_id = $this->smartlife_db->table('PortalUsers')->insertGetId($table_data);
            }

            $res = array(
                'success' => true,
                'user_id' => $user_id,
                'password' => $password
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