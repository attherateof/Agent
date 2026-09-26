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

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\Filesystem\DirectoryList;
use MageStack\Agent\Model\Agent\Agent;
use MageStack\Agent\Model\Agent\Planner;
use MageStack\Agent\Model\Agent\ToolExecutor;
use MageStack\Agent\Model\Context\CodebaseContext;
use MageStack\Agent\Model\Context\MagentoContext;
use MageStack\Agent\Model\Llm\OllamaClient;
use MageStack\Agent\Model\Tool\EditFileTool;
use MageStack\Agent\Model\Tool\GitDiffTool;
use MageStack\Agent\Model\Tool\ListFilesTool;
use MageStack\Agent\Model\Tool\ReadFileTool;
use MageStack\Agent\Model\Tool\SearchCodeTool;
use MageStack\Agent\Model\Tool\TerminalTool;
use MageStack\Agent\Model\Tool\ToolInterface;
use MageStack\Agent\Model\Tool\WriteFileTool;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * CLI entry point that wires the agent to Ollama and runs it against this Magento install, developer mode only;
 *
 * Class RunCommand
 *
 * @namespace MageStack\Agent\Console\Command
 */
class RunCommand extends Command
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

    private const DEFAULT_OLLAMA_HOST = 'http://host.docker.internal:11434';
    private const DEFAULT_MODEL = 'qwen2.5-coder:14b';

    /**
     * @param State $appState
     * @param DirectoryList $directoryList
     * @param LoggerInterface $logger
     * @param string|null $name
     */
    public function __construct(
        private readonly State $appState,
        private readonly DirectoryList $directoryList,
        private readonly LoggerInterface $logger,
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
        if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
            $output->writeln(sprintf(
                '<error>magestack:agent:run only runs in developer mode (current mode: %s). '
                    . 'Run "bin/magento deploy:mode:set developer" first.</error>',
                $this->appState->getMode()
            ));

            return Cli::RETURN_FAILURE;
        }

        $workspaceOption = $input->getOption('workspace') ?: (getenv('AGENT_WORKSPACE') ?: null);
        $workspace = realpath((string) ($workspaceOption ?? $this->directoryList->getPath(DirectoryList::ROOT)));
        if ($workspace === false) {
            $output->writeln('<error>Workspace directory not found.</error>');

            return Cli::RETURN_FAILURE;
        }

        $model = (string) ($input->getOption('model') ?: (getenv('OLLAMA_MODEL') ?: self::DEFAULT_MODEL));
        $host = (string) (getenv('OLLAMA_HOST') ?: self::DEFAULT_OLLAMA_HOST);
        $autoApprove = (bool) $input->getOption('yes');

        try {
            // $llm = new OllamaClient($host, $model, 0.1, 16384, 300, $this->logger);
            // $llm = new OllamaClient($host, $model, 0.1, 4096, 300, $this->logger);
            $llm = new OllamaClient($host, $model, 0.1, 8192, 300, $this->logger);

            $tools = [
                new ListFilesTool($workspace, $this->logger, self::IGNORE),
                new SearchCodeTool($workspace, $this->logger, self::IGNORE),
                new ReadFileTool($workspace, $this->logger),
                new EditFileTool($workspace, $this->logger),
                new WriteFileTool($workspace, $this->logger),
                new GitDiffTool($workspace, $this->logger),
                new TerminalTool($workspace, $this->logger, self::TERMINAL_ALLOWLIST, 120),
            ];

            $confirm = static function (ToolInterface $tool, array $args) use ($autoApprove, $input, $output): bool {
                if ($autoApprove) {
                    return true;
                }
                if (!$input->isInteractive()) {
                    $output->writeln(sprintf('<comment>%s skipped: not interactive and --yes was not passed.</comment>', $tool->name()));

                    return false;
                }

                $preview = match ($tool->name()) {
                    'edit_file' => sprintf(
                        "Path: %s\n\nSearch for:\n%s\n\nReplace with:\n%s",
                        (string) ($args['path'] ?? ''),
                        self::indentPreview((string) ($args['search'] ?? '')),
                        self::indentPreview((string) ($args['replace'] ?? ''))
                    ),
                    'write_file' => sprintf(
                        "Path: %s\n\nContent:\n%s",
                        (string) ($args['path'] ?? ''),
                        self::indentPreview((string) ($args['content'] ?? ''))
                    ),
                    default => (string) json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                };
                $output->writeln(sprintf("\n<comment>%s wants to run:</comment>\n%s", $tool->name(), $preview));
                $output->write('Allow? [y/N] ');
                $answer = fgets(STDIN);

                return $answer !== false && in_array(strtolower(trim($answer)), ['y', 'yes'], true);
            };

            $onEvent = static function (string $type, string $text) use ($output): void {
                if ($type === 'tool' && preg_match('/^(edit_file|write_file)\s/', $text, $matches) === 1) {
                    $output->writeln(sprintf('<info>> %s (approval details follow)</info>', $matches[1]));

                    return;
                }

                if ($type === 'tool' && preg_match('/^(\S+)\s+(\{.*\})$/s', $text, $matches) === 1) {
                    $args = json_decode($matches[2], true);
                    if (is_array($args)) {
                        $text = $matches[1] . "\n" . (string) json_encode(
                            $args,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                        );
                    }
                }

                $output->writeln(($type === 'tool' ? '<info>> ' : '<comment>') . $text . '</>');
            };

            $planner = new Planner($tools, [
                (new CodebaseContext($workspace, self::IGNORE))->render(),
                (new MagentoContext($workspace))->render(),
            ]);

            $agent = new Agent(
                $llm,
                $planner,
                new ToolExecutor($tools, $confirm, $this->logger, 8000),
                $this->logger,
                15,
                40,
                $onEvent
            );
        } catch (Throwable $e) {
            $this->logger->error('[MageStack][Agent][Bootstrap] Fatal error', [
                'error_message' => $e->getMessage(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            $output->writeln('<error>Fatal: ' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }

        $task = trim((string) $input->getArgument('task'));
        if ($task !== '') {
            $answer = $agent->run($task);
            $output->writeln("\n" . $answer);

            return $agent->wasSuccessful() ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
        }

        $output->writeln(sprintf('mage-agent (%s) - workspace: %s', $model, $workspace));
        $output->writeln("Type a task, or 'exit' to quit.");

        $sessionSuccessful = true;
        while (true) {
            $output->write("\n> ");
            $line = fgets(STDIN);
            if ($line === false) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (in_array(strtolower($line), ['exit', 'quit'], true)) {
                break;
            }
            $answer = $agent->run($line);
            $output->writeln("\n" . $answer);
            $sessionSuccessful = $sessionSuccessful && $agent->wasSuccessful();
        }

        return $sessionSuccessful ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }

    private static function indentPreview(string $text): string
    {
        return '    ' . str_replace("\n", "\n    ", $text);
    }
}
