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
use MageStack\Agent\Model\Service\AskService;
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
    private const DEFAULT_MODEL = 'qwen2.5-coder:14b';
    private const DEFAULT_NUM_CTX = 2048;

    /**
     * @param AskService $askService
     * @param string|null $name
     */
    public function __construct(
        private readonly AskService $askService,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('magestack:agent:ask')
            ->setDescription('Ask the configured Ollama model a question without agent tools')
            ->addArgument('question', InputArgument::REQUIRED, 'Question to ask the model')
            ->addOption('model', null, InputOption::VALUE_REQUIRED, 'Ollama model (defaults to OLLAMA_MODEL or ' . self::DEFAULT_MODEL . ')')
            ->addOption('num-ctx', null, InputOption::VALUE_REQUIRED, 'Context window size (default: ' . self::DEFAULT_NUM_CTX . ')');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $numCtxOption = $input->getOption('num-ctx');
        $numCtx = $numCtxOption === null ? null : (int) $numCtxOption;

        try {
            $answer = $this->askService->ask(
                (string) $input->getArgument('question'),
                $input->getOption('model') === null ? null : (string) $input->getOption('model'),
                $numCtx
            );
            $output->writeln($answer);

            return Cli::RETURN_SUCCESS;
        } catch (Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }
    }
}
