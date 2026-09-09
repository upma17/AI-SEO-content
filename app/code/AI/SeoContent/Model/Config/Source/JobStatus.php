<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Api\Data\JobInterface;
use Magento\Framework\Data\OptionSourceInterface;

class JobStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => JobInterface::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => JobInterface::STATUS_PROCESSING, 'label' => __('Processing')],
            ['value' => JobInterface::STATUS_COMPLETED, 'label' => __('Completed')],
            ['value' => JobInterface::STATUS_FAILED, 'label' => __('Failed')],
            ['value' => JobInterface::STATUS_SKIPPED, 'label' => __('Skipped')],
        ];
    }
}
