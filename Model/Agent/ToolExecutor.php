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

namespace MageStack\Agent\Model\Agent;

use Closure;
use MageStack\Agent\Model\Tool\ToolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Dispatches tool calls, asks for approval on risky tools and never lets an exception escape;
 *
 * Class ToolExecutor
 *
 * @namespace MageStack\Agent\Model\Agent
 */
class ToolExecutor
{
    /** @var array<string,ToolInterface> */
    private array $tools = [];

    /**
     * @param ToolInterface[] $tools
     * @param Closure(ToolInterface,array<string,mixed>):bool $confirm
     * @param LoggerInterface $logger
     * @param int $maxOutput
     */
    public function __construct(
        array $tools,
        private readonly Closure $confirm,
        private readonly LoggerInterface $logger,
        private readonly int $maxOutput = 8000
    ) {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @param string $name
     * @param array<string,mixed> $args
     * @return string Result text (errors are returned as "ERROR: ...")
     */
    public function execute(string $name, array $args): string
    {
        if (!isset($this->tools[$name])) {
            return sprintf('ERROR: Unknown tool "%s". Available tools: %s', $name, implode(', ', array_keys($this->tools)));
        }

        $tool = $this->tools[$name];

        try {
            if ($tool->requiresConfirmation() && !($this->confirm)($tool, $args)) {
                return 'ERROR: The user declined this action. Do not retry it; ask the user how to proceed or choose another approach.';
            }

            return $this->truncate($tool->execute($args));
        } catch (Throwable $e) {
            $this->logger->warning('[MageStack][Agent][ToolExecutor] Tool execution failed', [
                'tool'          => $name,
                'arg_keys'      => array_keys($args),
                'error_message' => $e->getMessage(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);

            return 'ERROR: ' . $e->getMessage();
        }
    }

    /**
     * @param string $output
     * @return string
     */
    private function truncate(string $output): string
    {
        if (strlen($output) <= $this->maxOutput) {
            return $output;
        }

        return substr($output, 0, $this->maxOutput) . sprintf("\n... [output truncated, %d bytes omitted]", strlen($output) - $this->maxOutput);
    }
}
