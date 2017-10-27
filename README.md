# MailjetSwiftMailer

[![Build Status](https://travis-ci.org/mailjet/MailjetSwiftMailer.svg?branch=master)](https://travis-ci.org/mailjet/MailjetSwiftMailer)
[![Packagist](https://img.shields.io/packagist/v/mailjet/mailjet-swiftmailer.svg)](https://packagist.org/packages/mailjet/mailjet-swiftmailer)
[![Packagist](https://img.shields.io/packagist/dt/mailjet/mailjet-swiftmailer.svg)](https://packagist.org/packages/mailjet/mailjet-swiftmailer)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/mailjet/MailjetSwiftMailer/blob/master/LICENSE.md)

A SwiftMailer transport implementation for Mailjet
([NEW] we now support send API v3.1 )
[Mailjet Send API v3.1](https://dev.mailjet.com/guides/#send-api-v3-1-beta)
*Compatible Mailjet send API V3 and V3.1*

If you found any problem, feel free to open an issue!

## TODO

* Adding URL tags
* Sandbox Mode
* Improve unit-tests (lots of code duplications)

## Installation

Require the package with composer

```bash
composer require mailjet/mailjet-swiftmailer
```

## Usage Example

```php
$transport = new MailjetTransport($dispatchEvent, $apiKey, $apiSecret);
$transport->setClientOptions(['url' => "api.mailjet.com", 'version' => 'v3.1', 'call' => true]);


$transport->send($message);
```


(Send API v3 is selected by default)
## Mailjet client custom configuration

You can pass an array in transport's constructor or use `setClientOptions` function:

```php
$clientOptions = ['url' => "api.mailjet.com", 'version' => 'v3.1', 'call' => false];
$transport = new MailjetTransport($dispatchEvent, $apiKey, $apiSecret, $clientOptions);


or

$transport->setClientOptions(['url' => "api.mailjet.com", 'version' => 'v3.1', 'call' => true]);
```

Properties of $options:

* url (Default: api.mailjet.com) : domain name of the API
* version (Default: v3) : API version (only working for Mailjet API V3 +)
* call (Default: true) : turns on(true) / off the call to the API
* secured (Default: true) : turns on(true) / off the use of 'https'

## Mailjet custom headers

It is possible to set specific Mailjet headers or custom user-defined headers, through SwiftMailer. 

For example:

```php
$headers = $message->getHeaders();

$headers->addTextHeader('X-MJ-TemplateID', $templateId);
$headers->addTextHeader('X-MJ-TemplateLanguage', true);
$vars = array("myFirstVar" => "foo", "mySecondVar" => "bar");
$headers->addTextHeader('X-MJ-Vars', json_encode($vars));
```

Note: You need to `json_encode`your array of variables in order to be compatible with SMTP transport. 

* [Mailjet Email Headers documentation v3](https://dev.mailjet.com/guides/#send-api-json-properties)
* [Mailjet Email Headers documentation v3.1](https://dev.mailjet.com/guides/#adding-email-headers)

## Mailjet bulk sending

```php

$emails = ['f001@bar.com', 'f002@bar.com', 'f003@bar.com', 'f004@bar.com', 'f005@bar.com', 'f006@bar.com', ...]

$messages = [];
foreach ($emails as $email) {
    $message = new \Swift_Message('Test Subject', '<p>Foo bar</p>', 'text/html');
    $message
        ->addTo($email)
        ->addFrom('from@example.com', 'From Name')
        ->addReplyTo('reply-to@example.com', 'Reply To Name')
    ;

    array_push($messages, $message);
}
$transport = new MailjetTransport($dispatchEvent, $apiKey, $apiSecret);
$result = $transport->bulkSend($messages);

```

Note: does not work with Spool (SwiftMailer removed bulkSend from its API).

## Integration in Symfony

If you want to use MailjetTransport in your Symfony project follow these small steps:

1. `composer require mailjet/mailjet-swiftmailer`
2. Into your `services.yml`, register MailjetTransport:

```yaml
swiftmailer.mailer.transport.mailjet:
    class: Mailjet\MailjetSwiftMailer\SwiftMailer\MailjetTransport
    arguments:
        - "@swiftmailer.transport.eventdispatcher.mailjet"
        - "%mailjet.api_key%"
        - "%mailjet.secret_key%"
```

Note: We set `mailjet.api_key` and `mailjet.secret_key` into parameters.yml

3. Finally, configure SwiftMailer in your `config.yml`:

```yaml
# Swiftmailer Configuration
swiftmailer:
    transport: mailjet
```

Note: You can also inject your own `Mailjet\Client`:

```yaml
mailjet.transactionnal.client:
    class: "%mailjet.client.class%"
    arguments:
        - "%mailjet.api_key%"
        - "%mailjet.secret_key%"
        - %mailjet.transactionnal.call%
        - %mailjet.transactionnal.options%

swiftmailer.transport.eventdispatcher.mailjet:
    class: Swift_Events_SimpleEventDispatcher

swiftmailer.mailer.transport.mailjet:
    class: Mailjet\MailjetSwiftMailer\SwiftMailer\MailjetTransport
    arguments:
        - "@swiftmailer.transport.eventdispatcher.mailjet"
        - "%mailjet.api_key%"
        - "%mailjet.secret_key%"
        - %mailjet.transactionnal.call%
        - %mailjet.transactionnal.options%
    calls:
        - method: setExternalMailjetClient
          arguments:
              - '@mailjet.transactionnal.client'
```

## Mailjet references

* [Mailjet PHP Wrapper](https://github.com/mailjet/mailjet-apiv3-php)
* [Mailjet documentation v3: send transactional email](https://dev.mailjet.com/guides/#send-transactional-email)
* [Mailjet documentation v3.1: send transactional email](https://dev.mailjet.com/beta/#send-transactional-email)

## Execute Tests

```bash
vendor/bin/phpunit -c .
```

## Contributing

If you want to contribute to this project, look at [over here](CONTRIBUTING.md)
