<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Api;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NotFoundException;
use NeutromeLabs\Mcp\Api\Data\LogFileInfoInterface;

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
     * @return LogFileInfoInterface[]
     * @throws FileSystemException
     */
    public function listLogFiles(): array;

    /**
     * Get the last N lines of a specific log file.
     *
     * @param string $filePath Relative path within var/log or absolute path.
     * @param int $lines Number of lines to retrieve from the end. Defaults to 50.
     * @return string[] Array of log lines.
     * @throws NotFoundException If the file does not exist.
     * @throws FileSystemException On file read errors.
     */
    public function tailLogFile(string $filePath, int $lines = 50): array;
}
