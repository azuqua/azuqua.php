<?php

class Azuqua {

	private $routes = array(
		"invoke" => "/api/flo/:id/invoke",
		"list" => "/api/account/flos"
	);

	private $httpOptions = array(
		"host" => "https://api.azuqua.com",
		"headers" => array(
			"Content-Type" => "application/json",
			"Accept" =>"*/*"
		)
	);

	public function config($accessKey, $accessSecret){
		$this->accessKey = $accessKey;
		$this->accessSecret = $accessSecret;
		$this->floCache = array();
	}

	function __construct($accessKey = null, $accessSecret = null){
		$this->config($accessKey, $accessSecret);
	}

	private function sign_data($secret, $data){
		return hash_hmac("sha256", $data, $this->accessSecret);
	}

	private function make_request($path, $data){
		$body = array(
			"accessKey" => $this->accessKey,
			"data" => $data,
			"hash" => $this->sign_data($this->accessSecret, $data)
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_URL, $this->httpOptions["host"] . $path);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->httpOptions["headers"]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
		$response = curl_exec($ch);
		curl_close($ch);	
		$response = json_decode($response);
		if($response["error"])
			return $response["error"];
		else
			return $response["data"];
	}

	public function listFlos($refresh = false){
		if($refresh || count($this->floCache) < 1){
			$this->floCache = array();
			$flos = make_request($this->routes["list"], "{}");
			foreach($flos as $flo)
				$this->floCache[$flo["name"]] = $flo["alias"];
		}
		$out = array();
		foreach($this->floCache as $name => $alias)
			array_push($out, $name);
		return $out;
	}

	public function invoke($floName, $data){
		$path = str_replace(":id", $this->floCache[$floName], $this->routes["invoke"]);
		return make_request($path, json_encode($data));
	}

}

