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

namespace MageStack\Agent\Model\Service;

use MageStack\Agent\Api\AgentSessionInteractionInterface;
use MageStack\Agent\Api\ToolInterface;
use MageStack\Agent\Model\Agent\Agent;
use MageStack\Agent\Model\Agent\AgentRuntimeBuilder;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Coordinates one-shot and interactive agent runs.
 */
class AgentSessionService
{
    private const IGNORE = ['.git', 'vendor', 'node_modules', 'generated', 'var', 'pub/static', 'pub/media'];

    private const TERMINAL_ALLOWLIST = [
        'php',
        'xml-lint',
        'composer',
        'git',
        'bin/magento',
        'vendor/bin/phpunit',
        'vendor/bin/phpcs',
        'ls',
        'cat',
        'grep',
        'find',
    ];

    /**
     * @param AgentConfigurationProvider $configurationProvider
     * @param AgentRuntimeBuilder $agentRuntimeBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AgentConfigurationProvider $configurationProvider,
        private readonly AgentRuntimeBuilder $agentRuntimeBuilder,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * @param AgentSessionRequest $request
     * @param AgentSessionInteractionInterface $interaction
     * @return bool
     */
    public function run(AgentSessionRequest $request, AgentSessionInteractionInterface $interaction): bool
    {
        try {
            $configuration = $this->configurationProvider->get(
                $request->getWorkspaceOverride(),
                $request->getModelOverride()
            );
            $agent = $this->agentRuntimeBuilder->create(
                $configuration['workspace'],
                $configuration['host'],
                $configuration['model'],
                $configuration['numCtx'],
                $this->logger,
                static function (ToolInterface $tool, array $arguments) use ($interaction, $request): bool {
                    return $interaction->confirm($tool, $arguments, $request->shouldAutoApprove());
                },
                static function (string $type, string $text) use ($interaction): void {
                    $interaction->onEvent($type, $text);
                },
                self::IGNORE,
                self::TERMINAL_ALLOWLIST
            );
        } catch (Throwable $exception) {
            $this->logger->error('[MageStack][Agent][Bootstrap] Fatal error', [
                'error_message' => $exception->getMessage(),
                'stack_trace' => $exception->getTraceAsString(),
            ]);
            $interaction->write('<error>Fatal: ' . $exception->getMessage() . "</error>\n");

            return false;
        }

        $task = trim((string) $request->getTask());
        if ($task !== '') {
            return $this->runTask($agent, $task, $interaction);
        }

        if (!$interaction->isInteractive()) {
            $interaction->write('<error>A task is required when input is not interactive.</error>' . "\n");

            return false;
        }

        $interaction->write(sprintf("mage-agent (%s) - workspace: %s\n", $configuration['model'], $configuration['workspace']));
        $interaction->write("Type a task, or 'exit' to quit.\n");

        $sessionSuccessful = true;
        while (true) {
            $line = $interaction->readTask();
            if ($line === null) {
                break;
            }
            $line = trim($line);
            if (in_array(strtolower($line), ['exit', 'quit'], true)) {
                break;
            }
            if ($line === '') {
                continue;
            }
            $sessionSuccessful = $this->runTask($agent, $line, $interaction) && $sessionSuccessful;
        }

        return $sessionSuccessful;
    }

    /**
     * @param Agent $agent
     * @param string $task
     * @param AgentSessionInteractionInterface $interaction
     * @return bool
     */
    private function runTask(
        Agent $agent,
        string $task,
        AgentSessionInteractionInterface $interaction
    ): bool {
        $answer = $agent->run($task);
        $interaction->write("\n" . $answer . "\n");

        return $agent->wasSuccessful();
    }
}
