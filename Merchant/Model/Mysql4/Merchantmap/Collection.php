<?php

class Rad_Merchant_Model_Mysql4_Merchantmap_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('merchant/merchantmap');
    }
}
?>