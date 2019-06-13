<?php

namespace Mailjet\MailjetSwiftMailer\SwiftMailer\MessageFormat;
use \Swift_Mime_SimpleMessage;
/**
 * Description of MessageFormatStrategyInterface
 *
 * @author l.atanasov
 */
interface MessageFormatStrategyInterface {

    public function getMailjetMessage(Swift_Mime_SimpleMessage $message);

    public function getVersion();
}
