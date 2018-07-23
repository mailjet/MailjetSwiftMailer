<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;

use \Swift_Message;
use \Swift_Attachment;
use \Swift_MimePart;

class MessagePayloadV31 extends BaseMessagePayload {

    private
        $version = 'v3.1';

    /**
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Message $message) {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];

        $attachments = array();
        $inline_attachments = array();

        // Process Headers
        $customHeaders = self::prepareHeaders($message, self::getMailjetHeaders());
        $userDefinedHeaders = self::findUserDefinedHeaders($message);


        // @TODO only Format To, Cc, Bcc
        $to = array();
        foreach ($toAddresses as $toEmail => $toName) {
            if ($toName !== null) {
                $to[] = ['Email' => $toEmail, 'Name' => $toName];
            } else {
                $to[] = ['Email' => $toEmail];
            }
        }
        $cc = array();
        foreach ($ccAddresses as $ccEmail => $ccName) {
            if ($ccName !== null) {
                $cc[] = ['Email' => $ccEmail, 'Name' => $ccName];
            } else {
                $cc[] = ['Email' => $ccEmail];
            }
        }
        $bcc = array();
        foreach ($bccAddresses as $bccEmail => $bccName) {
            if ($bccName !== null) {
                $bcc[] = ['Email' => $bccEmail, 'Name' => $bccName];
            } else {
                $bcc[] = ['Email' => $bccEmail];
            }
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
                if ($child->getDisposition() === 'attachment') {
                    $attachments[] = array(
                        'ContentType' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'Base64Content' => base64_encode($child->getBody())
                    );
                }
                //Handle inline attachments
                elseif ($child->getDisposition() === 'inline') {
                    $inline_attachments[] = array(
                        'ContentType' => $child->getContentType(),
                        'Filename' => $child->getFilename(),
                        'ContentID' => $child->getId(),
                        'Base64Content' => base64_encode($child->getBody())
                    );
                }
            } elseif ($child instanceof Swift_MimePart && self::supportsContentType($child->getContentType())) {
                if ($child->getContentType() === 'text/html') {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() === 'text/plain') {
                    $bodyText = $child->getBody();
                }
            }
        }
        $mailjetMessage = array();
        $from = array(
            'Email' => $fromEmails[0],
            'Name' => $fromAddresses[$fromEmails[0]]
        );

        if (!empty($from)) {
            if ($from['Name'] === null) {
                unset($from['Name']);
            }
            $mailjetMessage['From'] = $from;
        }
        if (!empty($to)) {
            $mailjetMessage['To'] = $to;
        }
        if (!empty($cc)) {
            $mailjetMessage['Cc'] = $cc;
        }
        if (!empty($bcc)) {
            $mailjetMessage['Bcc'] = $bcc;
        }
        if ($message->getSubject() !== null) {
            $mailjetMessage['Subject'] = $message->getSubject();
        }
        if ($bodyHtml !== null) {
            $mailjetMessage['HTMLPart'] = $bodyHtml;
        }
        if ($bodyText !== null) {
            $mailjetMessage['TextPart'] = $bodyText;
        }
        if ($replyTo = $this->getReplyTo($message)) {
            $mailjetMessage['ReplyTo'] = $replyTo;
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
            $mailjetMessage['InlinedAttachments'] = $inline_attachments;
        }


        return array('Messages' => array($mailjetMessage));
    }

    /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    private static function getMailjetHeaders() {
        return array(
            'X-MJ-TemplateID' => 'TemplateID',
            'X-MJ-TemplateLanguage' => 'TemplateLanguage',
            'X-MJ-TemplateErrorReporting' => 'TemplateErrorReporting',
            'X-MJ-TemplateErrorDeliver' => 'TemplateErrorDeliver',
            'X-Mailjet-Prio' => 'Priority',
            'X-Mailjet-Campaign' => 'CustomCampaign',
            'X-Mailjet-DeduplicateCampaign' => 'DeduplicateCampaign',
            'X-Mailjet-TrackOpen' => 'TrackOpens',
            'X-Mailjet-TrackClick' => 'TrackClicks',
            'X-MJ-CustomID' => 'CustomID',
            'X-MJ-EventPayLoad' => 'EventPayload',
            'X-MJ-MonitoringCategory' => 'MonitoringCategory',
            'X-MJ-Vars' => 'Variables'
        );
    }

    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Message $message
     *
     * @return array|null
     */
    private function getReplyTo(Swift_Message $message) {
        if (is_array($message->getReplyTo())) {
            return array('Email' => key($message->getReplyTo()), 'Name' => current($message->getReplyTo()));
        }

        if (is_string($message->getReplyTo())) {
            return array('Email' => $message->getReplyTo());
        }

        return null;
    }

    /**
     * Returns the version of the message format
     * @return string Version of the message format
     */
    public function getVersion() {

        return $this->version;
    }

}
