<?php

class Rad_Merchant_Block_Adminhtml_Merchant_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
  protected function _prepareForm()
  {
      $form = new Varien_Data_Form();
      $this->setForm($form);
      $fieldset = $form->addFieldset('merchant_form', array('legend'=>Mage::helper('merchant')->__('Item information')));
       
      /* $website_array=array();
      $_websites = Mage::app()->getWebsites();

       foreach($_websites as $website){
            array_unshift($website_array , array('value'=>$website->getId(), 'label'=> $website->getName()));
        }

        $currency_array=array();
        $data=explode(',', Mage::getStoreConfig('system/currency/installed'));
         array_unshift($currency_array , array('value'=>'', 'label'=> ''));
        foreach($data as $value){
            array_unshift($currency_array , array('value'=>$value, 'label'=> Mage::app()->getLocale()->currency($value)->getName()));
         
        }
        $currency_array=array_reverse($currency_array);*/
      
    
      /*$fieldset->addField('website_id', 'select', array(
          'label'     => Mage::helper('merchant')->__('Website Id'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'website_id',
          'values'    => $website_array,
      ));*/
      
      $website_array= Mage::getSingleton('adminhtml/system_config_source_website')->toOptionArray();
      foreach($website_array as $key=> $data)
      {
     
           $storeIds = Mage::getModel('core/website')->load($data['value'])->getStoreIds(); 
       
           foreach($storeIds as $storeid){
               $multipayment=Mage::getStoreConfig('payment/ccpayment/multipayment',$storeid);
               if(!$multipayment){
                   
                   unset($website_array[$key]);
               }
           }
                            
          
      }
   
    if (!Mage::app()->isSingleStoreMode()) {
            $fieldset->addField('website_ids', 'multiselect', array(
                'name'      => 'website_ids[]',
                'label'     => Mage::helper('merchant')->__('Websites'),
                'title'     => Mage::helper('merchant')->__('Websites'),
                'class'     => 'required-entry',
                'required'  => true,
                'values'    => $website_array,
            ));
        }else {
            $fieldset->addField('website_ids', 'hidden', array(
                'name'      => 'website_ids[]',
                'value'     => Mage::app()->getStore(true)->getWebsiteId()
            ));
            $data['website_ids'] = Mage::app()->getStore(true)->getWebsiteId();
        }

      $fieldset->addField('account_id', 'password', array(
          'label'     => Mage::helper('merchant')->__('Account ID'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'account_id',
       ));
      
       $fieldset->addField('secret_key', 'password', array(
          'label'     => Mage::helper('merchant')->__('Secret Key'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'secret_key',
      ));
       
      $fieldset->addField('merchant_name', 'text', array(
          'label'     => Mage::helper('merchant')->__('Merchant Name'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'merchant_name',
      ));
	
     $fieldset->addField('merchant_code', 'text', array(
          'label'     => Mage::helper('merchant')->__('Merchant Code'),
          'class'     => 'required-entry',
          'required'  => true,
          'name'      => 'merchant_code',
      ));
     
    /* $fieldset->addField('is_primary', 'select', array(
          'label'     => Mage::helper('merchant')->__('Primary Merchant'),
          'name'      => 'is_primary',
          'class'     => 'required-entry',
          'required'  => true,
          'values'    => array(
               array(
                  'value'     => '0',
                  'label'     => Mage::helper('merchant')->__('No'),
              ),
              array(
                  'value'     => '1',
                  'label'     => Mage::helper('merchant')->__('Yes'),
              ),
          ),
      ));*/
     
      $fieldset->addField('status', 'select', array(
          'label'     => Mage::helper('merchant')->__('Status'),
          'name'      => 'status',
          'values'    => array(
              array(
                  'value'     => 1,
                  'label'     => Mage::helper('merchant')->__('Enabled'),
              ),

              array(
                  'value'     => 0,
                  'label'     => Mage::helper('merchant')->__('Disabled'),
              ),
          ),
      ));

     
      if ( Mage::getSingleton('adminhtml/session')->getMerchantData() )
      {
          $form->setValues(Mage::getSingleton('adminhtml/session')->getMerchantData());
          Mage::getSingleton('adminhtml/session')->getMerchantData(null);
      } elseif ( Mage::registry('merchant_data') ) {
          $form->setValues(Mage::registry('merchant_data')->getData());
      }
      return parent::_prepareForm();
  }
}
