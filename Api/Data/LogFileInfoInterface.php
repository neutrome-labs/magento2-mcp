<?php
/**
 * Copyright © NeutromeLabs. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace NeutromeLabs\Mcp\Api\Data;

/**
 * Interface LogFileInfoInterface
 * Represents information about a log file.
 * @api
 * @since 1.0.0
 */
interface LogFileInfoInterface
{
    public const PATH = 'path';
    public const SIZE = 'size';
    public const LAST_MODIFIED = 'last_modified';

    /**
     * Get the full path to the log file.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Set the full path to the log file.
     *
     * @param string $path
     * @return $this
     */
    public function setPath(string $path): self;

    /**
     * Get the size of the log file in bytes.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Set the size of the log file in bytes.
     *
     * @param int $size
     * @return $this
     */
    public function setSize(int $size): self;

    /**
     * Get the last modified timestamp.
     *
     * @return int Unix timestamp
     */
    public function getLastModified(): int;

    /**
     * Set the last modified timestamp.
     *
     * @param int $timestamp Unix timestamp
     * @return $this
     */
    public function setLastModified(int $timestamp): self;
}
