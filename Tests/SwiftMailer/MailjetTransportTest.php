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
}
