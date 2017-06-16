<?php
namespace Mailjet\MailjetSwiftMailer\Tests\SwiftMailer;

use PHPUnit\Framework\TestCase;
use Mailjet\MailjetSwiftMailer\SwiftMailer\MailjetTransport;
use Symfony\Component\Config\Definition\Processor;

class MailjetTransportTest extends TestCase
{
    const MAILJET_TEST_API_KEY = 'ABCDEFG1234567';
    const MAILJET_TEST_API_SECRET = 'ABCDEFG1234567';
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|\Swift_Events_EventDispatcher
     */
    protected $dispatcher;


    protected function setUp()
    {
        $this->dispatcher = $this->createMock('\Swift_Events_EventDispatcher');
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
    }
    /**
     * Returns an instance of the transport through which test messages can be sent
     *
     * @return MailjetTransport
     */
    protected function createTransport()
    {
        $transport = new MailjetTransport($this->dispatcher);
        $transport->setApiKey(self::MAILJET_TEST_API_KEY);
        $transport->setApiSecret(self::MAILJET_TEST_API_SECRET);
        $transport->setCall(false); // Do not perform the call
        return $transport;
    }

    public function testCanBeInstanciable()
    {
        $this->assertInstanceOf(
            MailjetTransport::class,
            $this->createTransport()
        );
    }

    public function testSendTextEmail()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', 'Foo bar');
        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;
        $message->setBody("Hello world!", 'text/plain');
        $mailjetMessage = $transport->getMailjetMessage($message);
        $result = $transport->send($message);

        $this->assertEquals('Hello world!', $mailjetMessage['TextPart']);
        $this->assertMessageSendable($message);
    }

    public function testSendHTMLEmail()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', 'Foo bar');
        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;
        $message->setBody("<!DOCTYPE html>
                <html>
                <body>

                <h1>My First Heading</h1>

                <p>My first paragraph.</p>

                </body>
                </html>
                ", "text/html");
        $mailjetMessage = $transport->getMailjetMessage($message);
        $result = $transport->send($message);

        $this->assertEquals("<!DOCTYPE html>
                <html>
                <body>

                <h1>My First Heading</h1>

                <p>My first paragraph.</p>

                </body>
                </html>
                ", $mailjetMessage['HTMLPart']);
        $this->assertMessageSendable($message);
    }

    public function testSendTextEmailWithTemplateId()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', 'Foo bar');
        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;
        $message->setBody("Hello world!");
        $message->getHeaders()->addTextHeader('X-MJ-TemplateID', 'azertyuiop');
        $mailjetMessage = $transport->getMailjetMessage($message);
        $result = $transport->send($message);

        $this->assertEquals('azertyuiop', $mailjetMessage['TemplateID']);
        $this->assertMessageSendable($message);
    }

    public function testSendEmailWithAllCustomHeaders()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', 'Foo bar');
        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;
        $message->setBody("Hello world!");
        $message->getHeaders()->addTextHeader('X-MJ-TemplateID', 'azertyuiop');
        $message->getHeaders()->addTextHeader('X-MJ-TemplateLanguage', true);
        $message->getHeaders()->addTextHeader('X-MJ-TemplateErrorReporting', 'air-traffic-control@mailjet.com');
        $message->getHeaders()->addTextHeader('X-MJ-TemplateErrorDeliver', 'deliver');
        $message->getHeaders()->addTextHeader('X-Mailjet-Prio', 3);
        $message->getHeaders()->addTextHeader('X-Mailjet-Campaign', 'azertyuiop');
        $message->getHeaders()->addTextHeader('X-Mailjet-DeduplicateCampaign', false);
        $message->getHeaders()->addTextHeader('X-Mailjet-TrackOpen', 1);
        $message->getHeaders()->addTextHeader('X-Mailjet-TrackClick', 2);
        $message->getHeaders()->addTextHeader('X-MJ-CustomID', 'PassengerEticket1234');
        $message->getHeaders()->addTextHeader('X-MJ-EventPayLoad', 'Eticket,1234,row,15,seat,B');
        $message->getHeaders()->addTextHeader('X-MJ-Vars', array('today'=>'monday'));

        $mailjetMessage = $transport->getMailjetMessage($message);

        $result = $transport->send($message);

        $this->assertEquals('azertyuiop', $mailjetMessage['TemplateID']);
        $this->assertEquals(true, $mailjetMessage['TemplateLanguage']);
        $this->assertEquals('air-traffic-control@mailjet.com', $mailjetMessage['TemplateErrorReporting']);
        $this->assertEquals('deliver', $mailjetMessage['TemplateErrorDeliver']);
        $this->assertEquals(3, $mailjetMessage['Priority']);
        $this->assertEquals('azertyuiop', $mailjetMessage['CustomCampaign']);
        $this->assertEquals(false, $mailjetMessage['DeduplicateCampaign']);
        $this->assertEquals(1, $mailjetMessage['TrackOpens']);
        $this->assertEquals(2, $mailjetMessage['TrackClicks']);
        $this->assertEquals('PassengerEticket1234', $mailjetMessage['CustomID']);
        $this->assertEquals('Eticket,1234,row,15,seat,B', $mailjetMessage['EventPayload']);
        $this->assertEquals(array('today'=>'monday'), $mailjetMessage['Variables']);
        $this->assertMessageSendable($message);
    }

    public function testMultipartNullContentType()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', 'Foo bar');
        $message
            ->addPart('Foo bar', 'text/plain')
            ->addPart('<p>Foo bar</p>', 'text/html')
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
        ;
        $mailjetMessage = $transport->getMailjetMessage($message);

        $result = $transport->send($message);

        $this->assertEquals('Foo bar', $mailjetMessage['TextPart'], 'Multipart email should contain plaintext message');
        $this->assertEquals('<p>Foo bar</p>', $mailjetMessage['HTMLPart'], 'Multipart email should contain HTML message');
        $this->assertMessageSendable($message);
    }


    public function testMessage()
    {
        $transport = $this->createTransport();
        $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');
        $attachment = new \Swift_Attachment($this->createPngContent(), 'filename.png', 'image/png');
        $message->attach($attachment);
        $message
            ->addTo('to@example.com', 'To Name')
            ->addFrom('from@example.com', 'From Name')
            ->addCc('cc-1@example.com', 'CC 1 Name')
            ->addCc('cc-2@example.com', 'CC 2 Name')
            ->addBcc('bcc-1@example.com', 'BCC 1 Name')
            ->addBcc('bcc-2@example.com', 'BCC 2 Name')
            ->addReplyTo('reply-to@example.com', 'Reply To Name')
        ;
        $mailjetMessage = $transport->getMailjetMessage($message);

        $result = $transport->send($message);

        $this->assertEquals('<p>Foo bar</p>', $mailjetMessage['HTMLPart']);
        $this->assertNull($mailjetMessage['TextPart'], 'HTML only email should not contain plaintext counterpart');
        $this->assertEquals('Test Subject', $mailjetMessage['Subject']);
        $this->assertEquals('from@example.com', $mailjetMessage['From']['Email']);
        $this->assertEquals('From Name', $mailjetMessage['From']['Name']);

        $this->assertMailjetMessageContainsRecipient('to@example.com', 'To Name', 'To', $mailjetMessage);
        $this->assertMailjetMessageContainsRecipient('cc-1@example.com', 'CC 1 Name', 'Cc', $mailjetMessage);
        $this->assertMailjetMessageContainsRecipient('cc-2@example.com', 'CC 2 Name', 'Cc', $mailjetMessage);
        $this->assertMailjetMessageContainsRecipient('bcc-1@example.com', 'BCC 1 Name', 'Bcc', $mailjetMessage);
        $this->assertMailjetMessageContainsRecipient('bcc-2@example.com', 'BCC 2 Name', 'Bcc', $mailjetMessage);

        $this->assertMailjetMessageContainsAttachment('image/png', 'filename.png', $this->createPngContent(), $mailjetMessage);

        $this->assertArrayHasKey('ReplyTo', $mailjetMessage);
        $this->assertEquals(['Email' => 'reply-to@example.com', 'Name' => 'Reply To Name'], $mailjetMessage['ReplyTo']);

        $this->assertMessageSendable($message);
    }

    public function testBulkSendMessages()
    {
        $transport = $this->createTransport();

        $messages = [];
        for ($i=0; $i < 4; $i++) {
            $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');
            $attachment = new \Swift_Attachment($this->createPngContent(), 'filename.png', 'image/png');
            $message->attach($attachment);
            $message
                ->addTo('to@example.com', 'To Name')
                ->addFrom('from@example.com', 'From Name')
                ->addCc('cc-1@example.com', 'CC 1 Name')
                ->addCc('cc-2@example.com', 'CC 2 Name')
                ->addBcc('bcc-1@example.com', 'BCC 1 Name')
                ->addBcc('bcc-2@example.com', 'BCC 2 Name')
                ->addReplyTo('reply-to@example.com', 'Reply To Name')
            ;

            array_push($messages, $message);
        }


        $result = $transport->bulkSend($messages);

        foreach ($messages as $message) {
            $mailjetMessage = $transport->getMailjetMessage($message);
            $this->assertEquals('<p>Foo bar</p>', $mailjetMessage['HTMLPart']);
            $this->assertNull($mailjetMessage['TextPart'], 'HTML only email should not contain plaintext counterpart');
            $this->assertEquals('Test Subject', $mailjetMessage['Subject']);
            $this->assertEquals('from@example.com', $mailjetMessage['From']['Email']);
            $this->assertEquals('From Name', $mailjetMessage['From']['Name']);

            $this->assertMailjetMessageContainsRecipient('to@example.com', 'To Name', 'To', $mailjetMessage);
            $this->assertMailjetMessageContainsRecipient('cc-1@example.com', 'CC 1 Name', 'Cc', $mailjetMessage);
            $this->assertMailjetMessageContainsRecipient('cc-2@example.com', 'CC 2 Name', 'Cc', $mailjetMessage);
            $this->assertMailjetMessageContainsRecipient('bcc-1@example.com', 'BCC 1 Name', 'Bcc', $mailjetMessage);
            $this->assertMailjetMessageContainsRecipient('bcc-2@example.com', 'BCC 2 Name', 'Bcc', $mailjetMessage);

            $this->assertMailjetMessageContainsAttachment('image/png', 'filename.png', $this->createPngContent(), $mailjetMessage);

            $this->assertArrayHasKey('ReplyTo', $mailjetMessage);
            $this->assertEquals(['Email' => 'reply-to@example.com', 'Name' => 'Reply To Name'], $mailjetMessage['ReplyTo']);

            $this->assertMessageSendable($message);
        }
    }

    /**
     * @param string $email
     * @param string $name
     * @param string $type
     * @param array $message
     */
    protected function assertMailjetMessageContainsRecipient($email, $name, $type, array $message)
    {
        foreach ($message[$type] as $recipient) {
            if ($recipient['Email'] === $email && $recipient['Name'] === $name) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail(sprintf('Expected Mailjet message "to" contain %s recipient %s <%s>', $type, $email, $name));
    }

    /**
     * @param string $type
     * @param string $name
     * @param string $content
     * @param array $message
     */
    protected function assertMailjetMessageContainsAttachment($type, $name, $content, array $message)
    {
        foreach ($message['Attachments'] as $attachment) {
            if ($attachment['ContentType'] === $type && $attachment['Filename'] === $name) {
                $this->assertEquals($content, base64_decode($attachment['Base64Content']));
                return;
            }
        }
        $this->fail(sprintf('Expected Mailjet message to contain a %s attachment named %s', $type, $name));
    }

    /**
     * Performs a test send through the Mailjet API. Provides details of failure if there are any problems.
     * @param MailjetTransport|null $transport
     * @param \Swift_Message $message
     */
    protected function assertMessageSendable(\Swift_Message $message, $transport = null)
    {
        if (!$transport) {
            $transport = $this->createTransport();
        }
        $this->assertNotNull($transport->getApiKey(), 'No API key specified');
        $this->assertNotNull($transport->getApiSecret(), 'No API Secret specified');

        $parameters = array(
            'Messages' => $transport->getMailjetMessage($message)
        );

        try {
            $configuration = new MessageSendConfiguration();
            $processor = new Processor();
            $processor->processConfiguration($configuration, $parameters);
        } catch (\Exception $e) {
            $this->fail(sprintf(
                "Mailjet message contains errors, %s\n\n%s",
                $e->getMessage(),
                json_encode($parameters['Messages'], JSON_PRETTY_PRINT)
            ));
        }
    }

    protected function createPngContent()
    {
        return base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEX/TQBcNTh/AAAAAXRSTlPM0jRW/QAAAApJREFUeJxjYgAAAAYAAzY3fKgAAAAASUVORK5CYII=");
    }
}
