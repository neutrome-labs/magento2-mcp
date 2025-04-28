<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model;

use NeutromeLabs\Mcp\Api\ExecutionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Module\ModuleListInterface;
use Psr\Log\LoggerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface;
use NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterfaceFactory;

/**
 * Service class implementing the execution logic for PHP, SQL, Shell, and module introspection.
 */
class ExecutionService implements ExecutionInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var DirectoryList
      */
     private $fileDriver;

    /**
     * @var ModuleFileNodeInterfaceFactory
     */
    private $moduleFileNodeFactory;

    private DirectoryList $directoryList;

    /**
     * ExecutionService constructor.
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param ModuleListInterface $moduleList
     * @param ModuleDirReader $moduleDirReader
      * @param Filesystem $filesystem
      * @param DirectoryList $directoryList
      * @param FileDriver $fileDriver
      * @param ModuleFileNodeInterfaceFactory $moduleFileNodeFactory
      */
     public function __construct(
         ResourceConnection $resourceConnection,
        LoggerInterface $logger,
        ModuleListInterface $moduleList,
        ModuleDirReader $moduleDirReader,
         Filesystem $filesystem,
         DirectoryList $directoryList,
         FileDriver $fileDriver,
         ModuleFileNodeInterfaceFactory $moduleFileNodeFactory
     ) {
         $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->moduleDirReader = $moduleDirReader;
         $this->filesystem = $filesystem;
         $this->directoryList = $directoryList;
         $this->fileDriver = $fileDriver;
         $this->moduleFileNodeFactory = $moduleFileNodeFactory;
         // Get default connection
         $this->connection = $this->resourceConnection->getConnection();
    }

    /**
     * @inheritdoc
     * WARNING: Executes arbitrary PHP code. Use with extreme caution.
     */
    public function executePhp(string $code): string
    {
        $this->logger->warning('[NeutromeLabs_Mcp] Executing PHP code via MCP', ['code' => $code]);

        // Capture output using output buffering
        ob_start();
        $error = null;
        try {
            // Use eval - this is inherently dangerous!
            eval($code);
        } catch (\Throwable $e) {
            $error = $e;
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error executing PHP code via MCP: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
        $output = ob_get_clean();

        if ($error) {
            // Prepend error message to any output that might have occurred before the error
            return "ERROR: " . $error->getMessage() . "\n" . $output;
        }

        return $output ?: "OK (No output)";
    }

    /**
     * @inheritdoc
     * WARNING: Executes arbitrary SQL queries. Use with extreme caution.
     */
    public function executeSql(string $query): string
    {
        $this->logger->warning('[NeutromeLabs_Mcp] Executing SQL query via MCP', ['query' => $query]);
        $trimmedQuery = trim($query);
        $resultSummary = '';

        try {
            // Basic check for potentially very destructive commands (can be bypassed)
            if (preg_match('/^\s*(DROP|TRUNCATE)\s+(DATABASE|TABLE)/i', $trimmedQuery)) {
                throw new LocalizedException(
                    __('Execution of DROP/TRUNCATE statements is blocked for safety.')
                );
            }

            $statement = $this->connection->query($trimmedQuery);
            $rowCount = $statement->rowCount();

            if (preg_match('/^\s*SELECT/i', $trimmedQuery)) {
                $resultData = $statement->fetchAll(\PDO::FETCH_ASSOC);
                if ($resultData) {
                    // Limit result size to prevent memory issues/large responses
                    if (strlen(json_encode($resultData)) > 5 * 1024 * 1024) { // 5MB limit
                         return "ERROR: Result set too large (> 5MB). Please refine your query.";
                    }
                    $resultSummary = json_encode($resultData);
                } else {
                    $resultSummary = '[]'; // Empty JSON array for no results
                }
            } elseif (preg_match('/^\s*(INSERT|UPDATE|DELETE)/i', $trimmedQuery)) {
                $resultSummary = $rowCount . ' row(s) affected.';
            } else {
                $resultSummary = 'OK. Statement executed.';
            }

        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error executing SQL query via MCP: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return "ERROR: " . $e->getMessage();
        }

        return $resultSummary;
    }

    /**
     * @inheritdoc
     * WARNING: Executes arbitrary shell commands. Use with extreme caution.
     */
    public function executeShell(string $command): string
    {
        $this->logger->warning('[NeutromeLabs_Mcp] Executing shell command via MCP', ['command' => $command]);
        $output = '';
        $error = null;

        // Basic safety check (can be bypassed)
        if (preg_match('/(rm|mv|cp)\s+-rf/i', $command)) {
             return "ERROR: Potentially destructive command blocked.";
        }

        try {
            // Redirect stderr to stdout to capture errors
            $fullCommand = $command . ' 2>&1';
            $output = shell_exec($fullCommand);

            if ($output === null) {
                // shell_exec can return null on error or if command produces no output
                // Hard to distinguish, but log a warning.
                 $this->logger->warning('[NeutromeLabs_Mcp] Shell command executed but returned null.', ['command' => $command]);
                 $output = "OK (No output or error)";
            }

        } catch (\Throwable $e) {
            $error = $e;
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error executing shell command via MCP: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }

        if ($error) {
            return "ERROR: " . $error->getMessage() . "\n" . $output;
        }

        // Limit output size
        if (strlen($output) > 5 * 1024 * 1024) { // 5MB limit
            return "ERROR: Output too large (> 5MB). Command output truncated.\n" . substr($output, 0, 5 * 1024 * 1024);
        }

        return $output ?: "OK (No output)";
    }

    /**
     * @inheritdoc
     */
    public function getModuleList(): array
    {
        $this->logger->info('[NeutromeLabs_Mcp] Requesting module list via MCP');
        try {
            return $this->moduleList->getNames();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error retrieving module list: ' . $e->getMessage(),
                ['exception' => $e]
            );
            // Return error within the expected array structure if possible,
            // but the interface expects array<string>, so throwing is better.
            throw new \Exception("Error retrieving module list: " . $e->getMessage());
        }
    }

    /**
      * @inheritdoc
      */
     public function getModuleFiles(string $moduleName): array
     {
         $this->logger->info('[NeutromeLabs_Mcp] Requesting file list for module via MCP', ['module' => $moduleName]);
        try {
            // Check if module exists and is enabled
            if (!in_array($moduleName, $this->moduleList->getNames())) {
                 throw new LocalizedException(__('Module "%1" not found or is not enabled.', $moduleName));
            }

            $modulePath = $this->moduleDirReader->getModuleDir('', $moduleName);
            if (!$modulePath || !$this->fileDriver->isDirectory($modulePath)) {
                throw new LocalizedException(__('Could not determine directory for module "%1".', $moduleName));
            }

            $tree = [];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($modulePath, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $pathLength = strlen($modulePath) + 1; // +1 for the trailing slash

            foreach ($iterator as $file) {
                $relativePath = substr($file->getPathname(), $pathLength);
                $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
                $currentLevel = &$tree;

                foreach ($parts as $index => $part) {
                    if (empty($part)) continue; // Skip empty parts if any

                    if ($index === count($parts) - 1) {
                        // Last part: it's either a file or an empty directory added by SELF_FIRST
                        if ($file->isFile()) {
                            $currentLevel[$part] = 'file';
                        } elseif ($file->isDir() && !isset($currentLevel[$part])) {
                             // Ensure directory entry exists if it wasn't created by a child file/dir yet
                             $currentLevel[$part] = [];
                        }
                    } else {
                        // Intermediate part: must be a directory
                        if (!isset($currentLevel[$part])) {
                            $currentLevel[$part] = [];
                        } elseif ($currentLevel[$part] === 'file') {
                             // This case should ideally not happen with correct iteration
                             $this->logger->warning('Path conflict detected in module file listing', ['path' => $relativePath]);
                             // Overwrite file entry with directory structure
                             $currentLevel[$part] = [];
                        }
                        $currentLevel = &$currentLevel[$part];
                    }
                }
             }
             unset($currentLevel); // Unset reference

             // Convert the associative array tree to DTOs
             return $this->buildFileTreeNodes($tree);

         } catch (LocalizedException $e) {
             $this->logger->error(
                 '[NeutromeLabs_Mcp] Error getting module files: ' . $e->getMessage(),
                 ['module' => $moduleName, 'exception' => $e]
             );
             // Re-throw localized exceptions as they are user-friendly
             throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeutromeLabs_Mcp] General error getting module files: ' . $e->getMessage(),
                ['module' => $moduleName, 'exception' => $e]
            );
            throw new \Exception("Error retrieving file list for module '{$moduleName}': " . $e->getMessage());
         }
     }

     /**
      * Recursively builds the file tree node DTOs from an associative array.
      *
      * @param array $treeData
      * @return ModuleFileNodeInterface[]
      */
     private function buildFileTreeNodes(array $treeData): array
     {
         $nodes = [];
         foreach ($treeData as $name => $data) {
             /** @var ModuleFileNodeInterface $node */
             $node = $this->moduleFileNodeFactory->create();
             $node->setName($name);
             if (is_array($data)) {
                 $node->setType('dir');
                 $node->setChildren($this->buildFileTreeNodes($data));
             } else {
                 $node->setType('file');
                 $node->setChildren(null); // Explicitly set children to null for files
             }
             $nodes[] = $node;
         }
         // Sort nodes alphabetically by name (optional, but nice)
         usort($nodes, function (ModuleFileNodeInterface $a, ModuleFileNodeInterface $b) {
             return strcmp($a->getName(), $b->getName());
         });
         return $nodes;
     }

     /**
      * @inheritdoc
      */
    public function getModuleFileContent(string $moduleName, string $filePath): string
    {
         $this->logger->info(
             '[NeutromeLabs_Mcp] Requesting file content via MCP',
             ['module' => $moduleName, 'file' => $filePath]
         );
         try {
             // Basic path sanitization
             $filePath = ltrim(str_replace(['..', '\\'], ['', '/'], $filePath), '/');

             // Check if module exists and is enabled
             if (!in_array($moduleName, $this->moduleList->getNames())) {
                 throw new LocalizedException(__('Module "%1" not found or is not enabled.', $moduleName));
             }

             $modulePath = $this->moduleDirReader->getModuleDir('', $moduleName);
             if (!$modulePath || !$this->fileDriver->isDirectory($modulePath)) {
                 throw new LocalizedException(__('Could not determine directory for module "%1".', $moduleName));
             }

             $fullPath = $modulePath . DIRECTORY_SEPARATOR . $filePath;

             // Security check: Ensure the resolved path is still within the module directory
             $realModulePath = $this->fileDriver->getRealPath($modulePath);
             $realFullPath = $this->fileDriver->getRealPath($fullPath);

             if (strpos($realFullPath, $realModulePath) !== 0) {
                 throw new LocalizedException(__('Access denied: File path is outside the module directory.'));
             }

             if (!$this->fileDriver->isExists($fullPath)) {
                 throw new LocalizedException(__('File "%1" not found in module "%2".', $filePath, $moduleName));
             }

             if (!$this->fileDriver->isFile($fullPath)) {
                 throw new LocalizedException(__('Path "%1" is not a file in module "%2".', $filePath, $moduleName));
             }

             if (!$this->fileDriver->isReadable($fullPath)) {
                 throw new LocalizedException(__('File "%1" is not readable in module "%2".', $filePath, $moduleName));
             }

             // Limit file size to prevent reading huge files
             $stat = $this->fileDriver->stat($fullPath);
             if ($stat['size'] > 10 * 1024 * 1024) { // 10MB limit
                 throw new LocalizedException(__('File "%1" is too large (> 10MB).', $filePath));
             }

             $content = $this->fileDriver->fileGetContents($fullPath);
             return $content;

         } catch (LocalizedException $e) {
             $this->logger->error(
                 '[NeutromeLabs_Mcp] Error getting file content: ' . $e->getMessage(),
                 ['module' => $moduleName, 'file' => $filePath, 'exception' => $e]
             );
             // Re-throw localized exceptions
             throw $e;
         } catch (\Throwable $e) {
             $this->logger->error(
                 '[NeutromeLabs_Mcp] General error getting file content: ' . $e->getMessage(),
                 ['module' => $moduleName, 'file' => $filePath, 'exception' => $e]
             );
             throw new \Exception(
                 "Error retrieving content for file '{$filePath}' in module '{$moduleName}': " . $e->getMessage()
             );
         }
    }
}
