<?php

namespace Pkgroup\Productfeed\Console\Command;

use Pkgroup\Productfeed\Model\Feed\Exporter;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeedCommand extends Command
{
    private Exporter $exporter;
    private StoreManagerInterface $storeManager;
    private State $appState;

    public function __construct(Exporter $exporter, StoreManagerInterface $storeManager, State $appState)
    {
        $this->exporter = $exporter;
        $this->storeManager = $storeManager;
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('productreview:feed')
            ->setAliases(['proudctreview:feed'])
            ->setDescription('Generate public Google Product Reviews XML feeds');
        $this->addOption(
            'store',
            null,
            InputOption::VALUE_OPTIONAL,
            'Store code to generate (default: all stores)'
        );
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (LocalizedException $exception) {
            if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
                $output->writeln('<error>' . $exception->getMessage() . '</error>');
                return Command::FAILURE;
            }
        }

        $storeCode = $input->getOption('store');
        try {
            $stores = $storeCode !== null
                ? [$this->storeManager->getStore($storeCode)]
                : $this->storeManager->getStores();
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $failed = false;
        foreach ($stores as $store) {
            $output->writeln(sprintf('Generating feed for store "%s"...', $store->getCode()));
            try {
                $path = $this->exporter->generate($store);
                $relativePath = 'productreview/feed/store-' . (int)$store->getId() . '.xml';
                $url = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true), '/') . '/' . $relativePath;
                $output->writeln(sprintf('  Wrote: %s', $path));
                $output->writeln(sprintf('  URL:   %s', $url));
            } catch (\Throwable $exception) {
                $failed = true;
                $output->writeln(sprintf('  <error>Failed: %s</error>', $exception->getMessage()));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
