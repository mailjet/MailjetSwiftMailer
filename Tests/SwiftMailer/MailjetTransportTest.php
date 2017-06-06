<?php
namespace Welp\MailjetSwiftMailer\Tests\SwiftMailer;

use PHPUnit\Framework\TestCase;
use Welp\MailjetSwiftMailer\SwiftMailer\MailjetTransport;
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

        $this->assertEquals('Hello world!', $mailjetMessage['Text-part']);
        $this->assertMessageSendable($message);
    }

    /**
     * Performs a test send through the Mandrill API. Provides details of failure if there are any problems.
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
            'message' => $transport->getMailjetMessage($message)
        );

        try {
            $configuration = new MessageSendConfiguration();
            $processor = new Processor();
            $processor->processConfiguration($configuration, $parameters);
        } catch (\Exception $e) {
            $this->fail(sprintf(
                "Mailjet message contains errors, %s\n\n%s",
                $e->getMessage(),
                json_encode($parameters['message'], JSON_PRETTY_PRINT)
            ));
        }
    }
}
