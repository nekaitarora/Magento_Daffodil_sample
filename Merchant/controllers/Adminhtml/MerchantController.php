<?php

class Rad_Merchant_Adminhtml_MerchantController extends Mage_Adminhtml_Controller_action
{

	protected function _initAction() {
		$this->loadLayout()
			->_setActiveMenu('merchant/items')
			->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));
		
		return $this;
	}   
 
	public function indexAction() {
		$this->_initAction()
			->renderLayout();
	}

	public function editAction() {
           
		$id     = $this->getRequest()->getParam('id');
		$model  = Mage::getModel('merchant/merchant')->load($id);

		if ($model->getId() || $id == 0) {
			$data = Mage::getSingleton('adminhtml/session')->getFormData(true);
			if (!empty($data)) {
				$model->setData($data);
			}

			Mage::register('merchant_data', $model);

			$this->loadLayout();
			$this->_setActiveMenu('merchant/items');

			$this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item Manager'), Mage::helper('adminhtml')->__('Item Manager'));
			$this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item Merchant'), Mage::helper('adminhtml')->__('Item Merchant'));

			$this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

			$this->_addContent($this->getLayout()->createBlock('merchant/adminhtml_merchant_edit'))
				->_addLeft($this->getLayout()->createBlock('merchant/adminhtml_merchant_edit_tabs'));

			$this->renderLayout();
		} else {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('merchant')->__('Item does not exist'));
			$this->_redirect('*/*/');
		}
	}
 
	public function newAction() {
		$this->_forward('edit');
	}
 
	public function saveAction() {
		if ($data = $this->getRequest()->getPost()) {
			$this->getRequest()->getParam('id');
			/*if(isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
				try {	
					// Starting upload 	
					$uploader = new Varien_File_Uploader('filename');
					
					// Any extention would work
	           		$uploader->setAllowedExtensions(array('jpg','jpeg','gif','png'));
					$uploader->setAllowRenameFiles(false);
					
					// Set the file upload mode 
					// false -> get the file directly in the specified folder
					// true -> get the file in the product like folders 
					//	(file.jpg will go in something like /media/f/i/file.jpg)
					$uploader->setFilesDispersion(false);
							
					// We set media as the upload dir
					$path = Mage::getBaseDir('media') . DS ;
					$uploader->save($path, $_FILES['filename']['name'] );
					
				} catch (Exception $e) {
		      
		        }
	        
		        //this way the name is saved in DB
	  			$data['filename'] = $_FILES['filename']['name'];
			}*/
	  			
	  		if($this->getRequest()->getParam('id')){
                            $value = Mage::getModel('merchant/merchant')->load($this->getRequest()->getParam('id'));
                            $old_code=$value['merchant_code'];
                            $old_Status=$value['status'];
                            
                            $old_acc_id=$value['account_id'];
                            $old_sec_id=$value['secret_key'];
                        }else{
                            
                           $data['account_id'] = Mage::helper('core')->encrypt(base64_encode($data['account_id']));
                           $data['secret_key']=Mage::helper('core')->encrypt(base64_encode($data['secret_key']));
                            
                        }
                        $website_id = implode(",", $data['website_ids']);
                        $data['website_ids']=$website_id;
                       //echo"<pre>"; print_r($data);
                       //	$data['merchant_code'];
                       	//$data['account_id'] = Mage::helper('core')->encrypt(base64_encode($data['account_id']));
                      //  $data['secret_key']=Mage::helper('core')->encrypt(base64_encode($data['secret_key']));
                     //   $data['account_id']=md5($data['account_id']);
                     //   $data['secret_key']=md5($data['secret_key']);
			$model = Mage::getModel('merchant/merchant');		
			$model->setData($data)
				->setId($this->getRequest()->getParam('id'));
			
			try {
				if ($model->getCreatedTime == NULL || $model->getUpdateTime() == NULL) {
                                    
					$model->setCreatedTime(now())
						->setUpdateTime(now());
				} else { 
					$model->setUpdateTime(now());
				}	
				
				$model->save();
                           
                    
                        
                        if($old_acc_id != $model->getAccountId()){
                            
                            $model->setAccountId(Mage::helper('core')->encrypt(base64_encode($model->getAccountId())));
                            $model->save();
                        }
                        
                        if($old_sec_id != $model->getSecretKey()){
                            
                            $model->setSecretKey(Mage::helper('core')->encrypt(base64_encode($model->getSecretKey())));
                            $model->save();
                        }
                        
                        
        // Added by saurabh for entry in merchant code product option drop down
           
               $eavAttribute = new Mage_Eav_Model_Mysql4_Entity_Attribute();
               $attributeid = $eavAttribute->getIdByCode('catalog_product', 'merchant_code_drop');
               
               $resource = Mage::getSingleton('core/resource');
               $read = $resource->getConnection('core_read');
               $write = $resource->getConnection('core_write');
               
             if($old_code)    {
                 
                 if($old_Status == $model->getStatus()){
                   //  echo "in fi";
                     $searchLabel =$old_code;
                 }else if(!$old_Status && $model->getStatus()){
                      $searchLabel = $old_code.' inactive';
                    
                 }else if($old_Status && !$model->getStatus()){
                     $searchLabel = $old_code;
                 }
              //  echo " <pre> search label".$searchLabel;exit;
                //  $searchLabel = ($model->getStatus() == 1 ?  $old_code.' inactive':$old_code);
                
                  $query="SELECT * FROM eav_attribute_option_value where value='".$searchLabel."'";
                 $result=$read->fetchAll($query);
                 
                 
                 $label = ($model->getStatus() == 1 ? $data['merchant_code'] : $data['merchant_code'].' inactive');
                 if($result){
                 $updateValue="update eav_attribute_option_value set value='".mysql_escape_string($label)."' where value_id='".$result[0]['value_id']."'";
                 $write->query($updateValue);
                 }else{
                     
                     $insertOption= "insert into eav_attribute_option (attribute_id,sort_order) value ('".$attributeid."',0)";
                 $write->query($insertOption);
                 $lastId=$write ->lastInsertId();
                
                 $label = ($model->getStatus() == 1 ? $data['merchant_code'] : $data['merchant_code'].' inactive');
                 
                 $insertValue="insert into eav_attribute_option_value (option_id,store_id,value) value ('". $lastId."',0,'".mysql_escape_string($label)."')";
                 $write->query($insertValue);
                 }
                 
             }
             else{
                 
                 $insertOption= "insert into eav_attribute_option (attribute_id,sort_order) value ('".$attributeid."',0)";
                 $write->query($insertOption);
                 $lastId=$write ->lastInsertId();
                
                 $label = ($model->getStatus() == 1 ? $data['merchant_code'] : $data['merchant_code'].' inactive');
                 
                 $insertValue="insert into eav_attribute_option_value (option_id,store_id,value) value ('". $lastId."',0,'".mysql_escape_string($label)."')";
                 $write->query($insertValue);
             } 
               

          
      // end of code 
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('merchant')->__('Item was successfully saved'));
				Mage::getSingleton('adminhtml/session')->setFormData(false);

				if ($this->getRequest()->getParam('back')) {
					$this->_redirect('*/*/edit', array('id' => $model->getId()));
					return;
				}
				$this->_redirect('*/*/');
				return;
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                Mage::getSingleton('adminhtml/session')->setFormData($data);
                $this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
                return;
            }
        }
        Mage::getSingleton('adminhtml/session')->addError(Mage::helper('merchant')->__('Unable to find item to save'));
        $this->_redirect('*/*/');
	}
 
	public function deleteAction() {
		if( $this->getRequest()->getParam('id') > 0 ) {
			try {
				$model = Mage::getModel('merchant/merchant');
				 
				$model->setId($this->getRequest()->getParam('id'))
					->delete();
					 
				Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Item was successfully deleted'));
				$this->_redirect('*/*/');
			} catch (Exception $e) {
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
				$this->_redirect('*/*/edit', array('id' => $this->getRequest()->getParam('id')));
			}
		}
		$this->_redirect('*/*/');
	}

    public function massDeleteAction() {
        $merchantIds = $this->getRequest()->getParam('merchant');
        if(!is_array($merchantIds)) {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select item(s)'));
        } else {
            try {
                foreach ($merchantIds as $merchantId) {
                    $merchant = Mage::getModel('merchant/merchant')->load($merchantId);
                    $merchant->delete();
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d record(s) were successfully deleted', count($merchantIds)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }
	
    public function massStatusAction()
    {
        $merchantIds = $this->getRequest()->getParam('merchant');
        if(!is_array($merchantIds)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Please select item(s)'));
        } else {
            try {
                foreach ($merchantIds as $merchantId) {
                    $merchant = Mage::getSingleton('merchant/merchant')
                        ->load($merchantId)
                        ->setStatus($this->getRequest()->getParam('status'))
                        ->setIsMassupdate(true)
                        ->save();
                }
                $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) were successfully updated', count($merchantIds))
                );
            } catch (Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }
  
    public function exportCsvAction()
    {
        $fileName   = 'merchantcsv';
        $content    = $this->getLayout()->createBlock('merchant/adminhtml_merchant_grid')
            ->getCsv();

        $this->_sendUploadResponse($fileName, $content);
    }

    public function exportXmlAction()
    {
        $fileName   = 'merchant.xml';
        $content    = $this->getLayout()->createBlock('merchant/adminhtml_merchant_grid')
            ->getXml();

        $this->_sendUploadResponse($fileName, $content);
    }

    protected function _sendUploadResponse($fileName, $content, $contentType='application/octet-stream')
    {
        $response = $this->getResponse();
        $response->setHeader('HTTP/1.1 200 OK','');
        $response->setHeader('Pragma', 'public', true);
        $response->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true);
        $response->setHeader('Content-Disposition', 'attachment; filename='.$fileName);
        $response->setHeader('Last-Modified', date('r'));
        $response->setHeader('Accept-Ranges', 'bytes');
        $response->setHeader('Content-Length', strlen($content));
        $response->setHeader('Content-type', $contentType);
        $response->setBody($content);
        $response->sendResponse();
        die;
    }
}
