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

namespace MageStack\Agent\Model\Tool;

/**
 * Contract every agent tool must implement;
 *
 * Class ToolInterface
 *
 * @namespace MageStack\Agent\Model\Tool
 */
interface ToolInterface
{
    /**
     * @return string Unique snake_case tool name the model uses to call it
     */
    public function name(): string;

    /**
     * @return string One-line description shown to the model
     */
    public function description(): string;

    /**
     * @return string Human-readable argument list shown to the model
     */
    public function parameters(): string;

    /**
     * @return bool Whether the user must approve each call
     */
    public function requiresConfirmation(): bool;

    /**
     * @param array<string,mixed> $args
     * @return string Text result fed back to the model
     */
    public function execute(array $args): string;
}
