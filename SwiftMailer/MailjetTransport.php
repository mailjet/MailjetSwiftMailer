<?php
namespace Welp\MailjetSwiftMailer\SwiftMailer;

use \Swift_Events_EventDispatcher;
use \Swift_Events_EventListener;
use \Swift_Events_SendEvent;
use \Swift_Mime_Message;
use \Swift_Transport;
use \Swift_Attachment;
use \Swift_MimePart;
use Mailjet\Resources;

/**
 * A SwiftMailer transport implementation for Mailjet
 */
class MailjetTransport implements Swift_Transport
{
    /**
     * @var Swift_Events_EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Mailjet API Key
     * @var string|null
     */
    protected $apiKey;

    /**
     * Mailjet API Secret
     * @var string|null
     */
    protected $apiSecret;

    /**
     * performs the call or not
     * @var bool
     */
    protected $call;

    /**
     * url (Default: api.mailjet.com) : domain name of the API
     * version (Default: v3) : API version (only working for Mailjet API V3 +)
     * call (Default: true) : turns on(true) / off the call to the API
     * secured (Default: true) : turns on(true) / off the use of 'https'
     * @var array|null
     */
    protected $clientOptions;

    /**
     * @var array|null
     */
    protected $resultApi;

    /**
     * @param Swift_Events_EventDispatcher $eventDispatcher
     * @param string $apiKey
     * @param string $apiSecret
     * @param array $clientOptions
     */
    public function __construct(Swift_Events_EventDispatcher $eventDispatcher, $apiKey = null, $apiSecret = null, $call = true, array $clientOptions = [])
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->call = $call;
        $this->clientOptions = $clientOptions;
    }

    /**
     * Not used
     */
    public function isStarted()
    {
        return false;
    }
    /**
     * Not used
     */
    public function start()
    {
    }
    /**
     * Not used
     */
    public function stop()
    {
    }
    /**
     * Not used
     */
    public function ping()
    {
    }

    /**
     * @param Swift_Mime_Message $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;

        if ($event = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($event, 'beforeSendPerformed');
            if ($event->bubbleCancelled()) {
                return 0;
            }
        }

        $sendCount = 0;
        // extract Mailjet Message from SwiftMailer Message
        $mailjetMessage = $this->getMailjetMessage($message);
        // Create mailjetClient
        $mailjetClient = $this->createMailjetClient();

        try {
            // send API call
            $this->resultApi = $mailjetClient->post(Resources::$Email, ['body' => ['Messages' => $mailjetMessage]]);

            if (isset($this->resultApi->getBody()['Sent'])) {
                $sendCount += count($this->resultApi->getBody()['Sent']);
            }
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            $failedRecipients = $mailjetMessage['To'];
            $sendCount = 0;
            $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
        }

        // Send SwiftMailer Event
        if ($event) {
            $event->setResult($resultStatus);
            $event->setFailedRecipients($failedRecipients);
            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
        }

        return $sendCount;
    }

    /**
     * @param array $message (of Swift_Mime_Message)
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function bulkSend(array $messages, &$failedRecipients = null)
    {
        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;

        $sendCount = 0;
        $bodyRequest = ['Messages' => []];

        foreach ($messages as $message) {
            // extract Mailjet Message from SwiftMailer Message
            $mailjetMessage = $this->getMailjetMessage($message);
            array_push($bodyRequest['Messages'], $mailjetMessage);
        }
        // Create mailjetClient
        $mailjetClient = $this->createMailjetClient();

        try {
            // send API call
            $this->resultApi = $mailjetClient->post(Resources::$Email, ['body' => $bodyRequest]);

            if (isset($this->resultApi->getBody()['Sent'])) {
                $sendCount += count($this->resultApi->getBody()['Sent']);
            }
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            //$failedRecipients = $mailjetMessage['Recipients'];
            $sendCount = 0;
            $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
        }

        return $sendCount;
    }

    /**
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * @return \Mailjet\Client
     * @throws \Swift_TransportException
     */
    protected function createMailjetClient()
    {
        if ($this->apiKey === null || $this->apiSecret === null) {
            throw new \Swift_TransportException('Cannot create instance of \Mailjet\Client while API key is NULL');
        }

        if (isset($this->clientOptions)) {
            return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call, $this->clientOptions);
        }

        return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call);
    }

    /**
     * Get the special X-MJ|Mailjet-* headers. https://app.mailjet.com/docs/emails_headers
     *
     * @return array
     */
    public static function getMailjetHeaders()
    {
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
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_Message $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_Message $message)
    {
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];

        $attachments = array();

        // Process Headers
        $headers = array();
        $mailjetSpecificHeaders = $this->prepareHeaders($message);

        // @TODO only Format To, Cc, Bcc
        $to = array();
        foreach ($toAddresses as $toEmail => $toName) {
            array_push($to, ['Email' => $toEmail, 'Name' => $toName]);
        }
        $cc = array();
        foreach ($ccAddresses as $ccEmail => $ccName) {
            array_push($cc, ['Email' => $ccEmail, 'Name' => $ccName]);
        }
        $bcc = array();
        foreach ($bccAddresses as $bccEmail => $bccName) {
            array_push($bcc, ['Email' => $bccEmail, 'Name' => $bccName]);
        }

        // Handle content
        $bodyHtml = $bodyText = null;
        if ($contentType === 'text/plain') {
            $bodyText = $message->getBody();
        } elseif ($contentType === 'text/html') {
            $bodyHtml = $message->getBody();
        } else {
            $bodyHtml = $message->getBody();
        }


        // Handle attachments
        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment) {
                $attachments[] = array(
                    'ContentType'    => $child->getContentType(),
                    'Filename'    => $child->getFilename(),
                    'Base64Content' => base64_encode($child->getBody())
                );
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }

        $mailjetMessage = array(
            'From'  => array(
                'Email' => $fromEmails[0],
                'Name'  => $fromAddresses[$fromEmails[0]]
            ),
            'To'    => $to,
            'Cc'    => $cc,
            'Bcc'   => $bcc,
            'HTMLPart'  => $bodyHtml,
            'TextPart'  => $bodyText,
            'Subject'   => $message->getSubject(),
        );

        if ($replyTo = $this->getReplyTo($message)) {
            $mailjetMessage['ReplyTo'] = $replyTo;
        }

        if (count($headers) > 0) {
            $mailjetMessage['Headers'] = $headers;
        }

        if (count($mailjetSpecificHeaders) > 0) {
            $mailjetMessage = array_merge($mailjetMessage, $mailjetSpecificHeaders);
        }

        if (count($attachments) > 0) {
            $mailjetMessage['Attachments'] = $attachments;
        }

        // @TODO bulk messages

        return $mailjetMessage;
    }

    /**
     * Extract Mailjet specific header
     * return an array of formatted data for Mailjet send API
     * @param  Swift_Mime_Message $message
     * @return array
     */
    protected function prepareHeaders(Swift_Mime_Message $message)
    {
        $mailjetHeaders = self::getMailjetHeaders();
        $messageHeaders = $message->getHeaders();

        $mailjetData = array();


        foreach (array_keys($mailjetHeaders) as $headerName) {
            /** @var \Swift_Mime_Headers_MailboxHeader $value */
           if (null !== $value = $messageHeaders->get($headerName)) {
               // Handle custom headers
               $mailjetData[$mailjetHeaders[$headerName]] = $value->getValue();
               // remove Mailjet specific headers
               $messageHeaders->removeAll($headerName);
           }
        }

        return $mailjetData;
    }

    /**
     * Get the 'reply_to' headers and format as required by Mailjet.
     *
     * @param Swift_Mime_Message $message
     *
     * @return array|null
     */
    protected function getReplyTo(Swift_Mime_Message $message)
    {
        if (is_array($message->getReplyTo())) {
            return array('Email' => key($message->getReplyTo()), 'Name' => current($message->getReplyTo()));
        } elseif (is_string($message->getReplyTo())) {
            return array('Email' => $message->getReplyTo());
        } else {
            return null;
        }
    }

    /**
     * @return array
     */
    protected function getSupportedContentTypes()
    {
        return array(
            'text/plain',
            'text/html'
        );
    }

    /**
     * @param string $contentType
     * @return bool
     */
    protected function supportsContentType($contentType)
    {
        return in_array($contentType, $this->getSupportedContentTypes());
    }

    /**
     * @param Swift_Mime_Message $message
     * @return string
     */
    protected function getMessagePrimaryContentType(Swift_Mime_Message $message)
    {
        $contentType = $message->getContentType();
        if ($this->supportsContentType($contentType)) {
            return $contentType;
        }
        // SwiftMailer hides the content type set in the constructor of Swift_Mime_SimpleMessage as soon
        // as you add another part to the message. We need to access the protected property
        // userContentType to get the original type.
        $messageRef = new \ReflectionClass($message);
        if ($messageRef->hasProperty('userContentType')) {
            $propRef = $messageRef->getProperty('userContentType');
            $propRef->setAccessible(true);
            $contentType = $propRef->getValue($message);
        }
        return $contentType;
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }
    /**
     * @return null|string
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiSecret
     * @return $this
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }
    /**
     * @return null|string
     */
    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    /**
     * @param bool $call
     * @return $this
     */
    public function setCall($call)
    {
        $this->call = $call;
        return $this;
    }
    /**
     * @return bool
     */
    public function getCall()
    {
        return $this->call;
    }

    /**
     * @param array $clientOptions
     * @return $this
     */
    public function setClientOptions(array $clientOptions = [])
    {
        $this->clientOptions = $clientOptions;
        return $this;
    }
    /**
     * @return null|array
     */
    public function getClientOptions()
    {
        return $this->clientOptions;
    }

    /**
     * @return null|array
     */
    public function getResultApi()
    {
        return $this->resultApi;
    }
}
