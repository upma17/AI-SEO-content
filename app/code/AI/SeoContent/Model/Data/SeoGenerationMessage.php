<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Data;

use AI\SeoContent\Api\Data\SeoGenerationMessageInterface;
use Magento\Framework\DataObject;

class SeoGenerationMessage extends DataObject implements SeoGenerationMessageInterface
{
    public function getJobId(): int
    {
        return (int) $this->getData(self::JOB_ID);
    }

    public function setJobId(int $jobId): SeoGenerationMessageInterface
    {
        return $this->setData(self::JOB_ID, $jobId);
    }

    public function getProductId(): int
    {
        return (int) $this->getData(self::PRODUCT_ID);
    }

    public function setProductId(int $productId): SeoGenerationMessageInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): SeoGenerationMessageInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }
}
