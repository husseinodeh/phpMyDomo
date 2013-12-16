<?php
/*
	phpMyDomo : Home Automation Web Interface
	http://www.phpmydomo.org
    ----------------------------------------------
	Copyright (C) 2013  Francois Dechery

	LICENCE: ###########################################################
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>
	#####################################################################
*/
//namesapce for json rpc2 library
use JsonRPC\Client;

class PMD_Root_ApiClient extends PMD_Root{

	var $vars;	//api config

	var $o_jsonrpc;

	//public
	var $api_url;		//	last api url used
	var $api_status;	//	last api status returned
	var $api_response;	// last api response


	// devices list from remote server
	var $devices	=array();
/*
	uid 			=> 'unique id' per device if a hardware device report multiple values, you must create one device per value 
					(ie: A hardware device with id=223 reports temperature + barometer, you must create 2 devices: 223_temp + 223_baro)
	address			=> needed for commands: id to pass to the remote json API to control the device
	state			=> Current state value of the device: 'on' | 'off' | 'mixed' (for groups)
	value			=> Current value of the device: (int) for dimmers | (float) for sensors) | (hex) for rgb | '' for switches
	class 			=> Class of device: command | sensor  | scene | camera | security
	type			=> Type of the device
						-For Commands	: switch | dimmer | heating | shutter | fan | rgb | rgbw | therm
						-For Sensors	: temp | wind_speed | wind_gust | wind_temp | wind_chill | rain | baro | hygro | uv | pir | gas | bool | consum | counter
						-For Scenes 	: scene | group
						-For Cameras 	: cam_ip
						-For Security 	: door | window
	url				=> url of the video stream (for cam_ip only)
	raw (array)		=> original json from remote server
	battery_level 	=> (optionnal int 0/100) Battery level for sensor
	signal_level 	=> (optionnal int 0/100) Signal level for RF sensor
	raw_value1		=> (optionnal) raw main value of the device : used to autobuild 'state', if not specified in the RegisterDevice method
	unit			=> (optionnal) unit of the value, if  it cant be guessed by the type (ie current) ... to improve
*/

	// various info from remote server
	var $infos		=array();
	
	//----------------------------------------------------------------------------------
	function __construct(& $class){
		parent::__construct($class);
		$this->_require();
		$this->ApiLoad();
	}

	//----------------------------------------------------------------------------------
	private function _require(){
		$my_api		=$this->conf['app']['api'];
		require($this->conf['paths']['api'].'api_config.php');

		//override from global config
		if(isset($this->conf['urls']["server_$my_api"])){
			$api['urls']['api']=$this->conf['urls']["server_$my_api"];
		}
		if(isset($this->conf['urls']["www_$my_api"])){
			$api['urls']['www']=$this->conf['urls']["www_$my_api"];
		}
		$api['urls']['www'] or $api['urls']['www']=$api['urls']['api'];
		$api['urls']['admin']=$api['urls']['www'].$api['dir_admin'];

		$this->conf['api']= $api;
		$this->vars=& $this->conf['api'];
		
		if($this->vars['method']=="json_rpc2"){
			require_once($this->conf['libs']['jsonrpc_client']);
			$this->o_jsonrpc = new Client($this->vars['urls']['api']);
			
		}
		
	}

	//----------------------------------------------------------------------------------
	function ApiLoad(){
		$this->ApiListDevices();
		$this->ApiListInfos();
	}


	//----------------------------------------------------------------------------------
	function ApiListDevices(){
		echo "Extend this method to List all devices from remote api call";
		exit;
	}
	//----------------------------------------------------------------------------------
	function ApiListInfos(){
		echo "Extend this method to List general server information from remote api call";
	}


	//----------------------------------------------------------------------------------
	function GetInfos(){
		return $this->infos;
	}

	//----------------------------------------------------------------------------------
	function GetDevices($class='',$type='',$sort_field=''){
		return $this->_FilterDevices($class,$type,$sort_field);
	}

	//----------------------------------------------------------------------------------
	function GetCommands($type='',$sort_field=''){
		return $this->_FilterDevices('command',$type,$sort_field);
	}

	//----------------------------------------------------------------------------------
	function GetScenes($type='',$sort_field=''){
		return $this->_FilterDevices('scene',$type,$sort_field);
	}

	//----------------------------------------------------------------------------------
	function GetSensors($type='',$sort_field=''){
		return $this->_FilterDevices('sensor',$type,$sort_field);
	}

	//----------------------------------------------------------------------------------
	function GetCameras($type=''){
		$cameras = $this->_FilterDevices('camera',$type);
		//remove camera without urls
		foreach($cameras as $uid => $cam){
			if(!$cam['url']){
				unset($cameras[$uid]);
			}
		}
		// get additionnal cameras from conf
		if(isset($this->conf['cameras_urls'])){
			$i=0;
			foreach($this->conf['cameras_urls'] as $key=> $url){
				$i++;
				
				$cam=array();
				$cam['class']	='camera';
				$cam['type']	='cam_ip';
				$cam['url']		=$url;
				$cam['name']	=$this->conf['cameras_names'][$key] or $cam['name']="Camera $i";
				$cam['uid']		="conf_cam_$i";
				if(isset($this->conf['cameras_sizes'][$key])){
					list($cam['x'],$cam['y'])=explode('x',$this->conf['cameras_sizes'][$key]);
				}
				else{
					$cam['x']=320;
					$cam['y']=240;
				}
				$cameras[$cam['uid']]=$cam;
			}
		}
		//$this->Debug('cameras',$cameras);
		return $cameras;
	}

	//----------------------------------------------------------------------------------
	private function _FilterDevices($class='', $type='',$sort_field=''){
		$devices=$this->devices;
		if($class){
			$devices=$this->_ListDevicesIn($devices, $class, 'class');
		}
		if($type){
			$devices=$this->_ListDevicesIn($devices, $type, 'type');
		}
		if($sort_field){
			$devices=$this->_array_orderby($devices,$sort_field,SORT_ASC,'name','SORT_ASC');
		}
		return $devices;
	}
	
	//----------------------------------------------------------------------------------
	private function _ListDevicesIn($devices, $value, $type='class'){
		$out=array();
		foreach($devices as $uid => $device){
			if($device[$type]==$value){
				$out[$uid]=$device;
			}
		}
		return $out;
	}


	//----------------------------------------------------------------------------------
	function RegisterDevice($row,$suffix=''){
		$row['class']	or $row['class']	='undef';
		$row['type']	or $row['type']		='undef';
		$row['uid'] 	or $row['uid']		=$this->MakeUniqueId($row,$suffix);

		$row=$this->FormatState($row);
		$row=$this->FormatValue($row);
		
		$row['img_type']=$row['type'];
		if(strlen($row['state'])){
			$row['img_type'] .='_'.$row['state'];
		}

		$this->devices[$row['uid']]			=$row;
	}

	//----------------------------------------------------------------------------------
	function FormatState($row,$raw_field=''){
		//format State
		if($raw_field){
			$val=$row['raw'][$raw_field];
		}
		else{
			$val=$row['raw_value1'];
		}
		if(!$row['state'] and isset($this->vars['states'][$row['class']][$row['type']][$val])){
			$row['state']=$this->vars['states'][$row['class']][$row['type']][$val];
		}
		if(!$row['state'] and $row['type']=='shutter'){
			//is this true or the inverse?
			if($val >0) 	$row['state']='on';
			if($val ==0)	$row['state']='off';
		}
		return $row;
	}
	//----------------------------------------------------------------------------------
	function FormatValue($row,$raw_field=''){
		//format State
		if($raw_field){
			$val=$row['raw'][$raw_field];
		}
		else{
			$val=$row['value'];
		}
		//make Value
		if($row['type']=='dimmer'){
			//scale the value from 0 to 100
			if(isset($this->vars['set']['dimmer']['max']) and $this->vars['set']['dimmer']['max'] !=100){
				$row['value']=round($row['value'] / $this->vars['set']['dimmer']['max'] * 100);
			}
			//deduce state from value
			if(!$row['state']){
				$row['state']='on';
				if($row['value'] < 1){
					$row['state']='off';
				}
			}
		}

		return $row;
	}

	//----------------------------------------------------------------------------------
	function FormatResults($rows,$type='',$sorted=1){
		$out=array();
		$i=0;
		foreach($rows as $row){
			$formated=$this->FormatRow($row,$type);

			$name=$formated['name'];
			$name=strtolower($name)."_$i";

			$out[$name]			=$formated;
			$out[$name]['raw']	=$row;
			$i++;
		}
		if($sorted){
			arsort($out);
		}
		return $out;
	}

	//----------------------------------------------------------------------------------
	function FormatRow($row,$type=''){
		$out=array();
		foreach($this->vars['fields'] as $k => $field){
			if(isset($row[$field])){
				$out[$k]=$row[$field];
			}
		}
		return $out;
	}

	//----------------------------------------------------------------------------------
	function MakeUniqueId($row,$suffix=''){
		$suffix and $suffix="_$suffix";
		$uid=$row['class'].'_'.$row['type'].'_'.$row['address'].$suffix;
		$uid=strtolower(preg_replace('#[^a-z0-9]#i','_',$uid));
		return $uid;
	}


	//----------------------------------------------------------------------------------
	function GetResult($formated=true){
		if(isset($this->vars['json']['result'])){
			$r= $this->api_response[$this->vars['json']['result']];
		}
		else{
			$r= $this->api_response;
		}
		if($formated){
			$r=$this->FormatResults($r);
		}
		return $r;
	}
	

	//----------------------------------------------------------------------------------
	function ApiFetch($command, $type='', $address='',$state=''){
		//states presets
		if(isset($this->vars['set'][$type][$state])){
			$state=$this->vars['set'][$type][$state];
		}
		//dim scale
		if($command=='set' and $type=='dim_level'){
			$min=$this->vars['set']['dimmer']['min'];
			$max=$this->vars['set']['dimmer']['max'];
			if($min and $state < $min ){
				$state=$min;
			}
			elseif($max and $max < 100 ){
				$state=round($state/100*$max);
			}
		}
		
		//- json_rpc v2 -----------------------------------------------
		if($this->vars['method']=="json_rpc2"){
			$out=$this->ApiFetchJson_rpc2($command,$type,$address,$state);
		}
		// json_get -------------------------------------------------
		elseif($this->vars['method']=="json_get"){
			$out=$this->ApiFetchJson_get($command,$type,$address,$state);
		}
		// json_mixed -------------------------------------------------
		elseif($this->vars['method']=="json_mixed"){
			$out=$this->ApiFetchJson_mixed($command,$type,$address,$state);
		}
		// custom -------------------------------------------------
		elseif($this->vars['method']=="custom"){
			$out=$this->ApiFetchCustom($command,$type,$address,$state);
		}
		
		$this->api_response=$out;
		return $this->api_status;
	}

	//---------------------------------------------------------------
	function ApiFetchCustom($command, $type='', $address='',$state=''){
		echo "Extend this method in your plugin class";
	}

	//---------------------------------------------------------------
	function ApiFetchJson_mixed($command, $type='', $address='',$state=''){
		$def=$this->vars['actions'][$command][$type];
		$url	=$this->vars['urls']['api'];
		$url .=$def['url'];

		$url=str_replace('{address}',	$address,	$url);
		$url=str_replace('{state}',		$state,		$url);

		$this->api_url=$url; // used only in debug

		if($def['method']=='get'){
			$context=null;
		}
		elseif($def['method']=='post'){
			$content=$def['content'];
			$content=str_replace('{address}',	$address,	$content);
			$content=str_replace('{state}',		$state,		$content);
			$options = array(
				'http' => array(
					'header'  => "Content-type: text/plain\r\n",
					'method'  => 'POST',
					'content' => $content
				),
			  );
			$context  = stream_context_create($options);
			$this->api_url	.=', http_method=POST, params= '.json_encode($options['http']); // used only in debug
		}
		$out = trim(file_get_contents($url, false, $context));
		if($def['result_type']=='text_state'){
			if($out==$state){
				$this->api_status=true;		
			}
			else{
				$this->api_status=false;				
			}
		}
		else{
			$out=json_decode($out,true);
		}

		//status
		if($this->vars['json']['status'] and isset($out[$this->vars['json']['status']])){
			if($out[$this->vars['json']['status']] == $this->vars['json_status']['ok']){
				$this->api_status=true;
			}
			elseif($out[$this->vars['json']['status']] == $this->vars['json_status']['err']){
				$this->api_status=false;
			}
		}
		elseif(is_array($out)){
			$this->api_status=true;	
		}
		return $out;
	}

	//---------------------------------------------------------------
	function ApiFetchJson_get($command, $type='', $address='',$state=''){
		$url	=$this->vars['urls']['api'];
		$url	.= $this->vars['actions'][$command][$type];
		$url=str_replace('{address}',	$address,	$url);
		$url=str_replace('{state}',		$state,		$url);
		$this->api_url=$url;

		$out=file_get_contents($url);			
		$out=json_decode($out,true);
	
		//status
		if($this->vars['json']['status'] and isset($out[$this->vars['json']['status']])){
			if($out[$this->vars['json']['status']] == $this->vars['json_status']['ok']){
				$this->api_status=true;
			}
			elseif($out[$this->vars['json']['status']] == $this->vars['json_status']['err']){
				$this->api_status=false;
			}
		}
		elseif(is_array($out)){
			$this->api_status=true;	
		}
		return $out;
	}

	//---------------------------------------------------------------
	function ApiFetchJson_rpc2($command, $type='', $address='',$state=''){

		$method=$this->vars['actions'][$command][$type]['method'];
		$params=$this->vars['actions'][$command][$type]['params'];

		if(is_array($params)){
			foreach($params as $k => $v){
				$params[$k]=str_replace('{address}',$address,	$params[$k]);
				$params[$k]=str_replace('{state}',	$state,		$params[$k]);
				//cast to int because Domiga is SO sensitive
				if($params[$k]==$address and preg_match('#^[0-9]+$#',$address)  ){
					$params[$k]=(int) $address;
				}
				if($params[$k]==$state and preg_match('#^[0-9]+$#',$state)  ){
					$params[$k]=(int) $state;
				}
			}
		}
		else{
			$params=array();
		}
		$this->api_url=$this->vars['urls']['api'].', method='.$method.', params= '.json_encode($params); // used only in debug
		$result=$this->o_jsonrpc->execute($method,$params);
		if(is_array($result)){
			$out=$result;
			$this->api_status=true;
		}
		else{
			$this->api_status=false;					
		}
		return $out;
	}



	//---------------------------------------------------------------
	//http://php.net/manual/en/function.array-multisort.php#100534
	// ie _array_orderby($data, 'volume', SORT_DESC, 'edition', SORT_ASC);
	private function _array_orderby(){
		$args = func_get_args();
		$data = array_shift($args);
		foreach ($args as $n => $field) {
			if (is_string($field)) {
				$tmp = array();
				foreach ($data as $key => $row)
					$tmp[$key] = $row[$field];
				$args[$n] = $tmp;
				}
		}
		$args[] = &$data;
		call_user_func_array('array_multisort', $args);
		return array_pop($args);
	}


} 
?>