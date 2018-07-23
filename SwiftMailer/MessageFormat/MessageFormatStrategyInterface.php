<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;

use \Swift_Message;

/**
 * Description of MessageFormatStrategyInterface
 *
 * @author l.atanasov
 */
interface MessageFormatStrategyInterface {

    public function getMailjetMessage(Swift_Message $message);

    public function getVersion();
}
