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

/**
 * Detects the Magento 2 install/module and adds coding conventions to the prompt;
 *
 * Class MagentoContext
 *
 * @namespace MageStack\Agent\Model\Context
 */
class MagentoContext
{
    private const MAX_MODULES = 40;

    /**
     * @param string $workspace
     */
    public function __construct(private readonly string $workspace) {}

    /**
     * @return string Empty string when the workspace is not Magento related
     */
    public function render(): string
    {
        $root = rtrim($this->workspace, '/');
        $lines = [];

        if (is_file($root . '/bin/magento')) {
            $lines[] = 'Type: Magento 2 installation' . $this->version($root);
            $modules = $this->customModules($root);
            if ($modules !== []) {
                $lines[] = 'Custom modules (app/code): ' . implode(', ', $modules);
            }
        } elseif (is_file($root . '/etc/module.xml')) {
            $xml = (string) file_get_contents($root . '/etc/module.xml');
            $name = preg_match('/<module\s+name="([^"]+)"/', $xml, $m) === 1 ? $m[1] : 'unknown';
            $lines[] = 'Type: standalone Magento 2 module ' . $name;
        } else {
            return '';
        }

        $lines[] = <<<'TXT'
Magento conventions to follow:
- declare(strict_types=1); constructor dependency injection; never use ObjectManager directly.
- Extend behaviour with plugins, observers or DI preferences instead of core rewrites.
- Use service contracts (api interfaces) and repositories; database changes go in etc/db_schema.xml.
- Never touch app/etc/env.php, vendor/ or generated/. Do not edit core Magento code.
- Validate PHP with `php -l`; after DI/plugin changes the user runs `bin/magento setup:di:compile`.
TXT;

        return implode("\n", $lines);
    }

    /**
     * @param string $root
     * @return string
     */
    private function version(string $root): string
    {
        $composer = json_decode((string) @file_get_contents($root . '/composer.json'), true);
        foreach (['magento/product-community-edition', 'magento/product-enterprise-edition'] as $package) {
            if (isset($composer['require'][$package])) {
                return ' (' . $package . ' ' . $composer['require'][$package] . ')';
            }
        }

        return '';
    }

    /**
     * @param string $root
     * @return string[]
     */
    private function customModules(string $root): array
    {
        $modules = [];
        foreach (glob($root . '/app/code/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $modules[] = basename(dirname($dir)) . '_' . basename($dir);
            if (count($modules) >= self::MAX_MODULES) {
                break;
            }
        }

        return $modules;
    }
}
