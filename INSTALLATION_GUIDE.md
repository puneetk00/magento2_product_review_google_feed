# Productfeed Installation Guide

## Before installation

Confirm the Magento project has these modules enabled:

- `Magento_Catalog`
- `Magento_Review`
- `Magento_Cron`

Also confirm that PHP has the `XMLWriter` extension enabled and that the
Magento application can write to the `pub/` directory.

## Install with Composer

The Composer repository that hosts `pkgroup/productfeed` must first
be configured in the Magento project's Composer configuration. From the
Magento project root, run:

```bash
composer require pkgroup/productfeed
php bin/magento module:enable Pkgroup_Productfeed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Composer installation will not work until the package's repository is
available to Composer.

## Install from `app/code`

For a source checkout where the module is already present at
`app/code/Pkgroup/Productfeed`, run:

```bash
php bin/magento module:enable Pkgroup_Productfeed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Check that Magento reports the module as enabled:

```bash
php bin/magento module:status Pkgroup_Productfeed
```

## Production deployment

Run the deployment commands required by the site's Magento mode and release
process. Typical production commands include:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

The module has no frontend static assets, but include static deployment if it
is part of the site's standard release procedure.

## Generate and verify the first feed

Generate feeds for all store views:

```bash
php bin/magento productreview:feed
```

Or generate for one store view:

```bash
php bin/magento productreview:feed --store=default
```

The command outputs each generated file path and URL. For store ID `1`, expect
the file `pub/productreview/feed/store-1.xml` and the URL
`https://<store-domain>/productreview/feed/store-1.xml`.

Verify the static endpoint responds with XML:

```bash
curl -i https://<store-domain>/productreview/feed/store-1.xml
```

The dynamic endpoint for the current store view is:

```text
https://<store-domain>/productfeed/index/feed
```

## Configure nightly generation

The module registers a daily 02:00 job in Magento's `default` cron group.
Install and run Magento's operating-system cron according to the Magento
deployment documentation. The feed job only runs when the Magento cron runner
processes the default group and the scheduled job is due.

You may run the group manually:

```bash
php bin/magento cron:run --group=default
```

This processes due jobs; it is not an immediate feed-generation command. Use
`php bin/magento productreview:feed` to generate immediately.

## Uninstall or disable

Disable the module with:

```bash
php bin/magento module:disable Pkgroup_Productfeed
php bin/magento setup:upgrade
php bin/magento cache:flush
```

The generated static XML files under `pub/productreview/feed/` are not
automatically removed when the module is disabled. Remove them manually only
if they should no longer be publicly available.

## Security note

The static and dynamic feeds are public and currently include all Magento
review statuses. Pending or not-approved review content may therefore be
exposed. Review this behavior before sharing or registering the feed URL.
