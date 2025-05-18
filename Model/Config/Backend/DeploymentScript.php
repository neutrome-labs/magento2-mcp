<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model\Config\Backend;

use Exception;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use NeutromeLabs\Core\Model\ApiClient;
use NeutromeLabs\Mcp\Helper\Data as McpHelper;
use Psr\Log\LoggerInterface;

class DeploymentScript extends Value
{
    protected ApiClient $apiClient;
    // protected WriterInterface $configWriter; // No longer needed
    protected LoggerInterface $logger;
    protected McpHelper $mcpHelper;

    public function __construct(
        Context                                                 $context,
        Registry                                                $registry,
        ScopeConfigInterface                                    $config, // Injected by parent
        TypeListInterface                                       $cacheTypeList, // Injected by parent
        ApiClient                                               $apiClient,
        // WriterInterface $configWriter, // No longer needed
        LoggerInterface                                         $logger,
        McpHelper                                               $mcpHelper,
        AbstractResource $resource = null,
        AbstractDb           $resourceCollection = null,
        array                                                   $data = []
    )
    {
        $this->apiClient = $apiClient;
        // $this->configWriter = $configWriter; // No longer needed
        $this->logger = $logger;
        $this->mcpHelper = $mcpHelper;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Logic to run before saving the configuration.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        // getValue() here will be the selected deployment ID from the dropdown
        $deploymentId = $this->getValue();
        // $iframeScriptPath = McpHelper::XML_PATH_AI_CHAT_IFRAME_SCRIPT; // Path is in helper
        // $currentScope = $this->getScope(); // Will be default
        // $currentScopeId = $this->getScopeId(); // Will be 0

        // Since system.xml showInDefault="1" showInWebsite="0" showInStore="0",
        // scope is always default, so storeId for helper is null.

        if (empty($deploymentId)) {
            // If '-- Please Select --' or an empty option is chosen, clear the script
            $this->mcpHelper->setAiChatIframeScript('');
            $this->_logger->info('DeploymentScriptBackend: Cleared iframe script as no deployment was selected.');
        } else {
            try {
                $deploymentData = $this->apiClient->fetch("/collections/deployments/records/" . rawurlencode($deploymentId));
                if ($deploymentData && isset($deploymentData['status'])) {
                    if ($deploymentData['status'] !== 'running') {
                        throw new LocalizedException(
                            __('Cannot save configuration. The selected deployment "%1" has a status of "%2". Only deployments with a "running" status can be used.', $deploymentId, $deploymentData['status'])
                        );
                    }

                    if (isset($deploymentData['output']['maxkb_iframe']['value'])) {
                        $iframeScriptValue = $deploymentData['output']['maxkb_iframe']['value'];
                        $this->mcpHelper->setAiChatIframeScript($iframeScriptValue);
                        $this->_logger->info('DeploymentScriptBackend: Saved iframe script for deployment ID: ' . $deploymentId);
                    } else {
                        $this->logger->warning(
                            'DeploymentScriptBackend: maxkb_iframe.value not found for running deployment.',
                            ['deployment_id' => $deploymentId, 'data_keys' => is_array($deploymentData) ? json_encode(array_keys($deploymentData)) : null]
                        );
                        // If status is running but script is missing, this is an issue with the deployment data.
                        throw new LocalizedException(
                            __('The selected deployment "%1" is running, but its iframe script is missing. Please check the deployment configuration in the NeutromeLabs portal.', $deploymentId)
                        );
                    }
                } else {
                    $this->logger->warning(
                        'DeploymentScriptBackend: Status field or deployment data not found or failed to fetch for deployment.',
                        ['deployment_id' => $deploymentId, 'data_keys' => is_array($deploymentData) ? json_encode(array_keys($deploymentData)) : null]
                    );
                    // Throw an exception if essential deployment data (like status) couldn't be fetched.
                    throw new LocalizedException(
                        __('Failed to retrieve complete data for the selected deployment "%1". Please try again or check logs.', $deploymentId)
                    );
                }
            } catch (LocalizedException $e) { // Re-throw LocalizedExceptions directly
                throw $e;
            } catch (Exception $e) {
                $this->logger->error(
                    'DeploymentScriptBackend: Exception while fetching/saving iframe script - ' . $e->getMessage(),
                    ['deployment_id' => $deploymentId, 'exception' => $e]
                );
                // Throwing an exception will prevent the config from saving and show an error to the user.
                throw new LocalizedException(
                    __('An error occurred while processing the deployment script: %1', $e->getMessage())
                );
            }
        }

        return parent::beforeSave();
    }
}
