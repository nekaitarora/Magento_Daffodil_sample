<?php
class Rad_Merchant_Block_Merchant extends Mage_Core_Block_Template
{
	public function _prepareLayout()
    {
		return parent::_prepareLayout();
    }
    
     public function getMerchant()     
     { 
        if (!$this->hasData('merchant')) {
            $this->setData('merchant', Mage::registry('merchant'));
        }
        return $this->getData('merchant');
        
    }
}
