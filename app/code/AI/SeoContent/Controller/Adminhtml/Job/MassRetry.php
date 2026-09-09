<?php
declare(strict_types=1);

namespace AI\SeoContent\Controller\Adminhtml\Job;

use AI\SeoContent\Model\Queue\JobRetryService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use AI\SeoContent\Model\ResourceModel\Job\CollectionFactory;

class MassRetry extends Action
{
    public const ADMIN_RESOURCE = 'AI_SeoContent::jobs';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly JobRetryService $jobRetryService
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $jobIds = $collection->getAllIds();
        $retried = $this->jobRetryService->retryJobs($jobIds);

        $this->messageManager->addSuccessMessage(
            __('Retried %1 job(s). Ensure the queue consumer is running.', $retried)
        );

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('*/*/index');
    }
}
