<?php

namespace VMAuthentication\task;
use pocketmine\scheduler\PluginTask;
use VMAuthentication\VMAuthentication;
class MySQLPingTask extends PluginTask{
	/** @var \mysqli */
	private $database;
	public function __construct(VMAuthentication $owner, \mysqli $database){
		parent::__construct($owner);
		$this->database = $database;
	}
	public function onRun($currentTick){
		$this->database->ping();
	}
}

