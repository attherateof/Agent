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

/**
 * Shows the current git diff for the workspace or a single path;
 *
 * Class GitDiffTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class GitDiffTool extends AbstractTool
{
    public function name(): string
    {
        return 'git_diff';
    }

    public function description(): string
    {
        return 'Show uncommitted changes (git diff). Use it to review your own edits.';
    }

    public function parameters(): string
    {
        return 'path (string, optional); staged (bool, optional, default false)';
    }

    /**
     * @param array<string,mixed> $args
     * @return string
     */
    public function execute(array $args): string
    {
        $command = ['git', '--no-pager', 'diff', '--no-color'];
        if (filter_var($args['staged'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $command[] = '--staged';
        }
        $path = !empty($args['path'])
            ? $this->relative($this->resolvePath((string) $args['path']))
            : '.';
        array_push($command, '--', $path, ':(exclude)app/etc/env.php', ':(exclude).env', ':(exclude)**/.env');

        $result = $this->runProcess($command, 30);
        if ($result['exit'] !== 0) {
            return 'git error: ' . substr(trim($result['stderr']), 0, 300);
        }

        return trim($result['stdout']) === '' ? 'No changes.' : $result['stdout'];
    }
}
