<?php

namespace DevopsFuture\Iconsignit\Model\Config\Configuration;

class Productattributes implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory
     */
    private $attributes;

    /**
     * Exclude incompatible product attributes from the mapping.
     * @var array
     */
    private $excluded = [
        'quantity_and_stock_status',
        'name',
        'sku',
        'activity',
        'category_gear',
        'category_ids',
        'climate',
        'collar',
        'color',
        'cost',
        'country_of_manufacture',
        'custom_design',
        'custom_design_from',
        'custom_design_to',
        'custom_layout',
        'custom_layout_update',
        'description',
        'eco_collection',
        'erin_recommends',
        'features_bags',
        'format',
        'gallery',
        'gender',
        'gift_message_available',
        'image',
        'manufacturer',
        'material',
        'media_gallery',
        'meta_description',
        'meta_keyword',
        'meta_title',
        'msrp',
        'msrp_display_actual_price_type',
        'new',
        'news_from_date',
        'news_to_date',
        'options_container',
        'page_layout',
        'pattern',
        'performance_fabric',
        'price_type',
        'price_view',
        'sale',
        'shipment_type',
        'short_description',
        'sku_type',
        'sleeve',
        'small_image',
        'special_from_date',
        'special_price',
        'special_to_date',
        'status',
        'strap_bags',
        'style_bags',
        'style_bottom',
        'style_general',
        'swatch_image',
        'tax_class_id',
        'thumbnail',
        'tier_price',
        'ts_country_of_origin',
        'ts_hs_code',
        'ts_packaging_id',
        'ts_packaging_type',
        'url_key',
        'visibility',
        'weight_type',
        'price'
    ];

    /**
     * Productattributes constructor.
     *
     * @param \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $collectionFactory
     */
    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $collectionFactory
    ) {
        $this->attributes = $collectionFactory;
    }

    /**
     * Get options.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $attributes = $this->attributes
            ->create()
            ->addVisibleFilter();

        $attributeArray = [];
        $attributeArray[] = [
            'label' => __('---- Default Option ----'),
            'value' => '0',
        ];

        foreach ($attributes as $attribute) {
            $attributeCode = $attribute->getAttributeCode();

            if (!in_array($attributeCode, $this->excluded)) {
                $attributeArray[] = [
                    'label' => $attribute->getFrontendLabel(),
                    'value' => $attributeCode,
                ];
            }
        }
        return $attributeArray;
    }
}
