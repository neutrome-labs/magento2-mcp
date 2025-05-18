<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model;

use FilesystemIterator;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use NeutromeLabs\Mcp\Api\Data\LogFileInfoInterface;
use NeutromeLabs\Mcp\Api\Data\LogFileInfoInterfaceFactory;
use NeutromeLabs\Mcp\Api\LogServiceInterface;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

// Need factory for DTO

/**
 * Service class implementing log file operations.
 */
class LogService implements LogServiceInterface
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var FileDriver
     */
    private $fileDriver;

    /**
     * @var LogFileInfoInterfaceFactory
     */
    private $logFileInfoFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * LogService constructor.
     *
     * @param Filesystem $filesystem
     * @param DirectoryList $directoryList
     * @param FileDriver $fileDriver
     * @param LogFileInfoInterfaceFactory $logFileInfoFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Filesystem                  $filesystem,
        DirectoryList               $directoryList,
        FileDriver                  $fileDriver,
        LogFileInfoInterfaceFactory $logFileInfoFactory,
        LoggerInterface             $logger
    )
    {
        $this->filesystem = $filesystem;
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->logFileInfoFactory = $logFileInfoFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function listLogFiles(): array
    {
        $this->logger->info('[NeutromeLabs_Mcp] Requesting log file list via MCP');
        $logFilesInfo = [];
        try {
            $logDir = $this->directoryList->getPath('log');
            if (!$this->fileDriver->isDirectory($logDir)) {
                $this->logger->warning('[NeutromeLabs_Mcp] Log directory does not exist.', ['path' => $logDir]);
                return []; // Return empty if log dir doesn't exist
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $this->fileDriver->isReadable($file->getPathname())) {
                    try {
                        $stat = $this->fileDriver->stat($file->getPathname());
                        /** @var LogFileInfoInterface $logInfo */
                        $logInfo = $this->logFileInfoFactory->create();
                        $logInfo->setPath($this->getRelativeLogPath($file->getPathname(), $logDir));
                        $logInfo->setSize((int)$stat['size']);
                        $logInfo->setLastModified((int)$stat['mtime']);
                        $logFilesInfo[] = $logInfo;
                    } catch (FileSystemException $e) {
                        $this->logger->error(
                            '[NeutromeLabs_Mcp] Error stating log file: ' . $file->getPathname(),
                            ['exception' => $e]
                        );
                        // Skip this file but continue with others
                    }
                }
            }
        } catch (Throwable $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error listing log files: ' . $e->getMessage(),
                ['exception' => $e]
            );
            throw new FileSystemException(__('Error listing log files: %1', $e->getMessage()), $e);
        }

        // Sort by path for consistent ordering
        usort($logFilesInfo, function (LogFileInfoInterface $a, LogFileInfoInterface $b) {
            return strcmp($a->getPath(), $b->getPath());
        });

        return $logFilesInfo;
    }

    /**
     * Get the relative path of a log file based on the log directory.
     *
     * @param string $fullPath
     * @param string $logDir
     * @return string
     */
    private function getRelativeLogPath(string $fullPath, string $logDir): string
    {
        // Ensure logDir has a trailing slash for correct replacement
        $logDir = rtrim($logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($fullPath, $logDir) === 0) {
            return substr($fullPath, strlen($logDir));
        }
        return $fullPath; // Should not happen if iteration is correct, but fallback
    }

    /**
     * @inheritDoc
     */
    public function tailLogFile(string $filePath, int $lines = 50): array
    {
        $this->logger->info('[NeutromeLabs_Mcp] Requesting log file tail via MCP', ['file' => $filePath, 'lines' => $lines]);

        if ($lines <= 0) {
            $lines = 50; // Default to 50 if invalid lines count provided
        }

        try {
            $logDir = $this->directoryList->getPath('log');
            $fullPath = $this->resolveLogFilePath($filePath, $logDir);

            if (!$this->fileDriver->isExists($fullPath)) {
                throw new NotFoundException(__('Log file not found: %1', $filePath));
            }
            if (!$this->fileDriver->isFile($fullPath)) {
                throw new FileSystemException(__('Path is not a file: %1', $filePath));
            }
            if (!$this->fileDriver->isReadable($fullPath)) {
                throw new FileSystemException(__('Log file is not readable: %1', $filePath));
            }

            // Simple approach: Read all lines and slice. Good for moderate files.
            // For very large files (> hundreds of MB), a seek-based approach would be better.
            $fileContentLines = $this->fileDriver->fileReadLines($fullPath, -1); // Read all lines
            $lineCount = count($fileContentLines);
            $start = max(0, $lineCount - $lines);
            $resultLines = array_slice($fileContentLines, $start);

            // Remove trailing newline from the last line if present
            if (!empty($resultLines)) {
                $lastIndex = count($resultLines) - 1;
                $resultLines[$lastIndex] = rtrim($resultLines[$lastIndex], "\r\n");
            }


            return $resultLines;

        } catch (NotFoundException|FileSystemException $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error tailing log file: ' . $e->getMessage(),
                ['file' => $filePath, 'exception' => $e]
            );
            throw $e; // Re-throw specific exceptions
        } catch (Throwable $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] General error tailing log file: ' . $e->getMessage(),
                ['file' => $filePath, 'exception' => $e]
            );
            throw new FileSystemException(__('Error tailing log file "%1": %2', $filePath, $e->getMessage()), $e);
        }
    }

    /**
     * Resolve and validate the log file path.
     * Ensures the path is within the allowed log directory.
     *
     * @param string $filePath User-provided path (can be relative or potentially absolute)
     * @param string $logDir Absolute path to the Magento log directory
     * @return string Absolute, validated path to the log file
     * @throws FileSystemException If path is invalid or outside the log directory
     */
    private function resolveLogFilePath(string $filePath, string $logDir): string
    {
        // Basic sanitization
        $sanitizedPath = ltrim(str_replace(['..', '\\'], ['', '/'], $filePath), '/');
        $fullPath = $logDir . DIRECTORY_SEPARATOR . $sanitizedPath;

        // Security check: Ensure the resolved path is still within the log directory
        $realLogDir = $this->fileDriver->getRealPath($logDir);
        $realFullPath = $this->fileDriver->getRealPath($fullPath); // Resolves symlinks etc.

        // Check if real path starts with real log dir path + directory separator
        if (strpos($realFullPath, $realLogDir . DIRECTORY_SEPARATOR) !== 0) {
            // Also handle case where the requested path IS the log dir itself (which is not a file)
            if ($realFullPath !== $realLogDir) {
                throw new FileSystemException(__('Access denied: File path is outside the allowed log directory.'));
            }
        }

        return $fullPath; // Return the non-realpath version for consistency, validation done
    }
}
