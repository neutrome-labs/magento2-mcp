<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Api;

use NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface; // Need this for getModuleFiles return type hint

/**
 * Interface ModuleServiceInterface
 * Provides methods for introspecting Magento modules.
 * @api
 * @since 1.1.0 // Assuming a version bump for new features/refactoring
 */
interface ModuleServiceInterface
{
    /**
     * Get a list of all enabled Magento modules.
     *
     * @return string[] List of module names (e.g., ['Magento_Catalog', 'NeutromeLabs_Mcp']).
     * @throws \Exception If retrieval fails.
     */
    public function getModuleList(): array;

    /**
     * Get a list of files and directories within a specific module.
     *
     * @param string $moduleName The name of the module (e.g., 'NeutromeLabs_Mcp').
     * @return \NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface[] A list of root nodes (files/directories) for the module.
     * @throws \Magento\Framework\Exception\LocalizedException If the module is not found.
     * @throws \Exception If retrieval fails.
     */
    public function getModuleFiles(string $moduleName): array; // Keep array hint for PHP, Magento DI handles conversion

    /**
     * Get the content of a specific file within a module.
     *
     * @param string $moduleName The name of the module (e.g., 'NeutromeLabs_Mcp').
     * @param string $filePath The relative path to the file within the module's directory (e.g., 'etc/module.xml').
     * @return string The content of the file or an error message.
     * @throws \Magento\Framework\Exception\LocalizedException If the module or file is not found or not readable.
     * @throws \Exception If retrieval fails.
     */
    public function getModuleFileContent(string $moduleName, string $filePath): string;
}
