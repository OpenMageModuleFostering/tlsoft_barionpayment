<?php

class TLSoft_BarionPayment_Model_Paymentmethod extends Mage_Payment_Model_Method_Abstract
{
	protected $_code					= 'tlbarion';
	protected $_isGateway               = true;
	protected $_canCapture              = true; //capture
	protected $_canAuthorize            = false; //authorize
	protected $_canVoid					= false;
	protected $_canRefund               = false;//refund
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = false;
	protected $_canUseForMultishipping  = false;
	protected $_ordercurrency			= "";//order's currency code
	protected $_canRefundInvoicePartial = false;
	
	protected $_infoBlockType = "tlbarion/info";

	public function canUseForCurrency($currencyCode)
    {//pénznem ellenõrzése
		if ($currencyCode == "HUF"){
			$session=Mage::getSingleton('checkout/session');
			$session->setBarioncurrency($currencyCode);
			return true;
		}
		else {
			return false;
		}
    }
	
	public function canUseCheckout()
	{//ha nincs fent a certifikát fájl
		$helper=$this->otpHelper();
		$storeid = Mage::app()->getStore()->getStoreId();
		$cert=$helper->getOtpKeyFile($storeid);

		if (empty($cert)){
			return false;
		}else{
			return true;
		}
	}
	
	public function initialize($paymentAction, $stateObject)
    {
		$state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(false);
    }
	
	public function getOrderPlaceRedirectUrl()
	{//átirányítás az unicreditre-re vivõ oldalra
		return Mage::getUrl('tlbarion/redirection/redirect', array('_secure' => true));
	}
	
	public function otpHelper()
	{
		return Mage::helper('tlbarion');
	}
	
	public function getOtpUrl()
	{//redirect url to k&h site
		$helper = $this->otpHelper();
		$order = $helper->getCurrentOrder();
		$storeid = $order->getStoreId();
		$session = Mage::getSingleton('checkout/session');
		$currency=$order->getOrderCurrencyCode();//GET Currency Code
		$ordertotal = $this->getOrderTotal();
		$locale = $helper->checkLocalCode();
		$lastorderid = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		
		$header = array("ApplicationId"=>$helper->getShopId($storeid),"Amount"=>$ordertotal,"ShopTransactionId"=>$lastorderid);
		$products = array();
		$items = $order->getAllVisibleItems();
		$i=0;
		foreach($items as $item){
			$products[$i]["Name"]=$item->getName();
			$products[$i]["Quantity"]=$item->getQtyOrdered();
			$products[$i]["Price"]=$item->getPriceInclTax();
			$i++;
		}
		$shipping = $order->getShippingInclTax();
		if($shipping>0){
			$products[$i]["Name"]=$order->getShippingDescription();
			$products[$i]["Quantity"]=1;
			$products[$i]["Price"]=$shipping;
		}
		$header["Products"]=$products;
		$products="";
		$json = json_encode($header);
		
		$transid=$this->saveTrans(array('real_orderid'=>$lastorderid,'order_id'=>$order->getId(),'application_id'=>$helper->getShopId($storeid),'amount'=>$ordertotal,'ccy'=>$currency,'store_id'=>$storeid,'payment_status'=>"01",'created_at'=>Varien_Date::now()));
		$this->saveTransHistory(array('transaction_id'=>$transid));
		$result = $helper->callCurl($json,$storeid);
		$resultarray = "";
		if($result!=false){
			$resultarray = json_decode($result,true);
			if($resultarray["TransactionId"]!=""){
				$this->updateTrans(array("bariontransactionid"=>$resultarray["TransactionId"]),$transid);
				$url=$helper->getRedirectUrl($storeid);
				return $url."?TransactionId=".$resultarray["TransactionId"]."&ShopTransactionId=".$lastorderid;
			}
			else{
				$this->saveTransHistory(array('transaction_id'=>$transid,"error_message"=>$resultarray["ErrorList"]["ErrorMessage"],"error_number"=>$resultarray["ErrorList"]["ErrorNumber"]));
				return false;
			}
		}
		else{
			return false;
		}
	}
	
	protected function getOrderTotal()
	{//rendelés végösszegének lekérése
		$helper = $this->otpHelper();
		$order=$helper->getCurrentOrder();
		$grandTotal = round($order->getGrandTotal());
		return $grandTotal;
	}
	
	public function getTransModel()
	{
		return Mage::getModel('tlbarion/transactions');
	}
	
	public function getTransHistoryModel()
	{
		return Mage::getModel('tlbarion/transactions_history');
	}
	
	public function saveTransHistory($history)
	{
		
		$tablesave = $this->getTransHistoryModel()
		->setData($history)
		->save();

	}
	
	private function saveTrans($transaction)
	{
		$tablesave = $this->getTransModel()->setData($transaction)->save();	
		return $tablesave->getEntityId();
	}
	
	public function updateTrans($transaction,$transid)
	{
		$helper = $this->otpHelper();
		$tablesave = $this->getTransModel()->load($transid);
		$tablesave->addData($transaction);
		try{
			$tablesave->save();
		}
		catch (Exception $e){
			Mage::log($transaction,null,$helper->_logfilename);
		}
		return;
	}
	
	public function capture(Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();
		$transaction = Mage::getModel('tlbarion/transactions')->loadByOrderId($order->getId());
		$payment->setTransactionId($transaction->getOrderId());
		$payment->setIsTransactionClosed(0);
		
		return $this;
	}
	
	public function processBeforeRefund($invoice, $payment){return $this;} //before refund
	
	/*public function refund(Varien_Object $payment, $amount)
	{
		$tid = $payment->getLastTransId();
		$transaction = $this->getTransModel()->loadByOrderId($tid);
		$transid=$transaction->getEntityId();
		$helper = $this->otpHelper();
		$order = Mage::getModel('sales/order')->load($transaction->getOrderId());
		$storeid = $order->getStoreId();
		$currency=$transaction->getCcy();//GET Currency Code
		$ordertotal = $transaction->getAmount();
		$ordertotalotp = round($ordertotal);
		
		$type = 'WEBSHOPFIZETESJOVAIRAS';
		
		$text = $helper->getShopId($storeid).'|'.$transaction->getRealOrderid().'|'.$ordertotalotp;
		$sign = $helper->sign($text,$storeid);
		
		$refundresult = true;
		
		if($sign==false){
			$refundresult = false;
		}

		$xmlarray = array('name' => 'StartWorkflow',
						  'value' => '',
							array(
								'name'	=> 'TemplateName',
								'value'	=> $type,
							),
							array(
								'name'	=> 'Variables',
								'value'	=> '',
									array(
										'name'	=> 'isClientCode',
										'value'	=> 'WEBSHOP',
									),
									array(
										'name'	=> 'isPOSID',
										'value'	=> $helper->getShopId($storeid),
									),
									array(
										'name'	=> 'isTransactionID',
										'value'	=> $transaction->getRealOrderid(),
									),
									array(
										'name'	=> 'isAmount',
										'value'	=> $ordertotalotp,
									),
							),
						);
		$xmlarray[1][]=$helper->setSignature($sign);//set signature
		$xmltext = $helper->createXML($xmlarray);
		$soap = $helper->createSoapClient();
		$this->saveTransHistory(array('transaction_id'=>$transid,'transaction_type'=>$type));
		$result = $helper->startWorkflowSynch($type,$xmltext,$soap);
		$resultarray = array();
		$resultarray = $helper->convertXML($result);
		if($resultarray['completed']==1 && $resultarray['timeout']==""){
			$resultarray = $helper->convertXML($resultarray['result']);
		}
		else{
			$refundresult = false;
		}
		if($resultarray['messagelist']['message'] == 'SIKER'){
			$response = $resultarray['resultset']['record']['responsecode'];
			if($response=="000"||$response=="001"||$response=="010"){
				$auth=$resultarray['resultset']['record']['authorizationcode'];
				$status='02';
			}
			else{
				$refundresult = false;
			}
		}
		else{
			$refundresult = false;
		}

		if ($refundresult==false){
			$errorCode = 'Invalid Data';
            $errorMsg = $helper->__('Error Processing the request');
			Mage::log($result,null,$helper->getLogfilename());
            Mage::throwException($errorMsg);
		}else{
			$this->saveTransHistory(array('transaction_id'=>$transid,'response'=>$response,'auth_number'=>$auth));
			$transid=$this->updateTrans(array('payment_status'=>$status),$transid);
			$refunded = array('real_orderid'=>$transaction->getRealOrderid(),'order_id'=>$transaction->getOrderId(),'pos_id'=>$helper->getShopId($storeid),'amount'=>'-'.$ordertotalotp,'ccy'=>$transaction->getCcy(),'store_id'=>$transaction->getStoreId(),'payment_status'=>$status,'created_at'=>Varien_Date::now(),'lang'=>$transaction->getLang());
			$this->saveTrans($refunded);
			$helper->_sendStatusMail($order);
		}
		
		return $this;
	} //refund api
	*/

	public function processCreditmemo($creditmemo, $payment){return $this;} //after refund
}
?>