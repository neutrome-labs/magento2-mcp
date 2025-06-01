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
        return "ERROR: PHP Code execution is disabled for security reasons.";
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
        return "ERROR: Shell Access is disabled for security reasons.";
    }
}
