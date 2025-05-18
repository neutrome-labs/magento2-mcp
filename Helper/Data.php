<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const string XML_PATH_AI_CHAT_IFRAME_SCRIPT = 'mcp/general/ai_chat_iframe_script';

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
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value ? (string)$value : null;
    }
}
