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

namespace MageStack\Agent\Model\Agent;

use MageStack\Agent\Api\ToolInterface;
use MageStack\Agent\Model\Tool\DeleteFileToolFactory;
use MageStack\Agent\Model\Tool\EditFileToolFactory;
use MageStack\Agent\Model\Tool\GitDiffToolFactory;
use MageStack\Agent\Model\Tool\ListFilesToolFactory;
use MageStack\Agent\Model\Tool\ReadFileToolFactory;
use MageStack\Agent\Model\Tool\SearchCodeToolFactory;
use MageStack\Agent\Model\Tool\TerminalToolFactory;
use MageStack\Agent\Model\Tool\WriteFileToolFactory;
use Psr\Log\LoggerInterface;

/**
 * Creates the workspace-scoped tools used by the coding agent.
 */
class ToolSetBuilder
{
    public function __construct(
        private readonly ListFilesToolFactory $listFilesToolFactory,
        private readonly SearchCodeToolFactory $searchCodeToolFactory,
        private readonly ReadFileToolFactory $readFileToolFactory,
        private readonly EditFileToolFactory $editFileToolFactory,
        private readonly DeleteFileToolFactory $deleteFileToolFactory,
        private readonly WriteFileToolFactory $writeFileToolFactory,
        private readonly GitDiffToolFactory $gitDiffToolFactory,
        private readonly TerminalToolFactory $terminalToolFactory
    ) {}

    /**
     * @param string[] $ignore
     * @param string[] $terminalAllowlist
     * @return ToolInterface[]
     */
    public function create(
        string $workspace,
        LoggerInterface $logger,
        array $ignore,
        array $terminalAllowlist
    ): array {
        return [
            $this->listFilesToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
                'ignore' => $ignore,
            ]),
            $this->searchCodeToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
                'ignore' => $ignore,
            ]),
            $this->readFileToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
            ]),
            $this->editFileToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
            ]),
            $this->deleteFileToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
            ]),
            $this->writeFileToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
            ]),
            $this->gitDiffToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
            ]),
            $this->terminalToolFactory->create([
                'workspace' => $workspace,
                'logger' => $logger,
                'allowlist' => $terminalAllowlist,
                'timeout' => 120,
            ]),
        ];
    }
}
