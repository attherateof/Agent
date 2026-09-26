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
use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Searches file contents with grep using a regular expression;
 *
 * Class SearchCodeTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class SearchCodeTool extends AbstractTool
{
    private const MAX_LINES = 100;

    public function name(): string
    {
        return 'search_code';
    }

    public function description(): string
    {
        return 'Search file contents (extended regex, case-insensitive). Returns path:line:text matches.';
    }

    public function parameters(): string
    {
        return 'pattern (string, required); path (string, optional, default "."); glob (string, optional, e.g. "*.php")';
    }

    /**
     * @param array<string,mixed> $args
     * @return string
     */
    public function execute(array $args): string
    {
        $pattern = $this->requireString($args, 'pattern');
        $path = $this->resolvePath((string) ($args['path'] ?? '.'));
        $glob = isset($args['glob']) ? (string) $args['glob'] : null;
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $this->relative($path));
        if ($this->isIgnoredPath($relativePath)) {
            return 'No searchable files.';
        }

        if (is_file($path)) {
            $files = $glob === null || fnmatch($glob, basename($path)) ? [$path] : [];
        } elseif (is_dir($path)) {
            $filter = function (SplFileInfo $file) use ($glob): bool {
                if ($file->isLink()) {
                    return false;
                }
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $this->relative($file->getPathname()));
                if ($this->isIgnoredPath($relative) || $this->isDeniedPath($relative)) {
                    return false;
                }

                return $file->isDir() || $glob === null || fnmatch($glob, $file->getFilename());
            };
            $iterator = new RecursiveIteratorIterator(
                new RecursiveCallbackFilterIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                    $filter
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            $files = [];
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            throw new InvalidArgumentException('Not a file or directory: ' . $relativePath);
        }

        $matches = [];
        $matchCount = 0;
        foreach (array_chunk($files, 200) as $batch) {
            $command = array_merge(['grep', '-nIiE', '-H', '-e', $pattern, '--'], $batch);
            $result = $this->runProcess($command, 30);
            if ($result['exit'] > 1) {
                return 'grep error: ' . trim($result['stderr']);
            }
            if ($result['exit'] !== 0) {
                continue;
            }

            $batchMatches = explode("\n", rtrim($result['stdout'], "\n"));
            $matchCount += count($batchMatches);
            foreach ($batchMatches as $line) {
                if (count($matches) >= self::MAX_LINES) {
                    break;
                }
                $matches[] = str_replace($this->root . '/', '', $line);
            }
        }

        if ($matchCount === 0) {
            return 'No matches.';
        }

        $more = $matchCount > self::MAX_LINES
            ? sprintf("\n... (%d more matches, refine the pattern)", $matchCount - self::MAX_LINES)
            : '';

        return implode("\n", $matches) . $more;
    }
}
