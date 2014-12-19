<?

class CPAOQiwiPayment{
	private $_REST_ID;
	private $_PWD;
	private $_SHOP_ID;
	private $_qiwiApiURL;
	
	public static $message = array(
		'STATUS_PAID' => "Счёт оплачен",
		'STATUS_WAITING' => "Счёт выставлен, ожидает оплаты",
		'STATUS_REJECTED' => "Счёт отклонен",
		'STATUS_UNPAID' => "Ошибка при проведении оплаты. Счёт не оплачен",
		'STATUS_EXPIRED' => "Время жизни счёта истекло. Счёт не оплачен",
		'FULL_PAYED' => "Заказ полностью оплачен.",
		'PART_PAYMENT' => "Частичная оплата заказа на сумму ",
	);
	
	function __construct($SHOP_ID, $REST_ID, $PWD){
	
	
		if(empty($REST_ID) || empty($PWD) || empty($SHOP_ID))
			die('Miss important argument!'.__METHOD__);
	
		$this->_REST_ID = $REST_ID;
		$this->_PWD = $PWD;
		$this->_SHOP_ID = $SHOP_ID;
		$this->_qiwiApiURL = 'https://w.qiwi.com/api/v2/prv/'.$this->_SHOP_ID;
	}
	
	public static function getMessage($key){
		return trim(self::$message[$key]);
	}
	
	public function setBill($BILL_ID, $data){
	
		$url = $this->_qiwiApiURL.'/bills/'.$BILL_ID;
		
		$req_fields = array(
			"user",
			"amount",
			"ccy",
			"comment",
			"lifetime",
			"prv_name"
		);
		
		foreach($req_fields as $field){
			if(!isset($data[$field]) || empty($data[$field]))
				return false;
		}
		
		return $this->sendQiwiRequest($url, $data,false,'PUT');
		
	}
	
	public function checkBillStatus($BILL_ID){
	
		$url = $this->_qiwiApiURL.'/bills/'.$BILL_ID;
		
		return $this->sendQiwiRequest($url);
		
	}
	
	public function cancelBill($BILL_ID){
	
		$url = $this->_qiwiApiURL.'/bills/'.$BILL_ID;
		
		$data = array('status' => 'rejected');
		
		return $this->sendQiwiRequest($url, $data, false, 'PATCH');
		
	}
	
	public function refundBill($BILL_ID, $refund_id, $amount){
	
		$url = $this->_qiwiApiURL.'/bills/'.$BILL_ID.'/refund/'.$refund_id;
		
		$data = array('amount' => $amount);
		
		return $this->sendQiwiRequest($url, $data, false, 'PUT');
		
	}
	
	public function checkRefundStatus($BILL_ID, $refund_id){
	
		$url = $this->_qiwiApiURL.'/bills/'.$BILL_ID;
		
		$data = array('status' => 'rejected');
		
		return $this->sendQiwiRequest($url);
		
	}
	
	public function checkSign(){
		echo 'sd';
	}
	
	private function sendQiwiRequest($url, $data = array(), $headers = array(), $request_method = false){
		
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		
		if(is_string($request_method) && strlen($request_method) > 2)
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_method);
		else
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		
		if(!empty($data) && is_array($data))
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, $this->_REST_ID.":".$this->_PWD);
		
		if(!empty($headers) && is_array($headers))
			curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
		else
			curl_setopt($ch,CURLOPT_HTTPHEADER, array("Accept: text/json"));
			
		$results = curl_exec ($ch) or die(curl_error($ch));
		curl_close ($ch);
		
		return $results;
	}
}

?>