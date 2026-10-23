# Productfeed Reference Manual

## Module identity

| Property | Value |
| --- | --- |
| Magento module | `Pkgroup_Productfeed` |
| Composer package | `pkgroup/productfeed` |
| Feed format | Google Merchant Center Product Reviews XML 2.4 |
| Required Magento modules | `Magento_Catalog`, `Magento_Review`, `Magento_Cron` |
| XML PHP extension | `XMLWriter` |

## Interfaces

### Static feed

| Item | Value |
| --- | --- |
| Output directory | `pub/productreview/feed/` |
| File name | `store-<store-id>.xml` |
| Public path | `/productreview/feed/store-<store-id>.xml` |
| Generation | CLI or scheduled cron |

The exporter creates a temporary file in the output directory and renames it to
the final file after generation, avoiding publication of a partially written
XML file.

### Dynamic feed

| Item | Value |
| --- | --- |
| Route | `/productfeed/index/feed` |
| Controller | `Controller/Index/Feed` |
| Store scope | Current Magento store view |
| Behavior | Generates the XML for the HTTP response; does not read/write the static file |

Both routes are public and do not require authentication.

## CLI

```bash
php bin/magento productreview:feed
php bin/magento productreview:feed --store=<store-code>
```

The first form generates one file for each store returned by Magento's store
manager. The optional `--store` option selects a single store view. The
compatibility alias `proudctreview:feed` is also registered.

The command returns a failure exit code if one or more requested store feeds
fail. Generation errors are printed to the CLI output.

## Cron

| Property | Value |
| --- | --- |
| Cron group | `default` |
| Job code | `pkgroup_productreview_feed_generate` |
| Schedule | `0 2 * * *` |
| Handler | `Pkgroup\Productfeed\Cron\GenerateFeed::execute` |
| Store scope | All store views returned by Magento's store manager |

Magento's system cron must run for this scheduled job to execute. The cron
handler logs per-store success or failure to Magento's logger.

## Review and product selection

1. Select Magento product reviews associated with the current store view.
2. Do not filter by review status; approved, pending, and not-approved reviews
   are all considered.
3. Load products assigned to that store view and enabled in the catalog.
4. Load rating votes associated with each selected review for that store.
5. Write a feed entry only when the review has normalized non-empty content,
   at least one usable rating vote, and a product URL.

Reviews failing the final eligibility check, or whose product is unavailable
to the store view, are omitted. Review omissions due to missing required
content, rating, or URL are logged.

## XML fields

The feed declares Google's Product Reviews 2.4 XSD:

```text
http://www.google.com/shopping/reviews/schema/product/2.4/product_reviews.xsd
```

Each feed contains a top-level `feed`, `version` (`2.4`), `publisher`, and
`reviews` element. Individual review entries include:

| XML field | Source / behavior |
| --- | --- |
| `review_id` | Magento review ID with `magento-` prefix |
| `reviewer/name` | Constant `Anonymous`, with `is_anonymous="true"` |
| `review_timestamp` | Magento review creation timestamp, formatted as UTC |
| `title` | Magento review title, omitted if empty |
| `content` | Magento review detail after HTML stripping and entity decoding |
| `review_url` | Product URL, type `group` |
| `ratings/overall` | Arithmetic mean of associated vote percentages, converted to a 1–5 scale |
| `products/product/product_ids/skus/sku` | Product SKU |
| `.../gtins/gtin` | First available `gtin` or `upc` product attribute |
| `.../mpns/mpn` | Product `mpn` attribute |
| `.../brands/brand` | First available `brand` or `manufacturer` attribute |
| `products/product/product_name` | Product name |
| `products/product/product_url` | Product URL |

Optional identifier elements are omitted when the corresponding attribute is
not available or empty. XML element text is written through `XMLWriter`.

## Implementation map

| File | Responsibility |
| --- | --- |
| `Model/Feed/Generator.php` | Selects reviews and products, maps values, serializes XML |
| `Model/Feed/Exporter.php` | Writes static XML under Magento's `pub/` directory |
| `Controller/Index/Feed.php` | Serves a dynamically generated response |
| `Console/Command/GenerateFeedCommand.php` | Generates all-store or store-specific files |
| `Cron/GenerateFeed.php` | Regenerates static feeds for all stores |
| `etc/frontend/routes.xml` | Registers the dynamic frontend route |
| `etc/di.xml` | Registers the CLI command |
| `etc/crontab.xml` | Registers the nightly cron job |

## Operational and security considerations

- Feed endpoints are unauthenticated. Treat feed URLs and contents as public.
- All Magento review statuses are exported when data otherwise qualifies.
- Protect the generated file and route at the web-server layer if public
  distribution is not intended.
- `pub/` must be writable by the Magento process and served by the web server.
- The module generates feed data only; it does not upload the feed to Google.
