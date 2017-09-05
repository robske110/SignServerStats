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
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;
use pocketmine\block\BlockIds;

class SSSListener implements Listener{
	private $main;
	private $server;
	
	public function __construct(SignServerStats $main){
		$this->main = $main;
		$this->server = $main->getServer();
	}
	
	private function sendSSSmessage(Player $player, string $msg){
		$player->sendMessage(TF::GRAY."[SSS] ".$msg);
	}
	
	public function onBreak(BlockBreakEvent $event){ 
		$block = $event->getBlock();
		if($block->getId() !== BlockIds::WALL_SIGN && $block->getId() !== BlockIds::STANDING_SIGN){
			return;
		}
		$player = $event->getPlayer();
		$levelName = $player->getLevel()->getFolderName();
		$index = null;
		if($this->main->doesSignExist($block, $levelName, $index)){
			if($this->main->isAdmin($player)){
				if($this->main->internalRemoveSign($block, $levelName, $index)){
					$this->sendSSSmessage($player, TF::GREEN."Sign sucessfully deleted!");
				}else{
					$this->server->broadcast(
						TF::RED."CRITICAL/r003: removeSign() returned false. Has the sign already been removed?",
						Server::BROADCAST_CHANNEL_ADMINISTRATIVE
					);
				}
				$this->main->recalcdRSvar();
			}else{
				$this->sendSSSmessage($player, TF::RED."You are not allowed to do that!");
				$event->setCancelled();
			}
		}
	}
	
	public function onSignChange(SignChangeEvent $event){
		$block = $event->getBlock();
		$player = $event->getPlayer();
		$sign = $player->getLevel()->getTile($block);
		if(!($sign instanceof Sign)){
			return true;
		}
		$sign = $event->getLines();
		if($sign[0]=='[SSS]'){
			if(!$this->main->isAdmin($player)){
				$this->sendSSSmessage($player, TF::RED."You are not allowed to do that!");
				$event->setLine(0,"[BLOCKED]");
				return false;
			}
		}
		return true;
	}
	
	public function onSignTap(PlayerInteractEvent $event){
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			$block = $event->getBlock();
			$player = $event->getPlayer();
			$signTile = $player->getLevel()->getTile($block);
			if($signTile instanceof Sign){
				$sign = $signTile->getText();
				if($sign[0]=='[SSS]'){
					if($this->main->isAdmin($player)){
						if(!empty($sign[1])){
							if(!empty($sign[2])){
								if(is_numeric($sign[2])){
									$levelName = $block->getLevel()->getFolderName();
									$this->main->addSign($sign[1], $sign[2], $block, $levelName);
									$this->main->recalcdRSvar();
									$signTile->setText(...$this->main->calcSign([$sign[1], $sign[2]]));
									$this->sendSSSmessage(
										$player,
										TF::GREEN."The ServerStats Sign for the Server ".
										TF::DARK_GRAY.$sign[1].TF::GRAY.":".TF::DARK_GRAY.$sign[2].TF::GREEN.
										" has been set up correctly!"
									);
								}else{
									$this->sendSSSmessage($player, TF::RED."Port must be a number! (Line 3)");
									$signTile->setLine(0,"[BROKEN]");
								}
							}else{
								$this->sendSSSmessage($player, TF::RED."Port is missing! (Line 3)");
								$signTile->setLine(0,"[BROKEN]");
							}
						}else{
							$this->sendSSSmessage($player, TF::RED."[SSS] IP is missing. (Line 2)");
							$signTile->setLine(0,"[BROKEN]");
						}
					}else{
						$this->sendSSSmessage($player, TF::RED."You are not allowed to do that!");
					}
				}
			}
		}
	}		
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!