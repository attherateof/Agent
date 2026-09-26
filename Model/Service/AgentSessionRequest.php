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

namespace MageStack\Agent\Model\Service;

/**
 * Runtime options for one agent CLI session.
 */
class AgentSessionRequest
{
    /**
     * @param string|null $task
     * @param string|null $workspaceOverride
     * @param string|null $modelOverride
     * @param bool $autoApprove
     */
    public function __construct(
        private readonly ?string $task,
        private readonly ?string $workspaceOverride,
        private readonly ?string $modelOverride,
        private readonly bool $autoApprove
    ) {}

    /**
     * @return string|null
     */
    public function getTask(): ?string
    {
        return $this->task;
    }

    /**
     * @return string|null
     */
    public function getWorkspaceOverride(): ?string
    {
        return $this->workspaceOverride;
    }

    /**
     * @return string|null
     */
    public function getModelOverride(): ?string
    {
        return $this->modelOverride;
    }

    /**
     * @return bool
     */
    public function shouldAutoApprove(): bool
    {
        return $this->autoApprove;
    }
}
