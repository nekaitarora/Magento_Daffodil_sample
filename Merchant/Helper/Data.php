<?php

class Rad_Merchant_Helper_Data extends Mage_Core_Helper_Abstract
{

   const REQUEST_METHOD_CC     = 'CREDIT';
   
/**  Function is used to split the cart item as per merchant and calculate merchant amount and other details
     Date- 6-Feb-201
 */
   
  public function splitPayment($order,$zeroTrans){
	
    $storeid= $order->getStoreId(); 
    $multi_payment = Mage::getStoreConfig('payment/ccpayment/multipayment',$storeid);
    $website_id = Mage::getModel('core/store')->load($order->getStoreId())->getWebsiteId();
    $payment_array=array();
    $shipping_amount = $order->getShippingAmount();
    $pointDiscount=0;
    $total_qty=0;
    $flag=1;

    $primary_merchant_code  = $this->getPrimaryMerchant($order->getStoreId());
    $primary_acc_id         = Mage::getStoreConfig('payment/ccpayment/login',$order->getStoreId());
    $primary_secret_key     = Mage::getStoreConfig('payment/ccpayment/trans_key',$order->getStoreId());

     foreach ($order->getItemsCollection() as $item){
        $mer_code='';
        
        $_product = Mage::getModel('catalog/product')->setStoreId($order->getStoreId())->load($item->getProductId());
        
        if($_product->getTypeID()!='virtual'){
              $total_qty=$total_qty+$item->getQtyOrdered();
        }
   
      $product = Mage::getModel('catalog/product')->setStoreId($order->getStoreId())->setMerchantCodeDrop($_product->getMerchantCodeDrop()); // not loading the product - just creating a simple instance
      $mer_code = $product->getAttributeText('merchant_code_drop');
     
        //Check merchant is valid maerchant or not and get the details of merchant like account id and its secret key

              if($mer_code){
                $data = Mage::getModel('merchant/merchant')->getCollection()
                          ->addFieldToFilter('merchant_code', $mer_code)
                           ->addFieldToFilter('website_ids', array(array('finset' => $website_id)))
                          ->addFieldToFilter('status', 1)
                           ->getData();

                    if($data){         //  If condition check merchant exist or not
                            $merchant_id=  $data[0]['merchant_id'];
                    }
                    else{
                        
                         Mage::register('product_error', $_product->getName());
                         $flag=0; break;
                    }	
                    
               }else{ 
                  Mage::register('product_error', $_product->getName());
                  $flag=0; break;
               }
				
            if (array_key_exists($mer_code, $payment_array)) {
					
                if($merchant_id){  // If array exist and merchant is not primary merchant 

                   /// $payment_array[$mer_code]['amt']=$payment_array[$mer_code]['amt']+($item->getRowTotalInclTax()-$item->getDiscountAmount());
                    $payment_array[$mer_code]['amt']=$payment_array[$mer_code]['amt']+($item->getRowTotal()+$item->getTaxAmount()-$item->getDiscountAmount());
                    $payment_array[$mer_code]['productid']=$payment_array[$mer_code]['productid'].",".$item->getProductId();

                     if($_product->getTypeID()!='virtual'){
                           $payment_array[$mer_code]['totalqty']=$payment_array[$mer_code]['totalqty']+$item->getQtyOrdered();
                           $payment_array[$mer_code]['item'][$item->getProductId()]['qty']=$item->getQtyOrdered();
                           $payment_array[$mer_code]['item'][$item->getProductId()]['ship_amt']=0;
                    }
                    $payment_array[$mer_code]['item'][$item->getProductId()]['tax_amt']=$item->getTaxAmount();
                    $payment_array[$mer_code]['item'][$item->getProductId()]['discount_amt']=$item->getDiscountAmount();

               }
					
             } else {
					      
                if($merchant_id){  // For first time insertion of non-primary merchant in array

                    $payment_array[$mer_code]['accountno']=base64_decode(Mage::helper('core')->decrypt($data[0]['account_id']));
                    $payment_array[$mer_code]['secretkey']=base64_decode(Mage::helper('core')->decrypt($data[0]['secret_key']));
                    //$payment_array[$mer_code]['primary_merchant']=$data[0]['is_primary'];
                    $payment_array[$mer_code]['merchant_id']=$merchant_id;
                    $payment_array[$mer_code]['merchantcode']=$mer_code;
                    //$payment_array[$mer_code]['amt']=($item->getRowTotalInclTax()-$item->getDiscountAmount());//Price should be including tax
                    $payment_array[$mer_code]['amt']=($item->getRowTotal()+$item->getTaxAmount()-$item->getDiscountAmount());
                    $payment_array[$mer_code]['productid']=$item->getProductId();
                    $payment_array[$mer_code]['discount']=0;
                    $payment_array[$mer_code]['item'][$item->getProductId()]['tax_amt']=$item->getTaxAmount();
                    $payment_array[$mer_code]['item'][$item->getProductId()]['discount_amt']=$item->getDiscountAmount();
                    $payment_array[$mer_code]['zero_transaction']=0;
                    $payment_array[$mer_code]['totalqty']=0;

                   if($_product->getTypeID()!='virtual'){       // Check for virtual product
                     $payment_array[$mer_code]['totalqty']=$item->getQtyOrdered();
                     $payment_array[$mer_code]['item'][$item->getProductId()]['qty']=$item->getQtyOrdered();
                     $payment_array[$mer_code]['item'][$item->getProductId()]['ship_amt']=0;
                  }
                  else{

                     $payment_array[$mer_code]['item'][$item->getProductId()]['qty']=$item->getQtyOrdered();
                     $payment_array[$mer_code]['item'][$item->getProductId()]['ship_amt']=0;
                  }
                }                    
        }  
					 
                     $merchant_id='';
      }
      
    if($multi_payment){
        if($flag == 1){  // If flag is zero then merchant not found and transaction cannot processed

        // code to calculate and distribute shipping amount
         if($shipping_amount ){

            $avg_shipping_amount=$shipping_amount/$total_qty;
            foreach($payment_array as $key=>$data){
             if($data['totalqty']){
                $amount=$data['amt']+($data['totalqty']*$avg_shipping_amount);   
                $payment_array[$key]['total_ship_amt']=$data['totalqty']*$avg_shipping_amount;
                $payment_array[$key]['amt']=round($amount,2);

                //Calculating shipping amount applied on each product
                foreach($data['item'] as $key1=>$value){
                  $_product = Mage::getModel('catalog/product')->load($key1);

                  if($_product->getTypeID()!='virtual'){
                               $payment_array[$key]['item'][$key1]['ship_amt']=$value['qty']*$avg_shipping_amount;
                  }
                } //  End of inner foreach loop
              }
            }//  End of outer foreach loop
          }
        // End of shipping calculation
		
		
    // Reward point Calculation
	
        $reward = Mage::getSingleton('checkout/session')->getRewardSalesRules();
        $pointDiscount=$reward['use_point'];  
          if($pointDiscount){
            if (array_key_exists($primary_merchant_code, $payment_array)) {    //check for primary merchant exist or not

                    $pm_amt=$payment_array[$primary_merchant_code]['amt'];
                    if($pointDiscount <= $pm_amt){  //condtion to check discount amount is less than primary merchant total amount

                        $payment_array[$primary_merchant_code]['amt']=$pm_amt-$pointDiscount;
                        $payment_array[$primary_merchant_code]['discount']=$pointDiscount;
                   } 
                   else {
                        $payment_array=$this->spreadRewardPoint($payment_array,$pointDiscount,1);  //If primary merchant exist then parameter 1 send
                   }		  
            }  // End of primary merchant check conditions

            else {   
                $payment_array = $this->spreadRewardPoint($payment_array,$pointDiscount,0,$order->getStoreId());		//If primary merchant not exist then parameter 0 send		  
            }
          }
			  
        // End of reward point calculation 
	   
        // Condition for checking Zero transaction process 
		  
            if($zeroTrans){

                   $payment_array = $this->zeroTransaction($payment_array,$order->getStoreId()); 
                 }

        // End of Zero transaction process condition
		   
            Mage::register('paymentdata', $payment_array);
         //echo "<pre>great"; print_r($payment_array);exit;	
            return $payment_array;
	}
        else{
		
            return false;
	}
    }else{
        return $payment_array;
    }
}

// End of function
	 
 /** 
  * Function to calculate Reward Points
     Reward point distribution calcualtion 	
  */
	
public function spreadRewardPoint($payment_array,$discount,$check_primary,$storeId){
	
    $sorter= array();
    $result= array();
    
    foreach($payment_array as $key => $value){
		   
        $sorter[$key]=$value['amt'];
     }
    arsort($sorter);
    
    if($check_primary) { // Condition to check primary customer exist or not for applying discount.

        $primary_merchant_code = $this->getPrimaryMerchant($storeId);

        $result[$primary_merchant_code]['amt']=0;
        $result[$primary_merchant_code]['discount']=$sorter[$primary_merchant_code];
        $discount = $discount-$sorter[$primary_merchant_code];
			 
        foreach($sorter as $key=>$amt){    // Loop for deducting amount from other merchants.  
            if($key != $primary_merchant_code){

                if($discount > 0 ){  // condition check discount amount not equal zero if zero then break the loop

                    if($discount >= $amt){
                       $discount = round($discount,2) - round($amt,2);
                       $result[$key]['amt']=0;
                       $result[$key]['discount']=$amt;

                    }else{

                       $result[$key]['amt']=round($amt,2) - round($discount,2);
                       $result[$key]['discount']=$discount;
                       $discount = 0;
                    }

                }
                else{ break;}

            }
        } 
		 
      } 
       else {   //  If payment array does not have primary merchant
		 
        foreach($sorter as $key=>$amt){

             if($discount!=0){  // condition check discount amount not equal zero if zero then break the loop

                if($discount >= $amt){
                    $discount = $discount - $amt;
                    $result[$key]['amt']=0;
                    $result[$key]['discount']=$amt;

                 }else{

                    $result[$key]['amt']=$amt - $discount;
                    $result[$key]['discount']=$discount;
                    $discount = 0;
                 }

            }else{ break;}
        }
     }
		 
        foreach($result as $key=>$value){
            $payment_array[$key]['amt']=$value['amt'];
            $payment_array[$key]['discount']=$value['discount'];
       }

         return $payment_array;
}

// End of function reward point calculation
	

/** Function is used process zero dollar transaction
     Date- 12-Feb-2014
 */
	 
public function zeroTransaction($payment_array,$storeId){
	 
    $website_id = Mage::app()->getWebsite()->getId();
    $primary_merchant_code  = $this->getPrimaryMerchant($storeId);
    $primary_acc_id         = Mage::getStoreConfig('payment/ccpayment/login',$storeId);        //$this->getConfigData('login');
    $primary_secret_key     = Mage::getStoreConfig('payment/ccpayment/trans_key',$storeId);   //$this->getConfigData('trans_key') ;

    $data = Mage::getModel('merchant/merchant')->getCollection()
           ->addFieldToFilter('website_ids', array(array('finset' => $website_id)))     
           ->addFieldToFilter('status', 1)
           ->getData();
   
    foreach($data as $value){
        if (!array_key_exists($value['merchant_code'], $payment_array)) { // Checking in payment array primary merchant if not exist set primary merchant for zero transaction
            if($primary_merchant_code ==$value['merchant_code'] ){
                
                 $payment_array[$primary_merchant_code]['accountno']=base64_decode(Mage::helper('core')->decrypt($primary_acc_id));
                 $payment_array[$primary_merchant_code]['secretkey']=base64_decode(Mage::helper('core')->decrypt($primary_secret_key));
                
            }else{   //  for other merchants set zero transaction details 
              
                $payment_array[$value['merchant_code']]['accountno']=base64_decode(Mage::helper('core')->decrypt($value['account_id']));
                $payment_array[$value['merchant_code']]['secretkey']=base64_decode(Mage::helper('core')->decrypt($value['secret_key']));
            
            }
            
            $payment_array[$value['merchant_code']]['merchant_id']=$value['merchant_id'];
            $payment_array[$value['merchant_code']]['merchantcode']=$value['merchant_code'];
            $payment_array[$value['merchant_code']]['amt']=0;
            $payment_array[$value['merchant_code']]['discount']=0;
            $payment_array[$value['merchant_code']]['primary_merchant']=0;
            $payment_array[$value['merchant_code']]['zero_transaction']=1;
        }
    }
		
    return  $payment_array;			
		 
}  //End of function zeroTransaction
	
/** function will create request for spilt payments  */

public function _buildPaymentRequest($order,$data,$originalTransaction = null)
{
    $payment = $order->getPayment();
    $primary_acc_id         = Mage::getStoreConfig('payment/ccpayment/login',$order->getStoreId());//$this->getConfigData('login');
    $primary_secret_key     = Mage::getStoreConfig('payment/ccpayment/trans_key',$order->getStoreId());//$this->getConfigData('trans_key') ;

    Mage::getModel('creditcard/ccPayment')->setStore($order->getStoreId());
    $expDate = 0;

        if(!$order->getPayment()->getPaymentType()){

            $order->getPayment()->setPaymentType(self::REQUEST_METHOD_CC);
        }
	

        $mode = Mage::getStoreConfig('payment/ccpayment/test_mode') == 'TEST' ? 'T' : 'P';
        if($mode == 'T' && $data['amt']%2 == 0 )
        { 
                  $amount = $data['amt'] + 1;
        }else{
            $amount =   $data['amt'];
        }   
        
         if($data['zero_transaction'] || $data['amt']==0){
                $paymentType='A';
          } else{
                $paymentType='S';
        }

        if($order->getCustomerId()) {
                $customer = Mage::getSingleton('customer/customer')->load($order->getCustomerId());
                $customerId = $order->getCustomerId();
        }
        else
        {
                $customerId = NULL;
        } 
        //Request Xml 
       $cctype = $payment->getMethodInstance()->getInfoInstance()->getCcType(); 
       $billing = $order->getBillingAddress();
	   
        if($data['primary_merchant']==1){

                $user     = $primary_acc_id;
                $password = $primary_secret_key ;

        }else{

                $user = $data['accountno'];
                $password = $data['secretkey'];
        }

       if($originalTransaction)
       {
            if($customer->getData('expiry_month') < 10)
            {
                         $exp_month = $expDate.$customer->getData('expiry_month');
            }
            else
            {
                         $exp_month = $customer->getData('expiry_month');
            }
            
        $request = '<?xml  version = "1.0"?>
         <!DOCTYPE ASSUREBUY.ECOMREQUEST SYSTEM
             "http://docs.assurebuy.com/assurebuy_ecomrequest_46.dtd">
         <ASSUREBUY.ECOMREQUEST
             USERID="'.$user.'"
             PASSWORD="'.$password.'">
                    <Mode>'.$mode.'</Mode>
                    <ResponseType>1</ResponseType>
                    <Order>
                   <SellerOrderID>'.$order->getIncrementId().'</SellerOrderID>
                   <CustomerID>'.$customerId.'</CustomerID>
                <BillTo>
                        <Postal>
                                <Name>
                                    <First></First>
                                    <Last></Last>
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
                       <Total>'.$amount.'</Total>
                       <AmountPaid>'.$amount.'</AmountPaid>
               </Cost>
                <Payment>
                        <Action>'.$paymentType.'</Action>
                        <Type></Type>
                        <Number></Number>
                        <Verification></Verification>
                        <ExpDate>
                                <Month>'.$exp_month.'</Month>
                                <Year>'.$customer->getData('expiry_year').'</Year>
                        </ExpDate>
                        <OriginalTransaction>'.$originalTransaction.'</OriginalTransaction>
                </Payment>

            </Order>
         </ASSUREBUY.ECOMREQUEST>';
       }
       else
       {    
            if($payment->getCcExpMonth() < 10)
            {
                         $exp_month = $expDate.$payment->getCcExpMonth();
            }
            else
            {
                         $exp_month = $payment->getCcExpMonth();
            }
            
           
        $request = '<?xml  version = "1.0"?>
            <!DOCTYPE ASSUREBUY.ECOMREQUEST SYSTEM
                "http://docs.assurebuy.com/assurebuy_ecomrequest_46.dtd">
            <ASSUREBUY.ECOMREQUEST
                USERID="'.$user.'"
                PASSWORD="'.$password.'">
                            <Mode>'.$mode.'</Mode>
                            <ResponseType>1</ResponseType>
                            <Order>
                                    <SellerOrderID>'.$order->getIncrementId().'</SellerOrderID>
                                    <CustomerID>'.$customerId.'</CustomerID>
                            <BillTo>
                                            <Postal>
                                                    <Name>
                                                            <First>'.$billing->getFirstname().'</First>
                                                            <Last>'.$billing->getLastname().'</Last>
                                                    </Name>
                                                    <Street>
                                                            <Line1>'.$billing->getStreet(1).'</Line1>
                                                    </Street>
                                                    <City>'.$billing->getCity().'</City>
                                                    <StateProv>'.$billing->getRegion().'</StateProv>
                                                    <PostalCode>'.$billing->getPostcode().'</PostalCode>
                                                    <CountryCode>'.$billing->getCountry().'</CountryCode>
                                            </Postal>
                                            <Telecom>
                                                <DayPhone>'.$billing->getTelephone().'</DayPhone>
                                                <EvePhone>'.$billing->getTelephone().'</EvePhone>
                                            </Telecom>
                                    <Online>
                                                    <Email>'.$order->getCustomerEmail().'</Email>
                                            </Online>
                            </BillTo>

                             <Cost>
                                    <Total>'.$amount.'</Total>
                                    <AmountPaid>'.$amount.'</AmountPaid>
                            </Cost>
                            <Payment>
                                    <Action>'.$paymentType.'</Action>
                                    <Type>'.$cctype.'</Type>
                                    <Number>'.$payment->getCcNumber().'</Number>
                                    <Verification>'.$payment->getCcCid().'</Verification>
                                    <ExpDate>
                                            <Month>'.$exp_month.'</Month>
                                            <Year>'.$payment->getCcExpYear().'</Year>
                                    </ExpDate>
                                    <OriginalTransaction></OriginalTransaction>
                            </Payment>
                            </Order>
            </ASSUREBUY.ECOMREQUEST>';
       }
   
        return $request;
		
    }   //   End of function buildSplitCardOnFileRequest 
    
/*
 * Function will return primary merchant code
 * parmaeter required: store id
 */
    
public function getPrimaryMerchant($storeid){

    $primary_id = Mage::getStoreConfig('payment/ccpayment/merchantcode',$storeid);
    $mer = Mage::getModel('merchant/merchant')->load($primary_id);

     return $mer->getMerchantCode();

}
// End of function

/*
 *  If payment was successfully then go for databse insertion
 *  Function call from durm pro observer
 */

public function successTransaction($customerId,$storeid){
    
     $result = Mage::registry("result_data");
        
    if($customerId) {

           if($result){

               $trans_result=$this->insertTransaction($result,$customerId,$storeid);  // function call for inserting primary order details
               $this->insertItemDetails($result,$trans_result,$customerId);	// function call for inserting  order item details						
               $this->insertEcomid($result,$customerId);	// function call for inserting  ecomid details
             }

           }else{
                   
                   $trans_result=$this->insertTransaction($result,0);
                   $this->insertItemDetails($result,$trans_result,0);

           }
          Mage::unregister("result_data");
          Mage::unregister("paymentdata");
    }
    
 /* Insert record in transaction table 
  * DB Table: drg_orderdynamicpayment
  */
public function insertTransaction($result,$customerId,$storeId){

	 $payment = Mage::registry("paymentdata");
	 $last_id_array=array();
	
         foreach($result as $key=>$value){
                if($key!='all_processed' && $key!='trans_decline_merchant' && $key!='refund_reward' && $key!='refund_success'){
                    
                 if($payment[$key]['zero_transaction'] ||$payment[$key]['amt']==0){
                            $paymentType='A';
                   } else{
                       $paymentType='S';
                   }
                    $trans= Mage::getModel('merchant/merchanttrans')
                            ->setOrderId($value['orderid'])
                            ->setMerchantId($payment[$key]['merchant_id'])
                            ->setTransactionAmt($value['amt'])
                            ->setRewardPoint($payment[$key]['discount'])
                            ->setTransactionStatus($value['result'])
                            ->setTransactionAction($paymentType)
                            ->setTransactionEcomid($value['ecomid'])
                            ->setTransactionId($value['orderef'])
                            ->setStoreId($storeId)
                            ->setCreatedBy($customerId)
                            ->setCreationTime(NOW())
                            ->setModifiedBy($customerId)
                            ->setModificationTime(NOW())
                            ->save();	

                          $last_id_array[$key] = $trans->getId();
                }
	  }
		 return $last_id_array;
	
}
	
/* Insert record in source indicator / ecomid maaping table
 * DB Table: drg_customercardonfileindicator
 *  
 */

public function insertEcomid($result,$customerId){
    
  $payment = Mage::registry("paymentdata"); 
  
    foreach($result as $key=>$value){
       if($key!='all_processed' && $key!='trans_decline_merchant' && $key!='refund_reward' && $key!='refund_success'){
       $data = Mage::getModel('merchant/merchantmap')->getCollection()
           ->addFieldToFilter('customer_id', $customerId)
           ->addFieldToFilter('merchant_id', $payment[$key]['merchant_id'])
            ->getData();
     try{			   
         if($data){

            $ecom=Mage::getModel('merchant/merchantmap')->load($data[0]['drg_customercardonfileindicator_id']);
            $ecom->setEcomId($value['ecomid'])
                 ->setModifiedBy($customerId)
                 ->setModificationTime(NOW())
                  ->save();
        }else{			
                Mage::getModel('merchant/merchantmap')
                  ->setCustomerId($customerId)
                  ->setMerchantId($payment[$key]['merchant_id'])
                  ->setEcomId($value['ecomid'])
                  ->setCreatedBy($customerId)
                  ->setCreationTime(NOW())
                  ->setModifiedBy($customerId)
                  ->setModificationTime(NOW())
                  ->save();	
           }
	}catch (Exception $e) {
		 Mage::logException($e);
	 }				
       }				  
     }
  }
	
 /* 
  * Insert Item details 
  * DB Table: drg_orderdynamicpaymentitem
  */
 public function insertItemDetails($result,$trans_result,$customerId){
     
    $payment = Mage::registry("paymentdata");

     foreach($result as $key=>$value){
       if($key!='all_processed'){
        $items = explode(",", $value['pid']);
        foreach($items as $product){

          Mage::getModel('merchant/merchantitem')
           ->setDrgOrderdynamicpaymentId($trans_result[$key])
           ->setItemId($product)
           ->setItemShippingCharges($payment[$key]['item'][$product]['ship_amt'])
           ->setItemDiscount($payment[$key]['item'][$product]['discount_amt'])
           ->setItemTax($payment[$key]['item'][$product]['tax_amt'])
           ->setCreatedBy($customerId)
           ->setCreationTime(NOW())
           ->setModifiedBy($customerId)
           ->setModificationTime(NOW())
           ->save();
        }
       }
    }
          
  }// end of insertItemDetails
  
  /*
   * Payment Falied transaction Insertion start
   * Function calls from Rad/Merchant module observer
   */
  public function failureTransaction($result,$customerId,$storeId,$email=Null,$name=Null){
      
      if( $result['refund_success']){  // if refund process done successfully
          
            $trans_result=$this->insertFailureTransaction($result,$customerId,$storeId); // Function call for inserting failure transaction
            $this->insertRefundItemDetails($result,$trans_result,$customerId);           // Function call for inserting refund transaction item details
            $this->insertRefund($result,$customerId);                                   // Function call for inserting refund transaction details
          
      }else{
                $trans_result=$this->insertFailureTransaction($result,$customerId);
                $this->insertRefundItemDetails($result,$trans_result,$customerId);
                $this->insertRefund($result,$customerId,$storeId);
                $recevier=array();
              

                $recevier[0]['email']=$email;
                $recevier[0]['name'] =$name;

                $recevier[1]['email']=Mage::getStoreConfig('trans_email/ident_custom1/email',$storeId); 
                $recevier[1]['name'] =Mage::getStoreConfig('trans_email/ident_custom1/name',$storeId);

         $this->sendRefundMail($recevier,$storeId);
          
      }
      
  }
  
   /* Insert record in transaction table 
    * DB Table: drg_orderdynamicpayment
    */
public function insertFailureTransaction($result,$customerId,$storeId){
    
 $payment = Mage::registry("paymentdata");
 $last_id_array=array();
 $orderid='';
 
     foreach($result as $key=>$value){
         
        if($key!='all_processed' && $key!='trans_decline_merchant' && $key!='refund_reward' && $key!='refund_success'){
        if($value['result']=='Y'){   
            $trans= Mage::getModel('merchant/merchanttrans')
                     ->setOrderId($value['orderid'])
                     ->setMerchantId($payment[$key]['merchant_id'])
                     ->setTransactionAmt($value['amt'])
                     ->setRewardPoint($payment[$key]['discount'])
                     ->setTransactionStatus($value['result'])
                     ->setTransactionAction($value['ecom_trans_action'])
                     ->setTransactionEcomid($value['ecomid'])
                     ->setTransactionId($value['orderef'])
                     ->setStoreId($storeId)
                     ->setCreatedBy($customerId)
                     ->setCreationTime(NOW())
                     ->setModifiedBy($customerId)
                     ->setModificationTime(NOW())
                     ->save();
         $last_id_array[$key] = $trans->getId();
        }else{
                $trans= Mage::getModel('merchant/merchanttrans')
                     ->setOrderId($value['orderid'])
                    ->setMerchantId($payment[$key]['merchant_id'])
                    ->setTransactionAmt($value['amt'])
                    ->setTransactionStatus($value['result'])
                    ->setTransactionAction('S')
                    ->setStoreId($storeId)
                    ->setErrorCode($value['Ecom_Error_Code'])
                    ->setErrorDescription($value['Ecom_Error_Description'])
                    ->setCreatedBy($customerId)
                    ->setCreationTime(NOW())
                    ->setModifiedBy($customerId)
                    ->setModificationTime(NOW())
                    ->save();
        $last_id_array[$key] = $trans->getId();

                        }						 
                }
     
     }
    return $last_id_array;
}
	

/* 
 * Insert Item details 
 * DB Table: drg_orderdynamicpaymentitem
 * 
 */
public function insertRefundItemDetails($result,$trans_result,$customerId){
     $payment = Mage::registry("paymentdata");

      foreach($result as $key=>$value){
        if($value['result']=='Y'){  
            
            if($key!='all_processed' && $key!='trans_decline_merchant' && $key!='refund_reward' )
            {
                $items = explode(",", $value['pid']);
                foreach($items as $product){

                  Mage::getModel('merchant/merchantitem')
                   ->setDrgOrderdynamicpaymentId($trans_result[$key])
                   ->setItemId($product)
                   ->setItemShippingCharges($payment[$key]['item'][$product]['ship_amt'])
                   ->setItemDiscount($payment[$key]['item'][$product]['discount_amt'])
                   ->setItemTax($payment[$key]['item'][$product]['tax_amt'])
                   ->setCreatedBy($customerId)
                   ->setCreationTime(NOW())
                   ->setModifiedBy($customerId)
                   ->setModificationTime(NOW())
                   ->save();
                }
            }
        }
      }

 }
	
 /* 
  * STORING REFUND TRANSACTION 
  *  DB Table:drg_orderdynamicpayment
  */
public function insertRefund($result,$customerId,$storeId){
    
 $payment = Mage::registry("paymentdata");

  foreach($result as $key=>$value){
    if($key!='all_processed' && $key!='trans_decline_merchant' && $key!='refund_reward' )
    {            
         if($value['result']=='Y'){
             
                 if($value['refund']['result']=='R'){
                  Mage::getModel('merchant/merchanttrans')
                    ->setOrderId($value['orderid'])
                    ->setMerchantId($payment[$key]['merchant_id'])
                    ->setTransactionAmt($value['amt'])
                    ->setRewardPoint($payment[$key]['discount'])
                    ->setTransactionStatus($value['refund']['result'])
                    ->setTransactionAction($value['refund']['ecom_trans_action'])
                    ->setTransactionEcomid($value['refund']['ecomid'])
                    ->setTransactionId($value['refund']['orderef'])
                    ->setStoreId($storeId)
                    ->setCreatedBy($customerId)
                    ->setCreationTime(NOW())
                    ->setModifiedBy($customerId)
                    ->setModificationTime(NOW())
                    ->save();
                }
                else{

                  Mage::getModel('merchant/merchanttrans')
                    ->setOrderId($value['orderid'])
                    ->setMerchantId($payment[$key]['merchant_id'])
                    ->setTransactionAmt($value['amt'])
                    ->setRewardPoint($payment[$key]['discount'])
                    ->setTransactionStatus($value['refund']['result'])
                    ->setTransactionAction($value['refund']['ecom_trans_action'])
                    ->setStoreId($storeId)
                    ->setErrorCode($value['refund']['Ecom_Error_Code'])
                    ->setErrorDescription($value['refund']['Ecom_Error_Description'])
                    ->setCreatedBy($customerId)
                    ->setCreationTime(NOW())
                    ->setModifiedBy($customerId)
                    ->setModificationTime(NOW())
                    ->save();
               }
          }	 
       }
   }
           
}

/*
 * Function will send Refund mail to customer support and customer
 */
public function sendRefundMail($recevier,$storeId){
	
	$templateId = 16;
          
    $senderName = Mage::getStoreConfig('trans_email/ident_general/name',$storeId);
    $senderEmail = Mage::getStoreConfig('trans_email/ident_general/email',$storeId);    
    $sender = array('name' => $senderName,
                'email' => $senderEmail);
     
    foreach($recevier as $data){
        
        // Set recepient information
           $recepientEmail = $data['email'];
           $recepientName = $data['name'];       
             
        $translate  = Mage::getSingleton('core/translate');

        // Send Transactional Email
        Mage::getModel('core/email_template')
            ->sendTransactional($templateId, $sender, $recepientEmail, $recepientName, $vars, $storeId);

        $translate->setTranslateInline(true);   
    }
	  
  }
 
  /*
   * 
   * Function will provide merchant details
   * Parameter required: merchant code and website id
   */
  public function getMerchantData($mer_code,$website_id){
      
        $data = Mage::getModel('merchant/merchant')->getCollection()
                ->addFieldToFilter('merchant_code', $mer_code)
                ->addFieldToFilter('website_ids', array(array('finset' => $website_id)))
                ->addFieldToFilter('status', 1)
                ->getData();
        
      return $data;
  }
          
}
