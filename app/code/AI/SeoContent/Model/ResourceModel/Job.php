<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\ResourceModel;

use AI\SeoContent\Model\Job as JobModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Job extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('ai_seo_content_job', 'job_id');
    }
}
