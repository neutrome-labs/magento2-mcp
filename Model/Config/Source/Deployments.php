<?php
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use NeutromeLabs\Core\Model\ApiClient;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface as MagentoUrlInterface; // Alias to avoid conflict
use Psr\Log\LoggerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Deployments implements OptionSourceInterface
{
    protected ApiClient $apiClient;
    protected StoreManagerInterface $storeManager;
    protected LoggerInterface $logger;

    public function __construct(
        ApiClient $apiClient,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->apiClient = $apiClient;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Please Select a Deployment --')]];
        try {
            $magentoBaseUrl = $this->getMagentoBaseUrlForFilter();
            if (!$magentoBaseUrl) {
                $this->logger->warning('Deployments SourceModel: Could not determine Magento Base URL for filtering.');
                $options[] = ['value' => '', 'label' => __('Error: Could not determine Magento Base URL.'), 'disabled' => true];
                return $options;
            }

            // PocketBase filter: input.env.MAGENTO_BASE_URL contains magentoBaseUrl
            $filter = sprintf('input.env.MAGENTO_BASE_URL = "%s"', addslashes($magentoBaseUrl));

            $deploymentsData = $this->apiClient->fetch("/collections/deployments/records?filter=" . urlencode($filter) . "&perPage=50&sort=-created"); // Sort by newest

            if ($deploymentsData && isset($deploymentsData['items'])) {
                if (empty($deploymentsData['items'])) {
                    $options[] = ['value' => '', 'label' => __('No deployments found matching your Magento Base URL.'), 'disabled' => true];
                }
                foreach ($deploymentsData['items'] as $deployment) {
                    $label = $deployment['id']; // Default label
                    if (!empty($deployment['name'])) { // Pocketbase 'name' field for records
                        $label = $deployment['name'];
                    } else if (isset($deployment['input']['instance_name']) && !empty($deployment['input']['instance_name'])) {
                         // Fallback to a custom field if 'name' is not standard or used differently
                        $label = $deployment['input']['instance_name'];
                    }
                    $status = $deployment['status'] ?? 'unknown'; // Get status, default to 'unknown' if not set
                    $options[] = [
                        'value' => $deployment['id'],
                        'label' => $label . ' (' . $deployment['id'] . ') (' . $status . ')'
                    ];
                }
            } else {
                $this->logger->warning('Deployments SourceModel: No items found or error fetching deployments.', ['filter' => $filter, 'response_summary' => is_array($deploymentsData) ? json_encode(array_keys($deploymentsData)) : $deploymentsData]);
                $options[] = ['value' => '', 'label' => __('Error fetching deployments or no deployments found.'), 'disabled' => true];
            }
        } catch (NoSuchEntityException $e) {
            $this->logger->error('Deployments SourceModel: NoSuchEntityException - ' . $e->getMessage());
            $options[] = ['value' => '', 'label' => __('Error: Store not found for Base URL.'), 'disabled' => true];
        } catch (\Exception $e) {
            $this->logger->error('Deployments SourceModel: Exception fetching deployments - ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $options[] = ['value' => '', 'label' => __('Error fetching deployments (exception).'), 'disabled' => true];
        }
        return $options;
    }

    /**
     * Get the base frontend URL for filtering.
     * This should be the URL that is expected to be in `input.env.MAGENTO_BASE_URL` of the deployment records.
     * Per user: "base frontend url from backend url provider without storecodes"
     *
     * @return string|null
     * @throws NoSuchEntityException
     */
    private function getMagentoBaseUrlForFilter(): ?string
    {
        // Get the base URL for the default store of the default website.
        // URL_TYPE_WEB is generally the frontend URL. Secure is preferred.
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(MagentoUrlInterface::URL_TYPE_WEB, true);

        $baseUrl = trim($baseUrl);

        // Remove trailing slash if present, as PocketBase 'contains' might be sensitive
        $baseUrl = rtrim($baseUrl, '/');

        // Further processing might be needed if store codes are appended and shouldn't be part of the filter.
        // For example, if baseUrl is https://example.com/en/ and you need https://example.com
        // This part is highly dependent on the specific Magento setup and what's in PocketBase.
        // A common approach is to parse the URL if a pattern is known.
        // For now, we assume the getBaseUrl provides a clean enough URL or PocketBase 'contains' is flexible.

        return $baseUrl ?: null;
    }
}
