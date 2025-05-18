<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use NeutromeLabs\Mcp\Helper\Data as McpHelper;

class AiChatButton extends Template
{
    protected McpHelper $mcpHelper;

    /**
     * @param Context $context
     * @param McpHelper $mcpHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        McpHelper $mcpHelper,
        array $data = []
    ) {
        $this->mcpHelper = $mcpHelper;
        parent::__construct($context, $data);
    }

    /**
     * Get the AI Chat Iframe URL from configuration
     *
     * @return string|null
     */
    public function getAiChatUrl(): ?string
    {
        return $this->mcpHelper->getAiChatIframeScript();
    }

    /**
     * Check if the button should be displayed (i.e., if URL is configured)
     *
     * @return bool
     */
    public function shouldDisplayButton(): bool
    {
        return (bool)$this->getAiChatUrl();
    }
}
