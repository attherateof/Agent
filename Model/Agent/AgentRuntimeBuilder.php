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
use MageStack\Agent\Model\Context\CodebaseContextFactory;
use MageStack\Agent\Model\Context\MagentoContextFactory;
use MageStack\Agent\Model\Llm\OllamaClientFactory;
use Psr\Log\LoggerInterface;

/**
 * Builds an agent and its runtime-dependent collaborators for one CLI invocation.
 */
class AgentRuntimeBuilder
{
    public function __construct(
        private readonly OllamaClientFactory $ollamaClientFactory,
        private readonly PlannerFactory $plannerFactory,
        private readonly ToolExecutorFactory $toolExecutorFactory,
        private readonly AgentFactory $agentFactory,
        private readonly CodebaseContextFactory $codebaseContextFactory,
        private readonly MagentoContextFactory $magentoContextFactory,
        private readonly ToolSetBuilder $toolSetBuilder
    ) {}

    /**
     * @param string[] $ignore
     * @param string[] $terminalAllowlist
     */
    public function create(
        string $workspace,
        string $host,
        string $model,
        int $numCtx,
        LoggerInterface $logger,
        Closure $confirm,
        ?Closure $onEvent,
        array $ignore,
        array $terminalAllowlist
    ): Agent {
        $tools = $this->toolSetBuilder->create($workspace, $logger, $ignore, $terminalAllowlist);
        $contextBlocks = [
            $this->codebaseContextFactory->create([
                'workspace' => $workspace,
                'ignore' => $ignore,
            ])->render(),
            $this->magentoContextFactory->create([
                'workspace' => $workspace,
            ])->render(),
        ];

        $client = $this->ollamaClientFactory->create([
            'host' => $host,
            'model' => $model,
            'temperature' => 0.1,
            'numCtx' => $numCtx,
            'timeout' => 300,
            'logger' => $logger,
        ]);
        $planner = $this->plannerFactory->create([
            'tools' => $tools,
            'contextBlocks' => $contextBlocks,
        ]);
        $executor = $this->toolExecutorFactory->create([
            'tools' => $tools,
            'confirm' => $confirm,
            'logger' => $logger,
            'maxOutput' => 8000,
        ]);

        return $this->agentFactory->create([
            'llm' => $client,
            'planner' => $planner,
            'executor' => $executor,
            'logger' => $logger,
            'maxSteps' => 15,
            'maxMessages' => 40,
            'onEvent' => $onEvent,
        ]);
    }
}
