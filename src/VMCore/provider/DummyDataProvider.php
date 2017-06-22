<?php

namespace VMAuthentication\provider;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use VMAuthentication\VMAuthentication;
class DummyDataProvider implements DataProvider{
	/** @var VMAuthentication */
	protected $plugin;
	public function __construct(VMAuthentication $plugin){
		$this->plugin = $plugin;
	}
	public function getPlayer(IPlayer $player){
		return null;
	}
	public function isPlayerRegistered(IPlayer $player){
		return false;
	}
	public function registerPlayer(IPlayer $player, $hash){
		return null;
	}
	public function unregisterPlayer(IPlayer $player){
	}
	public function savePlayer(IPlayer $player, array $config){
	}
	public function updatePlayer(IPlayer $player, $lastIP = null, $ip = null, $loginDate = null, $cid = null, $skinhash = null, $pin = null){
	}
	public function close(){
	}
}

