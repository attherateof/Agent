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

namespace MageStack\Agent\Model\Context;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Builds a compact overview of the workspace layout for the system prompt;
 *
 * Class CodebaseContext
 *
 * @namespace MageStack\Agent\Model\Context
 */
class CodebaseContext
{
    private const MAX_FILES = 20000;

    /**
     * @param string $workspace
     * @param string[] $ignore
     */
    public function __construct(private readonly string $workspace, private readonly array $ignore = []) {}

    /**
     * @return string
     */
    public function render(): string
    {
        $root = rtrim($this->workspace, '/');
        $ignore = $this->ignore;

        $top = [];
        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!in_array($entry->getFilename(), $ignore, true)) {
                $top[] = $entry->getFilename() . ($entry->isDir() ? '/' : '');
            }
        }
        sort($top);

        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($ignore, $root): bool {
                $relative = ltrim(substr($file->getPathname(), strlen($root)), '/');
                return !in_array($file->getFilename(), $ignore, true) && !in_array($relative, $ignore, true);
            }
        ));

        $byType = [];
        $seen = 0;
        foreach ($iterator as $file) {
            if (++$seen > self::MAX_FILES) {
                break;
            }
            $ext = strtolower($file->getExtension()) ?: '(none)';
            $byType[$ext] = ($byType[$ext] ?? 0) + 1;
        }
        arsort($byType);

        $types = [];
        foreach (array_slice($byType, 0, 8, true) as $ext => $count) {
            $types[] = $ext . ':' . $count;
        }

        return sprintf("Workspace: %s\nTop level: %s\nFile types: %s", $root, implode(' ', $top), implode(' ', $types));
    }
}
