<?php
class CFAPI {
    public $apikey;
    public $email;
    public $zoneid;
    
    private function curl($url,$method="GET",$data=null){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Auth-Email: '.$this->email,
            'X-Auth-Key: '.$this->apikey,
            'Cache-Control: no-cache',
            'Content-Type:application/json'
        ));
        if (!empty($data)) {
            $data_string = json_encode($data);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        }
        $sonuc = curl_exec($ch);
        curl_close($ch);
        return $sonuc;
    }
    
    private function getZoneID($domain){
        preg_match_all("/[\w-]+\.[\w-]+$/", $domain, $match);
        $domain = $match[0][0];
        $result=$this->curl("https://api.cloudflare.com/client/v4/zones?name=".$domain);
        $json=json_decode($result);
        if (!empty($json->result) and $json->success) {
            return $json->result[0]->id;
        }else{
            return false;
        }
    }
    
    private function getDomainID($domain){
        if(empty($this->zoneid)){
            $this->zoneid = $this->getZoneID($domain);
        }
        $result=$this->curl("https://api.cloudflare.com/client/v4/zones/".$this->zoneid."/dns_records?name=".$domain);
        $json=json_decode($result);
        // var_dump($json->success);
        if (!empty($json->result) and $json->success) {
            return $json->result[0]->id;
        }else{
            return false;
        }
    }
    
    public function getuser(){
        return $this->curl("https://api.cloudflare.com/client/v4/user");
    }
    
    public function updateDNS($domain,$ip,$type="A",$ttl=120){
        $domainid=$this->getDomainID($domain);
        $data = array(
            'type' => $type,
            'name' => $domain,
            'content' => $ip,
            'proxied' => false,
            'ttl' => $ttl
        );
        if (empty($domainid)) {
            return $this->curl("https://api.cloudflare.com/client/v4/zones/".$this->zoneid."/dns_records","POST",$data);
        }else{
            return $this->curl("https://api.cloudflare.com/client/v4/zones/".$this->zoneid."/dns_records/".$domainid,"PUT",$data);
        }
    }
}

function getIP(){
    static $realip;
    if (isset($_SERVER)){
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")){
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    return $realip;
}


if(isset($_REQUEST['json'])){
    $param = json_decode($_REQUEST['json'],true);
    extract($param);
    $key = explode("|", $password);
    $apikey = $key[0];
    $zoneid = $key[1];
}else{
    $username = $_REQUEST['username'];
    $password = $_REQUEST['password'];
    $key = explode("|", $password);
    $apikey = $key[0];
    $zoneid = $key[1];
    $domain = $_REQUEST['hostname'];
    $myip = isset($_REQUEST['myip']) ? $_REQUEST['myip'] : getIP();
}

$cf = new CFAPI;
$cf->apikey = $apikey ;
$cf->email = $username;
$cf->zoneid = $zoneid;
//updateDNS : if domain do not exist,it will auto create a new record.
$result = $cf->updateDNS($domain, $myip);
$resultArr = json_decode($result, true);
if($resultArr['success']===true){
    echo 'good '.$resultArr['result']['content'];
}else{
    echo $result;
}
