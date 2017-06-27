<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;
use \Swift_Mime_Message;
/**
 * Description of MessageFormatStrategyInterface
 *
 * @author l.atanasov
 */
interface MessageFormatStrategyInterface {

    public function getMailjetMessage(Swift_Mime_Message $message);

    public function getVersion();
}
