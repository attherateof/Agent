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
 * @api
 * Defines the interaction boundary for running an agent session.
 */
interface AgentSessionInteractionInterface
{
    /**
     * @return bool
     */
    public function isInteractive(): bool;

    /**
     * @param ToolInterface $tool
     * @param array<string,mixed> $arguments
     * @param bool $autoApprove
     * @return bool
     */
    public function confirm(ToolInterface $tool, array $arguments, bool $autoApprove): bool;

    /**
     * @param string $type
     * @param string $text
     * @return void
     */
    public function onEvent(string $type, string $text): void;

    /**
     * @return string|null
     */
    public function readTask(): ?string;

    /**
     * @param string $type
     * @param string $heading
     * @param string $body
     * @return void
     */
    public function display(string $type, string $heading, string $body): void;

    /**
     * @param string $text
     * @return void
     */
    public function write(string $text): void;
}
