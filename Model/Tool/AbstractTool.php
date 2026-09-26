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
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Shared workspace sandboxing, argument validation and process helpers for tools;
 *
 * Class AbstractTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
abstract class AbstractTool implements ToolInterface
{
    /** Paths (relative to workspace) the agent may never touch */
    private const DENIED = ['app/etc/env.php', '.env', '.git'];

    protected string $root;

    /**
     * @param string $workspace
     * @param LoggerInterface $logger
     * @param string[] $ignore
     */
    public function __construct(
        string $workspace,
        protected readonly LoggerInterface $logger,
        protected readonly array $ignore = []
    ) {
        $root = realpath($workspace);
        if ($root === false) {
            throw new InvalidArgumentException('Workspace does not exist: ' . $workspace);
        }
        $this->root = $root;
    }

    /**
     * @return bool
     */
    public function requiresConfirmation(): bool
    {
        return false;
    }

    /**
     * Resolve a user/model supplied path and make sure it stays inside the workspace.
     *
     * @param string $path
     * @param bool $mustExist
     * @return string Absolute path
     * @throws InvalidArgumentException
     */
    protected function resolvePath(string $path, bool $mustExist = true): string
    {
        if (preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            throw new InvalidArgumentException('Path traversal is not allowed: ' . $path);
        }

        $candidate = str_starts_with($path, '/') ? $path : $this->root . '/' . $path;

        $componentPath = str_starts_with($candidate, '/') ? '/' : '';
        foreach (explode('/', trim($candidate, '/')) as $component) {
            if ($component === '' || $component === '.') {
                continue;
            }
            $componentPath = rtrim($componentPath, '/') . '/' . $component;
            if (is_link($componentPath)) {
                throw new InvalidArgumentException('Symbolic links are not allowed in paths: ' . $path);
            }
        }

        $existing = $candidate;
        $tail = '';
        while (!file_exists($existing) && $existing !== dirname($existing)) {
            $tail = '/' . basename($existing) . $tail;
            $existing = dirname($existing);
        }
        if ($mustExist && $tail !== '') {
            throw new InvalidArgumentException('Path not found: ' . $path);
        }
        $real = realpath($existing);
        if ($real === false) {
            throw new InvalidArgumentException('Cannot resolve path: ' . $path);
        }
        $full = $real . $tail;

        if ($full !== $this->root && !str_starts_with($full, $this->root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Path is outside the workspace: ' . $path);
        }

        $relative = ltrim(substr($full, strlen($this->root)), '/');
        if ($this->isDeniedPath($relative)) {
            throw new InvalidArgumentException('Access to this path is blocked: ' . $relative);
        }

        return $full;
    }

    /**
     * @param string $relative
     * @return bool
     */
    protected function isDeniedPath(string $relative): bool
    {
        if ($relative === self::DENIED[0] || str_starts_with($relative, self::DENIED[0] . '/')) {
            return true;
        }

        return array_intersect(explode('/', trim($relative, '/')), array_slice(self::DENIED, 1)) !== [];
    }

    protected function isIgnoredPath(string $relative): bool
    {
        $relative = trim($relative, '/');
        foreach ($this->ignore as $ignored) {
            $ignored = trim($ignored, '/');
            if ($relative === $ignored || str_starts_with($relative, $ignored . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $absolute
     * @return string Path relative to the workspace root
     */
    protected function relative(string $absolute): string
    {
        $rel = ltrim(substr($absolute, strlen($this->root)), '/');
        return $rel === '' ? '.' : $rel;
    }

    /**
     * @param array<string,mixed> $args
     * @param string $key
     * @return string
     * @throws InvalidArgumentException
     */
    protected function requireString(array $args, string $key): string
    {
        if (!isset($args[$key]) || !is_scalar($args[$key]) || (string) $args[$key] === '') {
            throw new InvalidArgumentException(sprintf('Missing required argument "%s"', $key));
        }

        return (string) $args[$key];
    }

    /**
     * Run a command without a shell (no injection possible) and capture output.
     *
     * @param string[] $command
     * @param int $timeout Seconds
     * @return array{exit:int,stdout:string,stderr:string}
     * @throws RuntimeException
     */
    protected function runProcess(array $command, int $timeout = 60): array
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start process: ' . $command[0]);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $exit = -1;
        $start = microtime(true);

        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (!$status['running']) {
                $exit = (int) $status['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($process, 9);
                $stderr .= sprintf("\n[timed out after %ds]", $timeout);
                break;
            }
            usleep(50000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return ['exit' => $exit, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
