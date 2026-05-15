<?php

declare(strict_types=1);

namespace Ayasoftware\McpApi\Api;

interface SeoManagementInterface
{
    /**
     * Audit a single product for SEO issues
     *
     * Checks for missing/empty meta tags, meta tag length validation,
     * duplicate meta tags, and URL key issues.
     *
     * @param string $sku Product SKU
     * @param int $storeId Store view ID (0 for default)
     * @return array<string, mixed>
     */
    public function auditProduct(string $sku, int $storeId = 0): array;

    /**
     * Audit a single category for SEO issues
     *
     * Checks for missing/empty meta tags, meta tag length validation,
     * duplicate meta tags, and URL key issues.
     *
     * @param int $categoryId Category ID
     * @param int $storeId Store view ID (0 for default)
     * @return array<string, mixed>
     */
    public function auditCategory(int $categoryId, int $storeId = 0): array;

    /**
     * Detect products or categories with missing meta tags
     *
     * Scans the catalog for entities with missing or empty meta tags.
     * Supports filtering by category, status, and date range.
     * Returns paginated results.
     *
     * @param string $entityType Entity type ('product' or 'category')
     * @param int $storeId Store view ID (0 for default)
     * @param array<string, mixed> $filters Filter criteria (category_id, status, date_from, date_to)
     * @param int $pageSize Number of items per page (max 100)
     * @param int $currentPage Current page number
     * @return array<string, mixed>
     */
    public function detectMissingMetaTags(
        string $entityType,
        int $storeId,
        array $filters,
        int $pageSize = 20,
        int $currentPage = 1
    ): array;

    /**
     * Update meta title for products or categories
     *
     * Supports both single and batch operations (max 100 per batch).
     * Validates meta title length (recommended: 50-60 characters).
     *
     * @param string $entityType Entity type ('product' or 'category')
     * @param array<string, mixed> $updates Array of updates with sku/id and meta_title
     * @param bool $stopOnError Stop processing on first error (default: false)
     * @return array<string, mixed>
     */
    public function updateMetaTitle(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array;

    /**
     * Update meta description for products or categories
     *
     * Supports both single and batch operations (max 100 per batch).
     * Validates meta description length (recommended: 150-160 characters).
     *
     * @param string $entityType Entity type ('product' or 'category')
     * @param array<string, mixed> $updates Array of updates with sku/id and meta_description
     * @param bool $stopOnError Stop processing on first error (default: false)
     * @return array<string, mixed>
     */
    public function updateMetaDescription(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array;

    /**
     * Update URL key with automatic 301 redirect creation
     *
     * Supports both single and batch operations (max 50 per batch).
     * Creates 301 redirects from old to new URLs automatically.
     * URL keys must be lowercase with hyphens only.
     *
     * @param string $entityType Entity type ('product' or 'category')
     * @param array<string, mixed> $updates Array of updates with sku/id and url_key
     * @param bool $stopOnError Stop processing on first error (default: false)
     * @return array<string, mixed>
     */
    public function updateUrlKey(
        string $entityType,
        array $updates,
        bool $stopOnError = false
    ): array;
}
