<?php

/*
 * This plugin is made by Zeao. Enjoy with this plugin.
 * WARNING - This may not work on PHP 7. As always, I'm trying to update the
 * projects I make to php 7, that way, it should work with your server.
 * Enjoy.
 * 
 */
        
namespace SimpleAuth;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\OfflinePlayer;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use SimpleAuth\event\PlayerAuthenticateEvent;
use SimpleAuth\event\PlayerDeauthenticateEvent;
use SimpleAuth\event\PlayerRegisterEvent;
use SimpleAuth\event\PlayerUnregisterEvent;
use SimpleAuth\provider\DataProvider;
use SimpleAuth\provider\DummyDataProvider;
use SimpleAuth\provider\MySQLDataProvider;
use SimpleAuth\provider\SQLite3DataProvider;
use SimpleAuth\provider\YAMLDataProvider;
use SimpleAuth\task\ShowMessageTask;
class SimpleAuth extends PluginBase{
	/** @var PermissionAttachment[] */
	protected $needAuth = [];
	/** @var EventListener */
	protected $listener;
	/** @var DataProvider */
	protected $provider;
	protected $blockPlayers = 6;
	protected $blockSessions = [];
    /** @var string[] */
    protected $vmmessages = [];
    protected $messageTask = null;
    private $vmantihack = [];
    private $purePerms;
	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isPlayerAuthenticated(Player $player){
		return !isset($this->needAuth[spl_object_hash($player)]);
	}
	/**
	 * @api
	 *
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function isPlayerRegistered(IPlayer $player){
		return $this->provider->isPlayerRegistered($player);
	}
	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function authenticatePlayer(Player $player){
		if($this->isPlayerAuthenticated($player)){
			return true;
		}
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerAuthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}
        if (isset($this->needAuth[spl_object_hash($player)])) {
            $attachment = $this->needAuth[spl_object_hash($player)];
            $player->removeAttachment($attachment);
            unset($this->needAuth[spl_object_hash($player)]);
        }
        $this->provider->updatePlayer($player, $player->getUniqueId()->toString(), $player->getAddress(), time());
        $player->recalculatePermissions();
        $player->sendMessage(TextFormat::GREEN . $this->getMessage("login.success"));
		$this->getMessageTask()->removePlayer($player);
		unset($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]);
		return true;
	}
	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function deauthenticatePlayer(Player $player){
		if(!$this->isPlayerAuthenticated($player)){
			return true;
		}
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerDeauthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}
		$attachment = $player->addAttachment($this);
		$this->removePermissions($attachment);
		$this->needAuth[spl_object_hash($player)] = $attachment;
		$this->sendAuthenticateMessage($player);
		$this->getMessageTask()->addPlayer($player);
		return true;
	}
	public function tryAuthenticatePlayer(Player $player){
		if($this->blockPlayers <= 0 and $this->isPlayerAuthenticated($player)){
			return;
		}
		if(count($this->blockSessions) > 2048){
			$this->blockSessions = [];
		}
		if(!isset($this->blockSessions[$player->getAddress()])){
			$this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] = 1;
		}else{
			$this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]++;
		}
		if($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] > $this->blockPlayers){
			$player->kick($this->getMessage("login.error.block"), true);
			$this->getServer()->getNetwork()->blockAddress($player->getAddress(), 600);
		}
	}
    /**
     * @api
     *
     * @param IPlayer $player
     * @param string  $password
     *
     * @return bool
     */
    public function registerPlayer(IPlayer $player, $password) {
        if (!$this->isPlayerRegistered($player)) {
            $this->getServer()->getPluginManager()->callEvent($ev = new PlayerRegisterEvent($this, $player));
            if ($ev->isCancelled()) {
                return false;
            }
            $this->provider->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
            if (!$this->antihack["enabled"])
                return true;
            $pin = mt_rand(1000, 9999);
            $this->provider->updatePlayer($player, $player->getUniqueId()->toString(), $player->getAddress(), time(), $player->getClientId(), hash("md5", $player->getSkinData()), $pin);
            $player->sendMessage(TEXTFORMAT::AQUA . $this->antihack["pinregister"] . TEXTFORMAT::WHITE . $pin);
            return true;
        }
        return false;
    }
	/**
	 * @api
	 *
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function unregisterPlayer(IPlayer $player){
		if($this->isPlayerRegistered($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new PlayerUnregisterEvent($this, $player));
			if($ev->isCancelled()){
				return false;
			}
			$this->provider->unregisterPlayer($player);
		}
		return true;
	}
	/**
	 * @api
	 *
	 * @param DataProvider $provider
	 */
	public function setDataProvider(DataProvider $provider){
		$this->provider = $provider;
	}
	/**
	 * @api
	 *
	 * @return DataProvider
	 */
	public function getDataProvider(){
		return $this->provider;
	}
	/* -------------------------- Non-API part -------------------------- */
	public function closePlayer(Player $player){
		unset($this->needAuth[spl_object_hash($player)]);
		$this->getMessageTask()->removePlayer($player);
	}
	public function sendAuthenticateMessage(Player $player){
		$config = $this->provider->getPlayer($player);
		$player->sendMessage(TextFormat::ITALIC . TextFormat::GREEN . $this->getMessage("join.message1"));
		$player->sendMessage(TextFormat::ITALIC . TextFormat::GREEN . $this->getMessage("join.message2"));
		if($config === null){
			$player->sendMessage(TextFormat::RED . $this->getMessage("join.register"));
		}else{
			$player->sendMessage(TextFormat::RED . $this->getMessage("join.login"));
		}
	}
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        switch ($command->getName()) {
            case "login":
                if ($sender instanceof Player) {
                    if ($this->isPlayerAuthenticated($sender)) {
                        if ($this->antihack["enabled"]) {
                            $pin = mt_rand(1000, 9999);
                            $this->provider->updatePlayer($sender, $sender->getUniqueId()->toString(), $sender->getAddress(), time(), $sender->getClientId(), hash("md5", $sender->getSkinData()), $pin);
                            $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pinchanged"] . TEXTFORMAT::WHITE . $pin);
                        }
                        return true;
                    }
                    if (!$this->isPlayerRegistered($sender) or ( $data = $this->provider->getPlayer($sender)) === null) {
                        $sender->sendMessage(TextFormat::RED . $this->getMessage("login.error.registered"));
                        return true;
                    }
                    $password = $args[0];
                    $data = $this->provider->getPlayer($sender);
                    $superadmin = false;
                    if (isset($this->purePerms)) {
                        $currentgroup = $this->purePerms->getUserDataMgr()->getGroup($sender);
                        $currentgroupName = $currentgroup->getName();
                        $superadminranks = $this->purePerms->getConfigValue("superadmin-ranks");
                        $superadmin = in_array($currentgroupName, $superadminranks);
                    }
                    $protectsuperadmins = $this->vmantihack["protectsuperadmins"];
                    $protectops = $this->vmantihack["protectops"];
                    $checkthisrank = ($protectops && $sender->isOp()) || (!$protectsuperadmins) || ($protectsuperadmins && $superadmin);
                    $concordance = 0;
                    if ($sender->getAddress() == $data["ip"])
                        $concordance++;
                    if ($sender->getClientId() == $data["cid"])
                        $concordance++;
                    if (hash("md5", $sender->getSkinData()) == $data["skinhash"])
                        $concordance++;
                    if ($checkthisrank && isset($data["pin"]) && ($this->vmantihack["enabled"])) {
                        $this->getLogger()->debug("Current IP: " . $sender->getAddress() . " - Saved IP: " . $data["ip"] . "\n");
                        $this->getLogger()->debug("Current SKIN: " . (hash("md5", $sender->getSkinData())) . " - Saved Skin: " . $data["skinhash"] . "\n");
                        if ($concordance < ($this->vmantihack["threat"]) && (!(isset($args[1]) && ($data["pin"] == $args[1])))) {
                            $this->tryAuthenticatePlayer($sender);
                            $this->getLogger()->info($this->vmantihack["hackwarning"] . $sender->getName());
                            $sender->sendMessage(TextFormat::RED . ($this->antihack["pinhelp1"]));
                            $sender->sendMessage(TextFormat::GOLD . ($this->antihack["pinhelp2"]));
                            $sender->sendMessage(TextFormat::RED . ($this->antihack["pinhelp3"]));
                            $sender->sendMessage(TextFormat::LIGHT_PURPLE . ($this->antihack["pinhelp4"]));
                            return true;
                        }
                    }
                    if (hash_equals($data["hash"], $this->hash(strtolower($sender->getName()), $password)) and $this->authenticatePlayer($sender)) {
                        if (!$this->antihack["enabled"] || !$checkthisrank)
                            return true;
                        if (!isset($data["pin"])) {
                            $pin = mt_rand(1000, 9999);
                            $this->provider->updatePlayer($sender, $sender->getUniqueId()->toString(), $sender->getAddress(), time(), $sender->getClientId(), hash("md5", $sender->getSkinData()), $pin);
                            $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pintext"] . TEXTFORMAT::WHITE . $pin);
                            return true;
                        }
                        if ($concordance < ($this->antihack["threat"])) {
                            $pin = mt_rand(1000, 9999);
                            $this->provider->updatePlayer($sender, $sender->getUniqueId()->toString(), $sender->getAddress(), time(), $sender->getClientId(), hash("md5", $sender->getSkinData()), $pin);
                            $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pinchanged"] . TEXTFORMAT::WHITE . $pin);
                        } else {
                            //ALL GOOD...
                            $this->provider->updatePlayer($sender, $sender->getUniqueId()->toString(), $sender->getAddress(), time(), $sender->getClientId(), hash("md5", $sender->getSkinData()), null);
                            $data = $this->provider->getPlayer($sender);
                            $pin = $data["pin"];
                            $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pinunchanged"] . TEXTFORMAT::WHITE . $pin);
                        }
                        return true;
                    } else {
                        $this->tryAuthenticatePlayer($sender);
                        $sender->sendMessage(TextFormat::RED . $this->getMessage("login.error.password"));
                        return true;
                    }
                } else {//Console reset Security Checks for a player
                    if (!isset($args[0])) {
                        $sender->sendMessage($this->antihack["consolehelp"]);
                        return true;
                    }
                    $player = $this->getServer()->getPlayer($args[0]);
                    if ($player instanceof Player) {
                        $this->provider->updatePlayer($player, $player->getUniqueId()->toString(), $player->getAddress(), time(), $player->getClientId(), hash("md5", $player->getSkinData()), 0);
                        $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pinreset"] . $player->getName());
                        return true;
                    }
                    $player = $this->getServer()->getOfflinePlayer($args[0]);
                    if ($player instanceof OfflinePlayer) {
                        $this->provider->updatePlayer($player, null, null, null, null, null, 0);
                        $sender->sendMessage(TEXTFORMAT::LIGHT_PURPLE . $this->antihack["pinreset"] . $player->getName());
                        return true;
                    }
                    $sender->sendMessage(TextFormat::RED . $this->antihack["noplayer"]);
                    return true;
                }
                break;
            case "register":
                if ($sender instanceof Player) {
                    if ($this->isPlayerRegistered($sender)) {
                        $sender->sendMessage(TextFormat::RED . $this->getMessage("register.error.registered"));
						return true;
					}
					$password = implode(" ", $args);
					if(strlen($password) < $this->getConfig()->get("minPasswordLength")){
						$sender->sendMessage($this->getMessage("register.error.password"));
						return true;
					}
					if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$sender->sendMessage(TextFormat::RED . $this->getMessage("register.error.general"));
						return true;
					}
				}else{
					$sender->sendMessage(TextFormat::RED . "This command only works in-game.");
					return true;
				}
				break;
		}
		return false;
	}
	private function parseMessages(array $messages){
		$result = [];
		foreach($messages as $key => $value){
			if(is_array($value)){
				foreach($this->parseMessages($value) as $k => $v){
					$result[$key . "." . $k] = $v;
				}
			}else{
				$result[$key] = $value;
			}
		}
		return $result;
	}
	public function getMessage($key){
		return isset($this->messages[$key]) ? $this->messages[$key] : $key;
	}
	public function onEnable(){
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource("messages.yml", false);
		$messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();
		$this->messages = $this->parseMessages($messages);
        $this->saveResource("antihack.yml", false);
        $this->antihack = (new Config($this->getDataFolder() . "antihack.yml"))->getAll();
        $registerCommand = $this->getCommand("register");
        $registerCommand->setUsage($this->getMessage("register.usage"));
        $registerCommand->setDescription($this->getMessage("register.description"));
        $registerCommand->setPermissionMessage($this->getMessage("register.permission"));
		$loginCommand = $this->getCommand("login");
		$loginCommand->setUsage($this->getMessage("login.usage"));
		$loginCommand->setDescription($this->getMessage("login.description"));
		$loginCommand->setPermissionMessage($this->getMessage("login.permission"));
		$this->blockPlayers = (int) $this->getConfig()->get("blockAfterFail", 6);
		$provider = $this->getConfig()->get("dataProvider");
		unset($this->provider);
		switch(strtolower($provider)){
			case "yaml":
				$this->getLogger()->debug("Using YAML data provider");
				$provider = new YAMLDataProvider($this);
				break;
			case "sqlite3":
				$this->getLogger()->debug("Using SQLite3 data provider");
				$provider = new SQLite3DataProvider($this);
				break;
			case "mysql":
				$this->getLogger()->debug("Using MySQL data provider");
				$provider = new MySQLDataProvider($this);
				break;
			case "none":
			default:
				$provider = new DummyDataProvider($this);
				break;
		}
		if(!isset($this->provider) or !($this->provider instanceof DataProvider)){ //Fix for getting a Dummy provider
			$this->provider = $provider;
		}
		$this->listener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->deauthenticatePlayer($player);
		}
        $this->purePerms = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
        if (isset($this->purePerms)) {
            $this->getLogger()->info("Connected to PurePerms succesfully");
        } else {
            $this->getLogger()->info("Could Not Find PurePerms. Did you install it?");
        }
        $this->getLogger()->info("Everything loaded! Great!");
    }
	public function onDisable(){
		$this->getServer()->getPluginManager();
		$this->provider->close();
		$this->messageTask = null;
		$this->blockSessions = [];
	}
	public static function orderPermissionsCallback($perm1, $perm2){
		if(self::isChild($perm1, $perm2)){
			return -1;
		}elseif(self::isChild($perm2, $perm1)){
			return 1;
		}else{
			return 0;
		}
	}
	public static function isChild($perm, $name){
		$perm = explode(".", $perm);
		$name = explode(".", $name);
		foreach($perm as $k => $component){
			if(!isset($name[$k])){
				return false;
			}elseif($name[$k] !== $component){
				return false;
			}
		}
		return true;
	}
	protected function removePermissions(PermissionAttachment $attachment){
		$permissions = [];
		foreach($this->getServer()->getPluginManager()->getPermissions() as $permission){
			$permissions[$permission->getName()] = false;
		}
		$permissions["pocketmine.command.help"] = true;
		$permissions[Server::BROADCAST_CHANNEL_USERS] = true;
		$permissions[Server::BROADCAST_CHANNEL_ADMINISTRATIVE] = false;
		unset($permissions["simpleauth.chat"]);
		unset($permissions["simpleauth.move"]);
		unset($permissions["simpleauth.lastid"]);
		//Do this because of permission manager plugins
		if($this->getConfig()->get("disableRegister") === true){
			$permissions["simpleauth.command.register"] = false;
		}else{
			$permissions["simpleauth.command.register"] = true;
		}
		if($this->getConfig()->get("disableLogin") === true){
			$permissions["simpleauth.command.register"] = false;
		}else{
			$permissions["simpleauth.command.login"] = true;
		}
		uksort($permissions, [SimpleAuth::class, "orderPermissionsCallback"]); //Set them in the correct order
		$attachment->setPermissions($permissions);
	}
	/**
	 *
	 * @param string $salt
	 * @param string $password
	 *
	 * @return string[128] hex 512-bit hash
	 */
	private function hash($salt, $password){
		return bin2hex(hash("Zeao", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}
	/**
	 * @return ShowMessageTask
	 */
	protected function getMessageTask(){
		if($this->messageTask === null){
			$this->messageTask = new ShowMessageTask($this);
			$this->getServer()->getScheduler()->scheduleRepeatingTask($this->messageTask, 10);
		}
		return $this->messageTask;
                
                }
}
                
