<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Events_EventDispatcher;
use \Swift_Events_EventListener;
use \Swift_Events_SendEvent;
use \Swift_Mime_SimpleMessage;
use \Swift_Transport;
use Mailjet\Resources;
use Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat\MessagePayloadV31;
use Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat\MessagePayloadV3;



/**
 * A SwiftMailer transport implementation for Mailjet
 */
class MailjetTransport implements Swift_Transport {

    /**
     * @var Swift_Events_EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Mailjet client
     * @var \Mailjet\Client
     */
    protected $mailjetClient = null;

    /**
     * @var Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat\MessageFormatStrategyInterface
     */
    public $messageFormat;

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
    public function __construct(Swift_Events_EventDispatcher $eventDispatcher, $apiKey = null, $apiSecret = null, $call = true, array $clientOptions = []) {
        $this->eventDispatcher = $eventDispatcher;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->call = $call;
        $this->setClientOptions($clientOptions);
    }

    /**
     * Not used
     */
    public function isStarted() {
        return false;
    }

    /**
     * Not used
     */
    public function start() {

    }

    /**
     * Not used
     */
    public function stop() {

    }

    /**
     * Not used
     */
    public function ping() {

    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null) {
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
        $mailjetMessage = $this->messageFormat->getMailjetMessage($message);
        if (is_null($this->mailjetClient)) {
            // create Mailjet client
            $this->mailjetClient = $this->createMailjetClient();
        }


        try {
            // send API call
            $this->resultApi = $this->mailjetClient->post(Resources::$Email, ['body' => $mailjetMessage]);

            $sendCount = $this->findNumberOfSentMails();
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            $failedRecipients = $message->getTo();
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
     * @param array $message (of Swift_Mime_SimpleMessage)
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function bulkSend(array $messages, &$failedRecipients = null) {

        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;
        $bulkContainer = ['Messages' => []];
        $sendCount = 0;
        foreach ($messages as $message) {
            // extract Mailjet Message from SwiftMailer Message
            $mailjetMessage = $this->messageFormat->getMailjetMessage($message);
            if (is_null($this->mailjetClient)) {
                // create Mailjet client
                $this->mailjetClient = $this->createMailjetClient();
            }
            /* No real bulk sending in v3.1. Even single message already
             * contains an array Messages.
             */
            if ($this->messageFormat->getVersion() === 'v3.1') {
                array_push($bulkContainer['Messages'], $mailjetMessage['Messages'][0]);
            } else {
                array_push($bulkContainer['Messages'], $mailjetMessage);
            }
        }
        // Create mailjetClient
        $mailjetClient = $this->createMailjetClient();

        try {
            // send API call
            $this->resultApi = $this->mailjetClient->post(Resources::$Email, ['body' => $bulkContainer]);

            $sendCount = $this->findNumberOfSentMails();
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
     *  Finds the number of sent emails  by last send call
     * @return int Number of messages sent
     */
    private function findNumberOfSentMails() {
        $sendCount = 0;
        if ($this->messageFormat->getVersion() === 'v3.1') {
            $messages = $this->resultApi->getBody()['Messages'];
            foreach ($messages as $message) {


                if (isset($message['To'])) {
                    $sendCount += count($message['To']);
                }
                if (isset($message['Bcc']) && (!empty($message['Bcc']))) {
                    $sendCount += count($message['Bcc']);
                }
                if (isset($message['Cc']) && (!empty($message['Cc']))) {
                    $sendCount += count($message['Cc']);
                }
            }
            return $sendCount;
        }
        if ($this->messageFormat->getVersion() === 'v3') {
            if (isset($this->resultApi->getBody()['Sent'])) {
                $sendCount += count($this->resultApi->getBody()['Sent']);
            }
            return $sendCount;
        }
        return $sendCount;
    }

    /**
     * @return \Mailjet\Client
     * @throws \Swift_TransportException
     */
    protected function createMailjetClient() {

        if ($this->apiKey === null || $this->apiSecret === null) {
            throw new \Swift_TransportException('Cannot create instance of \Mailjet\Client while API key is NULL');
        }

        if (isset($this->clientOptions)) {
            return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call, $this->clientOptions);
        }

        return new \Mailjet\Client($this->apiKey, $this->apiSecret, $this->call);
    }

    /**
     * Inject an external Mailjet\Client
     * @method setExternalMailjetClient
     * @param  \Mailjet\Client $client
     */
    public function setExternalMailjetClient(\Mailjet\Client $client)
    {
        $this->mailjetClient = $client;
        return $this->mailjetClient;
    }

    /**
     * @param Swift_Events_EventListener $plugin
     */
    public function registerPlugin(Swift_Events_EventListener $plugin) {
        $this->eventDispatcher->bindEventListener($plugin);
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }
    /**
     * @return null|string
     */
    public function getApiKey() {
        return $this->apiKey;
    }

    /**
     * @param string $apiSecret
     * @return $this
     */
    public function setApiSecret($apiSecret) {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getApiSecret() {
        return $this->apiSecret;
    }

    /**
     * @param bool $call
     * @return $this
     */
    public function setCall($call) {
        $this->call = $call;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCall() {
        return $this->call;
    }

    /**
     * @param array $clientOptions
     * @return $this
     */
    public function setClientOptions(array $clientOptions = []) {
        $this->clientOptions = $clientOptions;

        if (isset($this->clientOptions['version'])) {
            if ($this->clientOptions['version'] === 'v3.1') {
                $this->messageFormat = new MessagePayloadV31();
            } else {//v3 is default format
                $this->messageFormat = new MessagePayloadV3();
            }
        } else {//If no options were provided set the message format to v3 as default
            $this->messageFormat = new MessagePayloadV3();
        }
        return $this;
    }

    /**
     * @return null|array
     */
    public function getClientOptions() {
        return $this->clientOptions;
    }

    /**
     * @return null|array
     */
    public function getResultApi() {
        return $this->resultApi;
    }
}
