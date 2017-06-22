<?php

namespace VMAuthentication\task;
use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use VMAuthentication\VMAuthentication;
class ShowMessageTask extends PluginTask{
	/** @var Player[] */
	private $playerList = [];
	public function __construct(VMAuthentication $plugin){
		parent::__construct($plugin);
	}
	/**
	 * @return VMAuthentication
	 */
	public function getPlugin(){
		return $this->owner;
	}
	public function addPlayer(Player $player){
		$this->playerList[$player->getUniqueId()->toString()] = $player;
	}
	public function removePlayer(Player $player){
	if (null !== $player->getUniqueId()){
		unset($this->playerList[$player->getUniqueId()->toString()]);
		}
	}
	public function onRun($currentTick){
		$plugin = $this->getPlugin();
		if($plugin->isDisabled()){
			return;
		}
		foreach($this->playerList as $player){
			if($player==null){
				continue;
			}
			$player->sendPopup(TextFormat::ITALIC . TextFormat::GREEN . $this->getPlugin()->getMessage("vmjoin.popup.showmessage"));
		}
	}
}


