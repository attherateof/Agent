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
use InvalidArgumentException;
use MageStack\Agent\Model\Llm\OllamaClient;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs the think, act, observe loop between the Ollama model and the tools;
 *
 * Class Agent
 *
 * @namespace MageStack\Agent\Model\Agent
 */
class Agent
{
    /** @var array<int,array{role:string,content:string}> */
    private array $messages;

    private bool $lastRunSuccessful = false;

    /**
     * @param OllamaClient $llm
     * @param Planner $planner
     * @param ToolExecutor $executor
     * @param LoggerInterface $logger
     * @param int $maxSteps
     * @param int $maxMessages
     * @param Closure(string,string):void|null $onEvent Receives ("thought"|"tool", text) for live output
     */
    public function __construct(
        private readonly OllamaClient $llm,
        private readonly Planner $planner,
        private readonly ToolExecutor $executor,
        private readonly LoggerInterface $logger,
        private readonly int $maxSteps = 15,
        private readonly int $maxMessages = 40,
        private readonly ?Closure $onEvent = null
    ) {
        $this->messages = [['role' => 'system', 'content' => $this->planner->buildSystemPrompt()]];
    }

    /**
     * Run one task. Conversation history is kept between calls (REPL friendly).
     *
     * @param string $task
     * @return string Final answer for the user
     */
    public function run(string $task): string
    {
        $this->lastRunSuccessful = false;
        $this->messages[] = ['role' => 'user', 'content' => $task];

        $lastSignature = '';
        $repeats = 0;
        $parseFailures = 0;
        $writtenPaths = [];

        for ($step = 1; $step <= $this->maxSteps; $step++) {
            $this->trimHistory();

            try {
                $raw = $this->llm->chat($this->messages);
            } catch (Throwable $e) {
                $this->logger->error('[MageStack][Agent][Loop] LLM call failed', [
                    'step'          => $step,
                    'error_message' => $e->getMessage(),
                    'stack_trace'   => $e->getTraceAsString(),
                ]);
                return 'LLM request failed: ' . $e->getMessage();
            }

            $this->messages[] = ['role' => 'assistant', 'content' => $raw];

            try {
                $plan = $this->planner->parse($raw);
            } catch (InvalidArgumentException $e) {
                $this->logger->warning('[MageStack][Agent][Loop] Unparseable model reply', [
                    'step'          => $step,
                    'error_message' => $e->getMessage(),
                    'stack_trace'   => $e->getTraceAsString(),
                ]);
                if (++$parseFailures >= 3) {
                    return 'The model kept returning invalid output; aborting.';
                }
                $this->messages[] = [
                    'role'    => 'user',
                    'content' => 'Invalid reply: ' . $e->getMessage() . ' Reply with ONE valid JSON object in the specified format.',
                ];
                continue;
            }
            $parseFailures = 0;

            if ($plan['thought'] !== '') {
                $this->emit('thought', $plan['thought']);
            }
            if ($plan['final'] !== null) {
                $this->lastRunSuccessful = true;
                return $plan['final'];
            }

            $tool = (string) $plan['tool'];
            $signature = $tool . json_encode($plan['args']);
            $repeats = $signature === $lastSignature ? $repeats + 1 : 0;
            $lastSignature = $signature;

            $this->emit('tool', $tool . ' ' . json_encode($plan['args'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            $result = null;
            if ($repeats >= 2) {
                $result = sprintf(
                    'ERROR: This exact tool call was repeated and was not executed again: %s. Do not repeat it. Continue with any remaining verification, then return a final response.',
                    $tool
                );
            }

            if ($result === null && in_array($tool, ['write_file', 'delete_file'], true)) {
                $path = self::normalizeWritePath((string) ($plan['args']['path'] ?? ''));
                if ($path !== '' && isset($writtenPaths[$path])) {
                    $result = sprintf(
                        'ERROR: %s for "%s" was blocked because this task already changed that path. The file was not changed again. Inspect it if needed, perform the appropriate validation, then return a final response.',
                        $tool,
                        $path
                    );
                }
            }

            if ($result === null) {
                $result = $this->executor->execute($tool, $plan['args']);
                if (in_array($tool, ['write_file', 'delete_file'], true) && !str_starts_with($result, 'ERROR:')) {
                    $writtenPaths[self::normalizeWritePath((string) ($plan['args']['path'] ?? ''))] = true;
                }
            }
            $this->emit('result', $tool . "\n" . $result);
            $this->messages[] = ['role' => 'user', 'content' => sprintf("TOOL RESULT (%s):\n%s", $tool, $result)];
        }

        return sprintf('Stopped after %d steps without a final answer.', $this->maxSteps);
    }

    public function wasSuccessful(): bool
    {
        return $this->lastRunSuccessful;
    }

    private static function normalizeWritePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $parts = array_filter(explode('/', $path), static fn(string $part): bool => $part !== '' && $part !== '.');

        return implode('/', $parts);
    }

    /**
     * Keep the system prompt plus the most recent messages so num_ctx is not exceeded.
     *
     * @return void
     */
    private function trimHistory(): void
    {
        if (count($this->messages) <= $this->maxMessages) {
            return;
        }

        $this->messages = array_merge(
            [$this->messages[0]],
            array_slice($this->messages, - ($this->maxMessages - 1))
        );
    }

    /**
     * @param string $type
     * @param string $text
     * @return void
     */
    private function emit(string $type, string $text): void
    {
        if ($this->onEvent !== null) {
            ($this->onEvent)($type, $text);
        }
    }
}
