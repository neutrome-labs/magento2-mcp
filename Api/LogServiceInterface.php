<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Api;

/**
 * Interface LogServiceInterface
 * Provides methods for interacting with Magento log files.
 * @api
 * @since 1.1.0 // Assuming a version bump for new features/refactoring
 */
interface LogServiceInterface
{
    /**
     * List log files from var/log directory.
     *
     * @return \NeutromeLabs\Mcp\Api\Data\LogFileInfoInterface[]
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function listLogFiles(): array;

    /**
     * Get the last N lines of a specific log file.
     *
     * @param string $filePath Relative path within var/log or absolute path.
     * @param int $lines Number of lines to retrieve from the end. Defaults to 50.
     * @return string[] Array of log lines.
     * @throws \Magento\Framework\Exception\NotFoundException If the file does not exist.
     * @throws \Magento\Framework\Exception\FileSystemException On file read errors.
     */
    public function tailLogFile(string $filePath, int $lines = 50): array;
}
