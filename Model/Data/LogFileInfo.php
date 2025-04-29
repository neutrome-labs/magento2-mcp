<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model\Data;

use Magento\Framework\Api\AbstractSimpleObject;
use NeutromeLabs\Mcp\Api\Data\LogFileInfoInterface;

/**
 * Class LogFileInfo
 * Concrete implementation for LogFileInfoInterface.
 */
class LogFileInfo extends AbstractSimpleObject implements LogFileInfoInterface
{
    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return (string)$this->_get(self::PATH);
    }

    /**
     * @inheritDoc
     */
    public function setPath(string $path): LogFileInfoInterface
    {
        return $this->setData(self::PATH, $path);
    }

    /**
     * @inheritDoc
     */
    public function getSize(): int
    {
        return (int)$this->_get(self::SIZE);
    }

    /**
     * @inheritDoc
     */
    public function setSize(int $size): LogFileInfoInterface
    {
        return $this->setData(self::SIZE, $size);
    }

    /**
     * @inheritDoc
     */
    public function getLastModified(): int
    {
        return (int)$this->_get(self::LAST_MODIFIED);
    }

    /**
     * @inheritDoc
     */
    public function setLastModified(int $timestamp): LogFileInfoInterface
    {
        return $this->setData(self::LAST_MODIFIED, $timestamp);
    }
}
