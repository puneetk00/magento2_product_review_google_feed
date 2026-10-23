<?php

namespace Pkgroup\Productfeed\Controller\Index;

use Pkgroup\Productfeed\Model\Feed\Generator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Store\Model\StoreManagerInterface;

class Feed implements HttpGetActionInterface
{
    private ResultFactory $resultFactory;
    private StoreManagerInterface $storeManager;
    private Generator $generator;

    public function __construct(
        ResultFactory $resultFactory,
        StoreManagerInterface $storeManager,
        Generator $generator
    ) {
        $this->resultFactory = $resultFactory;
        $this->storeManager = $storeManager;
        $this->generator = $generator;
    }

    public function execute(): Raw
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'application/xml; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setContents($this->generator->generate($this->storeManager->getStore()));

        return $result;
    }
}
