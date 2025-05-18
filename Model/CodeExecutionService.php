<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use NeutromeLabs\Mcp\Api\CodeExecutionServiceInterface;
use PDO;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service class implementing code and command execution operations.
 */
class CodeExecutionService implements CodeExecutionServiceInterface
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
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * CodeExecutionService constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     * @param DirectoryList $directoryList
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        LoggerInterface    $logger,
        DirectoryList      $directoryList
    )
    {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
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
        } catch (Throwable $e) {
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
                $resultData = $statement->fetchAll(PDO::FETCH_ASSOC);
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

        } catch (Throwable $e) {
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
            // Change to application root directory before executing the command
            $appRoot = $this->directoryList->getRoot();
            $currentDir = getcwd();

            // Change to application root directory
            chdir($appRoot);

            // Redirect stderr to stdout to capture errors
            $fullCommand = $command . ' 2>&1';
            $output = shell_exec($fullCommand);

            // Restore original directory
            chdir($currentDir);

            if ($output === null) {
                // shell_exec can return null on error or if command produces no output
                // Hard to distinguish, but log a warning.
                $this->logger->warning('[NeutromeLabs_Mcp] Shell command executed but returned null.', ['command' => $command]);
                $output = "OK (No output or error)";
            }

        } catch (Throwable $e) {
            $error = $e;
            $this->logger->error(
                '[NeutromeLabs_Mcp] Error executing shell command via MCP: ' . $e->getMessage(),
                ['exception' => $e]
            );

            // Make sure we restore the directory even if there's an error
            if (isset($currentDir)) {
                chdir($currentDir);
            }
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
}
