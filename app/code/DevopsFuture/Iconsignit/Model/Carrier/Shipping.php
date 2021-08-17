<?php
namespace DevopsFuture\Iconsignit\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class Shipping extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    //const SHIPPING_API_URL = 'http://kis.iconsignit.com.au/api/getconsignrate';
    const SHIPPING_API_URL = 'http://test9.iconsignit.com.au/api/getconsignrate';

    /**
     * @var string
     */
    protected $_code = 'iconsignitshipping';

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $_rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $_rateMethodFactory;
    
    /*
     * var \Magento\Framework\HTTP\Client\Curl
    */
    
    protected $_curl;
    
    /*
     * var \Magento\Catalog\Model\ProductFactory
    */
    
    protected $_logger;
    
    /*
     * var \Psr\Log\LoggerInterface
    */
    
    protected $_product;
    
    /**
     * @var DevopsFuture\Iconsignit\Helper\Data
     */
    private $dataHelper;

    /**
     * Rate result data
     *
     * @var Result|null
     */
    protected $_result = null;
    
    
    /**
     * Shipping constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface            $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory    $rateErrorFactory
     * @param \Psr\Log\LoggerInterface                                      $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory                    $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory   $rateMethodFactory
     * @param \Magento\Framework\HTTP\Client\Curl                           $curl,
       @param \Magento\Catalog\Model\ProductFactory                         $_product,
       @param \DevopsFuture\Iconsignit\Helper\Data                              $helperData,
     * @param array                                                         $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Catalog\Model\ProductFactory $_product,
        \Magento\Directory\Helper\Data $helperData,
        array $data = []
    ) {
        $this->_rateResultFactory = $rateResultFactory;
        $this->_rateMethodFactory = $rateMethodFactory;
        $this->_curl = $curl;
        $this->_logger = $logger;
        $this->_product = $_product;
        $this->dataHelper = $helperData;
        
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * get allowed methods
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }


    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $this->_result = $this->_getQuotes($request);
        return $this->getResult();
    }
    
    
    /*
     * Load catalog products
     * @param $id
     * @return \Magento\Catalog\Model\ProductFactory
     */
    
    public function getLoadProduct($id){
        return $this->_product->create()->load($id);
    }
    
    
    /*
     * Get Shipping Rates
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return \Magento\Shipping\Model\Rate\ResultFactory
     */
    
    protected function _getQuotes($request){
        $_items = array();
        $attr_width = $this->getConfigData('product_attributes_width');
        $attr_height = $this->getConfigData('product_attributes_height');
        $attr_length = $this->getConfigData('product_attributes_length');

        $this->_logger->addDebug("$attr_width,$attr_height,$attr_length",array());

        if ($request->getAllItems()) {
            foreach ($request->getAllItems() as $item) {
                if ($item->getHasChildren()) {
                    foreach ($item->getChildren() as $child) {
                        if (!$child->getProduct()->isVirtual()) {
                            
                            //$this->_logger->addDebug('config and other');
                            
                            $_product = $this->getLoadProduct($child->getProduct()->getId());
                            $_item = array();
                            $_item['item_qty'] = $child->getQty();
                            $_item['item_code'] = $_product->getSku();
                            $_item['item_desc'] = $item->getName();
                            $this->_logger->addDebug("child",array());

                            if (null !== $_product->getCustomAttribute($attr_length)) {
                                $_item['item_length'] = $_product->getCustomAttribute($attr_length)->getValue();
                            } else {
                                $_item['item_length'] = $_product->getAttributeText($attr_length);
                            }

                            if (null !== $_product->getCustomAttribute($attr_width)) {
                                $_item['item_width'] = $_product->getCustomAttribute($attr_width)->getValue();
                            } else {
                                $_item['item_width'] = $_product->getAttributeText($attr_width);
                            }

                            if (null !== $_product->getCustomAttribute($attr_height)) {
                                $_item['item_height'] = $_product->getCustomAttribute($attr_height)->getValue();
                            } else {
                                $_item['item_height'] = $_product->getAttributeText($attr_height);
                            }

                            $_item['item_length'] = 1;
                            $_item['item_width']  = 1;
                            $_item['item_height'] = 1;
                            //$_item['item_weight'] = 4;
                            
                            $_item['item_palletised'] = 0;
                            $_items[$child->getProduct()->getId()] = $_item;
                        }
                    }
                } else {
                    if (!$item->getParentItem()) {
                        $_item = array();
                        $_product = $this->getLoadProduct($item->getProductId());
                        $_item['item_qty']  = $item->getQty();
                        $_item['item_code'] = $item->getSku();
                        $_item['item_desc'] = $item->getName();

                        if (null !== $_product->getCustomAttribute($attr_length)) {
                            $_item['item_length'] = $_product->getCustomAttribute($attr_length)->getValue();
                        } else {
                            $_item['item_length'] = $_product->getAttributeText($attr_length);
                        }

                        if (null !== $_product->getCustomAttribute($attr_width)) {
                            $_item['item_width'] = $_product->getCustomAttribute($attr_width)->getValue();
                        } else {
                            $_item['item_width'] = $_product->getAttributeText($attr_width);
                        }

                        if (null !== $_product->getCustomAttribute($attr_height)) {
                            $_item['item_height'] = $_product->getCustomAttribute($attr_height)->getValue();
                        } else {
                            $_item['item_height'] = $_product->getAttributeText($attr_height);
                        }

                        $_item['item_weight']   = intval($_product->getWeight());
                        //$_item['item_length']   = '';
                        //$_item['item_width']    = '';
                        //$_item['item_height']   = '';
                        //$_item['item_weight']   = 4;
                        
                        $_item['item_palletised'] = 0;
                        $_items[$item->getProductId()] = $_item;
                    }
                }
                
                
            }
        }
        
        
        $shippingOrigin = $this->getShippingOrigin(); 
        //$this->_logger->addDebug(json_encode($_items),array());        

        
        $fields = array(
            'ApiUrl' => $this->getConfigData('api_url'),
            'ApiToken' => $this->getConfigData('api_token'),
            'PickupTown' => $shippingOrigin['city'],
            'PickupPostcode' => $shippingOrigin['postcode'],
            'DeliveryTown' => $request->getDestCity(),
            'DeliveryPostcode' => $request->getDestPostcode(),
            'IsDangerousGoods' => 0,
            'IsResidential' => 0,
            'IsTailgate'=>0,
            'Items' => $_items            
        );

        $this->_logger->addDebug(json_encode($fields),array());

        //$this->_curl->post(self::SHIPPING_API_URL, $fields);
        $shipping_api_url = $this->getConfigData('shipping_api_url');
        $shipping_api_url = trim($shipping_api_url, '/') . '/api/getconsignrate';
        $this->_curl->post($shipping_api_url, $fields);

        $apiResponse = $this->_curl->getBody();

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();
        
        
        if($request->getDestCity()=='' || $request->getDestPostcode()=='') {
            return $result;
        }
        
        $apiResponse = json_decode($apiResponse,true);

        
        $this->_logger->addDebug(json_encode($apiResponse),array());
        
        if($apiResponse['status']!==false){
            foreach($apiResponse['result'] as $res){
                
                $methodCode = preg_replace('/\s+-\s*|\s*-\s+/','_',$res['carrier_nm']."-(".$res['service_nm'].")");
                $methodTitle = $res['carrier_nm']."-(".$res['service_nm'].")";
                
                /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
                $method = $this->_rateMethodFactory->create();

                //$extensionAttributes->setQuoteRateId($res['QuoteRateID']);

                $method->setCarrier($this->_code);
                $method->setCarrierTitle($this->getConfigData('title'));
                
                $method->setMethod($methodCode.'___'.$res['QuoteRateID']);
                $method->setMethodTitle($methodTitle);
                
                $method->setPrice($res['total_charge']);
                $method->setCost($res['total_charge']);
                
                $result->append($method);
            }
        }
        
        return $result;
    }
    
    
    /**
     * Get result of request
     *
     * @return Result|null
     */
    public function getResult(){
        return $this->_result;
    }
    
    
    /**
    * Get Shipping origin data from store scope config
    * Displays data on storefront
    * @return array
    */
    
    
    protected function getShippingOrigin(){
        
        return [
            'country_id' => $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_COUNTRY_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'region_id' => $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_REGION_ID,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'postcode' => $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_ZIP,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            ),
            'city' => $this->_scopeConfig->getValue(
                \Magento\Sales\Model\Order\Shipment::XML_PATH_STORE_CITY,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getData('store')
            )
        ];
    }
}