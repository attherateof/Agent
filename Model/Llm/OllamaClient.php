<?php

/**
 * MageStack
 *
 * @category  MageStack
 * @package   MageStack_Agent
 * @author    Amit Biswas <amit.biswas.webdeveloper@gmail.com>
 * @license   MIT
 * @link      https://github.com/your-account/mage-agent
 */

declare(strict_types=1);

namespace MageStack\Agent\Model\Llm;

use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Sends chat requests to a local Ollama server and returns the model reply;
 *
 * Class OllamaClient
 *
 * @namespace MageStack\Agent\Model\Llm
 */
class OllamaClient
{
    /**
     * @param string $host
     * @param string $model
     * @param float $temperature
     * @param int $numCtx
     * @param int $timeout
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly string $host,
        private readonly string $model,
        private readonly float $temperature,
        private readonly int $numCtx,
        private readonly int $timeout,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Send the conversation to Ollama and return the assistant message content.
     *
     * @param array<int,array{role:string,content:string}> $messages
     * @param bool $jsonMode Ask Ollama to constrain output to valid JSON
     * @return string
     * @throws RuntimeException
     */
    public function chat(array $messages, bool $jsonMode = true): string
    {
        $payload = [
            'model'    => $this->model,
            'messages' => $messages,
            'stream'   => false,
            'options'  => [
                'temperature' => $this->temperature,
                'num_ctx'     => $this->numCtx,
            ],
        ];
        if ($jsonMode) {
            $payload['format'] = 'json';
        }

        $ch = curl_init(rtrim($this->host, '/') . '/api/chat');
        if ($ch === false) {
            throw new RuntimeException('Unable to initialise cURL');
        }

        try {
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => $this->timeout,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
        } catch (JsonException $e) {
            $this->logger->error('[MageStack][Agent][Ollama] Failed to encode request', [
                'error_message' => $e->getMessage(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            throw new RuntimeException('Failed to encode Ollama request', 0, $e);
        }

        if ($raw === false || $status !== 200) {
            $this->logger->error('[MageStack][Agent][Ollama] Request failed', [
                'http_status'   => $status,
                'error_message' => $curlError !== '' ? $curlError : (string) $raw,
                'host'          => $this->host,
                'model'         => $this->model,
            ]);
            throw new RuntimeException(sprintf(
                'Ollama request failed (HTTP %d): %s',
                $status,
                $curlError !== '' ? $curlError : substr((string) $raw, 0, 300)
            ));
        }

        try {
            $data = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('[MageStack][Agent][Ollama] Invalid JSON in response', [
                'error_message' => $e->getMessage(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            throw new RuntimeException('Ollama returned invalid JSON', 0, $e);
        }

        return (string) ($data['message']['content'] ?? '');
    }
}
