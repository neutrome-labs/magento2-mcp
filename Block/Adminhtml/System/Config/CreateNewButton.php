<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use NeutromeLabs\Core\Helper\Data as CoreHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface as MagentoUrlInterface;

class CreateNewButton extends Field
{
    protected CoreHelper $coreHelper;
    protected ScopeConfigInterface $scopeConfig;
    protected StoreManagerInterface $storeManager;

    /**
     * @var string
     */
    protected $_template = 'NeutromeLabs_Mcp::system/config/create_new_button.phtml';

    public function __construct(
        Context $context,
        CoreHelper $coreHelper,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->coreHelper = $coreHelper;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Remove scope label
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Return element html
     *
     * @param  AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return Ajax URL for button
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'create_new_deployment_button_el', // HTML id
                'label' => __('Create New Deployment'),
            ]
        );
        return $button->toHtml();
    }

    public function getNeutromeLabsBaseUrl(): ?string
    {
        return $this->coreHelper->getNeutromeBaseUrl();
    }

    public function getMagentoStoreName(): ?string
    {
        return $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get the base frontend URL for the "Create New" button.
     * Per user: "base frontend url from backend url provider without storecodes"
     */
    public function getMagentoAdminBaseUrl(): ?string
    {
        try {
            // URL_TYPE_WEB should give the frontend base URL.
            // true for secure.
            $baseUrl = $this->storeManager->getStore()->getBaseUrl(MagentoUrlInterface::URL_TYPE_WEB, true);
            // Remove trailing slash for consistency
            return rtrim($baseUrl, '/');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->_logger->error('CreateNewButton: Could not get base URL - ' . $e->getMessage());
            return null;
        }
    }
}
