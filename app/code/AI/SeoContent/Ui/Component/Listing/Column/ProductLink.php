<?php
declare(strict_types=1);

namespace AI\SeoContent\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ProductLink extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $sku = (string) ($item['sku'] ?? '');
            $label = htmlspecialchars($sku !== '' ? $sku : (string) $productId, ENT_QUOTES, 'UTF-8');

            if ($productId > 0) {
                $url = $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]);
                $item[$fieldName] = sprintf('<a href="%s" target="_blank">%s</a>', $url, $label);
            } else {
                $item[$fieldName] = $label;
            }
        }

        return $dataSource;
    }
}
