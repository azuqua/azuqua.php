<?php

class Azuqua {

    private $routes = array(
        "invoke" => array(
            "path" => "/flo/:id/invoke",
            "method" => "post"
        ),
        "list" => array(
            "path" => "/account/flos",
            "method" => "get"
        )
    );
    
    private $host = "https://api.azuqua.com";

    public function config($accessKey, $accessSecret) {
        $this->accessKey = $accessKey;
        $this->accessSecret = $accessSecret;
        $this->floCache = array();
    }

    function __construct($accessKey = null, $accessSecret = null) {
        $this->config($accessKey, $accessSecret);
    }

    private function sign_data($secret, $data, $verb, $path, $timestamp) {
        $meta = join(":", array($verb, $path, $timestamp));
        if(is_array($data)){
            $json = json_encode($data);
        }
        return hash_hmac("sha256", $meta . $json, $secret);
    }
    
    private function get_timestamp(){
        $oldTz = date_default_timezone_get();
        date_default_timezone_set("UTC");
        $date = new DateTime();
        $timestamp = $date->format("Y-m-d\TH:i:sP"); // iso 8601 compatible with JS Date
        date_default_timezone_set($oldTz);
        return $timestamp;
    }

    private function make_request($path, $verb, $data) {
        if(is_null($data) || count($data) < 1){
            $data = "";
        }
        $timestamp = $this->get_timestamp();
        $headers = array(
            "Content-Type: application/json",
            "accepts: */*",
            "x-api-accessKey: " . $this->accessKey,
            "x-api-hash: " . $this->sign_data($this->accessSecret, $data, $verb, $path, $timestamp),
            "x-api-timestamp: " . $timestamp
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(strcmp($verb, "get") === 0 && is_array($data)){
            $path += http_build_query($data);
        }else if(strcmp($verb, "post") === 0){
            curl_setopt($ch, CURLOPT_POST, 1);  
            $body = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            array_push($headers, "Content-Length: " . (string) strlen($body));
        }
        curl_setopt($ch, CURLOPT_URL, $this->host . $path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);
        $json = json_decode($response, true);      
        if(is_null($json)){
            throw new Exception("Error invoking flo");
        }else{
            return $json;
        }
    }

    public function listFlos($refresh = false) {
        if ($refresh || count($this->floCache) < 1) {
            $this->floCache = array();
            $flos = $this->make_request($this->routes["list"]["path"], $this->routes["list"]["method"], null);
            if($flos["error"]){
                return $flos;
            }else{
                foreach ($flos as $flo){
                    $this->floCache[$flo["name"]] = $flo["alias"];
                }
            }
        }
        $out = array();
        foreach ($this->floCache as $name => $alias){
            array_push($out, $name);
        }
        return $out;
    }

    public function invoke($floName, $data) {
        $path = str_replace(":id", $this->floCache[$floName], $this->routes["invoke"]["path"]);
        return $this->make_request($path, $this->routes["invoke"]["method"], $data);
    }

}
