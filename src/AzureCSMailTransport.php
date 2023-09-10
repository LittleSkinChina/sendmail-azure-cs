<?php

namespace LittleSkin\SendmailAzureCS;

use Log;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AzureCSMailTransport extends AbstractApiTransport
{
    private $apiVersion;
    private $endpoint; //Azure Communication Service Endpoint
    private $timestamp; // RFC 1123 timestamp string

    public function __construct(HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->apiVersion = config('mail.mailers.azure.api_version');
        $this->endpoint = config('mail.mailers.azure.endpoint');
        $this->timestamp = gmdate('D, d M Y H:i:s T');
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'azure';
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $payload = $this->getPayload($email, $envelope);
        $payloadJson = json_encode($payload);
        $payloadHashed = $this->getPayloadHash($payloadJson);

        $response = $this->client->request('POST', 'https://' . $this->endpoint . '/emails:send?api-version=' . $this->apiVersion, [
            'headers' => [
                'x-ms-date' => $this->timestamp,
                'x-ms-content-sha256' => $payloadHashed,
                'Authorization' => 'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=' . $this->getSignature($payloadHashed),
                'Content-Type' => 'application/json',
            ],
            'body' => $payloadJson,
        ]);

        try {
            $result = $response->toArray();
        } catch (DecodingExceptionInterface | HttpExceptionInterface) {
            $statusCode = $response->getStatusCode();
            foreach($payload['recipients']['to'] as $recipient) {
                Log::channel('azure-cs')->error('Faild to send email to [' . $payload['recipients']['to'][0]['address'] . '], status code=' . $statusCode . ', body=' . $response->getContent(false));
            }
            if($statusCode == 429) { // Rate limit exceeded
                throw new HttpTransportException('邮件发送失败，请稍后再试，或联系站点管理员。', $response);
            }
            throw new HttpTransportException('邮件发送失败，请联系站点管理员。详细错误：'.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            foreach($payload['recipients']['to'] as $recipient) {
                Log::channel('azure-cs')->error('Faild to send email to [' . $payload['recipients']['to'][0]['address'] . ']. Could not reach the Azure Communication Service server.');
            }
            throw new HttpTransportException('无法连接邮件发送服务器，请稍后再试，或联系站点管理员。详细错误：Could not reach the Azure Communication Service server.', $response, 0, $e);
        }

        foreach($payload['recipients']['to'] as $recipient) {
            Log::channel('azure-cs')->info('Email sent to [' . $recipient['address'] . '], id='  . $result['id']);
        }

        $sentMessage->setMessageId($result['id']);

        return $response;
    }

    private function getAccessKey(): string
    {
        return config('mail.mailers.azure.access_key');
    }

    private function getDisableUserTracking(): bool
    {
        return config('mail.mailers.azure.disable_user_tracking');
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        return [
            'senderAddress' => config('mail.from.address'),
            'recipients' => [
                'to' => $this->getRecipientAddresses($envelope->getRecipients($email, $envelope))
            ],
            'content' => [
                'subject' => $email->getSubject(),
                'html' => $email->getHtmlBody(),
            ],
            'userEngagementTrackingDisabled' => $this->getDisableUserTracking(),
        ];
    }

    private function getPayloadHash($payload): string
    {
        return base64_encode(hash('sha256', $payload, true));
    }

    protected function getRecipientAddresses($recipients): array
    {
        $addresses = [];
        foreach ($recipients as $recipient) {
            $addresses[] = ['address' => $recipient->getAddress()];
        }
        return $addresses;
    }

    private function getSignature($payloadHashed): string
    {
        $stringToSign = "POST\n" .
            '/emails:send?api-version=' . $this->apiVersion . "\n" .
            $this->timestamp . ';' . $this->endpoint . ';' . $payloadHashed;
        $signature = hash_hmac('sha256', $stringToSign, base64_decode($this->getAccessKey()), true);
        return base64_encode($signature);
    }
}
