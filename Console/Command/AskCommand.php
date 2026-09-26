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
use MageStack\Agent\Model\Llm\OllamaClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Sends a plain question to Ollama without loading the coding-agent tools or context.
 */
class AskCommand extends Command
{
    private const DEFAULT_OLLAMA_HOST = 'http://host.docker.internal:11434';
    private const DEFAULT_MODEL = 'qwen2.5-coder:14b';
    private const DEFAULT_NUM_CTX = 2048;

    public function __construct(
        private readonly LoggerInterface $logger,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('magestack:agent:ask')
            ->setDescription('Ask the configured Ollama model a question without agent tools')
            ->addArgument('question', InputArgument::REQUIRED, 'Question to ask the model')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Ollama model (defaults to OLLAMA_MODEL or ' . self::DEFAULT_MODEL . ')')
            ->addOption('num-ctx', null, InputOption::VALUE_REQUIRED, 'Context window size (default: ' . self::DEFAULT_NUM_CTX . ')');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $question = trim((string) $input->getArgument('question'));
        if ($question === '') {
            $output->writeln('<error>Question cannot be empty.</error>');

            return Cli::RETURN_FAILURE;
        }

        $model = (string) ($input->getOption('model') ?: (getenv('OLLAMA_MODEL') ?: self::DEFAULT_MODEL));
        $host = (string) (getenv('OLLAMA_HOST') ?: self::DEFAULT_OLLAMA_HOST);
        $numCtxOption = $input->getOption('num-ctx');
        $numCtx = (int) ($numCtxOption !== null
            ? $numCtxOption
            : (getenv('OLLAMA_ASK_NUM_CTX') ?: self::DEFAULT_NUM_CTX));
        if ($numCtx < 512 || $numCtx > 131072) {
            $output->writeln('<error>--num-ctx must be between 512 and 131072.</error>');

            return Cli::RETURN_FAILURE;
        }

        try {
            $client = new OllamaClient($host, $model, 0.1, $numCtx, 300, $this->logger);
            $answer = $client->chat([
                ['role' => 'user', 'content' => $question],
            ], false);
            $output->writeln($answer);

            return Cli::RETURN_SUCCESS;
        } catch (Throwable $e) {
            $this->logger->error('[MageStack][Agent][Ask] Request failed', [
                'model'         => $model,
                'num_ctx'       => $numCtx,
                'error_message' => $e->getMessage(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }
    }
}
