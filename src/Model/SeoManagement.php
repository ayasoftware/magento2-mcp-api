<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Model;

use Ayasoftware\McpApi\Api\SeoManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteFactory as UrlRewriteResourceFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Eav\Model\Config as EavConfig;

class SeoManagement implements SeoManagementInterface
{
    private array $duplicateCache = [];
    private int $duplicateCacheTtl = 300; // 5 minutes
    private array $attributeIdCache = [];

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly UrlRewriteFactory $urlRewriteFactory,
        private readonly UrlRewriteResourceFactory $urlRewriteResourceFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly EavConfig $eavConfig
    ) {
    }

    public function auditProduct(string $sku, int $storeId = 0): array
    {
        try {
            $product = $this->productRepository->get($sku, false, $storeId);

            $metaTitle = $product->getMetaTitle() ?? '';
            $metaDescription = $product->getMetaDescription() ?? '';
            $urlKey = $product->getUrlKey() ?? '';

            $issues = [];

            // Check for missing/empty meta tags
            if (empty($metaTitle)) {
                $issues[] = [
                    'type' => 'missing_meta_title',
                    'severity' => 'high',
                    'message' => 'Meta title is missing or empty'
                ];
            }

            if (empty($metaDescription)) {
                $issues[] = [
                    'type' => 'missing_meta_description',
                    'severity' => 'high',
                    'message' => 'Meta description is missing or empty'
                ];
            }

            if (empty($urlKey)) {
                $issues[] = [
                    'type' => 'missing_url_key',
                    'severity' => 'high',
                    'message' => 'URL key is missing or empty'
                ];
            }

            // Check meta title length
            if (!empty($metaTitle)) {
                $titleLength = mb_strlen($metaTitle);
                if ($titleLength < 50 || $titleLength > 60) {
                    $issues[] = [
                        'type' => 'meta_title_length',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Meta title length (%d chars) is outside recommended range (50-60)',
                            $titleLength
                        ),
                        'actual_length' => $titleLength,
                        'recommended_min' => 50,
                        'recommended_max' => 60
                    ];
                }
            }

            // Check meta description length
            if (!empty($metaDescription)) {
                $descLength = mb_strlen($metaDescription);
                if ($descLength < 150 || $descLength > 160) {
                    $issues[] = [
                        'type' => 'meta_description_length',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Meta description length (%d chars) is outside recommended range (150-160)',
                            $descLength
                        ),
                        'actual_length' => $descLength,
                        'recommended_min' => 150,
                        'recommended_max' => 160
                    ];
                }
            }

            // Check URL key format
            if (!empty($urlKey) && !preg_match('/^[a-z0-9-]+$/', $urlKey)) {
                $issues[] = [
                    'type' => 'invalid_url_key',
                    'severity' => 'medium',
                    'message' => 'URL key contains invalid characters (must be lowercase, numbers, and hyphens only)'
                ];
            }

            // Check for duplicate meta tags
            if (!empty($metaTitle)) {
                $duplicates = $this->checkDuplicates('product', 'meta_title', $metaTitle, (int)$product->getId(), $storeId);
                if (count($duplicates) > 0) {
                    $issues[] = [
                        'type' => 'duplicate_meta_title',
                        'severity' => 'medium',
                        'message' => sprintf('Meta title is used by %d other product(s)', count($duplicates)),
                        'duplicate_count' => count($duplicates),
                        'duplicate_entity_ids' => $duplicates
                    ];
                }
            }

            if (!empty($metaDescription)) {
                $duplicates = $this->checkDuplicates('product', 'meta_description', $metaDescription, (int)$product->getId(), $storeId);
                if (count($duplicates) > 0) {
                    $issues[] = [
                        'type' => 'duplicate_meta_description',
                        'severity' => 'medium',
                        'message' => sprintf('Meta description is used by %d other product(s)', count($duplicates)),
                        'duplicate_count' => count($duplicates),
                        'duplicate_entity_ids' => $duplicates
                    ];
                }
            }

            $issueSummary = [
                'total' => count($issues),
                'high' => 0,
                'medium' => 0,
                'warning' => 0
            ];

            foreach ($issues as $issue) {
                $issueSummary[$issue['severity']]++;
            }

            return [
                'success' => true,
                'entity' => [
                    'type' => 'product',
                    'sku' => $sku,
                    'id' => (int)$product->getId(),
                    'name' => $product->getName()
                ],
                'issues' => $issues,
                'seo_data' => [
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'url_key' => $urlKey
                ],
                'issue_summary' => $issueSummary
            ];

        } catch (NoSuchEntityException $exception) {
            return [
                'success' => false,
                'message' => sprintf('Product with SKU "%s" not found.', $sku),
                'error' => $exception->getMessage()
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => sprintf('An unexpected error occurred while auditing product "%s".', $sku),
                'error' => $exception->getMessage()
            ];
        }
    }

    public function auditCategory(int $categoryId, int $storeId = 0): array
    {
        try {
            $category = $this->categoryRepository->get($categoryId, $storeId);

            $metaTitle = $category->getMetaTitle() ?? '';
            $metaDescription = $category->getMetaDescription() ?? '';
            $urlKey = $category->getUrlKey() ?? '';

            $issues = [];

            // Check for missing/empty meta tags
            if (empty($metaTitle)) {
                $issues[] = [
                    'type' => 'missing_meta_title',
                    'severity' => 'high',
                    'message' => 'Meta title is missing or empty'
                ];
            }

            if (empty($metaDescription)) {
                $issues[] = [
                    'type' => 'missing_meta_description',
                    'severity' => 'high',
                    'message' => 'Meta description is missing or empty'
                ];
            }

            if (empty($urlKey)) {
                $issues[] = [
                    'type' => 'missing_url_key',
                    'severity' => 'high',
                    'message' => 'URL key is missing or empty'
                ];
            }

            // Check meta title length
            if (!empty($metaTitle)) {
                $titleLength = mb_strlen($metaTitle);
                if ($titleLength < 50 || $titleLength > 60) {
                    $issues[] = [
                        'type' => 'meta_title_length',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Meta title length (%d chars) is outside recommended range (50-60)',
                            $titleLength
                        ),
                        'actual_length' => $titleLength,
                        'recommended_min' => 50,
                        'recommended_max' => 60
                    ];
                }
            }

            // Check meta description length
            if (!empty($metaDescription)) {
                $descLength = mb_strlen($metaDescription);
                if ($descLength < 150 || $descLength > 160) {
                    $issues[] = [
                        'type' => 'meta_description_length',
                        'severity' => 'warning',
                        'message' => sprintf(
                            'Meta description length (%d chars) is outside recommended range (150-160)',
                            $descLength
                        ),
                        'actual_length' => $descLength,
                        'recommended_min' => 150,
                        'recommended_max' => 160
                    ];
                }
            }

            // Check URL key format
            if (!empty($urlKey) && !preg_match('/^[a-z0-9-]+$/', $urlKey)) {
                $issues[] = [
                    'type' => 'invalid_url_key',
                    'severity' => 'medium',
                    'message' => 'URL key contains invalid characters (must be lowercase, numbers, and hyphens only)'
                ];
            }

            // Check for duplicate meta tags
            if (!empty($metaTitle)) {
                $duplicates = $this->checkDuplicates('category', 'meta_title', $metaTitle, $categoryId, $storeId);
                if (count($duplicates) > 0) {
                    $issues[] = [
                        'type' => 'duplicate_meta_title',
                        'severity' => 'medium',
                        'message' => sprintf('Meta title is used by %d other categor(ies)', count($duplicates)),
                        'duplicate_count' => count($duplicates),
                        'duplicate_entity_ids' => $duplicates
                    ];
                }
            }

            if (!empty($metaDescription)) {
                $duplicates = $this->checkDuplicates('category', 'meta_description', $metaDescription, $categoryId, $storeId);
                if (count($duplicates) > 0) {
                    $issues[] = [
                        'type' => 'duplicate_meta_description',
                        'severity' => 'medium',
                        'message' => sprintf('Meta description is used by %d other categor(ies)', count($duplicates)),
                        'duplicate_count' => count($duplicates),
                        'duplicate_entity_ids' => $duplicates
                    ];
                }
            }

            $issueSummary = [
                'total' => count($issues),
                'high' => 0,
                'medium' => 0,
                'warning' => 0
            ];

            foreach ($issues as $issue) {
                $issueSummary[$issue['severity']]++;
            }

            return [
                'success' => true,
                'entity' => [
                    'type' => 'category',
                    'id' => $categoryId,
                    'name' => $category->getName()
                ],
                'issues' => $issues,
                'seo_data' => [
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'url_key' => $urlKey
                ],
                'issue_summary' => $issueSummary
            ];

        } catch (NoSuchEntityException $exception) {
            return [
                'success' => false,
                'message' => sprintf('Category with ID %d not found.', $categoryId),
                'error' => $exception->getMessage()
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => sprintf('An unexpected error occurred while auditing category %d.', $categoryId),
                'error' => $exception->getMessage()
            ];
        }
    }

    public function detectMissingMetaTags(
        string $entityType,
        int $storeId,
        array $filters,
        int $pageSize = 20,
        int $currentPage = 1
    ): array {
        try {
            if (!in_array($entityType, ['product', 'category'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid entity type. Must be "product" or "category".',
                ];
            }

            $pageSize = min(max(1, $pageSize), 100); // Enforce max 100
            $currentPage = max(1, $currentPage);

            if ($entityType === 'product') {
                $collection = $this->productCollectionFactory->create();
                $collection->setStoreId($storeId);
                $collection->addAttributeToSelect(['sku', 'name', 'meta_title', 'meta_description', 'url_key', 'status']);

                // Apply filters
                if (isset($filters['category_id'])) {
                    $collection->addCategoriesFilter(['eq' => $filters['category_id']]);
                }

                if (isset($filters['status'])) {
                    $collection->addAttributeToFilter('status', ['eq' => $filters['status']]);
                }

                if (isset($filters['date_from'])) {
                    $collection->addAttributeToFilter('created_at', ['gteq' => $filters['date_from']]);
                }

                if (isset($filters['date_to'])) {
                    $collection->addAttributeToFilter('created_at', ['lteq' => $filters['date_to']]);
                }

                // Filter for missing meta tags
                $collection->addAttributeToFilter(
                    [
                        ['attribute' => 'meta_title', 'null' => true],
                        ['attribute' => 'meta_title', 'eq' => ''],
                        ['attribute' => 'meta_description', 'null' => true],
                        ['attribute' => 'meta_description', 'eq' => ''],
                    ]
                );

            } else {
                $collection = $this->categoryCollectionFactory->create();
                $collection->setStoreId($storeId);
                $collection->addAttributeToSelect(['name', 'meta_title', 'meta_description', 'url_key', 'is_active']);

                // Apply filters
                if (isset($filters['status'])) {
                    $collection->addAttributeToFilter('is_active', ['eq' => $filters['status']]);
                }

                if (isset($filters['date_from'])) {
                    $collection->addAttributeToFilter('created_at', ['gteq' => $filters['date_from']]);
                }

                if (isset($filters['date_to'])) {
                    $collection->addAttributeToFilter('created_at', ['lteq' => $filters['date_to']]);
                }

                // Filter for missing meta tags
                $collection->addAttributeToFilter(
                    [
                        ['attribute' => 'meta_title', 'null' => true],
                        ['attribute' => 'meta_title', 'eq' => ''],
                        ['attribute' => 'meta_description', 'null' => true],
                        ['attribute' => 'meta_description', 'eq' => ''],
                    ]
                );
            }

            $totalCount = $collection->getSize();
            $collection->setPageSize($pageSize);
            $collection->setCurPage($currentPage);

            $items = [];
            foreach ($collection as $entity) {
                $item = [
                    'id' => (int)$entity->getId(),
                    'name' => $entity->getName(),
                    'meta_title' => $entity->getMetaTitle() ?? '',
                    'meta_description' => $entity->getMetaDescription() ?? '',
                    'url_key' => $entity->getUrlKey() ?? '',
                ];

                if ($entityType === 'product') {
                    $item['sku'] = $entity->getSku();
                    $item['status'] = (int)$entity->getStatus();
                } else {
                    $item['is_active'] = (bool)$entity->getIsActive();
                }

                $item['missing_fields'] = [];
                if (empty($entity->getMetaTitle())) {
                    $item['missing_fields'][] = 'meta_title';
                }
                if (empty($entity->getMetaDescription())) {
                    $item['missing_fields'][] = 'meta_description';
                }

                $items[] = $item;
            }

            return [
                'success' => true,
                'entity_type' => $entityType,
                'items' => $items,
                'total_count' => $totalCount,
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => (int)ceil($totalCount / $pageSize)
            ];

        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while detecting missing meta tags.',
                'error' => $exception->getMessage()
            ];
        }
    }

    public function updateMetaTitle(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($updates as $update) {
            try {
                if ($entityType === 'product') {
                    $sku = $update['sku'] ?? null;
                    if (!$sku) {
                        throw new LocalizedException(__('SKU is required for product updates'));
                    }

                    $entity = $this->productRepository->get($sku);
                    $entityIdentifier = ['sku' => $sku];
                } else {
                    $id = $update['id'] ?? null;
                    if (!$id) {
                        throw new LocalizedException(__('ID is required for category updates'));
                    }

                    $entity = $this->categoryRepository->get($id);
                    $entityIdentifier = ['id' => $id];
                }

                $metaTitle = $update['meta_title'] ?? '';
                if (empty($metaTitle)) {
                    throw new LocalizedException(__('Meta title cannot be empty'));
                }

                // Validate length
                $titleLength = mb_strlen($metaTitle);
                $lengthWarning = null;
                if ($titleLength < 50 || $titleLength > 60) {
                    $lengthWarning = sprintf(
                        'Meta title length (%d chars) is outside recommended range (50-60)',
                        $titleLength
                    );
                }

                $entity->setMetaTitle($metaTitle);

                if ($entityType === 'product') {
                    $this->productRepository->save($entity);
                } else {
                    $this->categoryRepository->save($entity);
                }

                $result = [
                    'entity' => $entityIdentifier,
                    'success' => true,
                    'message' => 'Meta title updated successfully'
                ];

                if ($lengthWarning) {
                    $result['warning'] = $lengthWarning;
                }

                $results[] = $result;
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'entity' => $entityIdentifier ?? $update,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failureCount++;

                if ($stopOnError) {
                    break;
                }
            }
        }

        return [
            'success' => $failureCount === 0,
            'message' => sprintf('Updated %d of %d entities', $successCount, count($updates)),
            'summary' => [
                'total' => count($updates),
                'successful' => $successCount,
                'failed' => $failureCount,
                'stopped_early' => $stopOnError && $failureCount > 0
            ],
            'results' => $results
        ];
    }

    public function updateMetaDescription(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($updates as $update) {
            try {
                if ($entityType === 'product') {
                    $sku = $update['sku'] ?? null;
                    if (!$sku) {
                        throw new LocalizedException(__('SKU is required for product updates'));
                    }

                    $entity = $this->productRepository->get($sku);
                    $entityIdentifier = ['sku' => $sku];
                } else {
                    $id = $update['id'] ?? null;
                    if (!$id) {
                        throw new LocalizedException(__('ID is required for category updates'));
                    }

                    $entity = $this->categoryRepository->get($id);
                    $entityIdentifier = ['id' => $id];
                }

                $metaDescription = $update['meta_description'] ?? '';
                if (empty($metaDescription)) {
                    throw new LocalizedException(__('Meta description cannot be empty'));
                }

                // Validate length
                $descLength = mb_strlen($metaDescription);
                $lengthWarning = null;
                if ($descLength < 150 || $descLength > 160) {
                    $lengthWarning = sprintf(
                        'Meta description length (%d chars) is outside recommended range (150-160)',
                        $descLength
                    );
                }

                $entity->setMetaDescription($metaDescription);

                if ($entityType === 'product') {
                    $this->productRepository->save($entity);
                } else {
                    $this->categoryRepository->save($entity);
                }

                $result = [
                    'entity' => $entityIdentifier,
                    'success' => true,
                    'message' => 'Meta description updated successfully'
                ];

                if ($lengthWarning) {
                    $result['warning'] = $lengthWarning;
                }

                $results[] = $result;
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'entity' => $entityIdentifier ?? $update,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failureCount++;

                if ($stopOnError) {
                    break;
                }
            }
        }

        return [
            'success' => $failureCount === 0,
            'message' => sprintf('Updated %d of %d entities', $successCount, count($updates)),
            'summary' => [
                'total' => count($updates),
                'successful' => $successCount,
                'failed' => $failureCount,
                'stopped_early' => $stopOnError && $failureCount > 0
            ],
            'results' => $results
        ];
    }

    public function updateUrlKey(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($updates as $update) {
            try {
                if ($entityType === 'product') {
                    $sku = $update['sku'] ?? null;
                    if (!$sku) {
                        throw new LocalizedException(__('SKU is required for product updates'));
                    }

                    $entity = $this->productRepository->get($sku);
                    $entityIdentifier = ['sku' => $sku];
                } else {
                    $id = $update['id'] ?? null;
                    if (!$id) {
                        throw new LocalizedException(__('ID is required for category updates'));
                    }

                    $entity = $this->categoryRepository->get($id);
                    $entityIdentifier = ['id' => $id];
                }

                $newUrlKey = $update['url_key'] ?? '';
                if (empty($newUrlKey)) {
                    throw new LocalizedException(__('URL key cannot be empty'));
                }

                // Validate URL key format
                if (!preg_match('/^[a-z0-9-]+$/', $newUrlKey)) {
                    throw new LocalizedException(__('URL key must contain only lowercase letters, numbers, and hyphens'));
                }

                $oldUrlKey = $entity->getUrlKey();
                $storeId = $update['store_id'] ?? 0;

                // Create 301 redirect BEFORE updating URL key
                if (!empty($oldUrlKey) && $oldUrlKey !== $newUrlKey) {
                    try {
                        $urlRewrite = $this->urlRewriteFactory->create();
                        $urlRewrite->setEntityType($entityType === 'product' ? 'product' : 'category')
                                   ->setEntityId((int)$entity->getId())
                                   ->setRequestPath($oldUrlKey . '.html')
                                   ->setTargetPath($newUrlKey . '.html')
                                   ->setRedirectType(301)
                                   ->setStoreId($storeId)
                                   ->setDescription('Auto-created by MCP SEO tool');

                        $urlRewriteResource = $this->urlRewriteResourceFactory->create();
                        $urlRewriteResource->save($urlRewrite);

                        $redirectCreated = true;
                    } catch (\Exception $e) {
                        // Log error but continue with URL key update
                        $redirectCreated = false;
                        $redirectError = $e->getMessage();
                    }
                }

                $entity->setUrlKey($newUrlKey);

                if ($entityType === 'product') {
                    $this->productRepository->save($entity);
                } else {
                    $this->categoryRepository->save($entity);
                }

                $result = [
                    'entity' => $entityIdentifier,
                    'success' => true,
                    'message' => 'URL key updated successfully',
                    'old_url_key' => $oldUrlKey,
                    'new_url_key' => $newUrlKey
                ];

                if (isset($redirectCreated)) {
                    $result['redirect_created'] = $redirectCreated;
                    if (!$redirectCreated && isset($redirectError)) {
                        $result['redirect_error'] = $redirectError;
                    }
                }

                $results[] = $result;
                $successCount++;

            } catch (\Exception $e) {
                $results[] = [
                    'entity' => $entityIdentifier ?? $update,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failureCount++;

                if ($stopOnError) {
                    break;
                }
            }
        }

        return [
            'success' => $failureCount === 0,
            'message' => sprintf('Updated %d of %d entities', $successCount, count($updates)),
            'summary' => [
                'total' => count($updates),
                'successful' => $successCount,
                'failed' => $failureCount,
                'stopped_early' => $stopOnError && $failureCount > 0
            ],
            'results' => $results
        ];
    }

    /**
     * Check for duplicate attribute values
     *
     * @param string $entityType 'product' or 'category'
     * @param string $attributeCode Attribute code to check
     * @param string $value Value to search for
     * @param int $entityId Current entity ID to exclude
     * @param int $storeId Store view ID
     * @return array Array of entity IDs with duplicate values
     */
    private function checkDuplicates(
        string $entityType,
        string $attributeCode,
        string $value,
        int $entityId,
        int $storeId
    ): array {
        $cacheKey = sprintf('%s_%s_%s_%s_%d', $entityType, $attributeCode, md5($value), $entityId, $storeId);

        // Check cache
        if (isset($this->duplicateCache[$cacheKey])) {
            $cached = $this->duplicateCache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->duplicateCacheTtl) {
                return $cached['data'];
            }
        }

        try {
            // Get attribute ID
            $attribute = $this->eavConfig->getAttribute(
                $entityType === 'product' ? 'catalog_product' : 'catalog_category',
                $attributeCode
            );
            $attributeId = $attribute->getAttributeId();

            if (!$attributeId) {
                return [];
            }

            $connection = $this->resourceConnection->getConnection();
            $tableName = $entityType === 'product'
                ? $this->resourceConnection->getTableName('catalog_product_entity_varchar')
                : $this->resourceConnection->getTableName('catalog_category_entity_varchar');

            $select = $connection->select()
                ->from($tableName, ['entity_id'])
                ->where('attribute_id = ?', $attributeId)
                ->where('value = ?', $value)
                ->where('entity_id != ?', $entityId)
                ->where('store_id = ?', $storeId);

            $result = $connection->fetchCol($select);

            // Cache the result
            $this->duplicateCache[$cacheKey] = [
                'data' => $result,
                'timestamp' => time()
            ];

            return $result;

        } catch (\Exception $e) {
            return [];
        }
    }
}
