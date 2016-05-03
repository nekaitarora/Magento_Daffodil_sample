<?php
class Rad_Merchant_Block_Adminhtml_Merchant extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_merchant';
    $this->_blockGroup = 'merchant';
    $this->_headerText = Mage::helper('merchant')->__('Item Manager');
    $this->_addButtonLabel = Mage::helper('merchant')->__('Add Item');
    parent::__construct();
  }
}
