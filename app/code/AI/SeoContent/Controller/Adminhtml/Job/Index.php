<?php
declare(strict_types=1);

namespace AI\SeoContent\Controller\Adminhtml\Job;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'AI_SeoContent::jobs';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('AI_SeoContent::jobs');
        $resultPage->getConfig()->getTitle()->prepend(__('AI SEO Job Monitor'));

        return $resultPage;
    }
}
