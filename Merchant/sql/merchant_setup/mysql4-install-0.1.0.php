<?php

$installer = $this;

$installer->startSetup();

$installer->run("

DROP TABLE IF EXISTS {$this->getTable('merchant')};
CREATE TABLE {$this->getTable('merchant')} (
  `merchant_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `website_ids` text default '',
  `account_id` varchar(255) NOT NULL default '',
  `secret_key` varchar(255) NOT NULL default '',
  `merchant_name` varchar(255) NOT NULL default '',
  `merchant_code` varchar(255) NOT NULL default '',
  `status` smallint(6) NOT NULL default '0',
  `created_time` datetime NULL,
  `update_time` datetime NULL,
  PRIMARY KEY (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS {$this->getTable('drg_customercardonfileindicator')};
CREATE TABLE {$this->getTable('drg_customercardonfileindicator')} (
  `drg_customercardonfileindicator_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) unsigned NOT NULL,
  `merchant_id` int(11) unsigned NOT NULL,
  `ecom_id` varchar(255) NOT NULL default '',
  `created_by` int(11) unsigned NOT NULL default 0,
  `creation_time` datetime NULL,
  `modified_by` int(11) unsigned NOT NULL default 0,
  `modification_time` datetime NULL,
  PRIMARY KEY (`drg_customercardonfileindicator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS {$this->getTable('drg_orderdynamicpayment')};
CREATE TABLE {$this->getTable('drg_orderdynamicpayment')} (
  `drg_orderdynamicpayment_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL default '',
  `merchant_id` int(11) unsigned NOT NULL,
  `transaction_amt` float(10,2) unsigned NOT NULL,
  `reward_point` float(10,2) unsigned NOT NULL,
  `transaction_status` varchar(2) NOT NULL default '',
  `transaction_action` varchar(2) NOT NULL default '',
  `transaction_ecomid` varchar(255) NOT NULL default '',
  `transaction_id` varchar(255) NOT NULL default '',
  `store_id` int(11) unsigned NOT NULL,
  `error_code` varchar(255) NOT NULL default '',
  `error_description` varchar(255) NOT NULL default '',
  `created_by` int(11) unsigned NOT NULL default 0,
  `creation_time` datetime NULL,
  `modified_by` int(11) unsigned NOT NULL default 0,
  `modification_time` datetime NULL,
  PRIMARY KEY (`drg_orderdynamicpayment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS {$this->getTable('drg_orderdynamicpaymentitem')};
CREATE TABLE {$this->getTable('drg_orderdynamicpaymentitem')} (
  `drg_orderdynamicpaymentitem_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `drg_orderdynamicpayment_id` int(11) unsigned NOT NULL,
  `item_id`  int(11) unsigned NOT NULL,
  `item_shipping_charges` float(10,2) unsigned NOT NULL ,
  `item_discount` float(10,2) unsigned NOT NULL ,
  `item_tax` float(10,2) unsigned NOT NULL ,
  `created_by` int(11) unsigned NOT NULL default 0,
  `creation_time` datetime NULL,
  `modified_by` int(11) unsigned NOT NULL default 0,
  `modification_time` datetime NULL,
  PRIMARY KEY (`drg_orderdynamicpaymentitem_id`),
  INDEX (`drg_orderdynamicpayment_id`),
  FOREIGN KEY (`drg_orderdynamicpayment_id`)
  REFERENCES {$this->getTable('drg_orderdynamicpayment')} (`drg_orderdynamicpayment_id`)
  ON DELETE CASCADE
  ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

    ");

$installer->endSetup(); 
