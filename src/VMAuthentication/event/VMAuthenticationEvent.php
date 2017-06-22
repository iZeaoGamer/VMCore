<?php

namespace VMAuthentication\event;
use pocketmine\event\plugin\PluginEvent;
use VMAuthentication\VMAuthentication;
abstract class VMAuthenticationEvent extends PluginEvent{
	/**
	 * @param SimpleAuth $plugin
	 */
	public function __construct(VMAuthentication $plugin){
		parent::__construct($plugin);
	}
}
