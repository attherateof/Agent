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
 * Runs an allow-listed command without a shell after user approval;
 *
 * Class TerminalTool
 *
 * @namespace MageStack\Agent\Model\Tool
 */
class TerminalTool extends AbstractTool
{
    /**
     * @param string $workspace
     * @param LoggerInterface $logger
     * @param string[] $allowlist
     * @param int $timeout
     */
    public function __construct(
        string $workspace,
        LoggerInterface $logger,
        private readonly array $allowlist,
        private readonly int $timeout = 120
    ) {
        parent::__construct($workspace, $logger);
    }

    public function name(): string
    {
        return 'terminal';
    }

    public function description(): string
    {
        return 'Validate PHP syntax with "php -l <file.php>" or XML well-formedness with "xml-lint <file.xml>" for a file in the workspace.';
    }

    public function parameters(): string
    {
        return 'command (string, required), e.g. "php -l src/Foo.php" or "xml-lint etc/module.xml"';
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
        $commandLine = $this->requireString($args, 'command');

        if (preg_match('/[;&|<>`\n]|\$\(/', $commandLine) === 1) {
            throw new InvalidArgumentException('Pipes, redirects, chaining and substitution are not allowed. Run one simple command.');
        }

        preg_match_all('/"([^"]*)"|\'([^\']*)\'|(\S+)/', $commandLine, $matches, PREG_SET_ORDER);
        $tokens = array_map(
            static fn(array $match): string => $match[1] !== ''
                ? $match[1]
                : ($match[2] !== '' ? $match[2] : ($match[3] ?? '')),
            $matches
        );
        if ($tokens === []) {
            throw new InvalidArgumentException('Empty command');
        }
        $isPhpLint = $tokens[0] === 'php' && count($tokens) === 3 && $tokens[1] === '-l';
        $isXmlLint = $tokens[0] === 'xml-lint' && count($tokens) === 2;
        if ((!$isPhpLint && !$isXmlLint) || !in_array($tokens[0], $this->allowlist, true)) {
            throw new InvalidArgumentException('Only "php -l <file.php>" and "xml-lint <file.xml>" are allowed.');
        }

        $path = $this->resolvePath($isPhpLint ? $tokens[2] : $tokens[1]);
        if (!is_file($path)) {
            throw new InvalidArgumentException('Not a file: ' . $this->relative($path));
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($isPhpLint && $extension !== 'php') {
            throw new InvalidArgumentException('PHP syntax checks only apply to .php files.');
        }
        if ($isXmlLint) {
            if ($extension !== 'xml') {
                throw new InvalidArgumentException('XML well-formedness checks only apply to .xml files.');
            }

            return $this->validateXml($path);
        }

        $result = $this->runProcess(['php', '-l', $path], $this->timeout);

        return sprintf(
            "exit code: %d\n--- stdout ---\n%s\n--- stderr ---\n%s",
            $result['exit'],
            trim($result['stdout']),
            trim($result['stderr'])
        );
    }

    private function validateXml(string $path): string
    {
        if (!class_exists(\DOMDocument::class)) {
            throw new RuntimeException('The PHP DOM extension is required for XML validation.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            $valid = $document->load($path, LIBXML_NONET);
            $errors = libxml_get_errors();
            if (!$valid) {
                $details = array_map(
                    static fn(\LibXMLError $error): string => sprintf('line %d: %s', $error->line, trim($error->message)),
                    $errors
                );

                return sprintf("XML is not well-formed: %s\n%s", $this->relative($path), implode("\n", $details));
            }

            return 'XML is well-formed: ' . $this->relative($path);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
