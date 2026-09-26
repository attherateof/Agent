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
 * Public contract implemented by every agent tool.
 */
interface ToolInterface
{
    /**
     * @return string Unique snake_case name used by the model
     */
    public function name(): string;

    /**
     * @return string Description included in the agent prompt
     */
    public function description(): string;

    /**
     * @return string Human-readable parameter description
     */
    public function parameters(): string;

    /**
     * @return bool Whether the user must approve each call
     */
    public function requiresConfirmation(): bool;

    /**
     * @param array<string,mixed> $args
     * @return string Tool result returned to the model
     */
    public function execute(array $args): string;
}
