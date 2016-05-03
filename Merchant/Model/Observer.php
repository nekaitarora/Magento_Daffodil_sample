<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class Rad_Merchant_Model_Observer
{
  public function storeTransaction($observer)
  {   
        /** added by saurabh * for inserting data in model */
     
      $storeId=Mage::app()->getStore()->getId();
      $multi_payment = Mage::getStoreConfig('payment/ccpayment/multipayment',$storeId);
    if($multi_payment){
         
        $customer = Mage::getSingleton('customer/session')->getCustomer();	
        $customerId = $customer->getId();
        
      //  Mage::Helper('merchant')->failureTransaction($customerId,$storeId);
       $result = Mage::registry("result_data");
				  
				    
    if($result){ 
        if(array_key_exists('refund_success', $result)){ 


            // Check for Refund process
              if( $result['refund_success']){
              
                    Mage::Helper('merchant')->failureTransaction($result,$customerId,$storeId);

                }else {
                      $event = $observer->getEvent();
                      $recevier[0]['email']=$event->getEmail();
                      $recevier[0]['name'] =$event->getCustname();
                      Mage::Helper('merchant')->failureTransaction($result,$customerId,$storeId,$event->getEmail(),$event->getCustname());

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
          /** End of code */
  }
       
       
}
?>
