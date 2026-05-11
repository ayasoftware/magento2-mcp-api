<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Model;

use Ayasoftware\McpApi\Api\IndexerManagementInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory;

class IndexerManagement implements IndexerManagementInterface
{
    private const SEARCH_INDEXER_ID = 'catalogsearch_fulltext';

    public function __construct(
        private readonly CollectionFactory $indexerCollectionFactory
    ) {
    }

    public function reindex(): array
    {
        $startedAt = microtime(true);
        $startedAtIso = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $indexers = $this->indexerCollectionFactory->create()->getItems();
        $results = [];

        foreach ($this->getIndexerInfo() as $indexerInfo) {
            try {
                $indexer = $indexers[$indexerInfo['indexer_id']] ?? null;

                if (!$indexer) {
                    throw new \RuntimeException(sprintf('Indexer "%s" was not found.', $indexerInfo['indexer_id']));
                }

                $indexer->reindexAll();

                $results[] = [
                    'indexer_id' => $indexerInfo['indexer_id'],
                    'title' => $indexerInfo['title'],
                    'status' => $indexerInfo['status'],
                    'success' => true,
                ];
            } catch (\Throwable $exception) {
                $results[] = [
                    'indexer_id' => $indexerInfo['indexer_id'],
                    'title' => $indexerInfo['title'],
                    'status' => 'error',
                    'success' => false,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $finishedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return [
            'success' => !in_array(false, array_column($results, 'success'), true),
            'started_at' => $startedAtIso->format(DATE_ATOM),
            'finished_at' => $finishedAt->format(DATE_ATOM),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'results' => $results,
        ];
    }

    public function status(): array
    {
        return [
            'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'indexers' => $this->getIndexerInfo(),
        ];
    }

    public function info(): array
    {
        return [
            'indexers' => $this->getIndexerInfo(),
        ];
    }

    public function infoById(string $indexerId): array
    {
        $indexerInfo = $this->getIndexerInfoById($indexerId);

        if (!$indexerInfo) {
            return [
                'success' => false,
                'message' => sprintf('Indexer "%s" was not found.', $indexerId),
            ];
        }

        return [
            'indexer' => $indexerInfo,
        ];
    }

    public function statusById(string $indexerId): array
    {
        $indexerInfo = $this->getIndexerInfoById($indexerId);

        if (!$indexerInfo) {
            return [
                'success' => false,
                'message' => sprintf('Indexer "%s" was not found.', $indexerId),
            ];
        }

        return [
            'generated_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'indexer' => $indexerInfo,
        ];
    }

    public function reindexSearch(): array
    {
        return $this->reindexById(self::SEARCH_INDEXER_ID);
    }

    public function reindexById(string $indexerId): array
    {
        return $this->reindexIndexer($indexerId);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function getIndexerInfo(): array
    {
        $indexers = [];

        foreach ($this->indexerCollectionFactory->create()->getItems() as $indexerId => $indexer) {
            $indexers[] = [
                'indexer_id' => (string) $indexerId,
                'title' => (string) $indexer->getData('title'),
                'description' => (string) $indexer->getData('description'),
                'status' => (string) $indexer->getData('status'),
            ];
        }

        return $indexers;
    }

    private function reindexIndexer(string $indexerId): array
    {
        $startedAt = microtime(true);
        $startedAtIso = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $indexerInfo = $this->getIndexerInfoById($indexerId);
        $indexer = $this->indexerCollectionFactory->create()->getItems()[$indexerId] ?? null;

        if (!$indexerInfo) {
            return [
                'success' => false,
                'started_at' => $startedAtIso->format(DATE_ATOM),
                'finished_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'results' => [
                    [
                        'indexer_id' => $indexerId,
                        'success' => false,
                        'status' => 'error',
                        'message' => sprintf('Indexer "%s" was not found.', $indexerId),
                    ],
                ],
            ];
        }

        if (!$indexer) {
            return [
                'success' => false,
                'started_at' => $startedAtIso->format(DATE_ATOM),
                'finished_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'results' => [
                    [
                        'indexer_id' => $indexerId,
                        'title' => $indexerInfo['title'],
                        'success' => false,
                        'status' => 'error',
                        'message' => sprintf('Indexer "%s" was not found in the collection.', $indexerId),
                    ],
                ],
            ];
        }

        try {
            $indexer->reindexAll();

            return [
                'success' => true,
                'started_at' => $startedAtIso->format(DATE_ATOM),
                'finished_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'results' => [
                    [
                        'indexer_id' => $indexerId,
                        'title' => $indexerInfo['title'],
                        'status' => $indexerInfo['status'],
                        'success' => true,
                    ],
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'started_at' => $startedAtIso->format(DATE_ATOM),
                'finished_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'results' => [
                    [
                        'indexer_id' => $indexerId,
                        'title' => $indexerInfo['title'],
                        'status' => 'error',
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ],
                ],
            ];
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function getIndexerInfoById(string $indexerId): ?array
    {
        foreach ($this->getIndexerInfo() as $indexerInfo) {
            if ($indexerInfo['indexer_id'] === $indexerId) {
                return $indexerInfo;
            }
        }

        return null;
    }
}
