<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Helper;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{
    public const XML_PATH_AI_CHAT_IFRAME_SCRIPT = 'mcp/general/ai_chat_iframe_script';

    private WriterInterface $configWriter;

    public function __construct(
        Context         $context,
        WriterInterface $configWriter
    )
    {
        parent::__construct($context);
        $this->configWriter = $configWriter;
    }

    /**
     * Get the configured AI Chat Iframe URL
     *
     * @return string|null
     */
    public function getAiChatIframeScript(): ?string
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_AI_CHAT_IFRAME_SCRIPT);
        return $value ? (string)$value : null;
    }

    /**
     * Set the AI Chat Iframe URL
     *
     * @param string $value
     * @return void
     */
    public function setAiChatIframeScript(string $value): void
    {
        $this->configWriter->save(self::XML_PATH_AI_CHAT_IFRAME_SCRIPT, $value);
    }
}
