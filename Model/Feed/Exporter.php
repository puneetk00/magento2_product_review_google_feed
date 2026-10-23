<?php

namespace Exportaccelerator\Productfeed\Model\Feed;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Store\Api\Data\StoreInterface;

class Exporter
{
    private const FEED_DIRECTORY = 'productreview/feed';

    private Generator $generator;
    private WriteInterface $pubDirectory;

    public function __construct(Generator $generator, Filesystem $filesystem)
    {
        $this->generator = $generator;
        $this->pubDirectory = $filesystem->getDirectoryWrite(DirectoryList::PUB);
    }

    public function generate(StoreInterface $store): string
    {
        $feedPath = self::FEED_DIRECTORY . '/store-' . (int)$store->getId() . '.xml';
        $temporaryPath = $feedPath . '.' . bin2hex(random_bytes(8)) . '.tmp';

        $this->pubDirectory->create(self::FEED_DIRECTORY);

        try {
            $this->pubDirectory->writeFile($temporaryPath, $this->generator->generate($store));
            if (!$this->pubDirectory->renameFile($temporaryPath, $feedPath)) {
                throw new LocalizedException(__('Unable to publish the product review feed.'));
            }
        } catch (\Throwable $exception) {
            if ($this->pubDirectory->isExist($temporaryPath)) {
                $this->pubDirectory->delete($temporaryPath);
            }

            throw $exception;
        }

        return $this->pubDirectory->getAbsolutePath($feedPath);
    }
}
