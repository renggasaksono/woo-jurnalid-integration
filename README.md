# WooCommerce  Jurnal.ID Integration
WooCommerce and Jurnal.ID integration plugin for WordPress.

Automatically sync order data and product stock from WooCommerce to Jurnal (https://www.jurnal.id) accounting software.

This plugin if FREE to use, tested latest on WordPress v5.9.2 and WooCommerce 5.1.0.

## Main Features
- API Integration
- Account Mapping
- Product Mapping
- Sync History

## Automatic Sync
- Sync runs when new order is being created or status changes
- Journal entry being created depends on the paid status of the order
- Automatically sync when previous orders is cancelled

Each sync history will be recorded and can be seen on plugin admin section.

## Requirements
Valid Jurnal.ID API key. You can get this from your active account.

See here for more information https://www.jurnal.id/id/guidebooks/cara-melakukan-integrasi-melalui-api-key

## Screenshots

### Plugin Settings
![Plugin Settings](https://github.com/renggasaksono/woo-jurnalid-integration/blob/main/image/1-plugin-settings.jpg)

### Account Mapping
![Account Mapping](https://github.com/renggasaksono/woo-jurnalid-integration/blob/main/image/2-account-mapping.jpg)

### Product Mapping
![Product Mapping](https://github.com/renggasaksono/woo-jurnalid-integration/blob/main/image/3-product-mapping.jpg)

### Sync History
![Sync History](https://github.com/renggasaksono/woo-jurnalid-integration/blob/main/image/4-sync-history.jpg)

### Sync Process Flow
![Sync History](https://github.com/renggasaksono/woo-jurnalid-integration/blob/main/image/5-sync-process-flow)

## Changes Log
### 2.1.0
- New: Run order sync from admin setting
- New: Add sync validation for order status changes from admin dashboard
- Update: Change woocommerce hook on web checkout
- Update: Optimize log based on specific action (create/update/delete)
- Update: Rename sync status from Unsyced to Pending
- Update: Minor bug fixes
### 2.0.0
- Remove cron functions (fixed bug pending sync logs)
- Add new function to add metadata to order
- Optimize and refactor almost 80% of overall sync process

## To Do
- Filter and search function in log page
- Filter and search function in product mapping page
- Bulk actions for sync log ( run sync, delete)
- Unset product mapping
- Add custom value for calculate tax function
- Improve notes for errors returned from Jurnal API