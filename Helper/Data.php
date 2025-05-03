<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    private const XML_PATH_AI_CHAT_IFRAME_URL = 'mcp/general/ai_chat_iframe_url';

    /**
     * Get the configured AI Chat Iframe URL
     *
     * @param int|null $storeId
     * @return string|null
     */
    public function getAiChatIframeUrl(?int $storeId = null): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_AI_CHAT_IFRAME_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value ? (string)$value : null;
    }
}
