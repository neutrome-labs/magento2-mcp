<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Api\Data;

/**
 * Represents a node (file or directory) in a module's file tree.
 * @api
 */
interface ModuleFileNodeInterface
{
    const NAME = 'name';
    const TYPE = 'type';
    const CHILDREN = 'children';

    /**
     * Get the name of the file or directory.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Set the name of the file or directory.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Get the type of the node ('file' or 'dir').
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set the type of the node ('file' or 'dir').
     *
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * Get the children nodes (for directories).
     * Returns null for files.
     *
     * @return \NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface[]|null
     */
    public function getChildren(): ?array;

    /**
     * Set the children nodes (for directories).
     *
     * @param \NeutromeLabs\Mcp\Api\Data\ModuleFileNodeInterface[]|null $children
     * @return $this
     */
    public function setChildren(?array $children): self;
}
