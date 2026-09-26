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

namespace MageStack\Agent\Console;

use MageStack\Agent\Api\AgentSessionInteractionInterface;
use MageStack\Agent\Api\ToolInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Formats agent events and confirmation prompts for the CLI.
 */
class AgentConsolePresenter implements AgentSessionInteractionInterface
{
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     */
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly QuestionHelper $questionHelper
    ) {}

    /**
     * @return bool
     */
    public function isInteractive(): bool
    {
        return $this->input->isInteractive();
    }

    /**
     * @param ToolInterface $tool
     * @param array<string,mixed> $args
     * @param bool $autoApprove
     * @return bool
     */
    public function confirm(
        ToolInterface $tool,
        array $args,
        bool $autoApprove
    ): bool {
        if ($autoApprove) {
            return true;
        }
        if (!$this->input->isInteractive()) {
            $this->output->writeln(sprintf('<comment>%s skipped: not interactive and --yes was not passed.</comment>', $tool->name()));

            return false;
        }

        $preview = match ($tool->name()) {
            'edit_file' => sprintf(
                "Path: %s\n\nSearch for:\n%s\n\nReplace with:\n%s",
                (string) ($args['path'] ?? ''),
                self::indent((string) ($args['search'] ?? '')),
                self::indent((string) ($args['replace'] ?? ''))
            ),
            'write_file' => sprintf(
                "Path: %s\n\nContent:\n%s",
                (string) ($args['path'] ?? ''),
                self::indent((string) ($args['content'] ?? ''))
            ),
            default => (string) json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };

        $this->output->writeln(sprintf("\n<comment>%s wants to run:</comment>\n%s", $tool->name(), $preview));
        $question = new ConfirmationQuestion('Allow? [y/N] ', false);

        return (bool) $this->questionHelper->ask($this->input, $this->output, $question);
    }

    /**
     * @param string $type
     * @param string $text
     * @return void
     */
    public function onEvent(string $type, string $text): void
    {
        if ($type === 'tool' && preg_match('/^(edit_file|write_file)\s/', $text, $matches) === 1) {
            $this->output->writeln(sprintf('<info>> %s (approval details follow)</info>', $matches[1]));

            return;
        }

        if ($type === 'tool' && preg_match('/^(\S+)\s+(\{.*\})$/s', $text, $matches) === 1) {
            $arguments = json_decode($matches[2], true);
            if (is_array($arguments)) {
                $text = $matches[1] . "\n" . (string) json_encode(
                    $arguments,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            }
        }

        $this->output->writeln(($type === 'tool' ? '<info>> ' : '<comment>') . $text . '</>');
    }

    /**
     * @return string|null
     */
    public function readTask(): ?string
    {
        $answer = $this->questionHelper->ask($this->input, $this->output, new Question("\n> "));

        return is_string($answer) ? $answer : null;
    }

    /**
     * @param string $text
     * @return void
     */
    public function write(string $text): void
    {
        $this->output->write($text);
    }

    private static function indent(string $text): string
    {
        return '    ' . str_replace("\n", "\n    ", $text);
    }
}
