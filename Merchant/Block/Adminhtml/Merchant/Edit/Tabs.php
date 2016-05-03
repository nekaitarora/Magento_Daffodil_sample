<?php

class Rad_Merchant_Block_Adminhtml_Merchant_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('merchant_tabs');
      $this->setDestElementId('edit_form');
      $this->setTitle(Mage::helper('merchant')->__('Item Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('merchant')->__('Item Information'),
          'title'     => Mage::helper('merchant')->__('Item Information'),
          'content'   => $this->getLayout()->createBlock('merchant/adminhtml_merchant_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}
