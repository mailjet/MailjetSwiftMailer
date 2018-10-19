<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;

use \Swift_Mime_SimpleMessage;
use \Swift_Attachment;
use \Swift_MimePart;

class MessagePayloadV3 extends BaseMessagePayload {

    private $version = 'v3';

    /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_SimpleMessage $message) {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $attachments = array();
        $inline_attachments = array();
        // Process Headers
        $customHeaders = $this->prepareHeaders($message, $this->getMailjetHeaders());
        $userDefinedHeaders = $this->findUserDefinedHeaders($message);
        if ($replyTo = $this->getReplyTo($message)) {
            $userDefinedHeaders = array_merge($userDefinedHeaders, array('Reply-To' => $replyTo));
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
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }
        $mailjetMessage = array();
        $recipients = $this->getRecipients($message);
        if (count($recipients) > 0) {
            $mailjetMessage['Recipients'] = $recipients;
        }
        if (!is_null($fromEmails[0])) {
            $mailjetMessage['FromEmail'] = $fromEmails[0];
        }
        if (!is_null($message->getSubject())) {
            $mailjetMessage['Subject'] = $message->getSubject();
        }
        if (!is_null($fromAddresses[$fromEmails[0]])) {
            $mailjetMessage['FromName'] = $fromAddresses[$fromEmails[0]];
        }
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
     * @param Swift_Mime_SimpleMessage $message
     *
     * @return string|null
     */
    protected function getReplyTo(Swift_Mime_SimpleMessage $message) {
        if (is_array($message->getReplyTo())) {
            return current($message->getReplyTo()) . ' <' . key($message->getReplyTo()) . '>';
        }
    }

    /**
     * Get all the addresses this message should be sent to.
     *
     * @param Swift_Mime_SimpleMessage $message
     *
     * @return array
     */
    protected function getRecipients(Swift_Mime_SimpleMessage $message) {
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
            if (!is_null($name)) {
                $recipients[] = ['Email' => $address, 'Name' => $name];
            } else {
                $recipients[] = ['Email' => $address];
            }
        }
        return $recipients;
    }

    /**
     * Returns the version of the message format
     * @return version of the message format
     */
    public function getVersion() {

        return $this->version;
    }

}
