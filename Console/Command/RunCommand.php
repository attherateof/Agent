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

namespace MageStack\Agent\Console\Command;

use Magento\Framework\Console\Cli;
use MageStack\Agent\Console\AgentConsolePresenterFactory;
use MageStack\Agent\Model\Service\AgentSessionRequestFactory;
use MageStack\Agent\Model\Service\AgentSessionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI entry point that wires the agent to Ollama and runs it against this Magento install, developer mode only;
 *
 * Class RunCommand
 *
 * @namespace MageStack\Agent\Console\Command
 */
class RunCommand extends Command
{
    private const DEFAULT_MODEL = 'qwen2.5-coder:14b';

    /**
     * @param AgentSessionService $agentSessionService
     * @param AgentConsolePresenterFactory $consolePresenterFactory
     * @param AgentSessionRequestFactory $agentSessionRequestFactory
     * @param string|null $name
     */
    public function __construct(
        private readonly AgentSessionService $agentSessionService,
        private readonly AgentConsolePresenterFactory $consolePresenterFactory,
        private readonly AgentSessionRequestFactory $agentSessionRequestFactory,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('magestack:agent:run')
            ->setDescription('Run the local-LLM coding agent against this Magento installation (developer mode only)')
            ->addArgument('task', InputArgument::OPTIONAL, 'Task for the agent; omit to start an interactive session')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Ollama model to use (default: ' . self::DEFAULT_MODEL . ')')
            ->addOption('yes', null, InputOption::VALUE_NONE, 'Auto-approve write_file, edit_file and terminal calls')
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Override the workspace root (defaults to the Magento root)');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consolePresenter = $this->consolePresenterFactory->create([
            'input' => $input,
            'output' => $output,
            'questionHelper' => $this->getHelper('question'),
        ]);
        $request = $this->agentSessionRequestFactory->create([
            'task' => $input->getArgument('task'),
            'workspaceOverride' => $input->getOption('workspace'),
            'modelOverride' => $input->getOption('model'),
            'autoApprove' => (bool) $input->getOption('yes'),
        ]);

        $successful = $this->agentSessionService->run($request, $consolePresenter);

        return $successful ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }
}
