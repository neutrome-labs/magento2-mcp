<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Model\Data;

use NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface;
use Magento\Framework\DataObject;

/**
 * Data model for a module file tree node.
 */
class ModuleFileNode extends DataObject implements ModuleFileNodeInterface
{
    /**
     * @inheritdoc
      */
     public function getName(): string
     {
         return $this->getData(self::NAME);
     }

    /**
     * @inheritdoc
     */
    public function setName(string $name): ModuleFileNodeInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
      */
     public function getType(): string
     {
         return $this->getData(self::TYPE);
     }

    /**
     * @inheritdoc
     */
    public function setType(string $type): ModuleFileNodeInterface
    {
        // Basic validation
        if (!in_array($type, ['file', 'dir'])) {
            throw new \InvalidArgumentException("Invalid node type provided: {$type}. Must be 'file' or 'dir'.");
        }
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritdoc
      */
     public function getChildren(): ?array
     {
         return $this->getData(self::CHILDREN);
     }

    /**
     * @inheritdoc
     */
    public function setChildren(?array $children): ModuleFileNodeInterface
    {
        return $this->setData(self::CHILDREN, $children);
    }
}
