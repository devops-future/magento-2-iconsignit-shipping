<?php
/**
 * Copyright © 2017 Rejoiner. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace DevopsFuture\Iconsignit\Observer;

use DevopsFuture\Iconsignit\Model\Carrier\Shipping;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
class CheckoutOnepageControllerSuccessAction implements \Magento\Framework\Event\ObserverInterface
{
    const CONSIGMENT_API_URL = 'http://test9.iconsignit.com.au/api/CreateConsignment';

    protected $_code = 'iconsignitshipping';

    protected $_objectManager;
    protected $logger;
    protected $_curl;
    protected $iconsignitShipping;
    protected $_productFactory;
    protected $_trackFactory;

    /** @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone */
    private $timezone;

    /** @var \Magento\Sales\Model\OrderFactory $orderFactory */
    private $orderFactory;

    /**
     * CheckoutOnepageControllerSuccessAction constructor.
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $trackFactory,
        \DevopsFuture\Iconsignit\Model\Carrier\Shipping $iconsignitShipping,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone
    ) {
        $this->_objectManager = $objectManager;
        $this->orderFactory = $orderFactory;
        $this->_productFactory = $productFactory;
        $this->_trackFactory = $trackFactory;
        $this->iconsignitShipping = $iconsignitShipping;
        $this->_curl = $curl;
        $this->logger       = $logger;
        $this->timezone     = $timezone;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $attr_width = $this->iconsignitShipping->getConfigData('product_attributes_width');
        $attr_height = $this->iconsignitShipping->getConfigData('product_attributes_height');
        $attr_length = $this->iconsignitShipping->getConfigData('product_attributes_length');

        $lastOrderId = $observer->getEvent()->getData('order_ids');
        /** @var \Magento\Sales\Model\Order $order */

        $order = $this->orderFactory->create()->load($lastOrderId[0]);
        if (!$order->getId()) {
            return $this;
        }

        $shippingMethod = $order->getShippingMethod();
        $check_patterns = explode('_', $shippingMethod);
        if($check_patterns && isset($check_patterns[0]) && $check_patterns[0] === $this->_code) {
            $quote_patterns = explode('___', $shippingMethod);
            if($quote_patterns && isset($quote_patterns[1])) {
                $QuoteRateID = $quote_patterns[1];
                $post_data = [];
                $post_data['ApiUrl'] = $this->iconsignitShipping->getConfigData('api_url');
                $post_data['ApiToken'] = $this->iconsignitShipping->getConfigData('api_token');
                $post_data['QuoteRateID'] = $QuoteRateID;
                $post_data['DeliveryTown'] = $order->getShippingAddress()->getCity();
                $post_data['DeliveryPostcode'] = $order->getShippingAddress()->getPostcode();
                $post_data['DeliveryName'] = $order->getShippingAddress()->getFirstname() . ' ' . $order->getShippingAddress()->getLastname();
                $post_data['DeliveryAddressLine1'] = $order->getShippingAddress()->getStreetLine(1);
                $post_data['DeliveryPhoneNumber'] = $order->getShippingAddress()->getTelephone();
                $post_data['DeliveryContactName'] = $order->getShippingAddress()->getFirstname();
                $post_data['DeliveryEmail'] = $order->getShippingAddress()->getEmail();
                $post_data['DeliveryInstruction'] = NULL;

                $cnt = 0;
                foreach ($order->getAllItems() as $item) {
                    //$_product = $this->_productFactory->create()->load($item->getId());
                    $_product =  $item->getProduct();
                    if($_product) {
                        $post_data['Items'][$cnt]['item_code'] = $_product->getSku();
                    } else {
                        $post_data['Items'][$cnt]['item_code'] = $item->getId();
                    }
                    $post_data['Items'][$cnt]['item_desc'] = $item->getName();
                    $post_data['Items'][$cnt]['item_qty'] = intval($item->getQtyOrdered());

                    if (null !== $_product->getCustomAttribute($attr_length)) {
                        $post_data['Items'][$cnt]['item_length'] = $_product->getCustomAttribute($attr_length)->getValue();
                    } else {
                        $post_data['Items'][$cnt]['item_length'] = $_product->getAttributeText($attr_length);
                    }

                    if (null !== $_product->getCustomAttribute($attr_width)) {
                        $post_data['Items'][$cnt]['item_width'] = $_product->getCustomAttribute($attr_width)->getValue();
                    } else {
                        $post_data['Items'][$cnt]['item_width'] = $_product->getAttributeText($attr_width);
                    }

                    if (null !== $_product->getCustomAttribute($attr_height)) {
                        $post_data['Items'][$cnt]['item_height'] = $_product->getCustomAttribute($attr_height)->getValue();
                    } else {
                        $post_data['Items'][$cnt]['item_height'] = $_product->getAttributeText($attr_height);
                    }

                    //$post_data['Items'][$cnt]['item_length'] = '';
                    //$post_data['Items'][$cnt]['item_width']  = '';
                    //$post_data['Items'][$cnt]['item_height'] = '';
                    $post_data['Items'][$cnt]['item_weight'] = $_product->getWeight();
                    
                    $post_data['Items'][$cnt]['item_palletised'] = 0;
                    $cnt++;
                }

                //$this->_curl->post(self::CONSIGMENT_API_URL, $post_data);
                $consigment_api_url = $this->iconsignitShipping->getConfigData('shipping_api_url');
                $consigment_api_url = trim($consigment_api_url, '/') . '/api/CreateConsignment';
                $this->_curl->post($consigment_api_url, $post_data);

                $apiResponse = $this->_curl->getBody();
                $this->logger->critical('Quate1: '.print_r($post_data, true), array());
                $this->logger->critical('Quate2: '.print_r($apiResponse, true), array());
                $resp = json_decode($apiResponse, true);
                if (isset($resp['status'])) {
                    $data = array(
                        'carrier_code' => $this->_code,
                        'title' => 'Iconsignit Tracking Number',
                        'number' => $resp['result']['ConsignCode'], // Replace with your tracking number
                    );

                    $convertOrder = $this->_objectManager->create('Magento\Sales\Model\Convert\Order');
                    $shipment = $convertOrder->toShipment($order);

                    // Loop through order items
                    foreach ($order->getAllItems() AS $orderItem) {
                        // Check if order item has qty to ship or is virtual
                        if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                            continue;
                        }

                        $qtyShipped = $orderItem->getQtyToShip();

                        // Create shipment item with qty
                        $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);

                        // Add shipment item to shipment
                        $shipment->addItem($shipmentItem);
                    }

                    // Register shipment
                    $shipment->register();

                    //$shipment->getOrder()->setIsInProcess(true);

                    try {
                        // Save created shipment and order
                        $shipment->save();
                        $shipment->getOrder()->save();

                        // Send email
                        //$this->_objectManager->create('Magento\Shipping\Model\ShipmentNotifier')->notify($shipment);
                        $track = $this->_trackFactory->create()->addData($data);
                        $shipment->addTrack($track)->save();

                        $shipment->save();
                        $this->logger->critical('Shipment passed: '.$resp['result']['ConsignCode']);
                    } catch (\Exception $e) {
                        $this->logger->critical('Shipment error:'.$e->getMessage());
                    }
                }
            }
        }
        return $this;
    }
}