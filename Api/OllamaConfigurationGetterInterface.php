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
 * Reads persisted Ollama settings without resolving runtime overrides.
 *
 * @api
 */
interface OllamaConfigInterface
{
    /**
     * @return string|null
     */
    public function getHost(): ?string;

    /**
     * @return string|null
     */
    public function getModel(): ?string;

    /**
     * @return int|null
     */
    public function getAgentContextSize(): ?int;

    /**
     * @return int|null
     */
    public function getAskContextSize(): ?int;
}
