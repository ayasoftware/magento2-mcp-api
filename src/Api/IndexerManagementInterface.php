<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Api;

interface IndexerManagementInterface
{
    /**
     * Reindex all Magento indexers.
     *
     * @return array<string, mixed>
     */
    public function reindex(): array;

    /**
     * Reindex a single Magento indexer.
     *
     * @param string $indexerId
     *
     * @return array<string, mixed>
     */
    public function reindexById(string $indexerId): array;

    /**
     * Reindex the Magento search index only.
     *
     * @return array<string, mixed>
     */
    public function reindexSearch(): array;

    /**
     * Get the current indexer inventory.
     *
     * @return array<string, mixed>
     */
    public function info(): array;

    /**
     * Get the inventory for a single Magento indexer.
     *
     * @param string $indexerId
     * @return array<string, mixed>
     */
    public function infoById(string $indexerId): array;

    /**
     * Get the current status of all Magento indexers.
     *
     * @return array<string, mixed>
     */
    public function status(): array;

    /**
     * Get the current status for a single Magento indexer.
     *
     * @param string $indexerId
     * @return array<string, mixed>
     */
    public function statusById(string $indexerId): array;
}
