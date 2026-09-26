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

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Lists files and directories under a path while skipping ignored folders;
 *
 * Class ListFilesTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class ListFilesTool extends AbstractTool
{
    private const MAX_ENTRIES = 300;

    public function name(): string
    {
        return 'list_files';
    }

    public function description(): string
    {
        return 'List files and folders (folders end with /). Vendor, generated, var and .git are skipped.';
    }

    public function parameters(): string
    {
        return 'path (string, optional, default "."); depth (int, optional, default 2)';
    }

    /**
     * @param array<string,mixed> $args
     * @return string
     */
    public function execute(array $args): string
    {
        $base = $this->resolvePath((string) ($args['path'] ?? '.'));
        $depth = max(1, min(6, (int) ($args['depth'] ?? 2)));
        $relativeBase = str_replace(DIRECTORY_SEPARATOR, '/', $this->relative($base));
        if ($this->isIgnoredPath($relativeBase)) {
            return 'No files.';
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                function (SplFileInfo $file): bool {
                    if ($file->isLink()) {
                        return false;
                    }
                    $relative = str_replace(DIRECTORY_SEPARATOR, '/', $this->relative($file->getPathname()));
                    return !$this->isDeniedPath($relative) && !$this->isIgnoredPath($relative);
                }
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        $iterator->setMaxDepth($depth - 1);

        $entries = [];
        foreach ($iterator as $file) {
            $entries[] = $this->relative($file->getPathname()) . ($file->isDir() ? '/' : '');
            if (count($entries) >= self::MAX_ENTRIES) {
                $entries[] = '... (truncated, narrow the path)';
                break;
            }
        }
        sort($entries);

        return $entries === [] ? '(empty)' : implode("\n", $entries);
    }
}
