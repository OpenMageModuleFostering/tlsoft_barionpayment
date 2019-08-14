<?php

class TLSoft_BarionPayment_Helper_Data extends Mage_Core_Helper_Abstract
{

	const XML_PATH_EMAIL_IDENTITY               = 'sales_email/order/identity';
	
	protected $_testredirect = "https://test.barion.com/Payment/PayInShop/";
	protected $_redirect = "https://barion.com/Payment/PayInShop/";
	
	protected $_testtransid = "https://api.test.barion.com/Payment/GetTransactionId";
	protected $_transid = "https://api.barion.com/Payment/GetTransactionId";
	
	protected $_teststate = "https://api.test.barion.com/Payment/GetTransactionState";
	protected $_state = "https://api.barion.com/Payment/Payment/GetTransactionState";
	
	public $_logfilename 			= "tlbarion.log";
	
	public function getOrderPlaceRedirectUrl()
	{//átirányítás a k&h-ra vivő oldalra
		return Mage::getUrl('tlbarion/redirection/redirect', array('_secure' => true));
	}
	
	public function getLogfilename()
	{
		return $this->_logfilename;
	}
	
	public function getRedirectUrl($storeid)
	{
		if($this->isTest($storeid)==1){
			return $this->_testredirect;
		}
		else{
			return $this->_redirect;
		}
	}
	
	public function getStateUrl($storeid)
	{
		if($this->isTest($storeid)==1){
			return $this->_teststate;
		}
		else{
			return $this->_state;
		}
	}
	
	public function checkLocalCode()
	{//nyelv ellenőrzése
		$localcode=Mage::app()->getLocale()->getLocaleCode();
		$khlocal=substr(strtolower($localcode),3);
		$enabledlocals=array("hu");//engedélyezett nyelvek
		$endotplocal="";
			foreach ($enabledlocals as $enabledlocal)//engedélyezett nyelvek listájának végigfuttatása
			{
				if ($enabledlocal == $khlocal){//ha van találat
					$endotplocal=$khlocal;//akkor beállítás alapnyelvként
				}
			}
		if ($endotplocal != ""){//ha van találat
			return $endotplocal;//visszatérés a nyelvvel
		}
		else
			{//ha nincs találat
				$endotplocal = "hu";//angol beállítása mint alapnyelv
				return $endotplocal;
			}
	}
	
	public function processTransResult($order="",$transaction=array())
	{
		$otppayment = Mage::getModel('tlbarion/paymentmethod');
		
		if(empty($order)){
			$order = $this->getCurrentOrder();
		}
		
		$storeId = $order->getStoreId();
		if(is_array($transaction)){
			$transaction = $otppayment->getTransModel()->loadByOrderId($order->getId());
		}
		$transid = $transaction->getEntityId();

		$real_orderid = $transaction->getRealOrderid();

		$header=array("TransactionId"=>$transaction->getBariontransactionid());
		
		$json = json_encode($header);
		
		$result = $this->callCurl2($json,$storeId);
		$resultarray = array();
		$return="pending";
		if($result!=false){
			$resultarray = json_decode($result,true);
			if($resultarray["Status"]==2){
				$return = "success";
			}
			elseif($resultarray["Status"]==1){
				$return = "pending";
			}
			else{
				$return = "fail";
			}
		}
		if($return!='pending'){
			if($return=='success'){
				$status='02';
			}
			elseif($return=='fail'){
				$status='00';
				if(array_key_exists("ErrorList",$resultarray)){
					$otppayment->saveTransHistory(array('transaction_id'=>$transid,"error_message"=>$resultarray["ErrorList"]["ErrorMessage"],"error_number"=>$resultarray["ErrorList"]["ErrorNumber"]));
				}
			}
			$otppayment->updateTrans(array('payment_status'=>$status),$transid);
			//$this->_sendStatusMail($order);
		}
		return $return;
	}
	
	public function processOrderSuccess($order)
	{
		if ($order->canInvoice()) {
			$invoice = $order->prepareInvoice();
			$invoice->register()->capture();
			Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();				
		}
		if($order->getState()!=Mage_Sales_Model_Order::STATE_PROCESSING){
			$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
			$order->setStatus('processing');
			$order->save();
			if($order->getId()){
				$order->sendNewOrderEmail();
			}
		}
	}
		
	public function getCurrentOrder()
	{//jelenlegi rendelés lekérése
		$orderIncrementId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		return $order;
	}
	
	public function getShopId($storeId)
	{
		return Mage::getStoreConfig('payment/tlbarion/shop_id',$storeId);
	}
		
	public function getOtpurl()
	{
		return $this->_customer_url;
	}
	
		
	public function getOtpKeyFile($storeId)
	{
		$var=Mage::getBaseDir('var');
		$route = $var."/uploads/";
		if($this->isTest($storeId)==1){
			return $route."barion_test.crt";
		}
		else{
			return $route."barion.crt";
		}
	}
	
	public function isTest($storeId)
	{
		return Mage::getStoreConfig('payment/tlbarion/is_test',$storeId);
	}
	
	public function callCurl($json,$storeId)
	{
		if($this->isTest($storeId)==1){
			$url = $this->_testtransid;
		}
		else{
			$url = $this->_transid;
		}
		$cert = $this->getOtpKeyFile($storeId);
		try{
				$options = array(
					CURLOPT_RETURNTRANSFER => true,     // return web page
					CURLOPT_HEADER         => 0,    // don't return headers
					CURLOPT_FOLLOWLOCATION => 0,     // follow redirects
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
					CURLOPT_CAINFO		   => $cert,
					CURLOPT_URL			   => $url,
					CURLOPT_POST		   => 1,
					CURLOPT_POSTFIELDS     => $json,
					CURLOPT_HTTPHEADER     => array("Content-Type: application/json","Accept: application/json")
				);
				$ch = curl_init();
				curl_setopt_array($ch,$options);
				$content = curl_exec($ch);
				$err = curl_errno($ch);
				if(!$err){
					curl_close($ch);
					return $content;
				}
				else{
					return false;
				}
			}
			catch(Exception $e){
				return false;
			}
	}
	
	public function callCurl2($json,$storeId)
	{
		$url = $this->getStateUrl($storeId);
		$cert = $this->getOtpKeyFile($storeId);
		try{
				$options = array(
					CURLOPT_RETURNTRANSFER => true,     // return web page
					CURLOPT_HEADER         => 0,    // don't return headers
					CURLOPT_FOLLOWLOCATION => 0,     // follow redirects
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
					CURLOPT_CAINFO		   => $cert,
					CURLOPT_URL			   => $url,
					CURLOPT_POST		   => 1,
					CURLOPT_POSTFIELDS     => $json,
					CURLOPT_HTTPHEADER     => array("Content-Type: application/json","Accept: application/json")
				);
				$ch = curl_init();
				curl_setopt_array($ch,$options);
				$content = curl_exec($ch);
				$err = curl_errno($ch);
				if(!$err){
					curl_close($ch);
					return $content;
				}
				else{
					return false;
				}
			}
			catch(Exception $e){
				return false;
			}
	}
 
}