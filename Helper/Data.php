<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Data extends AbstractHelper
{
    public const string XML_PATH_AI_CHAT_IFRAME_SCRIPT = 'mcp/general/ai_chat_iframe_script';

    private WriterInterface $configWriter;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        WriterInterface $configWriter
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
    }

    /**
     * Get the configured AI Chat Iframe URL
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAiChatIframeScript(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_AI_CHAT_IFRAME_SCRIPT,
            $storeId ? ScopeInterface::SCOPE_STORE : 'default',
            $storeId
        );
        return $value ? (string)$value : null;
    }

    /**
     * Set the AI Chat Iframe URL
     *
     * @param string $value
     * @param int|null $storeId
     * @return void
     */
    public function setAiChatIframeScript(string $value, ?int $storeId = null): void
    {
        $this->configWriter->save(
            self::XML_PATH_AI_CHAT_IFRAME_SCRIPT,
            $value,
            $storeId ? ScopeInterface::SCOPE_STORE : 'default',
            $storeId
        );
    }
}
