<?php

namespace Exportaccelerator\Productfeed\Cron;

use Exportaccelerator\Productfeed\Model\Feed\Exporter;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class GenerateFeed
{
    private Exporter $exporter;
    private StoreManagerInterface $storeManager;
    private LoggerInterface $logger;

    public function __construct(
        Exporter $exporter,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->exporter = $exporter;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            try {
                $path = $this->exporter->generate($store);
                $this->logger->info(sprintf(
                    'Product review feed generated for store "%s": %s',
                    $store->getCode(),
                    $path
                ));
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf(
                    'Product review feed generation failed for store "%s": %s',
                    $store->getCode(),
                    $exception->getMessage()
                ));
            }
        }
    }
}
