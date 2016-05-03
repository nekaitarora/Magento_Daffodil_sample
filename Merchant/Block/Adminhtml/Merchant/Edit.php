<?php

class Rad_Merchant_Block_Adminhtml_Merchant_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
                 
        $this->_objectId = 'id';
        $this->_blockGroup = 'merchant';
        $this->_controller = 'adminhtml_merchant';
        
        $this->_updateButton('save', 'label', Mage::helper('merchant')->__('Save Item'));
        $this->_updateButton('delete', 'label', Mage::helper('merchant')->__('Delete Item'));
		
        $this->_addButton('saveandcontinue', array(
            'label'     => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);

        $this->_formScripts[] = "
            function toggleEditor() {
                if (tinyMCE.getInstanceById('news_content') == null) {
                    tinyMCE.execCommand('mceAddControl', false, 'news_content');
                } else {
                    tinyMCE.execCommand('mceRemoveControl', false, 'news_content');
                }
            }

            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    public function getHeaderText()
    {
        if( Mage::registry('merchant_data') && Mage::registry('merchant_data')->getId() ) {
            return Mage::helper('merchant')->__("Edit Item '%s'", $this->htmlEscape(Mage::registry('merchant_data')->getTitle()));
        } else {
            return Mage::helper('merchant')->__('Add Item');
        }
    }
}
