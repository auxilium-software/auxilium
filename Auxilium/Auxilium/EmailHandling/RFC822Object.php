<?php

namespace Auxilium\EmailHandling;

use auxilium\DatabaseConnection;
use Auxilium\DataObject;
use auxilium\PersistentObject;
use Auxilium\ReadPermissionException;
use Auxilium\Utilities\EncodingTools;
use DOMDocument;
use HTMLPurifier;
use HTMLPurifier_Config;
use PDO;
use PDOException;
use ZBateson\MailMimeParser\Message;

class RFC822Object extends DataObject
{
    protected $message = null;
    protected $attachmentParts = null;
    protected $headers = null;

    public function __construct($objectUuid = null)
    {
        parent::__construct($objectUuid);
    }

    public function __toString()
    {
        return "{" . $this->getUuid() . "}";
    }

    public function getAttachments()
    {
        if($this->attachmentParts == null)
        {
            $this->attachmentParts = [];
            $msg = $this->getMessage();
            if($msg != null)
            {
                $this->attachmentParts = $msg->getAllAttachmentParts();
            }
        }
        return $this->attachmentParts;
    }

    protected function getMessage()
    {
        if($this->message == null)
        {
            $this->message = Message::from($this->getRawData(), false);
        }
        return $this->message;
    }

    public function getAttachmentsMetadata()
    {
        if($this->attachmentParts == null)
        {
            $this->attachmentParts = [];
            $msg = $this->getMessage();
            if($msg != null)
            {
                $this->attachmentParts = $msg->getAllAttachmentParts();
            }
        }
        $output = [];
        foreach($this->attachmentParts as $attachmentPart)
        {
            $repr = [
                "contentId" => $attachmentPart->getContentId(),
                "mimeType" => $attachmentPart->getContentType(),
                "fileName" => $attachmentPart->getFilename(),
                "fileSize" => strlen($attachmentPart->getContent())
            ];
            array_push($output, $repr);
        }
        return $output;
    }

    public function getAttachmentByContentId(string $contentId)
    {
        $msg = $this->getMessage();
        if($msg != null)
        {
            return $msg->getPartByContentId($contentId);
        }
        return null;
    }

    public function getSubject()
    {
        if(isset($this->getHeaders()["subject"]))
        {
            return $this->getHeaders()["subject"];
        }
        return null;
    }

    public function getHeaders()
    {
        if($this->headers == null)
        {
            $this->headers = [];
            foreach($this->getMessage()->getAllHeaders() as $header)
            {
                $parts = $header->getParts();
                if(count($parts) > 1)
                {
                    $res = [];
                    foreach($parts as &$part)
                    {
                        array_push($res, $part->getValue());
                    }
                    $this->headers[strtolower($header->getName())] = $res;
                }
                else
                {
                    if(count($parts) > 0)
                    {
                        $this->headers[strtolower($header->getName())] = $parts[0]->getValue();
                    }
                }
            }
        }
        return $this->headers;
    }

    public function getSenderOrEmail()
    {
        $sender = $this->getSender();
        if($sender == null)
        {
            $sender = $this->getSenderEmailAddress();
        }
        if($sender == null)
        {
            return null;
        }
        return $this->mixedEmailAddressesLookupEmails([$sender])[0];
    }

    public function getSender()
    {
        $senders = $this->getRelatedObjectsOfType("SENDER");
        return end($senders);
    }

    public function getSenderEmailAddress()
    {
        if(isset($this->getHeaders()["from"]))
        {
            return $this->getHeaders()["from"];
        }
        return null;
    }

    protected function mixedEmailAddressesLookupEmails(array $emails, string $field = null)
    {
        $output = [];
        $field = strtolower($field);
        foreach($emails as &$emailAddress)
        {
            if(is_string($emailAddress))
            {
                $bindVariables = [
                    "email_address" => $emailAddress,
                ];
                $sql = "SELECT BinToUuid(user_uuid) AS user_uuid FROM users WHERE email_address=:email_address AND (account_type='WEB_ACCOUNT' OR account_type='VERIFIED_CONTACT')";
                $statement = DatabaseConnection::get_pdo()->prepare($sql);
                try
                {
                    $statement->execute($bindVariables);
                    $user = $statement->fetch(PDO::FETCH_ASSOC);
                    if($user === false)
                    {
                        array_push($output, $emailAddress);
                    }
                    else
                    {
                        $user = PersistentObject::from_uuid($user["user_uuid"]);
                        switch($field)
                        {
                            case "to":
                                $this->addRelationshipUnsafe("RECIPIENT", $user);
                                break;
                            case "from":
                                $this->addRelationshipUnsafe("SENDER", $user);
                                break;
                        }
                        array_push($output, $user);
                    }
                }
                catch(PDOException $e)
                {
                    array_push($output, $emailAddress);
                } // Not the end of the world if this doesn't work, just move on and return the email as a string
            }
            else
            {
                array_push($output, $emailAddress);
            }
        }
        return $output;
    }

    public function getRecipientsOrEmail()
    {
        $recipients = $this->getRecipients();
        $recipientsEmails = [];
        $recipientsEmailsRaw = [];
        $recipientsMixed = [];
        if(isset($this->getHeaders()["to"]))
        {
            $recipientsEmailsRaw = $this->getHeaders()["to"];
            if(!is_array($recipientsEmailsRaw))
            {
                $recipientsEmailsRaw = [$recipientsEmailsRaw];
            }
        }
        foreach($recipients as $recipient)
        {
            try
            {
                array_push($recipientsEmails, $recipient->getEmailAddress());
            }
            catch(ReadPermissionException $e)
            {
            }
        }
        foreach($recipientsEmailsRaw as $address)
        {
            if(!in_array($address, $recipientsEmails))
            {
                array_push($recipientsMixed, $address);
            }
        }
        return $this->mixedEmailAddressesLookupEmails(array_merge($recipientsMixed, $recipients));
    }

    public function getRecipients()
    {
        return $this->getRelatedObjectsOfType("RECIPIENT");
    }

    public function getBccdRecipientsOrEmail()
    {
        $recipientsBcc = [];
        if(isset($this->getHeaders()["bcc"]))
        {
            $recipientsBcc = $this->getHeaders()["bcc"];
            if(!is_array($recipientsBcc))
            {
                $recipientsBcc = [$recipientsBcc];
            }
        }
        return $this->mixedEmailAddressesLookupEmails($recipientsBcc);
    }

    public function getCcdRecipientsOrEmail()
    {
        $recipientsCc = [];
        if(isset($this->getHeaders()["cc"]))
        {
            $recipientsCc = $this->getHeaders()["cc"];
            if(!is_array($recipientsCc))
            {
                $recipientsCc = [$recipientsCc];
            }
        }
        return $this->mixedEmailAddressesLookupEmails($recipientsCc);
    }

    public function getAsTextOnly()
    {
        if($this->getMessage() == null)
        {
            return null;
        }
        $body = $this->getMessage()->getHtmlContent();
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $sanitized = $this->sanitizeHtmlEmail($body, "PRINT_OUT", false);
        if($sanitized == null)
        {
            return null;
        }
        $document->loadHTML($sanitized);
        return str_replace("\n", "\n\n", $document->documentElement->nodeValue);
    }

    private function sanitizeHtmlEmail($body, $linkMode = "INTERVENE", $removeHistory = true)
    {
        if($body == null)
        {
            return null;
        }

        $document = new DOMDocument();

        libxml_use_internal_errors(true);

        // $document->loadHTML(mb_convert_encoding($body, "HTML-ENTITIES", "UTF-8"));
        $document->loadHTML($body);

        $imagesToInsert = [];

        $tags = $document->getElementsByTagName("*");
        for($i = 0; $i < $tags->length; $i++)
        {
            $tag = $tags->item($i);
            $tagMarkedForDeletion = false;
            $tagDestination = $tag->attributes->getNamedItem("href");
            $tagClass = $tag->attributes->getNamedItem("class");
            $tagId = $tag->attributes->getNamedItem("id");

            if($tagDestination != null)
            {
                switch($linkMode)
                {
                    case "PASSTHROUGH":
                    case "STRIP":
                        $tagDestination->nodeValue = "";
                        break;
                    case "PRINT_OUT":
                        $tag->nodeValue = $tag->nodeValue . "[" . $tagDestination->nodeValue . "]";
                        $tagDestination->nodeValue = "";
                        break;
                    case "INTERVENE":
                    default:
                        $tagDestination->nodeValue = "/email-link/" . EncodingTools::Base64EncodeURLSafe($tagDestination->nodeValue);
                }
            }

            if($tag->nodeName == "img")
            {
                $newtag = $tag->ownerDocument->createElement("span");
                $src = $tag->attributes->getNamedItem("src")->nodeValue;

                if(preg_match("/^cid:[^\"]+$/", $src))
                {
                    $cid = substr($src, 4);
                    array_push($imagesToInsert, $cid);
                    $newtag->nodeValue = "::auxpckimg:" . $cid . "::";
                }
                else
                {
                    $newtag->nodeValue = "External image [" . $src . "]";
                }
                $tag->parentNode->replaceChild($newtag, $tag);
            }

            if($tag->nodeValue == "[RHYBUDD! E-BOST ALLANOL / CAUTION! EXTERNAL E-MAIL]")
            {
                $tagMarkedForDeletion = true;
            }

            $auxrelPos = strpos($tag->nodeValue, "::auxrel:"); // for the actual tags
            if(!($auxrelPos === false))
            {
                //echo "a[".$auxrelPos."]";
                if($auxrelPos < 2)
                {
                    $tagMarkedForDeletion = true;
                }
            }

            //echo "b[".$tag->nodeValue."]";
            $auxrelPos = strpos($tag->nodeValue, "::auxrel::"); // for the help text
            if(!($auxrelPos === false))
            {
                if($auxrelPos < 15)
                {
                    $tagMarkedForDeletion = true;
                }
            }

            if($removeHistory)
            {
                $foundQuote = null;
                if($tag->nodeName == "hr")
                {
                    if($tag->nextSibling)
                    {
                        $scanString = $tag->nextSibling->nodeValue;
                        $matches = true;

                        if(strpos($scanString, "From") === false) $matches = false;
                        if(strpos($scanString, "Sent") === false) $matches = false;
                        if(strpos($scanString, "To") === false) $matches = false;
                        if(strpos($scanString, "Subject") === false) $matches = false;

                        if($matches)
                        {
                            //$foundQuote = $tag->nextSibling;
                            $tagMarkedForDeletion = true;
                        }

                        $foundQuote = $tag->nextSibling;
                        while($foundQuote != null)
                        {
                            $origFoundQuote = $foundQuote;
                            $foundQuote = $origFoundQuote->nextSibling;
                            $origFoundQuote->parentNode->removeChild($origFoundQuote);
                        }
                    }
                }
                if($tagClass != null)
                {
                    if(!(strpos($tagClass->nodeValue, "gmail_quote") === false)) $foundQuote = $tag;
                }
                if($foundQuote != null)
                {
                    $foundQuote->parentNode->removeChild($foundQuote);
                }
            }

            if($tagMarkedForDeletion)
            {
                $tag->parentNode->removeChild($tag);
                $i--;
            }
        }

        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        $cleanHtml = $purifier->purify($document->saveHTML());

        foreach($imagesToInsert as $cid)
        {
            $cleanHtml = str_replace("::auxpckimg:" . $cid . "::", "<img src='/api/v1/data/" . $this->getUuid() . "/attachments/" . EncodingTools::Base64EncodeURLSafe($cid) . "' style='max-width: 100%; max-height: 100vh;'/>", $cleanHtml);
        }

        return $cleanHtml;
    }

    public function getSentTimestamp($sent_import_tolerance = 120)
    { // If it's within 120 seconds don't bother showing a difference, as this was practically imported as soon as it was sent.
        $import_timestamp = strtotime($this->fetchMetadata()["creation_timestamp"]);
        $selected_timestamp = $import_timestamp;
        if(isset($this->getHeaders()["date"]))
        {
            $send_timestamp = strtotime($this->getHeaders()["date"]);
            if(!(($send_timestamp >= ($import_timestamp - $sent_import_tolerance)) && ($send_timestamp <= ($import_timestamp + $sent_import_tolerance))))
            {
                $selected_timestamp = $send_timestamp;
            }
        }
        return date("c", $selected_timestamp);
    }

    public function getBody($linkMode = "INTERVENE")
    {
        if($this->getMessage() == null)
        {
            return null;
        }
        $body = $this->getMessage()->getHtmlContent();
        return $this->sanitizeHtmlEmail($body, $linkMode, false);
    }

    public function getBodyAboveFold($linkMode = "INTERVENE")
    {
        if($this->getMessage() == null)
        {
            return null;
        }
        $body = $this->getMessage()->getHtmlContent();
        return $this->sanitizeHtmlEmail($body, $linkMode);
    }

    public function getRawBody()
    {
        if($this->getMessage() == null)
        {
            return null;
        }
        return $this->getMessage()->getHtmlContent();
    }
}
