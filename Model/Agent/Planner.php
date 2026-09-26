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

use InvalidArgumentException;
use MageStack\Agent\Api\ToolInterface;

/**
 * Builds the system prompt and parses the model reply into a tool call or final answer;
 *
 * Class Planner
 *
 * @namespace MageStack\Agent\Model\Agent
 */
class Planner
{
    private const BASE_PROMPT = <<<'PROMPT'
You are mage-agent, an autonomous coding assistant working inside a Magento 2 installation. You act by calling tools, one per turn, and then reading the result.

OUTPUT FORMAT - reply with exactly ONE JSON object and nothing else (no markdown, no code fences):
  Call a tool:  {"thought": "<one short sentence>", "tool": "<tool name>", "args": {<arguments>}}
  Finish:       {"thought": "<one short sentence>", "final": "<answer for the user>"}

RULES:
1. Explore before you change anything: list files, search, then read the relevant files.
2. Never guess file contents or paths. Use tools to check.
3. Prefer edit_file with a small, exact "search" snippet over rewriting whole files with write_file.
4. Paths are relative to the workspace root. You cannot leave the workspace.
5. Verify changes with a check appropriate to the file type: use `php -l <file.php>` only for PHP files and `xml-lint <file.xml>` for XML well-formedness. Never run PHP lint on XML, JSON, JavaScript, CSS, or other non-PHP files. `xml-lint` does not validate Magento XSD/schema rules; only claim schema validation if an available tool actually performs it, otherwise say it was not checked.
6. After a file is successfully created or edited, do not recreate or rewrite it. If more changes are needed, use `edit_file` with a focused replacement.
7. After all requested files are written and the relevant checks pass, return a `final` response immediately. Do not restart exploration or repeat completed file writes.
8. If the agent blocks a duplicate write, do not stop or retry the write. Verify the already-written file with the appropriate available check, then return a `final` response.
9. If a tool returns ERROR, read the message and adjust. Do not repeat the same failing call.
10. Finish with a concise summary of what you found or changed. Escape newlines inside JSON strings as \n.
PROMPT;

    /**
     * @param ToolInterface[] $tools
     * @param string[] $contextBlocks
     */
    public function __construct(private readonly array $tools, private readonly array $contextBlocks = []) {}

    /**
     * @return string
     */
    public function buildSystemPrompt(): string
    {
        $toolLines = [];
        foreach ($this->tools as $tool) {
            $toolLines[] = sprintf(
                "- %s: %s\n  args: %s%s",
                $tool->name(),
                $tool->description(),
                $tool->parameters(),
                $tool->requiresConfirmation() ? "\n  (the user must approve each call)" : ''
            );
        }

        $context = trim(implode("\n\n", array_filter($this->contextBlocks)));

        return implode("\n\n", array_filter([
            self::BASE_PROMPT,
            "TOOLS:\n" . implode("\n", $toolLines),
            $context !== '' ? "PROJECT CONTEXT:\n" . $context : '',
        ]));
    }

    /**
     * @param string $raw
     * @return array{thought:string,tool:?string,args:array<string,mixed>,final:?string}
     * @throws InvalidArgumentException
     */
    public function parse(string $raw): array
    {
        $text = trim($raw);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        $data = json_decode($text, true);
        if (!is_array($data)) {
            $start = strpos($text, '{');
            $end = strrpos($text, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $data = json_decode(substr($text, $start, $end - $start + 1), true);
            }
        }
        if (!is_array($data)) {
            throw new InvalidArgumentException('Reply was not valid JSON.');
        }

        $thought = is_string($data['thought'] ?? null) ? $data['thought'] : '';

        if (isset($data['final']) && is_string($data['final'])) {
            return ['thought' => $thought, 'tool' => null, 'args' => [], 'final' => $data['final']];
        }
        if (isset($data['tool']) && is_string($data['tool']) && $data['tool'] !== '') {
            $args = $data['args'] ?? [];
            if (!is_array($args)) {
                throw new InvalidArgumentException('"args" must be a JSON object.');
            }
            return ['thought' => $thought, 'tool' => $data['tool'], 'args' => $args, 'final' => null];
        }

        throw new InvalidArgumentException('JSON must contain either "tool" (with "args") or "final".');
    }
}
