# Magento 2 MCP API

Minimal Magento 2 module scaffold for the `ayasoftware/magento2-mcp-api` package.

## Layout

- `composer.json`
- `src/registration.php`
- `src/etc/module.xml`
- `src/etc/acl.xml`
- `src/etc/di.xml`
- `src/etc/webapi.xml`
- `src/Api/IndexerManagementInterface.php`
- `src/Model/IndexerManagement.php`

## Endpoints

- `POST /V1/mg_indexer_reindex`
- `GET /V1/mg_indexer_info`
- `GET /V1/mg_indexer_info/:indexerId`
- `POST /V1/mg_indexer_reindex/:indexerId`
- `POST /V1/mg_indexer_reindex_search`
- `GET /V1/mg_indexer_status`
- `GET /V1/mg_indexer_status/:indexerId`
