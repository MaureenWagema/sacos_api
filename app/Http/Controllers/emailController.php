<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\DbHelper;
use App\Helpers\EmailHelper;
use Carbon\Carbon;
use GuzzleHttp\Client;

class emailController extends Controller
{
    public function sendLink(Request $request)
    {
        try{
                //get the record data
                $name = $request->input('name');
                $email_to = $request->input('email');
                //$email_content = $request->input('email_content');
                //$email_subject = $request->input('email_subject');
                //$attachement = $request->input('attachement');
                $attachement = null;
                $record_id = $request->input('record_id');
                $plan_code = $request->input('plan_code');
                $plan_name = DbHelper::getColumnValue('planinfo', 'plan_code',$plan_code,'description');

                $email_subject = $plan_name ." APPLICATION FORM";



                $email_content = "<p>Thank you for choosing GLICO to be your Insurance provider</p>";
                //$email_content .= "<p>To fill the Mortage Application form  <a href='http://197.159.142.171:90/smartlife/#application_form/1?item=%7B%22plan_code%22%3A%229%22%2C%22rd_form%22%3A0%7D&n=1'>click here</a></p>";

                $email_content .= "<p>To fill the ".$plan_name ." Application form  <a href='http://197.159.142.171:90/smartlife/#application_form/1?item=%7B%22plan_code%22%3A%22".$plan_code."%22%2C%22id%22%3A%22".$record_id."%22%2C%22rd_form%22%3Afalse%7D&n=1'>click here</a></p>";
                $email_content .= "<p>To get the most out of your online application, 
                make sure you complete the form as well as your contact preferences.</p>";
                $email_content .= "<p>Regards,</p><p>The GLICO Credit Life Team</p>";

                
                $msg = EmailHelper::sendMailNotification($name, $email_to,$email_subject,
                "<br/><br/>".$email_content,'','',$attachement,'','', '');
                
                
                $res = array(
                    'success' => true,
                    'message' => 'Email Sent Successfully!!'
                );

            }catch (\Exception $exception) {
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
    public function sendEmail(Request $request)
    {
        try{
                //get the record data
                $name = $request->input('name');
                $email_to = $request->input('email_to');
                $email_content = $request->input('email_content');
                $email_subject = $request->input('email_subject');
                $attachement = $request->input('attachement');

                $email_subject = "MORTGAGE APPLICATION FORM";


                $email_content = "<p>Thank you for choosing GLICO Mortgage Cover.</p>";
                $email_content .= "<p>To fill the Mortage Application form  <a href='http://197.159.142.171:90/smartlife/#application_form/1?item=%7B%22plan_code%22%3A%229%22%2C%22rd_form%22%3A0%7D&n=1'>click here</a></p>";
                $email_content .= "<p>To get the most out of your online application, 
                make sure you complete the form as well as your contact preferences.</p>";
                $email_content .= "<p>Regards,</p><p>The GLICO Credit Life Team</p>";

                
                $msg = EmailHelper::sendMailNotification($name, $email_to,$email_subject,
                "<br/><br/>".$email_content,'','',$attachement,'','', '');
                //print_r($msg);
                //exit();
                
                $res = array(
                    'success' => true,
                    'message' => 'Email Sent Successfully!!'
                );

            }catch (\Exception $exception) {
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
    public function smsPOST(Request $request)
    {
        try{
                //get the record data
                /*$name = $request->input('name');
                $email_to = $request->input('email_to');
                $email_content = $request->input('email_content');
                $email_subject = $request->input('email_subject');
                $attachement = $request->input('attachement');*/

                /* <a href="https://www.google.com">hyperlink</a> */

                $url = "http://193.105.74.59/api/sendsms/xml";

                // XML-formatted data
                $xmlString =
                "<SMS>
                    <authentification>
                        <username>Glico2018</username>
                        <password>glicosmpp</password>
                    </authentification>
                    <message>
                        <sender>GLICO</sender>
                        <text>This is some text with a in the middle of it</text>
                    </message>
                    <recipients>
                        <gsm messageId=\"1000\">233545412010</gsm>
                    </recipients>
                </SMS>";

                // previously formatted XML data becomes value of "XML" POST variable
                $xml = "XML=" . urlencode($xmlString);


                
                //$msg = EmailHelper::sendMailNotification($name, $email_to,$email_subject,$email_content,'','',$attachement,'','', '');
                //print_r($msg);
                //exit();

                $client = new Client();


                // Define the request body data
                $data = [
                    'key1' => 'value1',
                    'key2' => 'value2'
                ];

                // Send the POST request with the client and request options
                $response = $client->request('POST', $url, [
                    'body' => $xml,
                    'headers' => [
                        'Content-Type' => 'application/xml'
                    ]
                ]);

                // Get the response body content
                $body = $response->getBody()->getContents();

                // Decode the response JSON string into a PHP object
                $result = json_decode($body);

                // Do something with the result

                
                $res = array(
                    'success' => true,
                    'result' => $result,
                    'message' => 'POST SMS Sent Successfully!!'
                );

            }catch (\Exception $exception) {
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
