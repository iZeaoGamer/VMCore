<?php

namespace VMAuthentication\provider;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use VMAuthentication\VMAuthentication;
class YAMLDataProvider implements DataProvider{
	/** @var VMAuthentication */
	protected $plugin;
	public function __construct(SimpleAuth $plugin){
		$this->plugin = $plugin;
		if(!file_exists($this->plugin->getDataFolder() . "players/")){
			@mkdir($this->plugin->getDataFolder() . "players/");
		}
	}
	public function getPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		if($name === ""){
			return null;
		}
		$path = $this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml";
		if(!file_exists($path)){
			return null;
		}else{
			$config = new Config($path, Config::YAML);
			return $config->getAll();
		}
	}
	public function isPlayerRegistered(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		return file_exists($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}
	public function unregisterPlayer(IPlayer $player){
		$name = trim(strtolower($player->getName()));
		@unlink($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml");
	}
	public function registerPlayer(IPlayer $player, $hash){
		$name = trim(strtolower($player->getName()));
		@mkdir($this->plugin->getDataFolder() . "players/" . $name{0} . "/");
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->set("registerdate", time());
		$data->set("logindate", time());
		$data->set("ip", $player->getAddress());
		$data->set("hash", $hash);
                $data->set("cid", $player->getClientId());
                $data->set("skinhash", hash("md5", $player->getSkinData()));
                $data->set("pin", null);
		$data->save();
		return $data->getAll();
	}
	public function savePlayer(IPlayer $player, array $config){
		$name = trim(strtolower($player->getName()));
		$data = new Config($this->plugin->getDataFolder() . "players/" . $name{0} . "/$name.yml", Config::YAML);
		$data->setAll($config);
		$data->save();
	}
	public function updatePlayer(IPlayer $player, $lastIP = null, $ip = null, $loginDate = null, $cid = null, $skinhash = null, $pin = null){
		$data = $this->getPlayer($player);
		if($data !== null){
			if($ip !== null){
				$data["ip"] = $ip;
			}
                        if($lastIP !== null){
				$data["lastip"] = $lastIP;
			}
			if($loginDate !== null){
				$data["logindate"] = $loginDate;
			}
                        if($cid !== null){
				$data["cid"] = $cid;
			}
                        if($skinhash !== null){
				$data["skinhash"] = $skinhash;
			}
                        if($pin !== null){
				$data["pin"] = $pin;
			}
                        if(isset($pin) && $pin === 0){
				unset($data["pin"]);
			}
			$this->savePlayer($player, $data);
		}
	}
	public function close(){
	}
}
