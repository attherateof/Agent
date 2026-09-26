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

namespace MageStack\Agent\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use MageStack\Agent\Api\OllamaConfigurationInterface;
use RuntimeException;

/**
 * Resolves and validates the runtime settings for an agent CLI invocation.
 */
class AgentConfigurationProvider
{
    /**
     * @param State $appState
     * @param DirectoryList $directoryList
     * @param OllamaConfigurationInterface $ollamaConfiguration
     */
    public function __construct(
        private readonly State $appState,
        private readonly DirectoryList $directoryList,
        private readonly OllamaConfigurationInterface $ollamaConfiguration
    ) {}

    /**
     * @param string|null $workspaceOverride
     * @param string|null $modelOverride
     * @return array{workspace:string,host:string,model:string,numCtx:int}
     */
    public function get(?string $workspaceOverride, ?string $modelOverride): array
    {
        if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
            throw new RuntimeException(sprintf(
                'magestack:agent:run only runs in developer mode (current mode: %s). Run "bin/magento deploy:mode:set developer" first.',
                $this->appState->getMode()
            ));
        }

        $workspaceOption = $workspaceOverride ?: (getenv('AGENT_WORKSPACE') ?: null);
        $workspace = realpath((string) ($workspaceOption ?? $this->directoryList->getPath(DirectoryList::ROOT)));
        if ($workspace === false || !is_dir($workspace)) {
            throw new RuntimeException('Workspace directory not found.');
        }

        return ['workspace' => $workspace] + $this->ollamaConfiguration->getAgentConfiguration($modelOverride);
    }
}
