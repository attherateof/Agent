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

use Magento\Framework\App\Config\ScopeConfigInterface;
use MageStack\Agent\Api\OllamaConfigInterface;

/**
 * Reads saved Ollama values from Magento configuration.
 */
class OllamaConfig implements OllamaConfigInterface
{
    private const XML_PATH_HOST = 'magestack_agent/ollama/host';
    private const XML_PATH_MODEL = 'magestack_agent/ollama/model';
    private const XML_PATH_AGENT_NUM_CTX = 'magestack_agent/ollama/agent_num_ctx';
    private const XML_PATH_ASK_NUM_CTX = 'magestack_agent/ollama/ask_num_ctx';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig) {}

    /**
     * @return string|null
     */
    public function getHost(): ?string
    {
        return $this->getStringValue(self::XML_PATH_HOST);
    }

    /**
     * @return string|null
     */
    public function getModel(): ?string
    {
        return $this->getStringValue(self::XML_PATH_MODEL);
    }

    /**
     * @return int|null
     */
    public function getAgentContextSize(): ?int
    {
        return $this->getIntegerValue(self::XML_PATH_AGENT_NUM_CTX);
    }

    /**
     * @return int|null
     */
    public function getAskContextSize(): ?int
    {
        return $this->getIntegerValue(self::XML_PATH_ASK_NUM_CTX);
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function getStringValue(string $path): ?string
    {
        $value = $this->scopeConfig->getValue($path);
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param string $path
     * @return int|null
     */
    private function getIntegerValue(string $path): ?int
    {
        $value = $this->scopeConfig->getValue($path);

        return is_numeric($value) ? (int) $value : null;
    }
}
