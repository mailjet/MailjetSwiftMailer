<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Mime_Message;
use \Swift_Attachment;
use \Swift_MimePart;

class messagePayloadV3 implements messageFormatStrategy {

    private $version = 'v3';

    /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_Message $message) {
       $contentType = Utils::getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];
        $attachments = array();
        $inline_attachments = array();
        // Process Headers
        $customHeaders = Utils::prepareHeaders($message, $this->getMailjetHeaders());
        $userDefinedHeaders = Utils::findUserDefinedHeaders($message);
        if ($replyTo = $this->getReplyTo($message)) {
            $userDefinedHeaders = array_merge($userDefinedHeaders, array('Reply-To' => $replyTo));
        }
        // @TODO only Format To, Cc, Bcc
        $to = "";
        foreach ($toAddresses as $toEmail => $toName) {
            $to .= "$toName <$toEmail>";
        }
        $cc = "";
        foreach ($ccAddresses as $ccEmail => $ccName) {
            $cc .= "$toName <$toEmail>";
        }
        $bcc = "";
        foreach ($bccAddresses as $bccEmail => $bccName) {
            $bcc .= "$toName <$toEmail>";
        }
        // Handle content
         $bodyHtml = $bodyText = null;
        if ($contentType === 'text/plain') {
            $bodyText = $message->getBody();
        } else {
            $bodyHtml = $message->getBody();
        }

        // Handle attachments
        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment) {
                //Handle regular attachments
                if ($child->getDisposition() === "attachment") {
                    $attachments[] = array(
                        'Content-type' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'content' => base64_encode($child->getBody())
                    );
                }
                //Handle inline attachments
                elseif ($child->getDisposition() === "inline") {
                    $inline_attachments[] = array(
                        'Content-type' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'content' => base64_encode($child->getBody())
                    );
                }
            } elseif ($child instanceof Swift_MimePart && Utils::supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }
        $mailjetMessage = array(
            'FromEmail' => $fromEmails[0],
            'FromName' => $fromAddresses[$fromEmails[0]],
            'Subject' => $message->getSubject(),
            'Recipients' => $this->getRecipients($message)
        );
        if (!is_null($bodyHtml)) {
            $mailjetMessage['Html-part'] = $bodyHtml;
        }
        if (!is_null($bodyText)) {
            $mailjetMessage['Text-part'] = $bodyText;
        }
        if (count($userDefinedHeaders) > 0) {
            $mailjetMessage['Headers'] = $userDefinedHeaders;
        }
        if (count($customHeaders) > 0) {
            $mailjetMessage = array_merge($mailjetMessage, $customHeaders);
        }
        if (count($attachments) > 0) {
            $mailjetMessage['Attachments'] = $attachments;
        }
        if (count($inline_attachments) > 0) {
            $mailjetMessage['Inline_attachments'] = $inline_attachments;
        }

        // @TODO bulk messages
        return $mailjetMessage;
    }

    /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    private static function getMailjetHeaders() {
        return array(
            'X-MJ-TemplateID' => 'Mj-TemplateID',
            'X-MJ-TemplateLanguage' => 'Mj-TemplateLanguage',
            'X-MJ-TemplateErrorReporting' => 'MJ-TemplateErrorReporting',
            'X-MJ-TemplateErrorDeliver' => 'MJ-TemplateErrorDeliver',
            'X-Mailjet-Prio' => 'Mj-Prio',
            'X-Mailjet-Campaign' => 'Mj-campaign',
            'X-Mailjet-DeduplicateCampaign' => 'Mj-deduplicatecampaign',
            'X-Mailjet-TrackOpen' => 'Mj-trackopen',
            'X-Mailjet-TrackClick' => 'Mj-trackclick',
            'X-MJ-CustomID' => 'Mj-CustomID',
            'X-MJ-EventPayLoad' => 'Mj-EventPayLoad',
            'X-MJ-Vars' => 'Vars'
        );
    }

    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return string|null
     */
    protected function getReplyTo(Swift_Mime_Message $message) {
        if (is_array($message->getReplyTo())) {
            return current($message->getReplyTo()) . ' <' . key($message->getReplyTo()) . '>';
        }
    }


    /**
     * Get all the addresses this message should be sent to.
     *
     * @param Swift_Mime_Message $message
     *
     * @return array
     */
    protected function getRecipients(Swift_Mime_Message $message) {
        $to = [];
        if ($message->getTo()) {
            $to = array_merge($to, $message->getTo());
        }
        if ($message->getCc()) {
            $to = array_merge($to, $message->getCc());
        }
        if ($message->getBcc()) {
            $to = array_merge($to, $message->getBcc());
        }
        $recipients = [];
        foreach ($to as $address => $name) {
            $recipients[] = ['Email' => $address, 'Name' => $name];
        }
        return $recipients;
    }

    public function getVersion() {

        return $this->version;
    }

}
