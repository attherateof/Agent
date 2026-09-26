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

use RuntimeException;

/**
 * Creates or overwrites a file after user approval;
 *
 * Class WriteFileTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class WriteFileTool extends AbstractTool
{
    public function name(): string
    {
        return 'write_file';
    }

    public function description(): string
    {
        return 'Create a new file or completely overwrite an existing one. Prefer edit_file for small changes.';
    }

    public function parameters(): string
    {
        return 'path (string, required); content (string, required)';
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    /**
     * @param array<string,mixed> $args
     * @return string
     */
    public function execute(array $args): string
    {
        $path = $this->resolvePath($this->requireString($args, 'path'), false);
        $content = (string) ($args['content'] ?? '');

        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create directory: ' . $this->relative($dir));
        }

        $existed = is_file($path);
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException('Unable to write file: ' . $this->relative($path));
        }

        return sprintf('%s %s (%d bytes)', $existed ? 'Overwrote' : 'Created', $this->relative($path), strlen($content));
    }
}
