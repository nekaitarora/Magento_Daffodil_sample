<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


class Rad_Merchant_Block_Adminhtml_Merchant_Renderer_Getweb extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
   public function render(Varien_Object $row)
    {
            $rowwebsite=explode(',',$row['website_ids']);
            if(count($rowwebsite)==1){
                $website = Mage::app()->getWebsite($row['website_ids']);
                 $websitedata= $website->getName();
            }else {

            foreach($rowwebsite as $key=>$value){

               $website = Mage::app()->getWebsite($value);
               if($key==0){$websitedata =$website->getName();}else{
              $websitedata =$websitedata.','.$website->getName();}

            }}
            echo $websitedata;
                   
            
        } 
}
?>
