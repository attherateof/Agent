<?php

/**
 * MageStack
 *
 * @category  MageStack
 * @package   MageStack_Agent
 * @author    Amit Biswas <amitbiswas.webdeveloper@gmail.com>
 * @license   MIT
 * @link      https://github.com/your-account/mage-agent
 */

declare(strict_types=1);

namespace MageStack\Agent\Model\Tool;

use InvalidArgumentException;
use RuntimeException;

/**
 * Deletes a single file within the workspace after user approval.
 */
class DeleteFileTool extends AbstractTool
{
    public function name(): string
    {
        return 'delete_file';
    }

    public function description(): string
    {
        return 'Delete one file inside the workspace after user approval. Directories and symbolic links cannot be deleted.';
    }

    public function parameters(): string
    {
        return 'path (string, required); must identify an existing file, not a directory.';
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
        $path = $this->resolvePath($this->requireString($args, 'path'));
        if (is_link($path)) {
            throw new InvalidArgumentException('Symbolic links cannot be deleted with delete_file.');
        }
        if (!is_file($path)) {
            throw new InvalidArgumentException('Not a regular file: ' . $this->relative($path));
        }
        if (!is_writable($path)) {
            throw new RuntimeException('File is not writable: ' . $this->relative($path));
        }
        if (!unlink($path)) {
            throw new RuntimeException('Unable to delete file: ' . $this->relative($path));
        }

        return 'Deleted ' . $this->relative($path);
    }
}
