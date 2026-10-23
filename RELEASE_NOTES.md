# Release Notes

## Initial release — 2026-10-23

Introduces the `Pkgroup_Productfeed` Magento 2 module for exporting
existing product reviews as Google Merchant Center Product Reviews XML 2.4.

### Added

- Public dynamic feed for the current store view:
  `/productfeed/index/feed`.
- Static XML feed files, generated per store view at
  `pub/productreview/feed/store-<store-id>.xml`.
- Magento CLI command to generate feeds for all store views:
  `php bin/magento productreview:feed`.
- Optional store-specific generation:
  `php bin/magento productreview:feed --store=<store-code>`.
- Compatibility alias `php bin/magento proudctreview:feed`.
- Nightly feed regeneration at 02:00 through Magento's default cron group.
- Anonymous reviewer names and available product identifiers (SKU, GTIN/UPC,
  MPN, and brand).
- Atomic static-file replacement after successful feed generation.

### Feed behavior and compatibility notes

- The feed considers reviews of all Magento statuses. Since feed URLs are
  public, pending and not-approved review content may also be exposed.
- Only enabled products assigned to the current store view are eligible.
- Reviews without required content, a rating, or a product URL are omitted and
  logged; rating values are not fabricated.
- Requires Magento Catalog, Review, and Cron modules, the PHP `XMLWriter`
  extension, and a writable `pub/` directory.
- Magento cron must be running for nightly regeneration.

### Installation

For Composer-based installation, the Composer repository containing the module
must be configured:

```bash
composer require pkgroup/productfeed
php bin/magento module:enable Pkgroup_Productfeed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

For installation from `app/code`, run the Magento module enable, setup upgrade,
and cache flush commands without the Composer command.
