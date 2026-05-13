# Magento 2 MCP API

Additional REST API endpoints for Magento 2 MCP Server that extend Magento's native capabilities.

## Version

**Current Version:** 1.1.0

## Installation

```bash
composer require ayasoftware/magento2-mcp-api
php bin/magento module:enable Ayasoftware_McpApi
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Features

### Indexer Management
Advanced indexer operations beyond Magento's default REST API.

### Coupon Management
Update individual coupon properties (not available in Magento's default REST API).

## Layout

- `composer.json`
- `src/registration.php`
- `src/etc/module.xml`
- `src/etc/acl.xml`
- `src/etc/di.xml`
- `src/etc/webapi.xml`
- `src/Api/IndexerManagementInterface.php`
- `src/Model/IndexerManagement.php`
- `src/Api/CouponManagementInterface.php`
- `src/Model/CouponManagement.php`

## Endpoints

### Indexer Management

- `POST /V1/mg_indexer_reindex` - Reindex all indexers
- `GET /V1/mg_indexer_info` - Get info for all indexers
- `GET /V1/mg_indexer_info/:indexerId` - Get info for specific indexer
- `POST /V1/mg_indexer_reindex/:indexerId` - Reindex specific indexer
- `POST /V1/mg_indexer_reindex_search` - Reindex search index only
- `GET /V1/mg_indexer_status` - Get status for all indexers
- `GET /V1/mg_indexer_status/:indexerId` - Get status for specific indexer

### Coupon Management

- `PUT /V1/mg_coupon/:couponId` - Update coupon properties

#### Update Coupon Example

```bash
curl -X PUT "https://your-store.com/rest/V1/mg_coupon/123" \
  -H "Authorization: Bearer your-access-token" \
  -H "Content-Type: application/json" \
  -d '{
    "couponData": {
      "code": "SUMMER2026",
      "usage_limit": 1000,
      "usage_per_customer": 5,
      "expiration_date": "2026-08-31"
    }
  }'
```

**Updatable Fields:**
- `code` - Coupon code string
- `usage_limit` - Maximum number of times coupon can be used
- `usage_per_customer` - Maximum uses per customer
- `times_used` - Current usage count
- `expiration_date` - Expiration date (Y-m-d format)
- `is_primary` - Whether this is the primary coupon for the rule

## License

MIT
