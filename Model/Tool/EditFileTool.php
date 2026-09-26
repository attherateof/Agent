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
use RuntimeException;

/**
 * Replaces one exact, unique text snippet inside an existing file after user approval;
 *
 * Class EditFileTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class EditFileTool extends AbstractTool
{
    public function name(): string
    {
        return 'edit_file';
    }

    public function description(): string
    {
        return 'Replace an exact snippet in a file. "search" must match the file text exactly (including whitespace) and be unique.';
    }

    public function parameters(): string
    {
        return 'path (string, required); search (string, required); replace (string, required, may be empty)';
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
        $search = $this->requireString($args, 'search');
        $replace = (string) ($args['replace'] ?? '');

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read file: ' . $this->relative($path));
        }

        $count = substr_count($content, $search);
        if ($count === 0) {
            throw new InvalidArgumentException('"search" text not found. Re-read the file and copy the snippet exactly.');
        }
        if ($count > 1) {
            throw new InvalidArgumentException(sprintf('"search" matches %d places; include more surrounding lines to make it unique.', $count));
        }

        $updated = preg_replace('/' . preg_quote($search, '/') . '/', addcslashes($replace, '\\$'), $content, 1);
        if ($updated === null || file_put_contents($path, $updated) === false) {
            throw new RuntimeException('Unable to write file: ' . $this->relative($path));
        }

        return sprintf('Edited %s: replaced %d chars with %d chars', $this->relative($path), strlen($search), strlen($replace));
    }
}
