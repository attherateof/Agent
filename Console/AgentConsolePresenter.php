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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Terminal;

/**
 * Formats agent events and confirmation prompts for the CLI.
 */
class AgentConsolePresenter implements AgentSessionInteractionInterface
{
    /** Types whose lines are never wrapped, so code and diffs stay copy-paste accurate */
    private const NO_WRAP_TYPES = ['code', 'diff'];

    /** Minimum content width to wrap to, even in a very narrow terminal */
    private const MIN_WRAP_WIDTH = 20;

    /** Characters consumed by the box border/padding on each side ("| " + " |") */
    private const BOX_CHROME_WIDTH = 6;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $questionHelper
     * @param Terminal $terminal
     */
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly QuestionHelper $questionHelper,
        private readonly Terminal $terminal
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

        $this->display('question', 'Approval required: ' . $tool->name(), 'Review the operation details below.');
        $this->display('statement', 'Target', (string) ($args['path'] ?? 'No path specified'));
        foreach (['search', 'replace', 'content'] as $codeKey) {
            if (isset($args[$codeKey]) && is_scalar($args[$codeKey])) {
                $this->display('code', ucfirst($codeKey), (string) $args[$codeKey]);
            }
        }
        if (!in_array($tool->name(), ['edit_file', 'write_file', 'delete_file'], true)) {
            $this->display(
                'code',
                'Arguments',
                (string) json_encode($args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }
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
        if ($type === 'result') {
            [$tool, $result] = array_pad(explode("\n", $text, 2), 2, '');
            $resultType = match ($tool) {
                'read_file', 'search_code' => 'code',
                'git_diff' => 'diff',
                default => str_starts_with($result, 'ERROR:') ? 'error' : 'statement',
            };
            $this->display($resultType, 'Result: ' . $tool, $result);

            return;
        }

        if ($type !== 'tool') {
            $this->display('statement', 'Agent thought', $text);

            return;
        }

        if (preg_match('/^(\S+)\s+(\{.*\})$/s', $text, $matches) === 1) {
            $arguments = json_decode($matches[2], true);
            if (is_array($arguments)) {
                $summary = [];
                foreach ($arguments as $key => $value) {
                    if (in_array($key, ['content', 'search', 'replace'], true) && is_scalar($value)) {
                        $this->display('code', ucfirst((string) $key), (string) $value);
                        continue;
                    }
                    $summary[] = ucfirst((string) $key) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value));
                }
                $this->display('tool', 'Tool: ' . $matches[1], implode("\n", $summary));

                return;
            }
        }

        $this->display('tool', 'Tool activity', $text);
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

    /**
     * @param string $type
     * @param string $heading
     * @param string $body
     * @return void
     */
    public function display(string $type, string $heading, string $body): void
    {
        $color = match ($type) {
            'question' => 'yellow',
            'error' => 'red',
            'final' => 'green',
            'tool' => 'magenta',
            'code', 'diff' => 'cyan',
            default => 'white',
        };

        $lines = explode("\n", $body);
        if (in_array($type, self::NO_WRAP_TYPES, true)) {
            // Trim trailing whitespace so box width reflects real content, not incidental
            // trailing spaces carried over from source files or diff output.
            $lines = array_map('rtrim', $lines);
        } else {
            $lines = $this->wrapLines($lines, $this->contentWidth());
        }
        $heading = rtrim($heading);

        // Width is measured from the RAW (unescaped) text. OutputFormatter::format() - called
        // internally by writeln() - strips the backslash that escape() adds back out of "\<"
        // right before printing, so the string's ESCAPED length is not its visible length on
        // screen. Padding computed from the escaped length under-pads any line containing "<"
        // once rendered, which is what produced the ragged right border. padForDisplay() pads
        // using the raw length instead, so every row lands at exactly $width once printed.
        $width = max(strlen($heading), ...array_map('strlen', $lines));
        $border = '+' . str_repeat('-', $width + 2) . '+';

        $this->output->writeln(sprintf('<fg=%s>%s</>', $color, $border));
        $this->output->writeln(sprintf(
            '<fg=%s;options=bold>| %s |</>',
            $color,
            $this->padForDisplay($heading, $width)
        ));
        $this->output->writeln(sprintf('<fg=%s>%s</>', $color, $border));
        foreach ($lines as $line) {
            $lineColor = match (true) {
                $type === 'diff' && str_starts_with($line, '+') => 'green',
                $type === 'diff' && str_starts_with($line, '-') => 'red',
                $type === 'diff' && str_starts_with($line, '@@') => 'cyan',
                $type === 'code' => 'cyan',
                default => 'white',
            };
            $this->output->writeln(sprintf(
                '<fg=%s>| %s |</>',
                $lineColor,
                $this->padForDisplay($line, $width)
            ));
        }
        $this->output->writeln(sprintf('<fg=%s>%s</>', $color, $border));
    }

    /**
     * Escapes "<" for safe rendering, then right-pads to $width using the RAW (pre-escape)
     * length. Console's formatter strips the escaping backslash back out of "\<" right before
     * printing, so the escaped string's length is never what actually appears on screen -
     * padding must be computed from the raw length or lines containing "<" end up short.
     *
     * @param string $line
     * @param int $width
     * @return string
     */
    private function padForDisplay(string $line, int $width): string
    {
        $pad = max(0, $width - strlen($line));

        return OutputFormatter::escape($line) . str_repeat(' ', $pad);
    }

    /**
     * Word-wrap each line to the given width, splitting only long words that don't fit
     * (e.g. long paths) rather than leaving them to overflow the box.
     *
     * @param string[] $lines
     * @param int $width
     * @return string[]
     */
    private function wrapLines(array $lines, int $width): array
    {
        $wrapped = [];
        foreach ($lines as $line) {
            if ($line === '') {
                $wrapped[] = '';
                continue;
            }
            foreach (explode("\n", wordwrap($line, $width, "\n", true)) as $segment) {
                $wrapped[] = $segment;
            }
        }

        return $wrapped;
    }

    /**
     * Usable content width for wrapped lines, based on the real terminal width where
     * available, minus the box border/padding, with a sane floor for narrow terminals.
     *
     * @return int
     */
    private function contentWidth(): int
    {
        $terminalWidth = $this->terminal->getWidth();

        return max(self::MIN_WRAP_WIDTH, $terminalWidth - self::BOX_CHROME_WIDTH);
    }
}
