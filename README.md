# WooCommerce  Jurnal.ID Integration
WooCommerce and Jurnal.ID integration plugin for WordPress.

Automatically sync order data and product stock from WooCommerce to Jurnal (https://www.jurnal.id) accounting software.

This plugin if FREE to use, tested latest on WordPress v5.6.

## Main Features
- API Integration
- Account Mapping
- Product Mapping
- Sync History

## Automatic Sync
Sync runs automatically every 10 minutes using WP-Cron, currently processing up to 20 records per job.
- Create Journal Entry when new Order is On Hold
- Update Journal Entry when Order is Processing
- Delete Journal Entry when Order is Cancelled
- Create Stock Adjustment when Order is Processing
- Delete Stock Adjustment when Order is Cancelled

## Requirements
Valid Jurnal.ID API key. You can get this from your active account.

See here for more information https://www.jurnal.id/id/guidebooks/cara-melakukan-integrasi-melalui-api-key
