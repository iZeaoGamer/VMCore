<?php

namespace VMAuthentication\event;
use pocketmine\event\Cancellable;
use pocketmine\Player;
use VMAuthentication\VMAuthentication;
class PlayerDeauthenticateEvent extends \VMAuthenticationEvent implements Cancellable{
	public static $handlerList = null;
	/** @var Player */
	private $player;
	/**
	 * @param VMAuthentication $plugin
	 * @param Player     $player
	 */
	public function __construct(\VMAuthentication $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}
	/**
	 * @return Player
	 */
	public function getPlayer(){
		return $this->player;
	}
}

