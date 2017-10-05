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
	
	/**
	 * @param Player $player
	 * @param string $msg
	 */
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
				if($sign[0] == '[SSS]'){
					$address = null;
					$port = null;
					if($this->main->isAdmin($player)){
						if(!empty($sign[1])){
							if(!empty($sign[2])){
								if($sign[1]{strlen($sign[1]) - 1} == "-"){
									if(!empty($sign[3])){
										if(is_numeric($sign[3])){
											$address = substr($sign[1], 0, strlen($sign[1]) - 1).$sign[2];
											$port = $sign[3];
										}else{
											$this->sendSSSmessage($player, TF::RED."Port must be a number! (Line 4)");
										}
									}else{
										$this->sendSSSmessage($player, TF::RED."Port is missing! (Line 4)");
									}
								}elseif(is_numeric($sign[2])){
									$address = $sign[1];
									$port = $sign[2];
								}else{
									$this->sendSSSmessage($player, TF::RED."Port must be a number! (Line 3)");
								}
							}else{
								$this->sendSSSmessage($player, TF::RED."Port is missing! (Line 3)");
							}
						}else{
							$this->sendSSSmessage($player, TF::RED."[SSS] IP is missing. (Line 2)");
						}
					}else{
						$this->sendSSSmessage($player, TF::RED."You are not allowed to do that!");
					}
					if($address === null || $port === null){
						$signTile->setLine(0,"[BROKEN]");
					}else{
						$levelName = $block->getLevel()->getFolderName();
						$this->main->addSign($address, $port, $block, $levelName);
						$this->main->recalcdRSvar();
						$signTile->setText(...$this->main->calcSign([$address, $port]));
						$this->sendSSSmessage(
							$player,
							TF::GREEN."The ServerStats Sign for the Server ".
							TF::DARK_GRAY.$address.TF::GRAY.":".TF::DARK_GRAY.$port.TF::GREEN.
							" has been set up correctly!"
						);
					}
				}
			}
		}
	}		
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!