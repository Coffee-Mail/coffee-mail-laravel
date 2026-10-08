<?php

declare(strict_types=1);

namespace CoffeeMail\Laravel\Transport;

use CoffeeMail\CoffeeMail;
use CoffeeMail\Payloads\AttachmentPayload;
use CoffeeMail\Payloads\EmailPayload;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final class CoffeeMailTransport extends AbstractTransport
{
    /**
     * Cabeçalhos MIME padrão do Symfony que não devem ser repassados como custom headers.
     *
     * @var list<string>
     */
    private const IGNORED_MIME_HEADERS = [
        'from',
        'to',
        'cc',
        'bcc',
        'reply-to',
        'subject',
        'content-type',
        'mime-version',
        'date',
        'message-id',
    ];

    public function __construct(
        private readonly CoffeeMail $client,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $rawMessage = $message->getOriginalMessage();

        if (! $rawMessage instanceof Email) {
            throw new InvalidArgumentException(
                'O CoffeeMailTransport suporta apenas instâncias de Symfony\Component\Mime\Email.'
            );
        }

        $payload = $this->buildEmailPayload($rawMessage);

        [$data, $error] = $this->client->emails->send($payload);

        if ($error !== null) {
            $errorMessage = $error->getMessage();
            $statusCode = $error->status;

            throw new TransportException(
                "Falha ao despachar e-mail via CoffeeMail: {$errorMessage} (Status: {$statusCode})",
                $statusCode,
                $error
            );
        }

        if (is_array($data) && isset($data['id']) && is_string($data['id'])) {
            $message->setMessageId($data['id']);
        }
    }

    public function __toString(): string
    {
        return 'coffeemail';
    }

    /**
     * Converte o objeto MIME do Symfony em um EmailPayload estruturado para o CoffeeMail.
     */
    private function buildEmailPayload(Email $email): EmailPayload
    {
        $from = $this->formatAddresses($email->getFrom());
        if (empty($from)) {
            throw new InvalidArgumentException('O e-mail deve conter ao menos um remetente (From).');
        }

        $to = $this->formatAddresses($email->getTo());
        if (empty($to)) {
            throw new InvalidArgumentException('O e-mail deve conter ao menos um destinatário (To).');
        }

        $cc = $this->formatAddresses($email->getCc());
        $bcc = $this->formatAddresses($email->getBcc());
        $replyTo = $this->formatAddresses($email->getReplyTo());
        $replyToString = ! empty($replyTo) ? $replyTo[0]['email'] : null;

        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        $htmlString = is_string($htmlBody)
            ? $htmlBody
            : (is_resource($htmlBody) ? stream_get_contents($htmlBody) : null);

        $textString = is_string($textBody)
            ? $textBody
            : (is_resource($textBody) ? stream_get_contents($textBody) : null);

        $attachments = $this->buildAttachments($email);
        [$headers, $idempotencyKey, $isSandbox, $scheduledAt] = $this->extractHeaders($email);

        return new EmailPayload(
            from: $from[0],
            to: $to,
            subject: (string) ($email->getSubject() ?? ''),
            html: is_string($htmlString) ? $htmlString : null,
            text: is_string($textString) ? $textString : null,
            cc: ! empty($cc) ? $cc : null,
            bcc: ! empty($bcc) ? $bcc : null,
            replyTo: $replyToString,
            headers: ! empty($headers) ? $headers : null,
            attachments: ! empty($attachments) ? $attachments : null,
            idempotencyKey: $idempotencyKey,
            isSandbox: $isSandbox,
            scheduledAt: $scheduledAt,
        );
    }

    /**
     * @param iterable<Address> $addresses
     * @return list<array{email: string, name?: string}>
     */
    private function formatAddresses(iterable $addresses): array
    {
        $result = [];

        foreach ($addresses as $address) {
            $entry = ['email' => $address->getAddress()];
            $name = trim($address->getName());

            if ($name !== '') {
                $entry['name'] = $name;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return list<AttachmentPayload>
     */
    private function buildAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $part) {
            $filename = $part->getFilename() ?: 'attachment.bin';
            $body = $part->getBody();
            $contentType = $part->getMediaType() . '/' . $part->getMediaSubtype();

            $attachments[] = new AttachmentPayload(
                filename: $filename,
                content: base64_encode($body),
                contentType: $contentType,
            );
        }

        return $attachments;
    }

    /**
     * Extrai cabeçalhos customizados e flags de controle (Idempotência, Sandbox).
     *
     * @return array{0: array<string, string>, 1: string|null, 2: bool|null, 3: string|null}
     */
    private function extractHeaders(Email $email): array
    {
        $customHeaders = [];
        $idempotencyKey = null;
        $isSandbox = null;
        $scheduledAt = null;

        foreach ($email->getHeaders()->all() as $header) {
            $name = strtolower($header->getName());
            $value = $header->getBodyAsString();

            if (in_array($name, self::IGNORED_MIME_HEADERS, true)) {
                continue;
            }

            if ($name === 'x-idempotency-key' || $name === 'idempotency-key') {
                $idempotencyKey = $value;
                continue;
            }

            if ($name === 'x-coffeemail-sandbox') {
                $isSandbox = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                continue;
            }

            if ($name === 'x-coffeemail-scheduled-at') {
                $scheduledAt = $value;
                continue;
            }

            $customHeaders[$header->getName()] = $value;
        }

        return [$customHeaders, $idempotencyKey, $isSandbox, $scheduledAt];
    }
}
