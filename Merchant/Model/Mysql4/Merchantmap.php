<?php

class Rad_Merchant_Model_Mysql4_Merchantmap extends Mage_Core_Model_Mysql4_Abstract
{
    public function _construct()
    {    
        // Note that the <module>_id refers to the key field in your database table.
        $this->_init('merchant/merchantmap', 'drg_customercardonfileindicator_id');
    }
}
