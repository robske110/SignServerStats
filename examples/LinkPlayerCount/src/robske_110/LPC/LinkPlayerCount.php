<?php

namespace robske_110\LPC;

use robske_110\SSS\SignServerStats;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class LinkPlayerCount extends PluginBase{
	const SSS_API_VERSION = "1.1.0";
	const API_VERSION = "1.0.0";
	
	/** @var Config */
	private $cfg;
	/** @var Config */
	private $db;
	/** @var LinkPlayerCountManager */
	private $linkPlayerCountManager;
	/** @var array */
	private $ownedServers;
	
	public function onEnable(){
		@mkdir($this->getDataFolder());
		if(($sss = $this->getSSS()) !== null){
			if(!$sss->isCompatible(self::SSS_API_VERSION)){
				$newOld = version_compare(self::SSS_API_VERSION, SignServerStats::API_VERSION, ">") ? "old" : "new";
				$this->getLogger()->critical("Your version of SignServerStats is too ".$newOld." for this plugin.");
				$this->getServer()->getPluginManager()->disablePlugin($this);
				return;
			}
		}else{
			$this->getLogger()->critical("SignServerStats is required for this plugin. PM ignored my dependencies!");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->cfg = new Config($this->getDataFolder()."SSSconfig.yml", Config::YAML, []);
		if($this->cfg->get("ConfigVersion") != 1){
			$this->cfg->set('combine-max-slots', true);
			$this->cfg->set('ConfigVersion', 1);
		}
		$this->cfg->save();
		$this->linkPlayerCountManager = new LinkPlayerCountManager($this, (bool) $this->cfg->get("combine-max-slots"));
		$this->getServer()->getPluginManager()->registerEvents($this->linkPlayerCountManager, $this);
		$this->db = new Config($this->getDataFolder()."servers.yml", Config::YAML, []);
		foreach($this->db->getAll() as $server){
			$this->addServer($server[0], $server[1], $sss, false);
		}
	}
	
	/**
	 * @return null|SignServerStats
	 */
	public function getSSS(): ?SignServerStats{
		if(($sss = $this->getServer()->getPluginManager()->getPlugin("SignServerStats")) instanceof SignServerStats){
			return $sss;
		}else{
			$this->getLogger()->critical("Unexpected error: Trying to get SignServerStats plugin instance failed!");
			return null;
		}
	}
	
	/**
	 * This is for extension plugins to test if they are compatible with the version
	 * of LPC installed. Extensions should be disabled/disable any interfaces with this plugin if this returns false.
	 *
	 * @param string $apiVersion The API version your plugin was last tested on.
	 *
	 * @return bool Indicates whether your plugin is compatible.
	 */
	public function isCompatible(string $apiVersion): bool{
		$extensionApiVersion = explode(".", $apiVersion);
		$myApiVersion = explode(".", self::API_VERSION);
		if($extensionApiVersion[0] !== $myApiVersion[0]){
			return false;
		}
		if($extensionApiVersion[1] > $myApiVersion[1]){
			return false;
		}
		return true;
	}
	
	/**
	 * @return LinkPlayerCountManager
	 */
	public function getLinkPlayerCountManager(): LinkPlayerCountManager{
		return $this->linkPlayerCountManager;
	}
	
	/**
	 * Adds a Server
	 *
	 * @param string		  $hostname
	 * @param int			  $port
	 * @param SignServerStats $sss
	 * @param bool			  $save Whether the server should be saved to disk and reloaded on next reboot or not.
	 *
	 * @return bool
	 */
	public function addServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->linkPlayerCountManager->addServer($hostname, $port)){
			if($sss->addServer($hostname, $port)){
				$this->ownedServers[$hostname."@".$port] = null;
			}
			if($save){
				$listServers = $this->db->getAll();
				$listServers[$hostname."@".$port] = [$hostname, $port];
				$this->db->setAll($listServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * Removes a Server
	 *
	 * @param string		  $hostname
	 * @param int			  $port
	 * @param SignServerStats $sss
	 * @param bool			  $save Whether the removal should be saved to disk and also be gone on next reboot or not.
	 *
	 * @return bool
	 */
	public function remServer(string $hostname, int $port, SignServerStats $sss, bool $save = true): bool{
		if($this->linkPlayerCountManager->remServer($hostname, $port)){
			if(array_key_exists($hostname."@".$port, $this->ownedServers)){
				$sss->removeServer($hostname, $port);
				unset($this->ownedServers[$hostname."@".$port]);
			}
			if($save){
				$listServers = $this->db->getAll();
				unset($listServers[$hostname."@".$port]);
				$this->db->setAll($listServers);
				$this->db->save(true);
			}
			return true;
		}else{
			return false;
		}
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
		if(isset($args[0])){
			$hostname = $args[0];
			$port = 19132;
			if(isset($args[1])){
				if(is_numeric($args[1])){
					$port = $args[1];
				}else{
					return false;
				}
			}
		}else{
			return false;
		}
		if(($sss = $this->getSSS()) === null){
			$sender->sendMessage("Error. Check console.");
			return true;
		}
		switch($command->getName()){
			case "linkplayercount add":
				if($this->addServer($hostname, $port, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully added the server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN."."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED.
						" is already addded!"
					);
				}
				return true;
				break;
			case "linkplayercount rem":
				if($this->remServer($hostname, $port, $sss)){
					$sender->sendMessage(
						TF::GREEN."Successfully removed the server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN."."
					);
				}else{
					$sender->sendMessage(
						TF::DARK_RED."The server ".
						TF::DARK_GRAY.$hostname.TF::GRAY.":".TF::DARK_GRAY.$port.TF::DARK_RED.
						" is not addded!"
					);
				}
				return true;
				break;
		}
		return false;
	}
}