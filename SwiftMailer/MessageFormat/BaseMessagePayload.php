<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;

use \Swift_Mime_SimpleMessage;

abstract class BaseMessagePayload implements MessageFormatStrategyInterface
{
    /**
     * @param Swift_Mime_SimpleMessage $message
     *
     * @return string
     * @throws \ReflectionException
     */
    protected static function getMessagePrimaryContentType(Swift_Mime_SimpleMessage $message)
    {
        $contentType = $message->getContentType();
        if (self::supportsContentType($contentType)) {
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
     * @return array
     */
    private static function getSupportedContentTypes()
    {
        return [
            'text/plain',
            'text/html',
        ];
    }

    /**
     * @param string $contentType
     *
     * @return bool
     */
    protected static function supportsContentType($contentType)
    {
        return in_array($contentType, self::getSupportedContentTypes());
    }

    /**
     * Extract Mailjet specific header
     * return an array of formatted data for Mailjet send API
     *
     * @param Swift_Mime_SimpleMessage $message
     * @param array              $mailjetHeaders
     *
     * @return array
     */
    protected static function prepareHeaders(Swift_Mime_SimpleMessage $message, $mailjetHeaders)
    {
        $messageHeaders = $message->getHeaders();

        $mailjetData = [];

        foreach (array_keys($mailjetHeaders) as $headerName) {
            /** @var \Swift_Mime_Headers_UnstructuredHeader $value */
            if (null !== $value = $messageHeaders->get($headerName)) {
                // Handle custom headers
                if($headerName == "X-MJ-Vars" && is_string($value->getValue())){
                    $mailjetData[$mailjetHeaders[$headerName]] = json_decode($value->getValue());
                } else {
                    $mailjetData[$mailjetHeaders[$headerName]] = $value->getValue();
                }
                // remove Mailjet specific headers
                $messageHeaders->removeAll($headerName);
            }
        }

        return $mailjetData;
    }

    /**
     * Extract user defined starting with X-*
     *
     * @param  Swift_Mime_SimpleMessage $message
     *
     * @return array
     */
    protected static function findUserDefinedHeaders(Swift_Mime_SimpleMessage $message)
    {
        $messageHeaders = $message->getHeaders();
        $userDefinedHeaders = [];
        /* At this moment $messageHeaders is left with non-Mailjet specific headers
         *
         */
        /** @var \Swift_Mime_Headers_AbstractHeader $header */
        foreach ($messageHeaders->getAll() as $header) {
            if (0 === strpos($header->getFieldName(), 'X-')) {
                $userDefinedHeaders[$header->getFieldName()] = $header->getFieldBody();
            }
        }

        return $userDefinedHeaders;
    }

    /**
     * Convert Swift_Mime_SimpleMessage into Mailjet Payload for send API
     *
     * @param Swift_Mime_SimpleMessage $message
     *
     * @return array Mailjet Send Message
     * @throws \Swift_SwiftException
     */
    abstract public function getMailjetMessage(Swift_Mime_SimpleMessage $message);

    /**
     * Returns the version of the message format
     *
     * @return string version of the message format
     */
    abstract public function getVersion();
}
