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

namespace MageStack\Agent\Model\Tool;

use InvalidArgumentException;

/**
 * Reads a file (or a line range) and returns it with line numbers;
 *
 * Class ReadFileTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class ReadFileTool extends AbstractTool
{
    private const MAX_BYTES_WITHOUT_RANGE = 200000;

    public function name(): string
    {
        return 'read_file';
    }

    public function description(): string
    {
        return 'Read a text file. Output is prefixed with line numbers (the numbers are NOT part of the file).';
    }

    public function parameters(): string
    {
        return 'path (string, required); start_line (int, optional, default 1); end_line (int, optional)';
    }

    /**
     * @param array<string,mixed> $args
     * @return string
     */
    public function execute(array $args): string
    {
        $path = $this->resolvePath($this->requireString($args, 'path'));
        if (!is_file($path)) {
            throw new InvalidArgumentException('Not a file: ' . $this->relative($path));
        }

        $hasRange = isset($args['start_line']) || isset($args['end_line']);
        if (!$hasRange && filesize($path) > self::MAX_BYTES_WITHOUT_RANGE) {
            throw new InvalidArgumentException('File is large; pass start_line and end_line to read a slice.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new InvalidArgumentException('Unable to read file: ' . $this->relative($path));
        }

        $total = count($lines);
        $start = max(1, (int) ($args['start_line'] ?? 1));
        $end = min($total, (int) ($args['end_line'] ?? $total));

        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = sprintf('%5d| %s', $i, $lines[$i - 1]);
        }

        return sprintf("%s (lines %d-%d of %d)\n%s", $this->relative($path), $start, $end, $total, implode("\n", $out));
    }
}
