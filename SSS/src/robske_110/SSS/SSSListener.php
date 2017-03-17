<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\SSS;

use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\tile\Sign;

class SSSListener implements Listener{
	private $main;
	private $server;
	
	public function __construct(SignServerStats $main){
		$this->main = $main;
		$this->server = $main->getServer();
	}
	
	public function onBreak(BlockBreakEvent $event){ 
		$block = $event->getBlock();
		$levelName = $event->getPlayer()->getLevel()->getFolderName();
		if($this->main->doesSignExist($block, $levelName)){
			if($this->main->isAdmin($event->getPlayer())){ 
				if($this->main->removeSign($block, $levelName)){
					$event->getPlayer()->sendMessage("[SSS] Sign sucessfully deleted!");
				}else{
					$this->server->broadcast("CRITICAL/r005_FAIL::removeSign [Additional Info: removeSign() has returned false]", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
				}
				$this->main->recalcdRSvar();
			}else{
				$event->getPlayer()->sendMessage("[SSS] No, you are not allowed to do that!");
				$event->setCancelled();
			}
		}
	}
	
	public function signUpdate(SignChangeEvent $event){
		$block = $event->getBlock();
		$sign = $event->getPlayer()->getLevel()->getTile($block);
		if(!($sign instanceof Sign)){
			return true;
		}
		$sign = $event->getLines();
		if($sign[0]=='[SSS]'){
			if($this->main->isAdmin($event->getPlayer())){
				if(!empty($sign[1])){
					if(!empty($sign[2])){
						$levelName = $block->getLevel()->getFolderName();
						$this->main->addSign($sign[1], $sign[2], $block, $levelName);
						$this->main->recalcdRSvar();
						$event->setLines($this->main-calcSign([$sign[1], $sign[2]]));
						$event->getPlayer()->sendMessage("[SSS] The ServerStats Sign for the IP '".$sign[1]."' Port '".$sign[2]."' is set up correctly!");
					}else{
						$event->getPlayer()->sendMessage("[SSS] PORT_MISSING (LINE3)");
						$this->server->broadcast("r003_PORT_MISSING", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
						$event->setLine(0,"[BROKEN]");
						return false;
					}
				}else{
					$event->getPlayer()->sendMessage("[SSS] IP_MISSING (LINE2)");
					$this->server->broadcast("r004_IP_MISSING", Server::BROADCAST_CHANNEL_ADMINISTRATIVE);
					$event->setLine(0,"[BROKEN]");
					return false;
				}
			}else{
				$event->getPlayer()->sendMessage("[SSS] No, you are not allowed to do that!");
				$event->setLine(0,"[BLOCKED]");
				return false;
			}
		}
		return true;
	}
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!