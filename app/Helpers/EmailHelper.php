<?php
/**
 * Created by PhpStorm.
 * Time: 2:43 PM
 */

namespace App\Helpers;

use App\Jobs\GenericSendEmailJob;
use App\Jobs\GenericAttachmentSendEmailJob;
use App\Jobs\GenericMultipleAttachmentsSendEmailJob;
use App\Mail\AccountActivation;
use App\Mail\GenericPlainMail;
use App\Mail\GenericAttachmentMail;
use App\Mail\GenericMultipleAttachmentsMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotPassword;
use Illuminate\Support\Carbon;

class EmailHelper
{

    /*static function getEmailTemplateInfo($template_id, $vars)
    {
        $template_info = $this->smartlife_db->table('par_email_messages_templates')
            ->where('id', $template_id)
            ->first();
        if (is_null($template_info)) {
            $template_info = (object)array(
                'subject' => 'Error',
                'body' => 'Sorry this email was delivered wrongly, kindly ignore.'
            );
        }
        $template_info->subject = strtr($template_info->subject, $vars);
        $template_info->body = strtr($template_info->body, $vars);
        return $template_info;
    }

    static function onlineApplicationNotificationMail($template_id, $email, $vars,$identification_no)
    {
        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
        //email notofications 
        
        //Mail::to($email)->send(new GenericPlainMail($subject, $message));
        $emailJob = (new GenericSendEmailJob($email, $subject, $message))->delay(Carbon::now()->addSeconds(5));
        dispatch($emailJob);
        
        //the details 
        if(validateIsNumeric($identification_no)){
                //insert data 
                $data = array('identification_no'=>$identification_no,
                                'subject'=>$subject,
                                'message'=>$message,
                                'sent_on'=>Carbon::now(),
                                'is_read'=>0);
                                
                insertRecord('wb_appnotification_details', $data, '', 'portal_db');

        }
    }

    static function forgotPasswordEmail($template_id, $email, $link, $vars)
    {
        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
        Mail::to($email)->send(new ForgotPassword($subject, $message, $link));
        if (count(Mail::failures()) > 0) {
            $res = array(
                'success' => false,
                'message' => 'Problem was encountered while sending email. Please try again later!!'
            );
        } else {
            $res = array(
                'success' => true,
                'message' => 'Password reset instructions sent to your email address!!'
            );
        }
        return $res;
    }

    static function accountRegistrationEmail($template_id, $email, $password, $link, $vars)
    {
        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
        Mail::to($email)->send(new AccountActivation($subject, $message, $email, $password, $link));
        if (count(Mail::failures()) > 0) {
            $res = array(
                'success' => false,
                'message' => 'Problem was encountered while sending email. Please try again later!!'
            );
        } else {
            $res = array(
                'success' => true,
                'message' => 'Account registration instructions sent to your email address!!'
            );
        }
        $res = array(
                'success' => true,
                'message' => 'Account registration instructions sent to your email address!!'
            );
        return $res;
    }

    static function applicationInvoiceEmail($template_id, $email, $vars, $report, $attachment_name)
    {
        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
        Mail::to($email)->send(new GenericAttachmentMail($subject, $message, $report, $attachment_name));
       // return Mail::failures();
        return true;
    }

    static function applicationPermitEmail($template_id, $email, $vars, $permit_report, $certificate_report)
    {
        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
        //Mail::to($email)->send(new GenericMultipleAttachmentsMail($subject, $message, $permit_report, $certificate_report));
        $emailJob = (new GenericMultipleAttachmentsSendEmailJob($email, $subject, $message, $permit_report, $certificate_report))->delay(Carbon::now()->addSeconds(5));
        dispatch($emailJob);
    }*/
    static function sendMailNotification($trader_name, $to, $subject, $email_content, $cc, $bcc, $attachement, $attachement_name, $template_id, $vars)
    {
        $from_email = Config('constants.mail_from_address');
        /*if(validateIsNumeric($template_id)){
            $template_info = self::getEmailTemplateInfo($template_id, $vars);
            $subject = $template_info->subject;
            $email_content = $template_info->body;  
        }*/
        $data = array(
            'subject' => $subject,
            'email_content' => $email_content,
            'trader_name' => $trader_name,
            'from_email' => $from_email,
            'to' => $to,
            'title' => $subject
        );
        //cleaning address
        $to = str_replace(' ', '', $to);
        $bcc = str_replace(' ', '', $bcc);
        $cc = str_replace(' ', '', $cc);
        //expode
        if ($to != '') {
            $to = explode(';', $to);
        }
        if ($bcc != '') {
            $bcc = explode(';', $bcc);
        }
        if ($cc != '') {
            $cc = explode(';', $cc);
        }

        //send mail
        Mail::send('emailnotification', $data, function ($message) use ($to, $trader_name, $subject, $cc, $bcc, $attachement, $attachement_name) {
            if ($bcc != '') {
                $message->bcc($bcc, $trader_name)
                    ->subject($subject);
            } else if ($cc) {
                $message->to($to, $trader_name)
                    ->cc($cc)
                    ->subject($subject);
            } else {
                $message->to($to, $trader_name)
                    ->subject($subject);
            }
            if ($attachement != '') {
                $message->attach($attachement, [
                    'as' => $attachement_name . '.pdf',
                    'mime' => 'application/pdf',
                ]);
            }


        });

        //if (Mail::failures()) {
        //$data = array('success'=>false, 'message'=>'Email submission failed, contact system admin for further guidelines');
        //}
        //else{
        $data = array('success' => true, 'message' => 'Email Sent successfully');
        //}
        return $data;
        return true;
    }

    /*static function SendMailQueue($email, $subject, $message){
        $emailJob = (new GenericSendEmailJob($email, $subject, $message))->delay(Carbon::now()->addSeconds(5));
        dispatch($emailJob);
    }

    //indicated mail from
    static function sendMailFromNotification($trader_name, $to,$subject,$email_content,$from, $cc){
        
        $from_email = $from;
        
        $data = array(
            'subject' => $subject,
            'email_content' => $email_content,
            'trader_name' => $trader_name,
            'from_email'=>$from_email,
            'to'=>$to,
            'title'=>$subject
        );
        // //cleaning address
        // if($cc!=''){
        //     $cc = str_replace(' ', '', $cc);
        //     //expode
        //     $cc = explode(',',$cc);
        // }else{
        //     $cc = '';
        // }
        $emailJob = (new GenericSendEmailJob($to, $subject, $email_content, $cc))->delay(Carbon::now()->addSeconds(2));
         dispatch($emailJob);
        //send mail
        // Mail::send('emailnotification', $data, function($message)use ($to,$trader_name,$subject,$cc,$from_email) {
        //     if($cc!=''){
        //         $message->to($to, $trader_name)
        //                 ->cc($cc)
        //                 ->subject($subject);
        //     }
        //     else{
        //         $message->to($to, $trader_name)
        //                 ->subject($subject);
        //     }

        // });
        $data = array('success'=>true, 'message'=>'Email Sent successfully');
        // if (Mail::failures()) {
        //     $data = array('success'=>false, 'message'=>'Email submission failed, contact system admin for further guidelines');
        // }
        // else{
        //     $data = array('success'=>true, 'message'=>'Email Sent successfully');
        // }
        return $data;
    }

   static function sendTemplatedApplicationNotificationEmail($template_id, $email, $vars)
    {

        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;
//dd($message);
        //email notofications job creation
         $emailJob = (new GenericSendEmailJob($email, $subject, $message))->delay(Carbon::now()->addSeconds(2));
         dispatch($emailJob);


    }
//mails for expiry notification
    static function applicationExpiryNotificationMail($template_id, $email, $vars,$applicant_id)
    {

        $template_info = self::getEmailTemplateInfo($template_id, $vars);
        $subject = $template_info->subject;
        $message = $template_info->body;

        //email notofications job creation
         $emailJob = (new GenericSendEmailJob($email, $subject, $message))->delay(Carbon::now()->addSeconds(2));
         dispatch($emailJob);


    }*/
}