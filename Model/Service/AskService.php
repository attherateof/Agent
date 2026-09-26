<?php

/**
 * MageStack
 *
 * @category  MageStack
 * @package   MageStack_Agent
 * @author    Amit Biswas <amitbiswas.webdeveloper@gmail.com>
 * @license   MIT
 * @link      https://github.com/your-account/mage-agent
 */

declare(strict_types=1);

namespace MageStack\Agent\Model\Service;

use InvalidArgumentException;
use Magento\Framework\App\State;
use MageStack\Agent\Api\OllamaConfigurationInterface;
use MageStack\Agent\Model\Llm\OllamaClientFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Sends a standalone question to the configured Ollama model.
 */
class AskService
{
    /**
     * @param OllamaClientFactory $ollamaClientFactory
     * @param OllamaConfigurationInterface $ollamaConfiguration
     * @param State $appState
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OllamaClientFactory $ollamaClientFactory,
        private readonly OllamaConfigurationInterface $ollamaConfiguration,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @param string $question
     * @param string|null $modelOverride
     * @param int|null $numCtxOverride
     * @return string
     */
    public function ask(string $question, ?string $modelOverride, ?int $numCtxOverride): string
    {
        if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
            throw new RuntimeException(sprintf(
                'magestack:agent:ask only runs in developer mode (current mode: %s).',
                $this->appState->getMode()
            ));
        }

        $question = trim($question);
        if ($question === '') {
            throw new InvalidArgumentException('Question cannot be empty.');
        }

        $configuration = $this->ollamaConfiguration->getAskConfiguration($modelOverride, $numCtxOverride);
        $client = $this->ollamaClientFactory->create([
            'host' => $configuration['host'],
            'model' => $configuration['model'],
            'temperature' => 0.1,
            'numCtx' => $configuration['numCtx'],
            'timeout' => 300,
            'logger' => $this->logger,
        ]);

        return $client->chat([
            ['role' => 'user', 'content' => $question],
        ], false);
    }
}
