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

namespace MageStack\Agent\Api;

/**
 * Provides effective Ollama settings for agent and ask modes.
 *
 * @api
 */
interface OllamaConfigurationInterface
{
    /**
     * @param string|null $modelOverride
     * @param int|null $numCtxOverride
     * @return array{host:string,model:string,numCtx:int}
     */
    public function getAgentConfiguration(?string $modelOverride = null, ?int $numCtxOverride = null): array;

    /**
     * @param string|null $modelOverride
     * @param int|null $numCtxOverride
     * @return array{host:string,model:string,numCtx:int}
     */
    public function getAskConfiguration(?string $modelOverride = null, ?int $numCtxOverride = null): array;
}
