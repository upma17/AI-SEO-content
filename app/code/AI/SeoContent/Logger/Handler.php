<?php
declare(strict_types=1);

namespace AI\SeoContent\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base
{
  /** @var string */
  protected $fileName = '/var/log/ai_seo_content.log';

  /** @var int */
  protected $loggerType = MonologLogger::INFO;
}
