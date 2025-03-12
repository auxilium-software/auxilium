<?php

namespace Auxilium\Helpers\Messaging;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Message;

class SMTPUtilities
{
    public PHPMailer $Mailer;

    public function __construct()
    {
        $this->Mailer = new PHPMailer();

        // Settings
        $this->Mailer->IsSMTP();
        $this->Mailer->CharSet = 'UTF-8';

        $this->Mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        $this->Mailer->Host = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["smtp"];    // SMTP server example
        $this->Mailer->SMTPDebug = 0;                     // enables SMTP debug information (for testing)
        $this->Mailer->SMTPAuth = true;                  // enable SMTP authentication
        $this->Mailer->Port = 465;                    // set the SMTP port for the GMAIL server
        $this->Mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->Mailer->Username = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"];            // SMTP account username example
        $this->Mailer->Password = INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["password"];            // SMTP account password example
    }

    public function SetMessage(string $rawRFC822Message)
    {
        //Recipients
        //$mail->setFrom(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"], INSTANCE_BRANDING_NAME);
        //$mail->addAddress('joe@example.net', 'Joe User');     //Add a recipient
        //$mail->addAddress('ellen@example.com');               //Name is optional
        //$mail->addReplyTo('info@example.com', 'Information');
        //$mail->addCC('cc@example.com');
        //$mail->addBCC('bcc@example.com');

        //$this->emailData["recipients"] = [];
        //foreach($this->recipients as &$user) {
        //    if ($user != null) {
        //        if ($user instanceof \Auxilium\User) {
        //            $mail->addAddress($user->getEmailAddress(), $user->getFullName());
        //            array_push($this->emailData["recipients"], $user->getUuid());
        //        } else {
        //            $mail->addAddress($user);
        //        }
        //    }
        //}

        // Content
        //$mail->isHTML(true);                       // Set email format to HTML
        //$mail->Subject = \Auxilium\MicroTemplate::from_packed_template($this->emailData["subject"], $this->emailData["template_properties"]["selected_lang"]);
        //$mail->Body = $content;






        $message = Message::from($rawRFC822Message, false);
        //$message->getHeaderValue(HeaderConsts::FROM)->getEmail()


        $senderName = $message->getHeader(HeaderConsts::FROM)->getPersonName();
        if($senderName)
            $this->Mailer->setFrom(INSTANCE_CREDENTIAL_EMAIL_ACCOUNTS["primary"]["address"], $senderName);
        foreach($message->getHeader(HeaderConsts::TO)->getAddresses() as &$user)
        {
            $this->Mailer->addAddress($user->getEmail(), $user->getName());
        }


        $text = $message->getTextContent();
        $html = $message->getHtmlContent();

        if($html == null)
        {
            $this->Mailer->Body = $text;
        }
        else
        {
            $this->Mailer->isHTML(true);
            $this->Mailer->Body = $html;
        }


        /*
        $mail->Subject = $message->getHeaderValue(\ZBateson\MailMimeParser\Header\HeaderConsts::SUBJECT);
        */

        foreach($message->getAllHeaders() as &$header)
        {
            $headerName = $header->getName();
            switch($headerName)
            {
                default:
                    echo "CH: " . $headerName . ": " . $header->getRawValue() . "\n";
                    $this->Mailer->addCustomHeader($headerName, $header->getRawValue());
                    break;
            }
        }
    }
}
