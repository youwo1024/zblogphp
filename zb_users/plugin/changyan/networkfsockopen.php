<?php
/**
 * Z-Blog with PHP
 * @author
 * @copyright (C) RainbowSoft Studio
 * @version 2.0 2013-06-14
 */

/**
*
*/
class Networkfsockopen implements iNetwork
{
	private $readyState = 0;        #状态
	private $responseBody = NULL;   #返回的二进制
	private $responseStream = NULL; #返回的数据流
	private $responseText = '';     #返回的数据
	private $responseXML = NULL;    #尝试把responseText格式化为XMLDom
	private $status = 0;            #状态码
	private $statusText = '';       #状态码文本
	private $responseVersion = '';  #返回的HTTP版体

	private $option = array();
	private $url = '';
	private $postdata = array();
	private $httpheader = array();
	private $responseHeader = array();
	private $parsed_url = array();
	private $port = 80;
	private $timeout = 30;
	private $errstr = '';
	private $errno = 0;
	private $isgzip = false;

	public function __set($property_name, $value){
		throw new Exception($property_name.' readonly');
	}

	public function __get($property_name){
		if(strtolower($property_name)=='responsexml')
		{
			$w = new DOMDocument();
			return $w->loadXML($this->responseText);
		}
		else
		{
			return $this->$property_name;
		}
	}

	public function abort(){

	}

	public function getAllResponseHeaders(){
		return implode("\r\n",$this->responseHeader);
	}

	public function getResponseHeader($bstrHeader){
		$name=strtolower($bstrHeader);
		foreach($this->responseHeader as $w){
			if(strtolower(substr($w,0,strpos($w,':')))==$name){
				return substr(strstr($w,': '),2);
			}
		}
		return '';
	}
	
	public function setTimeOuts($resolveTimeout,$connectTimeout,$sendTimeout,$receiveTimeout){

	}

	public function open($bstrMethod, $bstrUrl, $varAsync=true, $bstrUser='', $bstrPassword=''){ //Async无用
		//初始化变量
		$this->reinit();
		$method=strtoupper($bstrMethod);
		$this->option['method'] = $method;

		$this->parsed_url = parse_url($bstrUrl);
		if(!$this->parsed_url)
		{
			throw new Exception('URL Syntax Error!');
		}
		else{
			//bstrUser & bstrPassword ?
		}

		return true;
	}

	public function send($varBody=''){
		$data=$varBody;
		if(is_array($data)){
			$data=http_build_query($data);
		}

		if($this->option['method']=='POST')
		{

			if($data==''){
				$data=http_build_query($this->postdata);
			}
			$this->option['content'] = $data;

			$this->httpheader[] = 'Content-Type: application/x-www-form-urlencoded';
			$this->httpheader[] = 'Content-Length: ' . strlen($data);

		}

		$this->httpheader[] = 'Host: ' . $this->parsed_url['host'];
		$this->httpheader[] = 'Connection: close';
		
		if(!isset($this->httpheader['Accept'])){
			if(isset($_SERVER['HTTP_ACCEPT'])){
				$this->httpheader['Accept']='Accept:' . $_SERVER['HTTP_ACCEPT'];
			}
		}
		
		if(!isset($this->httpheader['Accept-Language'])){
			if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
				$this->httpheader['Accept-Language']='Accept-Language: ' . $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
		}

		if($this->isgzip == true){
			$this->httpheader['Accept-Encoding']='Accept-Encoding: gzip';
		}

		$this->option['header'] = implode("\r\n",$this->httpheader);

		$socket = fsockopen(
					$this->parsed_url['host'],
					$this->port,
					$this->errno,
					$this->errstr,
					$this->timeout
				  );

		$url = $this->option['method'] . ' ' . $this->parsed_url['path'];

		if(isset($this->parsed_url["query"]))
		{
			$url.= "?" . $this->parsed_url["query"];
		}
		fwrite($socket,
			   $url . ' HTTP/1.1' . "\r\n"
	    );
		fwrite($socket,$this->option['header']."\r\n");
		fwrite($socket,"\r\n");
		if(isset($this->option['content'])){
			fwrite($socket,$this->option['content']."\r\n");
			fwrite($socket,"\r\n");
		}

		while (!feof($socket))
		{
			$this->responseText .= fgets($socket,128);
		}

		$this->responseHeader = substr($this->responseText,0,strpos($this->responseText, "\r\n\r\n"));
	
		$this->responseText = substr($this->responseText, strpos($this->responseText, "\r\n\r\n") + 4);
		
		$this->responseHeader = explode("\r\n",$this->responseHeader);

		if($this->getResponseHeader('Transfer-Encoding')=='chunked'){
			if(!function_exists('http_chunked_decode')){
				$this->responseText=$this->http_chunked_decode($this->responseText);
			}else{
				$this->responseText=http_chunked_decode($this->responseText);
			}
		}

		if($this->getResponseHeader('Content-Encoding')=='gzip'){
			if(!function_exists('gzdecode')){
				$this->responseText=$this->gzdecode($this->responseText);
			}else{
				$this->responseText=gzdecode($this->responseText);
			}
		}
		
		if(isset($this->responseHeader[0])){
			$this->statusText=$this->responseHeader[0];
			$a=explode(' ',$this->statusText);
			if(isset($a[0]))$this->responseVersion=$a[0];
			if(isset($a[1]))$this->status=$a[1];
			unset($this->responseHeader[0]);
		}
		
		fclose($socket);

	}
	public function setRequestHeader($bstrHeader, $bstrValue, $append=false){
		if($append==false){
			$this->httpheader[$bstrHeader]=$bstrHeader.': '.$bstrValue;
		}else{
			if(isset($this->httpheader[$bstrHeader])){
				$this->httpheader[$bstrHeader] = $this->httpheader[$bstrHeader].$bstrValue;
			}else{
				$this->httpheader[$bstrHeader]=$bstrHeader.': '.$bstrValue;
			}
		}
		return true;
	}

	public function add_postdata($bstrItem, $bstrValue){
		array_push($this->postdata,array(
			$bstrItem => $bstrValue
		));
	}

	private function reinit(){
		$this->readyState = 0;        #状态
		$this->responseBody = NULL;   #返回的二进制
		$this->responseStream = NULL; #返回的数据流
		$this->responseText = '';     #返回的数据
		$this->responseXML = NULL;    #尝试把responseText格式化为XMLDom
		$this->status = 0;            #状态码
		$this->statusText = '';       #状态码文本

		$this->option = array();
		$this->url = '';
		$this->postdata = array();
		$this->httpheader = array();
		$this->responseHeader = array();
		$this->parsed_url = array();
		$this->port = 80;
		$this->timeout = 30;
		$this->errstr = '';
		$this->errno = 0;

		$this->setRequestHeader('User-Agent','Mozilla/5.0');
	}
	
    private function http_chunked_decode($chunk) { 
        $pos = 0; 
        $len = strlen($chunk); 
        $dechunk = null; 

        while(($pos < $len) 
            && ($chunkLenHex = substr($chunk,$pos, ($newlineAt = strpos($chunk,"\n",$pos+1))-$pos)))
        { 
            if (! $this->is_hex($chunkLenHex)) { 
                trigger_error('Value is not properly chunk encoded', E_USER_WARNING);
                return $chunk; 
            } 

            $pos = $newlineAt + 1; 
            $chunkLen = hexdec(rtrim($chunkLenHex,"\r\n")); 
            $dechunk .= substr($chunk, $pos, $chunkLen); 
            $pos = strpos($chunk, "\n", $pos + $chunkLen) + 1; 
        } 
        return $dechunk; 
    } 

    /** 
     * determine if a string can represent a number in hexadecimal 
     * 
     * @param string $hex 
     * @return boolean true if the string is a hex, otherwise false 
     */ 
	private function is_hex($hex) { 
		// regex is for weenies 
		$hex = strtolower(trim(ltrim($hex,"0"))); 
		if (empty($hex)) { $hex = 0; }; 
		$dec = hexdec($hex); 
		return ($hex == dechex($dec)); 
	} 
	
	private function gzdecode($string) {// no support for 2nd argument
        return file_get_contents('compress.zlib://data:zbp/ths;base64,'. base64_encode($string));
    }
	
	public function enableGzip(){
		$this->isgzip = true;
	}
}