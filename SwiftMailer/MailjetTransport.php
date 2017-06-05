<?php
namespace Welp\MailjetSwiftMailer\SwiftMailer;

use \Swift_Events_EventDispatcher;
use \Swift_Events_EventListener;
use \Swift_Events_SendEvent;
use \Swift_Mime_SimpleMessage;
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
     * @var string|null
     */
    protected $apiKey;

    /**
     * @var string|null
     */
    protected $apiSecret;

    /**
     * @var array|null
     */
    protected $resultApi;

    /**
     * @param Swift_Events_EventDispatcher $eventDispatcher
     */
    public function __construct(Swift_Events_EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->apiKey = null;
        $this->apiSecret = null;
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
     * @return null|array
     */
    public function getResultApi()
    {
        return $this->resultApi;
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
        return new \Mailjet\Client($this->apiKey, $this->apiSecret);
    }

    /**
     * @param Swift_Mime_SimpleMessage $message
     * @param null $failedRecipients
     * @return int Number of messages sent
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->resultApi = null;

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
        // send API call
        $this->resultApi = $mailjetClient->post(Resources::$Email, $mailjetMessage);

        // get result
        if ($this->resultApi->success()) {
            $sendCount += $this->resultApi->getCount();
        }

        // Send SwiftMailer Event
        if ($event) {
            if ($sendCount > 0) {
                $event->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
            } else {
                $event->setResult(Swift_Events_SendEvent::RESULT_FAILED);
            }
            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
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
     * https://dev.mailjet.com/guides/#send-api-json-properties
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_SimpleMessage $message
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    public function getMailjetMessage(Swift_Mime_SimpleMessage $message)
    {
        // @TODO
        // FromEmail
        // FromName
        // Sender
        // Recipients
        // To
        // Cc, bcc
        // Subject
        // Text-part
        // Html-parts
        // Mj-TemplateID
        // Mj-TemplateLanguage
        // MJ-TemplateErrorReporting
        // MJ-TemplateErrorDeliver
        // Attachments
        // Inline_attachments
        // Mj-prio
        // Mj-campaign
        // Mj-deduplicatecampaign
        // Mj-trackopen
        // Mj-trackclick
        // Mj-CustomID
        // Mj-EventPayLoad
        // Headers
        // Vars
        // Messages
        $contentType = $this->getMessagePrimaryContentType($message);
        $fromAddresses = $message->getFrom();
        $fromEmails = array_keys($fromAddresses);
        $toAddresses = $message->getTo();
        $ccAddresses = $message->getCc() ? $message->getCc() : [];
        $bccAddresses = $message->getBcc() ? $message->getBcc() : [];
        $replyToAddresses = $message->getReplyTo() ? $message->getReplyTo() : [];
        $to = array();
        $attachments = array();
        $images = array();
        $headers = array();
        $tags = array();
        $inlineCss = null;
        foreach ($toAddresses as $toEmail => $toName) {
            $to[] = array(
                'email' => $toEmail,
                'name'  => $toName,
                'type'  => 'to'
            );
        }
        foreach ($replyToAddresses as $replyToEmail => $replyToName) {
            if ($replyToName) {
                $headers['Reply-To'] = sprintf('%s <%s>', $replyToEmail, $replyToName);
            } else {
                $headers['Reply-To'] = $replyToEmail;
            }
        }
        foreach ($ccAddresses as $ccEmail => $ccName) {
            $to[] = array(
                'email' => $ccEmail,
                'name'  => $ccName,
                'type'  => 'cc'
            );
        }
        foreach ($bccAddresses as $bccEmail => $bccName) {
            $to[] = array(
                'email' => $bccEmail,
                'name'  => $bccName,
                'type'  => 'bcc'
            );
        }
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
            if ($child instanceof \Swift_Image) {
                $images[] = array(
                    'type'    => $child->getContentType(),
                    'name'    => $child->getId(),
                    'content' => base64_encode($child->getBody()),
                );
            } elseif ($child instanceof Swift_Attachment && ! ($child instanceof \Swift_Image)) {
                $attachments[] = array(
                    'type'    => $child->getContentType(),
                    'name'    => $child->getFilename(),
                    'content' => base64_encode($child->getBody())
                );
            } elseif ($child instanceof Swift_MimePart && $this->supportsContentType($child->getContentType())) {
                if ($child->getContentType() == "text/html") {
                    $bodyHtml = $child->getBody();
                } elseif ($child->getContentType() == "text/plain") {
                    $bodyText = $child->getBody();
                }
            }
        }
        /*if ($message->getHeaders()->has('List-Unsubscribe')) {
            $headers['List-Unsubscribe'] = $message->getHeaders()->get('List-Unsubscribe')->getValue();
        }
        if ($message->getHeaders()->has('X-MC-InlineCSS')) {
            $inlineCss = $message->getHeaders()->get('X-MC-InlineCSS')->getValue();
        }
        if ($message->getHeaders()->has('X-MC-Tags')) {
            $tags = $message->getHeaders()->get('X-MC-Tags')->getValue();
            if (!is_array($tags)) {
                $tags = explode(',', $tags);
            }
        }*/
        $mailjetMessage = array(
            'html'       => $bodyHtml,
            'text'       => $bodyText,
            'subject'    => $message->getSubject(),
            'from_email' => $fromEmails[0],
            'from_name'  => $fromAddresses[$fromEmails[0]],
            'to'         => $to,
            'headers'    => $headers,
            'inline_css' => $inlineCss,
            'tags'       => $tags
        );
        if (count($attachments) > 0) {
            $mailjetMessage['attachments'] = $attachments;
        }

        if (count($images) > 0) {
            $mailjetMessage['images'] = $images;
        }
        /*if ($message->getHeaders()->has('X-MC-Autotext')) {
            $autoText = $message->getHeaders()->get('X-MC-Autotext')->getValue();
            if (in_array($autoText, array('true','on','yes','y', true), true)) {
                $mailjetMessage['auto_text'] = true;
            }
            if (in_array($autoText, array('false','off','no','n', false), true)) {
                $mailjetMessage['auto_text'] = false;
            }
        }
        if ($message->getHeaders()->has('X-MC-GoogleAnalytics')) {
            $analyticsDomains = explode(',', $message->getHeaders()->get('X-MC-GoogleAnalytics')->getValue());
            if (is_array($analyticsDomains)) {
                $mailjetMessage['google_analytics_domains'] = $analyticsDomains;
            }
        }
        if ($message->getHeaders()->has('X-MC-GoogleAnalyticsCampaign')) {
            $mailjetMessage['google_analytics_campaign'] = $message->getHeaders()->get('X-MC-GoogleAnalyticsCampaign')->getValue();
        }
        if ($this->getSubaccount()) {
            $mailjetMessage['subaccount'] = $this->getSubaccount();
        }*/
        return $mailjetMessage;
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
     * @param Swift_Mime_SimpleMessage $message
     * @return string
     */
    protected function getMessagePrimaryContentType(Swift_Mime_SimpleMessage $message)
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
}
