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
use MageStack\Agent\Api\OllamaConfigInterface;
use MageStack\Agent\Api\OllamaConfigurationInterface;

/**
 * Resolves common Ollama connection and generation settings.
 */
class OllamaConfigurationProvider implements OllamaConfigurationInterface
{
    private const DEFAULT_HOST = 'http://host.docker.internal:11434';
    private const DEFAULT_MODEL = 'qwen2.5-coder:14b';
    private const MIN_NUM_CTX = 512;
    private const MAX_NUM_CTX = 131072;

    /**
     * @param OllamaConfigInterface $configurationGetter
     */
    public function __construct(private readonly OllamaConfigInterface $configurationGetter) {}

    /**
     * @inheritDoc
     */
    public function getAgentConfiguration(?string $modelOverride = null, ?int $numCtxOverride = null): array
    {
        return $this->getConfiguration(
            $modelOverride,
            $numCtxOverride,
            $this->configurationGetter->getAgentContextSize(),
            'OLLAMA_NUM_CTX',
            8192
        );
    }

    /**
     * @inheritDoc
     */
    public function getAskConfiguration(?string $modelOverride = null, ?int $numCtxOverride = null): array
    {
        return $this->getConfiguration(
            $modelOverride,
            $numCtxOverride,
            $this->configurationGetter->getAskContextSize(),
            'OLLAMA_ASK_NUM_CTX',
            2048
        );
    }

    /**
     * @param string|null $modelOverride
     * @param int|null $numCtxOverride
     * @param int|null $configuredNumCtx
     * @param string $numCtxEnvironmentVariable
     * @param int $defaultNumCtx
     * @return array{host:string,model:string,numCtx:int}
     */
    private function getConfiguration(
        ?string $modelOverride,
        ?int $numCtxOverride,
        ?int $configuredNumCtx,
        string $numCtxEnvironmentVariable,
        int $defaultNumCtx
    ): array {
        $environmentHost = getenv('OLLAMA_HOST');
        $environmentModel = getenv('OLLAMA_MODEL');
        $environmentNumCtx = getenv($numCtxEnvironmentVariable);
        $numCtx = $numCtxOverride
            ?? (($environmentNumCtx !== false && $environmentNumCtx !== '')
                ? (int) $environmentNumCtx
                : ($configuredNumCtx ?? $defaultNumCtx));

        if ($numCtx < self::MIN_NUM_CTX || $numCtx > self::MAX_NUM_CTX) {
            throw new InvalidArgumentException(sprintf(
                'Context size must be between %d and %d tokens.',
                self::MIN_NUM_CTX,
                self::MAX_NUM_CTX
            ));
        }

        return [
            'host' => (string) ($environmentHost ?: ($this->configurationGetter->getHost() ?: self::DEFAULT_HOST)),
            'model' => (string) ($modelOverride ?: ($environmentModel ?: ($this->configurationGetter->getModel() ?: self::DEFAULT_MODEL))),
            'numCtx' => $numCtx,
        ];
    }
}
