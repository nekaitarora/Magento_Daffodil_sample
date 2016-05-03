<?php
/**
 * aheadWorks Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://ecommerce.aheadworks.com/AW-LICENSE.txt
 *
 * =================================================================
 *                 MAGENTO EDITION USAGE NOTICE
 * =================================================================
 * This package designed for Magento community edition
 * aheadWorks does not guarantee correct work of this extension
 * on any other Magento edition except Magento community edition.
 * aheadWorks does not provide extension support in case of
 * incorrect edition usage.
 * =================================================================
 *
 * @category   AW
 * @package    AW_Sarp
 * @version    1.9.1
 * @copyright  Copyright (c) 2010-2012 aheadWorks Co. (http://www.aheadworks.com)
 * @license    http://ecommerce.aheadworks.com/AW-LICENSE.txt
 */

class AW_Sarp_Model_Subscription extends AW_Core_Model_Abstract
{

    const STATUS_ENABLED = 1;
    const STATUS_SUSPENDED = 2;
    const STATUS_CANCELED = -1;
    const STATUS_SUSPENDED_BY_CUSTOMER = 3;
    const STATUS_EXPIRED = 0;
    const STATUS_DISABLED = 0;

    const INTERNAL_DATE_FORMAT = 'yyyy-MM-dd'; // DON'T use Y(uppercase here)
    const DB_DATE_FORMAT = 'yyyy-MM-dd'; // DON'T use Y(uppercase here)
    const DB_DATETIME_FORMAT = 'yyyy-MM-dd H:m:s'; // DON'T use Y(uppercase here)

    const ITERATE_STATUS_REGISTRY_NAME = 'AW_SARP_PAYMENT_STATUS';
    const ITERATE_STATUS_RUNNING = 2;
    const ITERATE_STATUS_FINISHED = 12;

    protected $_product;
    protected $_virtualItems = array();
    protected static $_subscription;

    public static $_suspendTitle = 'You have suspended subscriptions!';

    protected function _construct()
    {
        $this->_init('sarp/subscription');
    }

    /**
     * Determines if subscription period is infinite
     * @return bool
     */
    public function isInfinite()
    {
        return $this->getPeriod()->isInfinite();
    }


    /**
     * Says wether subscription is active
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() == self::STATUS_ENABLED;
    }


    /**
     * Returns probably expire date
     * @return Zend_Date
     */
    public function getDateExpire()
    {
        if (!$this->getData('date_expire')) {
            if (!$this->isInfinite()) {
                $ExpireDate = new Zend_Date($this->getData('date_start'), self::DB_DATE_FORMAT);
                switch ($this->getPeriod()->getExpireType()) {
                    case 'day':
                        $method_expire = 'addDayOfYear';
                        break;
                    case 'month':
                        $method_expire = 'addMonth';
                        break;
                    case 'week':
                        $method_expire = 'addWeek';
                        break;
                    case 'year':
                        $method_expire = 'addYear';
                        break;
                    default:
                        throw new Mage_Core_Exception("Unknown subscription expire period type for #" . $this->getPeriod()->getId());
                }
                $expireMultiplier = $this->getPeriod()->getExpireValue();
                $ExpireDate = call_user_func(array($ExpireDate, $method_expire), $expireMultiplier);
                return $ExpireDate;
            } else {
                // No expiration date
                return $this->getNextSubscriptionEventDate(new Zend_Date);
            }
        }
        return new Zend_Date;
    }


    /**
     * Returns last paid date. Returns NULL if no payments made yet
     * @todo optimize that method
     * @return Zend_Date
     */
    public function getLastPaidDate()
    {
        foreach (Mage::getModel('sarp/sequence')->getCollection()
                ->addStatusFilter(AW_Sarp_Model_Sequence::STATUS_PAYED)
                ->addSubscriptionFilter($this)->setOrder('date', 'desc')
            as $SequenceItem) {
            return new Zend_Date($SequenceItem->getDate(), self::DB_DATE_FORMAT);
        }
        return null;
    }

    /**
     * Returns subscription start date
     * @return Zend_Date
     */
    public function getDateStart()
    {
        return new Zend_Date($this->getData('date_start'), self::DB_DATE_FORMAT);
        //return Mage::app()->getLocale()->date($this->getData('date_start'), self::DB_DATE_FORMAT);
    }

    /**
     * Easy way to set customer as object
     * @param Mage_Customer_Model_Customer $Customer
     * @return AW_Sarp_Model_Subscription
     */
    public function setCustomer(Mage_Customer_Model_Customer $Customer)
    {
        $this->setCustomerId($Customer->getId());
        return $this;
    }

    /**
     * Returns current customer
     * @return Varien_Object
     */
    public function getCustomer()
    {
        if ($this->getCustomerId()) {
            return Mage::getModel('customer/customer')->load($this->getCustomerId());
        } else {
            $data = $this->getQuote()->getBillingAddress()->getData();
            $customer = new Varien_Object;
            foreach ($data as $k => $v) {
                $customer->setData($k, $v);
            }
            $customer->setName($customer->getFirstname() . ' ' . $customer->getLastname());
            return $customer;
        }
    }

    /**
     * Generates events for purchasing to subscription
     * @return AW_Sarp_Model_Subscription
     */
    protected function _generateSubscriptionEvents()
    {

        // Delete all sequencies
        if ($this->_origData['date_start'] != $this->_data['date_start'] ||
            $this->_origData['period_type'] != $this->_data['period_type'] ||
            (!$this->getIsNew() && $this->getIsReactivated())
        ) {
            Mage::getResourceModel('sarp/sequence')->deleteBySubscriptionId($this->getId());

            $Date = new Zend_Date(strtotime($this->getDateStart()), Zend_Date::TIMESTAMP);
            Zend_Date::setOptions(array('extend_month' => true)); // Fix Zend_Date::addMonth unexpected result

            switch ($this->getPeriod()->getPeriodType()) {
                case 'day':
                    $method = 'addDayOfYear';
                    break;
                case 'month':
                    $method = 'addMonth';
                    break;
                case 'week':
                    $method = 'addWeek';
                    break;
                case 'year':
                    $method = 'addYear';
                    break;
                default:
                    throw new Mage_Core_Exception("Unknown subscription period type for #" . $this->getPeriod()->getId());
            }
            switch ($this->getPeriod()->getExpireType()) {
                case 'day':
                    $method_expire = 'addDayOfYear';
                    break;
                case 'month':
                    $method_expire = 'addMonth';
                    break;
                case 'week':
                    $method_expire = 'addWeek';
                    break;
                case 'year':
                    $method_expire = 'addYear';
                    break;
                default:
                    throw new Mage_Core_Exception("Unknown subscription expire period type for #" . $this->getPeriod()->getId());
            }
            $ExpireDate = clone $Date;
            $expireMultiplier = $this->getPeriod()->getExpireValue();
            if (!$expireMultiplier) {
                // 0 means infinite expiration date
                $expireMultiplier = 3;
                // 0 means expire_method = method
                $method_expire = $method;
                $_Date = $this->getLastPaidDate();
                if (is_object($_Date)) {
                    $ExpireDate = clone $_Date;
                }
            }
            $ExpireDate = call_user_func(array($ExpireDate, $method_expire), $expireMultiplier);

            // Substract delivery offset. This is
            $Date->addDayOfYear(0 - $this->getPeriod()->getPaymentOffset());
            $ExpireDate->addDayOfYear(0 - $this->getPeriod()->getPaymentOffset());

            try {
                $this->getPeriod()->validate();
                while ($Date->compare($ExpireDate) == -1) {
                    $Date = call_user_func(array($Date, $method), $this->getPeriod()->getPeriodValue());
                    Mage::getModel('sarp/sequence')
                            ->setSubscriptionId($this->getId())
                            ->setDate($Date->toString(self::DB_DATE_FORMAT))
                            ->save();
                }
            } catch (AW_Sarp_Exception $e) {
                $this->log(
                    'Unable create sequences to subscription #' . $this->getId(),
                    AW_Core_Model_Logger::LOG_SEVERITY_WARNING,
                    'Unable create sequences to subscription. Message: "' . $e->getMessage() . '"'
                );
            }

        }
        return $this;
    }

    /**
     * Generates next payment date
     * @param Zend_Date $CurrentDate
     * @return Zend_Date
     */
    public function getNextSubscriptionEventDate($CurrentDate = null)
    {
        Zend_Date::setOptions(array('extend_month' => true)); // Fix Zend_Date::addMonth unexpected result
        if (!($CurrentDate instanceof Zend_Date)) {
            if (is_null($CurrentDate)) {
                if (!($CurrentDate = $this->getLastPaidDate())) {
                    throw new AW_Sarp_Exception("Failed to detect last paid date");
                }
            } else {
                throw new AW_Sarp_Exception("getNextSubscriptionEventDate accepts only Zend_Date or null");
            }
        }
        switch ($this->getPeriod()->getPeriodType()) {
            case 'day':
                $method = 'addDayOfYear';
                break;
            case 'month':
                $method = 'addMonth';
                break;
            case 'week':
                $method = 'addWeek';
                break;
            case 'year':
                $method = 'addYear';
                break;
            default:
                throw new Mage_Core_Exception("Unknown subscription period #" . $this->getPeriod()->getId() . " for subscription #{$this->getId()}");
        }

        $CurrentDate = call_user_func(array($CurrentDate, $method), $this->getPeriod()->getPeriodValue());
        return $CurrentDate;
    }


    /**
     * Re-generates subscription events
     * @return AW_Sarp_Model_Subscription
     */
    protected function _generateAlertEvents()
    {
        foreach (Mage::getModel('sarp/alert')->getCollection()->addEnabledFilter() as $Alert) {

            $storesForAlert = explode(',', $Alert->getStoreIds());
            $subscriptionStoreId = $this->getOrder()->getStoreId();

            if (in_array(0, $storesForAlert) || in_array($subscriptionStoreId, $storesForAlert))
                $Alert->generateSubscriptionEvents($this);
        }
        return $this;
    }

    /**
     * After save handler
     * @return
     */
    public function _afterSave()
    {
        if (!empty($this->_virtualItems)) {
            // Save virtual items
            foreach ($this->_virtualItems as $Item) {
                $Item->setSubscriptionId($this->getId())->save();
            }
        }
        if ($this->isActive() && ($this->getIsReactivated() || $this->getIsNew())) {
            foreach ($this->getItems() as $item) {
                if (($product = Mage::getModel('catalog/product')->load($item->getOrderItem()->getProductId())) && ($product->getAwSarpIncludeToGroup() > 0)) {
                    if ($this->getCustomer() instanceof Mage_Customer_Model_Customer) {
                        $this->getCustomer()->setGroupId($product->getAwSarpIncludeToGroup())->save();
                    }
                }
            }
        } elseif ($this->getIsStopping()) {
            foreach ($this->getItems() as $item) {
                if (($product = Mage::getModel('catalog/product')->load($item->getOrderItem()->getProductId())) && ($product->getAwSarpExcludeToGroup() > 0)) {
                    if ($this->getCustomer() instanceof Mage_Customer_Model_Customer) {
                        $this->getCustomer()->setGroupId($product->getAwSarpExcludeToGroup())->save();
                    }
                }
            }
        }
        if (!$this->getFlagNoSequenceUpdate()) {
            $this->_generateSubscriptionEvents();
        }

        // Set also customer details to flat data table
        $flat = Mage::getModel('sarp/subscription_flat')->load($this->getId(), 'subscription_id')->setSubscription($this)->save();
        $this
                ->setFlatNextDeliveryDate($flat->getFlatNextDeliveryDate());

        $this->_generateAlertEvents();
        return parent::_afterSave();
    }

    /**
     * Returns period for subscription
     * @return AW_Sarp_Model_Period
     */
    public function getPeriod()
    {
        if (!$this->getData('period')) {
            $this->setPeriod(Mage::getModel('sarp/period')->load($this->getPeriodType()));
        }

        return $this->getData('period');
    }

    /**
     * Returns affected subscription items
     * @return AW_Sarp_Model_Mysql4_Subscription_Item_Collection
     */
    public function getItems()
    {
        if (!$this->getData('items')) {

            $this->setItems(Mage::getModel('sarp/subscription_item')->getCollection()->addSubscriptionFilter($this));
        }
        return $this->getData('items');
    }

    /**
     * Initiates subscription items from order items
     * @param object $OrderItems
     * @return AW_Sarp_Model_Subscription
     */
    public function initFromOrderItems($OrderItems, Mage_Sales_Model_Order $Order)
    {
        $this->_virtualItems = array();
        foreach ($OrderItems as $OrderItem) {
            $this->_virtualItems[] = Mage::getModel('sarp/subscription_item')
                    ->setPrimaryOrderId($Order->getId())
                    ->setPrimaryOrderItemId($OrderItem->getId());
        }
        return $this;
    }

    /**
     * Returns primary order
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->getData('order')) {
            foreach ($this->getItems() as $Item) {
                $this->setOrder(Mage::getModel('sales/order')->load($Item->getPrimaryOrderId()));
                break;
            }

        }
        return $this->getData('order');
    }

    /**
     * Returns last created order
     * @return Mage_Sales_Model_Order
     */
    public function getLastOrder()
    {
        if (!$this->getData('last_order')) {
            $coll = Mage::getModel('sarp/sequence')
                    ->getCollection()
                    ->addSubscriptionFilter($this)
                    ->addStatusFilter(AW_Sarp_Model_Sequence::STATUS_PAYED)
                    ->setOrder('date', Varien_Data_Collection::SORT_ORDER_ASC);

            foreach ($coll as $SequenceItem) {
                if (!$SequenceItem->getOrderId()) {
                    throw new AW_Sarp_Exception("Subscription record marked as paid but no order found: #{$SequenceItem->getId()}, suscription #{$SequenceItem->getSubscriptionId()}");
                }
                $orderId = $SequenceItem->getOrderId();
            }
            if (isset($orderId)) {
                $order = Mage::getModel('sales/order')->load($orderId);
            } else {
                $order = $this->getOrder(); // Primary order
            }
            $this->setData('last_order', $order);
        }
        return $this->getData('last_order');
    }

    /**
     * Returns primary order
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (!$this->getData('quote')) {
            $this->setQuote(Mage::getModel('sales/quote')->setStoreId($this->getStoreId())->load($this->getPrimaryQuoteId()));
        }
        return $this->getData('quote');
    }

    /**
     * Creates new order for subscription
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        foreach ($this->getItems() as $Item) {
            $order = Mage::getModel('sales/order')->load($Item->getPrimaryOrderId());
            break;
        }
        $items = $this->getItems()->getOrderItems();
        $order
                ->setReordered(true);
        $quote = $this->getQuote()->setUpdatedAt(now());
        $quoteCurrency = Mage::getModel('directory/currency')->load($quote->getQuoteCurrencyCode());
        $quote->setForcedCurrency($quoteCurrency);
        $quote->save();

        $no = Mage::getModel('sarp/order_create');
        $no
                ->reset()
                ->setRecollect(1)
                ->setPrimaryQuote($quote);

        $no->getSession()->setUseOldShippingMethod(true);
        $no->setSendConfirmation(true);
        $no->setData('account', $this->getCustomer()->getData());

        //if($this->getLastOrder()->getId() == $order->getId()){
        // If first iteration, no recurrent orders yet
        foreach ($this->getItems() as $Item) {
            $arr[] = $Item->getPrimaryOrderItemId();
        }
        $no->setItemIdFilter($arr);
        // }

        $no->initFromOrder($order);

        //fix for guest subscription
        $customer = $this->getGuestByEmail($order->getCustomerEmail());
        if (!$order->getCustomerId()) {
            if (!$customer->getId()) {
                $no->getQuote()->getCustomer()
                                     ->setEmail($order->getCustomerEmail())
                                     ->setFirstname($order->getCustomerFirstname())
                                     ->setLastname($order->getCustomerLastname());
            }
            elseif (!$order->getCustomerId())
                $no->getQuote()->setCustomer($customer);
            //$no->getQuote()->setCustomer($no->getQuote()->getCustomer()->setEmail($order->getCustomerEmail())->setId($customer->getId()));
        }
        else
            $no->getQuote()->setCustomer($customer);

        // Apply quote changes to order
        $or = $no->createOrder();
        /*if($order->getDeliveryDate()){
            $or->setDeliveryDate($order->getDeliveryDate());
        }
        if($order->getDeliveryComment()){
            $or->setDeliveryComment($order->getDeliveryComment());
        }*/
        if ($order->getPostmanNotice()) {
            $or->setPostmanNotice($order->getPostmanNotice());
        }
        $or->save();

        //clean base from fake guest records
        if (!$order->getCustomerId() && !$customer->getId()) {
            Mage::register('isSecureArea', true);
            $this->deleteFakeGuestsByEmail($order->getCustomerEmail());
        }

        return $or;
    }

    /**
     *
     * Get customer by email
     * @param string $email
     *
     * native magento method loadByEmail don't work with customer which webside id = 0
     */

    public function getGuestByEmail($email)
    {
        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $select = $db->select()->from($resource->getTableName('customer/entity'), array('entity_id'))->where('email = ?', $email);
        $id = $db->fetchOne($select);
        return Mage::getModel('customer/customer')->load($id);
    }

    public function deleteFakeGuestsByEmail($email)
    {

        $resource = Mage::getSingleton('core/resource');
        $db = $resource->getConnection('core_read');
        $select = $db->select()
                ->from($resource->getTableName('customer/entity'), array('entity_id'))
                ->where('email = ?', $email)//->where('website_id = ?', 0)
        ;
        $id = $db->fetchOne($select);
        if ($id)
            Mage::getModel('customer/customer')->load($id)->delete();
    }

    /**
     * Processes payment.
     * @param object $Order
     * @return AW_Sarp_Model_Subscription
     */
    public function processPayment($Order)
    {
        try {
            $pm_code = $this->getOrder()->getPayment()->getMethod();
            $PaymentInstance = $this->_getMethodInstance($pm_code);
            $PaymentInstance
                    ->setSubscription($this)
                    ->processOrder($this->getOrder(), $Order);
        } catch(SoapFault $e) {
            throw $e;
        } catch (Exception $e) {
            throw new AW_Sarp_Exception("Payment message: #{$this->getId()}: {$e->getMessage()}");
        }
        return $this;
    }

    /**
     * Creates order and runs payment for it
     * @return bool|object
     */
    protected function _iterate()
    {
        try {
            Mage::unregister(self::ITERATE_STATUS_REGISTRY_NAME);
            Mage::register(self::ITERATE_STATUS_REGISTRY_NAME, self::ITERATE_STATUS_RUNNING);
            $newOrder = $this->createOrder();
            $this->log(
                'Created order #' . $newOrder->getId() . ' for subscription #' . $this->getId(),
                null,
                "Order #{$newOrder->getIncrementId()} created."
            );

            if ($this->getOrder()->getPayment()->getMethod() == 'epay_standard') $this->processPayment($newOrder);

            $this->setData('last_order', $newOrder);
            $this->setStatus(self::STATUS_ENABLED)->save();
            $this->log(
                'Subscription #' . $this->getId() . ' successfully payed',
                null,
                "Order #{$newOrder->getIncrementId()} payed."
            );

            Mage::unregister(self::ITERATE_STATUS_REGISTRY_NAME);
            Mage::register(self::ITERATE_STATUS_REGISTRY_NAME, self::ITERATE_STATUS_FINISHED);
            return $newOrder;
        } catch (Mage_Core_Exception $e) {
            // Something goes wrong. Suspend subscription.
            Mage::unregister(self::ITERATE_STATUS_REGISTRY_NAME);
            Mage::register(self::ITERATE_STATUS_REGISTRY_NAME, self::ITERATE_STATUS_FINISHED);
            throw $e;
        }
    }

    /**
     * Processes subscription by sequence
     * @param object $Item
     * @return bool
     */
    public function payBySequence($Item)
    {
        self::$_subscription = $this;
        if (!$this->_getMethodInstance()->isValidForTransaction($Item)) {
			$Item->setStatus(AW_Sarp_Model_Sequence::STATUS_FAILED)->save();
            $this->log("sequence is skipped");
            return false;
        }
        $subscription = Mage::getSingleton('sarp/subscription')->setId($this->getId());
        try {
            try {
				$Order = $this->_iterate();
    
                }
                catch(Exception $e){
                        $sender = array('name' => Mage::getStoreConfig('trans_email/ident_support/name'), 'email' => Mage::getStoreConfig('trans_email/ident_support/email'));
                        $email = Mage::getStoreConfig('trans_email/ident_support/email');
                        $name = Mage::getStoreConfig('trans_email/ident_support/name');
                        $var = array('id' => $this->getId());
                        //sendEmail($templateId, $sender, $email, $name, $subject, $params = array())
                        Mage::helper('sarp/data')->sendEmail(8, $sender, $email, $name, $var);
                        Mage::log($e->getMessage(), null, "recurring_order_log.log", true);
                        return;
                }
                
                /*
                 * Code added by saurabh chandela
                 */
                $multi_payment = Mage::getStoreConfig('payment/ccpayment/multipayment',$Order->getStoreId());
            
                 if($multi_payment){
                     
                        $payment_array =Mage::helper('merchant')->splitPayment($Order,0);
                        $result_array= Mage::getModel('creditcard/ccpayment')->excutePayment($Order,$payment_array,1);
                
                       $success= ($result_array['all_processed']==1 ?  true : false);
                  }else{
                     
                    $response = $this->getPaymentForSubscriptionOrder($Order,$subscription);
                    $success= ($response['Ecom_Trans_Result'] == 'Y' ? true : false);
                 }
                 
                 // End of code
                //if($response['Ecom_Trans_Result'] == 'Y'){
                if($success){
                    
                        try {					
                                if($Order){
                                    
                                    // code added by saurabh chandela for sunscription data details enter in drg table
                                    if($multi_payment){
                                      Mage::Helper('merchant')->successTransaction($Order->getCustomerId(),$Order->getStoreId());
                                      Mage::unregister("result_data");
                                      Mage::unregister("paymentdata");
                                      
                                    }
                                    // End of code
                                        if(!$Order->canInvoice()){
                                                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
                                        }
                                        $invoice = Mage::getModel('sales/service_order', $Order)->prepareInvoice();
                                        if (!$invoice->getTotalQty()) {
                                                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
                                        }
                                        
                                        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                                        $invoice->register();
                                        $transactionSave = Mage::getModel('core/resource_transaction')
                                                                        ->addObject($invoice)
                                                                        ->addObject($invoice->getOrder());
                                        $transactionSave->save();
                                }
                        }
                        catch (Mage_Core_Exception $e){
                                $sender = array('name' => Mage::getStoreConfig('trans_email/ident_support/name'), 'email' => Mage::getStoreConfig('trans_email/ident_support/email'));
                                $email = Mage::getStoreConfig('trans_email/ident_support/email');
                                $name = Mage::getStoreConfig('trans_email/ident_support/name');
                                $var = array('id' => $this->getId(),
                                                         'orderId' => $this->getIncrementId(),
                                                         'msg' => $e->getMessage());
                                //sendEmail($templateId, $sender, $email, $name, $subject, $params = array())
                                Mage::helper('sarp/data')->sendEmail(10, $sender, $email, $name, $var);
                                Mage::log($e->getMessage(), null, "recurring_order_log.log", true);
                                return;
                        }
                }
                else{
                    
                    
                    // Code to insert failure transaction details in drg table. BY Saurabh chandela
                     if($multi_payment){
                           $result = Mage::registry("result_data");
                                    if($result){ 
                                      if(array_key_exists('refund_success', $result)){ 


                                      // Check for Refund process
                                        if( $result['refund_success']){
                                               Mage::Helper('merchant')->failureTransaction($result,$Order->getCustomerId(),$storeId);

                                }else {
                                    
                                    $customerdata = Mage::getModel('customer/customer')->load($Order->getCustomerId());
                                     $name=$customerdata->getFirstName()." ".$customerdata->getLastName();

                                     Mage::Helper('merchant')->failureTransaction($result,$Order->getCustomerId(),$storeId,$Order->getCustomerEmail(),$name);
                                }
                                }
                             else{
                              reset($result);
                              $merchant_key = key($result);
                                                if($customerId){
                                                        $error_description ="Ecomid not found for customer : ".$customer->getFirstname()." ".$customer->getLastname()." - customer id is : ".$customerId." - merchant code is :".$merchant_key." - order id :".$result[$merchant_key]['orderid']." - Amount is :".$result[$merchant_key]['amt']  ; 
                                                        Mage::log($error_description,null,'order_error.log');
                                                    }else{
                                                          $error_description = "Payment gateway error for guest customer and merchant code is :".$merchant_key." -  order id :".$result[$merchant_key]['orderid']."- Amount is :".$result[$merchant_key]['amt']  ; 
                                                          Mage::log($error_description,null,'order_error.log');
                                                    }

                                            }
            }
                     
            
             Mage::unregister("result_data");
        Mage::unregister("paymentdata");
            
                     }
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                        // End of code
                       
                        $sender = array('name' => Mage::getStoreConfig('trans_email/ident_support/name'), 'email' => Mage::getStoreConfig('trans_email/ident_support/email'));
                        $email = Mage::getStoreConfig('trans_email/ident_support/email');
                        $name = Mage::getStoreConfig('trans_email/ident_support/name');
                        
                        $var = array('id' => $this->getId(),
                                                 'orderId' => $this->getIncrementId());
                        //sendEmail($templateId, $sender, $email, $name, $subject, $params = array())
                        Mage::helper('sarp/data')->sendEmail(18, $sender, $email, $name, $var);
                }
        } 
		catch(SoapFault $e) {
            //is soap fault was generated
            $this->log("Payment message: #{$this->getId()}: {$e->getMessage()}");
            $attemptsQty = $Item->getData('attempts_qty') + 1;
            $Item->setData('attempts_qty', $attemptsQty)
                 ->save();
            $numberOfRetries = intval(Mage::getStoreConfig(AW_Sarp_Helper_Config::XML_PATH_ADVANCED_NUMBER_OF_RETRIES));
            if ($attemptsQty < $numberOfRetries) {
                return true;
            }
            $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_FAILED)->save();
            $this->log("sequence is skipped");
            throw new AW_Sarp_Exception("Payment message: #{$this->getId()}: {$e->getMessage()}");
        }

        if ($Order instanceof Mage_Sales_Model_Order) {
            switch ($Order->getState()){
                case Mage_Sales_Model_Order::STATE_COMPLETE:
                    $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_PAYED);
                    break;
                case Mage_Sales_Model_Order::STATE_CANCELED:
                    $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_FAILED);
                    break;
                default:
                    $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_PENDING_PAYMENT);
            }
            if (Mage::helper('sarp')->isOrderStatusValidForActivation($Order->getStatus())) {
                $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_PAYED);
            } else {
                $setStatusSuspended = 1;
            }
            $date = Mage::app()->getLocale()->date(new Zend_Date);
            $this->log('Date ' . $date . ' for subscription #' . $this->getId() . ' successfully marked as payed');

            $Item ->setOrderId($Order->getId())
                  ->save();
        } else{
            // Order creation failed
			$sender = array('name' => Mage::getStoreConfig('trans_email/ident_support/name'), 'email' => Mage::getStoreConfig('trans_email/ident_support/email'));
			$email = Mage::getStoreConfig('trans_email/ident_support/email');
			$name = Mage::getStoreConfig('trans_email/ident_support/name');
			$var = array('id' => $this->getId());
			//sendEmail($templateId, $sender, $email, $name, $subject, $params = array())
			Mage::helper('sarp/data')->sendEmail(8, $sender, $email, $name, $var);
            $Item->setStatus(AW_Sarp_Model_Sequence::STATUS_FAILED)
                 ->save();
        }
        if (isset($setStatusSuspended) && $setStatusSuspended) {
            $this->setStatus(self::STATUS_SUSPENDED)->setFlagNoSequenceUpdate(1)->save()->setFlagNoSequenceUpdate(0);
        }
        return true;
    }

    /**
     * Processes payment for date
     * @param object $date
     * @return AW_Sarp_Model_Subscription
     */
    public function payForDate($date)
    {
        $this->updateSequences();
        $date = Mage::app()->getLocale()->date($date);
        $sequenceItems = Mage::getModel('sarp/sequence')->getCollection()
                ->addSubscriptionFilter($this)
                ->prepareForPayment()
                ->addDateFilter($date)
                ->setOrder('attempts_qty', Varien_Data_Collection::SORT_ORDER_ASC);

        foreach ($sequenceItems as $Item)
        {
            if (!$this->_getMethodInstance()->isValidForTransaction($Item)) {
                continue;
            }
            try {
                $this->payBySequence($Item);
            } catch(Exception $e) {
                $this->setStatus(self::STATUS_SUSPENDED)->save();
                $this->log(
                    'Suspending subscription #' . $this->getId(),
                    AW_Core_Model_Logger::LOG_SEVERITY_WARNING,
                    'Subscription suspended because of new order failed. Message: "' . $e->getMessage() . '"'
                );
            }

            break;
        }
        return $this;
    }

    public function updateSequences()
    {
        //checks if this is a last item of the infinite subscription
        if ($this->isInfinite()) {
            $coll = Mage::getModel('sarp/sequence')->getCollection()
                    ->addSubscriptionFilter($this)
                    ->addStatusFilter(AW_Sarp_Model_Sequence::STATUS_PENDING);

            if ($coll->count() == 1) //this is a last pending sequence, we need a new one
            {
                $_seq = $coll->getFirstItem();
                $_aDate = new Zend_Date($_seq->getDate(), AW_Sarp_Model_Subscription::DB_DATE_FORMAT);
                $nextDate = $this->getNextSubscriptionEventDate($_aDate);

                $newSeq = Mage::getModel('sarp/sequence')
                        ->setSubscriptionId($this->getId())
                        ->setstatus(AW_Sarp_Model_Sequence::STATUS_PENDING)
                        ->setDate($nextDate->toString(AW_Sarp_Model_Subscription::DB_DATE_FORMAT))
                        ->save();

                $this->log("Sequence #{$newSeq->getId()} was created for subscription #{$this->getId()}");
            }
        }
    }

    /**
     * Returns payment model instance by code
     * @param string $method
     * @throws Mage_Core_Exception
     * @return object
     */
    protected function _getMethodInstance($method = null)
    {
        if (!$method && $this->getOrder()) {
            try {
                $method = $this->getOrder()->getPayment()->getMethod();
            } catch (Exception $e) {
                $this->log("Can't find payment for subscription #{$this->getId()}. Order missing?");
            }
        }
        if ($model = Mage::getModel('sarp/payment_method_' . $method)) {
            return $model->setSubscription($this);
        } else {
            throw new AW_Sarp_Exception(Mage::helper('sarp')->__("Can't find implementation of payment method $method"));
        }
    }

    /**
     * Returns payment model instance by code
     * @param string $method
     * @throws Mage_Core_Exception
     * @return object
     */
    public function getMethodInstance($method)
    {
        return $this->_getMethodInstance($method);
    }

    /**
     * Check if payment method exists
     * @param string $method
     * @return bool
     */
    public function hasMethodInstance($method)
    {
        try
        {
            @$methodAvailable = Mage::getModel('sarp/payment_method_' . $method);
        }
        catch (Exception $ex)
        {
            return false;
        }
        return $methodAvailable;
    }

    /**
     * Determines wether subscription is set to canceled
     * @return bool
     */
    public function getIsCancelling()
    {
        return (($this->_origData['status'] != $this->_data['status']) && ($this->_data['status'] == self::STATUS_CANCELED));
    }

    /**
     * Determines wether subscription is set to expired
     * @return bool
     */
    public function getIsExpiring()
    {
        return (($this->_origData['status'] != $this->_data['status']) && ($this->_data['status'] == self::STATUS_EXPIRED));
    }

    /**
     * Determines wether subscription is set to not active
     * @return bool
     */
    public function getIsStopping()
    {
        return (($this->_origData['status'] != $this->_data['status']) && ($this->_data['status'] != self::STATUS_ENABLED));
    }

    /**
     * Determines wether subscription is set to suspended
     * @return bool
     */
    public function getIsSuspending()
    {
        return (($this->_origData['status'] != $this->_data['status']) && ($this->_data['status'] == self::STATUS_SUSPENDED || $this->_data['status'] == self::STATUS_SUSPENDED_BY_CUSTOMER));
    }

    /**
     * Determines wether subscription is set back to active
     * @return bool
     */
    public function getIsReactivated()
    {
        return (($this->_origData['status'] != $this->_data['status']) && ($this->_data['status'] == self::STATUS_ENABLED));
    }

    /**
     * Cancels current subscription
     * @return AW_Sarp_Model_Subscription
     */
    public function cancel()
    {
        // Set canceled status
        return $this->setStatus(self::STATUS_CANCELED);
    }


    /**
     * Prepares for save
     * @return
     */
    protected function _beforeSave()
    {
        if (!$this->getId()) {
            $this->getQuote()->setUpdatedAt(now())->save();
            $this->setIsNew(true);
        }
        if (is_null($this->getStoreId())) {
            $storeId = (Mage::getSingleton('adminhtml/session_quote')->getStoreId())
                    ? Mage::getSingleton('adminhtml/session_quote')->getStoreId() : Mage::app()->getStore()->getId();
            $this->setStoreId($storeId);

        }

        if ($this->getIsCancelling() && !$this->getIsNew()) {
            Mage::dispatchEvent('sarp_subscription_cancel_before', array('subscription' => $this));
            // Delete payment sequence
            Mage::getResourceModel('sarp/sequence')->deleteBySubscriptionId($this->getId());
            // Delete alert events
            Mage::getResourceModel('sarp/alert_event')->deleteBySubscriptionId($this->getId());
            // Throw payment onCancel
            $this->_getMethodInstance()->onSubscriptionCancel($this);
        }

        if ($this->getIsSuspending() && !$this->getIsNew()) {
            Mage::dispatchEvent('sarp_subscription_suspend_before', array('subscription' => $this));
            $this->_getMethodInstance()->onSubscriptionSuspend($this);
        }
        if ($this->getIsReactivated() && !$this->getIsNew()) {
            Mage::dispatchEvent('sarp_subscription_reactivate_before', array('subscription' => $this));
            $this->_getMethodInstance()->onSubscriptionReactivate($this);
        }
        if ($this->getIsExpiring() && !$this->getIsNew()) {
            Mage::dispatchEvent('sarp_subscription_expire_before', array('subscription' => $this));
            $this->_getMethodInstance()->onSubscriptionSuspend($this);
        }

        return parent::_beforeSave();
    }


    public static function isIterating()
    {
        return Mage::registry(self::ITERATE_STATUS_REGISTRY_NAME) == self::ITERATE_STATUS_RUNNING;
    }

    /**
     * Returns subscription status for order
     * @param Mage_Sales_Model_Order $Order
     * @return AW_Sarp_Model_Subscription
     */
    public function getStatusByOrder(Mage_Sales_Model_Order $Order)
    {

    }

    /**
     * Returns customer URL
     * @return string
     */
    public function getCustomerUrl()
    {
        return Mage::getUrl('sarp/customer/view', array('id' => $this->getId()));
    }

    /**
     * Returns admin URL
     * @return string
     */
    public function getAdminUrl()
    {
        return Mage::getSingleton('adminhtml/url')->getUrl('sarp_admin/subscriptions/edit', array('id' => $this->getId()));
    }

    /**
     * Return current iterating subscription
     * @static
     * @return AW_Sarp_Model_Subscription
     */
    public static function getInstance()
    {
        return self::$_subscription;
    }

    public function issetSuspendedSubscription()
    {
        $collection = $this->getCollection();
        $collection->getSelect()->where('status = ?', self::STATUS_SUSPENDED);
        return (count($collection->getItems()) > 0);
    }
    
    public function getPaymentForSubscriptionOrder($order,$subscription)
    {
        $xml = $this->getXml($order,$subscription);
		Mage::log($xml, null, "recurring_order_log.log", true);
        if($xml == null)
        {
            return;
        }    
        $url = "https://secure.bluepay.com/ics_gateway.exe";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 
        array(
               'Content-type: text/xml', 
               'Content-length: ' . strlen($xml)
             ));
        $output = curl_exec($ch);
        curl_close($ch);
        //Parse the response from curl
        
        $resp = explode("&",$output);
        foreach($resp as $res)
        {
            $val = explode("=",$res);
            $response[$val[0]] = $val[1];
        }
		Mage::log($response, null, "recurring_order_log.log", true);
		return $response;
    }
    
    public function getXml($order,$subscription)
    {
		$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $exp_month = null;
        $exp_year = null;

        /* Check for ecom id in customer table. If not exist, use realid in subscription table. */
        if($customer->getEcomOrderReferenceId() != null){
                $orderReference = $customer->getEcomOrderReferenceId();
                if($customer->getData('expiry_month') < 10){
                        $expDate = 0;
                        $exp_month = $expDate.$customer->getData('expiry_month');
                }
                else{
                        $exp_month = $customer->getData('expiry_month');
                }
                $exp_year = $customer->getData('expiry_year');
        }
        else{
                if($subscription != null){
                        $orderReference = $subscription->getRealId();				
                }
        }        
        if(!$orderReference){
           $this->log("Real Id and Customer Reference Id is not Available for Subscription. Order Payment must be processed Manually");
           Mage::log($request, null, "recurring_order_log.log", true);
           return null;
	}
        $store = Mage::app()->getStore();
		$mode = Mage::getStoreConfig('payment/ccpayment/test_mode',$store) == 'TEST' ? 'T' : 'P';
        if($mode == 'T' && $amount%2 == 0){
            $amount = $amount + 1;
        }    
			
        $user = Mage::getStoreConfig('payment/ccpayment/login',$store);
        $password = Mage::getStoreConfig('payment/ccpayment/trans_key',$store);
		
		return '<?xml  version = "1.0"?>
            <!DOCTYPE ASSUREBUY.ECOMREQUEST SYSTEM
                "http://docs.assurebuy.com/assurebuy_ecomrequest_46.dtd">
            <ASSUREBUY.ECOMREQUEST
                USERID="'.$user.'"
                PASSWORD="'.$password.'">
                            <Mode>'.$mode.'</Mode>
                            <ResponseType>1</ResponseType>
                            <Order>
				<SellerOrderID>'.$order->getIncrementId().'</SellerOrderID>
                                    <CustomerID>'.$order->getCustomerId().'</CustomerID>
                                    <Source>2</Source>
                                    <IPAddress></IPAddress>

                            <BillTo>
                                            <Postal>
                                                    <Name>
                                                            <First>'.$order->getCustomerFirstName().'</First>
                                                            <Last>'.$order->getCustomerLastName().'</Last>
                                                    </Name>
                                                    <Street>
                                                            <Line1></Line1>
                                                    </Street>
                                                    <City></City>
                                                    <StateProv></StateProv>
                                                    <PostalCode></PostalCode>
                                                    <CountryCode></CountryCode>
                                            </Postal>
                                            <Telecom>
                                                <DayPhone></DayPhone>
                                                <EvePhone></EvePhone>
                                            </Telecom>
                                            <Online>
                                                    <Email>'.$order->getCustomerEmail().'</Email>
                                            </Online>
                            </BillTo>

                                    <Cost>
                                            <Total>'.$order->getGrandTotal().'</Total>
                                            <AmountPaid>'.$order->getGrandTotal().'</AmountPaid>
									</Cost>

                                    <Payment>
										<Action>S</Action>
										<Type></Type>
										<Number></Number>
										<Verification></Verification>
										<ExpDate>
												<Month>'.$exp_month.'</Month>
												<Year>'.$exp_year.'</Year>
										</ExpDate>
										<OriginalTransaction>'.$orderReference.'</OriginalTransaction>
									</Payment>
                            </Order>
            </ASSUREBUY.ECOMREQUEST>';
    }
    
}
