# Exportaccelerator Productfeed for Magento 2

`Exportaccelerator_Productfeed` exports existing Magento product reviews as a
Google Merchant Center Product Reviews XML 2.3 feed. It is a feed exporter: it
does not collect reviews, send review invitation emails, provide review
widgets, or moderate reviews.

The module creates a separate feed for each Magento store view. The feed can be
generated on demand from the command line, regenerated nightly by Magento
cron, or requested through the module's frontend route.

## Features

- Exports Magento product reviews in Google's Product Reviews XML 2.3 format.
- Creates a static XML file for each store view under
  `pub/productreview/feed/store-<store-id>.xml`.
- Publishes the static file at
  `https://<store-domain>/productreview/feed/store-<store-id>.xml`.
- Regenerates feeds daily at 02:00 through Magento's default cron group.
- Supports generating feeds for all store views or a single store view using
  the Magento CLI.
- Also provides a dynamic feed route at
  `https://<store-domain>/productfeed/index/feed`, which generates XML for the
  current store view when requested.
- Exports reviewer names anonymously.
- Includes available product identifiers (SKU, GTIN/UPC, MPN, and brand).
- Writes feed files to a temporary file and renames them into place after
  generation, so readers do not see a partially written feed.

## Important behavior and privacy

The feed selects product reviews of **all Magento statuses** (approved,
pending, and not approved) assigned to the current store view. It is publicly
accessible, without authentication. Review text from pending or not-approved
reviews can therefore be read by anyone who can access the feed URL. Review
status does not currently control export.

Only enabled products assigned to the selected store view are included.
Google's format requires review content, a rating, and a product URL, so reviews
missing any of these are omitted. Omitted reviews are logged. Rating values are
derived from Magento review rating votes; the feed does not invent ratings.

This module does not send review data to Google. Submit the public static feed
URL to the appropriate Google Merchant Center program and inspect Google's
feed diagnostics after processing.

## Requirements

- Magento 2 with `Magento_Catalog`, `Magento_Review`, and `Magento_Cron`
  enabled.
- PHP's `XMLWriter` extension.
- Magento cron configured and running for automatic nightly generation.
- A writable Magento `pub/` directory.

## Installation

### Install from this Magento project

The module is located at `app/code/Exportaccelerator/Productfeed`. From the
Magento project root, run:

```bash
php bin/magento module:enable Exportaccelerator_Productfeed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Confirm Magento sees the module:

```bash
php bin/magento module:status Exportaccelerator_Productfeed
```

For a production deployment, run the normal Magento deployment steps for your
environment, including dependency injection compilation when appropriate:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
```

The module is registered as a local Magento module; its Composer metadata does
not by itself install it from Packagist or another Composer repository.

## Generate feeds immediately

Generate or refresh the static feed for every store view:

```bash
php bin/magento productreview:feed
```

Generate or refresh the feed for one store view by its store code:

```bash
php bin/magento productreview:feed --store=default
```

The command reports the generated file path and its public URL. For example,
the `default` store view with ID `1` generates:

```text
File: pub/productreview/feed/store-1.xml
URL:  https://<store-domain>/productreview/feed/store-1.xml
```

The misspelled alias below is also available for compatibility with the
original command example:

```bash
php bin/magento proudctreview:feed
```

## Automatic nightly generation

The module registers `exportaccelerator_productreview_feed_generate` in
Magento's `default` cron group with the schedule `0 2 * * *` (daily at 02:00
in Magento cron's configured timezone). The job attempts to generate a static
feed for every store view and logs success or failure for each store.

Magento's operating-system cron must be installed and running. To check the
Magento cron configuration, run:

```bash
php bin/magento cron:run --group=default
```

This command runs the due jobs in that cron group; it does not change the
configured schedule. The scheduled job will only execute when Magento cron is
running and the 02:00 job is due.

## Feed URLs

There are two ways to access a feed:

1. **Static feed (recommended for scheduled use):**
   `https://<store-domain>/productreview/feed/store-<store-id>.xml`
2. **Dynamic feed for the current store view:**
   `https://<store-domain>/productfeed/index/feed`

The static file is updated by the CLI command or nightly cron. The dynamic
route builds the XML during the HTTP request and does not read the generated
static file.

## Troubleshooting

- **No static file exists:** run `php bin/magento productreview:feed` and check
  that Magento can write to `pub/productreview/feed/`.
- **The command is not recognized:** confirm the module is enabled, then run
  `php bin/magento setup:upgrade` and `php bin/magento cache:flush`.
- **The feed contains fewer reviews than Magento Admin:** check that each
  review has non-empty text and a Magento rating vote, its product is enabled
  and assigned to the store view, and the product URL is available. All review
  statuses are considered; missing required feed fields can still cause a
  review to be omitted.
- **Nightly feed is stale:** verify Magento's system cron is running, the
  default cron group is being processed, and check Magento logs for feed
  generation errors.
- **A public feed exposes a review that is not approved:** this is expected
  with the current all-status export behavior. Do not publish the URL if that
  is not acceptable for your store.
