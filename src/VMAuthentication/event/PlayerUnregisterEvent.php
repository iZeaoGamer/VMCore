<?php

namespace VMAuthentication\event;
use pocketmine\event\Cancellable;
use pocketmine\IPlayer;
use VMAuthentication\VMAuthentication;
class PlayerUnregisterEvent extends VMAuthenticationEvent implements Cancellable{
	public static $handlerList = null;
	/** @var IPlayer */
	private $player;
	/**
	 * @param VMAuthentication $plugin
	 * @param IPlayer    $player
	 */
	public function __construct(VMAuthentication $plugin, IPlayer $player){
		$this->player = $player;
		parent::__construct($plugin);
	}
	/**
	 * @return IPlayer
	 */
	public function getPlayer(){
		return $this->player;
	}
}

