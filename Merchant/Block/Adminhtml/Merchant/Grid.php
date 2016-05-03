<?php

class Rad_Merchant_Block_Adminhtml_Merchant_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  public function __construct()
  {
      parent::__construct();
      $this->setId('merchantGrid');
      $this->setDefaultSort('merchant_id');
      $this->setDefaultDir('ASC');
      $this->setSaveParametersInSession(true);
  }

  protected function _prepareCollection()
  {
      $collection = Mage::getModel('merchant/merchant')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      $this->addColumn('merchant_id', array(
          'header'    => Mage::helper('merchant')->__('ID'),
          'align'     =>'left',
          'width'     => '50px',
          'index'     => 'merchant_id',
         
         
      ));
      
     /* $this->addColumn('website_id', array(
          'header'    => Mage::helper('merchant')->__('Website'),
          'align'     =>'left',
          'index'     => 'website_id',
          'renderer'  => 'Rad_Merchant_Block_Adminhtml_Merchant_Renderer_Getweb',
      ));*/
      
      $this->addColumn('website_ids', array(
            'header'	=> Mage::helper('merchant')->__('Website'),
            'align'	=> 'left',
            'width'	=> '200px',
            'type'	=> 'options',
            'options'	=> Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(),
            'index'	=> 'website_ids',
            'renderer'  => 'Rad_Merchant_Block_Adminhtml_Merchant_Renderer_Getweb',
            'filter_condition_callback'	=> array($this, 'filterCallback'),
            'sortable'	=> false,
        ));
       $this->addColumn('merchant_name', array(
            'header'    => Mage::helper('merchant')->__('Merchant Name'),
            'align'     =>'left',
            'index'     => 'merchant_name',
      ));

      $this->addColumn('merchant_code', array(
            'header'    => Mage::helper('merchant')->__('Merchant Code'),
            'align'     =>'left',
            'index'     => 'merchant_code',
      ));
      
    /*  $this->addColumn('is_primary', array(
          'header'    => Mage::helper('merchant')->__('Is Primary'),
          'align'     => 'left',
          'width'     => '80px',
          'index'     => 'is_primary',
          'type'      => 'options',
          'options'   => array(
              1 => 'Yes',
              0 => 'No',
          ),
      ));*/


      $this->addColumn('status', array(
          'header'    => Mage::helper('merchant')->__('Status'),
          'align'     => 'left',
          'width'     => '80px',
          'index'     => 'status',
          'type'      => 'options',
          'options'   => array(
              1 => 'Enabled',
              0 => 'Disabled',
          ),
      ));
	  
        $this->addColumn('action',
            array(
                'header'    =>  Mage::helper('merchant')->__('Action'),
                'width'     => '100',
                'type'      => 'action',
                'getter'    => 'getId',
                'actions'   => array(
                    array(
                        'caption'   => Mage::helper('merchant')->__('Edit'),
                        'url'       => array('base'=> '*/*/edit'),
                        'field'     => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
                'is_system' => true,
        ));
		
		$this->addExportType('*/*/exportCsv', Mage::helper('merchant')->__('CSV'));
		$this->addExportType('*/*/exportXml', Mage::helper('merchant')->__('XML'));
	  
      return parent::_prepareColumns();
  }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('merchant_id');
        $this->getMassactionBlock()->setFormFieldName('merchant');

        $this->getMassactionBlock()->addItem('delete', array(
             'label'    => Mage::helper('merchant')->__('Delete'),
             'url'      => $this->getUrl('*/*/massDelete'),
             'confirm'  => Mage::helper('merchant')->__('Are you sure?')
        ));

       
       $status =array(array('label'=>'', 'value'=>''),array('label'=>'Enable', 'value'=>'1'),array('label'=>'Disable', 'value'=>'0'));
        
        $this->getMassactionBlock()->addItem('status', array(
             'label'=> Mage::helper('merchant')->__('Change status'),
             'url'  => $this->getUrl('*/*/massStatus', array('_current'=>true)),
             'additional' => array(
                    'visibility' => array(
                         'name' => 'status',
                         'type' => 'select',
                         'class' => 'required-entry',
                         'label' => Mage::helper('merchant')->__('Status'),
                         'values' => $status
                     )
             )
        ));
        return $this;
    }

  public function getRowUrl($row)
  {
      return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }
  
  public function filterCallback($collection,$column){
  	if (!$value = $column->getFilter()->getValue()) {
        return;
    }
 
    $this->getCollection()->addFieldToFilter('website_ids', array('finset' => $value));
  }

}
