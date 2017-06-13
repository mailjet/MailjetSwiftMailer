<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer;

use \Swift_Mime_Message;

/**
 * Contains common helper methods
 *
 * @author l.atanasov
 */
class Utils {

    /**
     * @param Swift_Mime_Message $message
     * @return string
     */
    public static function getMessagePrimaryContentType(Swift_Mime_Message $message) {
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
    private static function getSupportedContentTypes() {
        return array(
            'text/plain',
            'text/html'
        );
    }

    /**
     * @param string $contentType
     * @return bool
     */
    public static function supportsContentType($contentType) {
        return in_array($contentType, self::getSupportedContentTypes());
    }

    /**
     * Extract Mailjet specific header
     * return an array of formatted data for Mailjet send API
     * @param  Swift_Mime_Message $message
     * @return array
     */
    public static function prepareHeaders(Swift_Mime_Message $message,$mailjetHeaders) {
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
     * Extract user defined starting with X-*
     * @param  Swift_Mime_Message $message
     * @return array
     */
    public static function findUserDefinedHeaders(Swift_Mime_Message $message) {
        $messageHeaders = $message->getHeaders();
        $userDefinedHeaders = array();
        /* At this moment $messageHeaders is left with non-Mailjet specific headers
         * 
         */
        foreach ($messageHeaders->getAll() as $header) {
            if (0 === strpos($header->getFieldName(), 'X-')) {
                $userDefinedHeaders[$header->getFieldName()] = $header->getValue();
            }
        }
        return $userDefinedHeaders;
    }

}
