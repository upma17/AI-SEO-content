<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\ResourceModel\Job;

use AI\SeoContent\Model\Job as JobModel;
use AI\SeoContent\Model\ResourceModel\Job as JobResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(JobModel::class, JobResource::class);
    }
}
