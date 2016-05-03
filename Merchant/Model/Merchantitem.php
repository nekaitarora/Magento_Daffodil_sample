<?php

class Rad_Merchant_Model_Merchantitem extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('merchant/merchantitem');
    }
}
