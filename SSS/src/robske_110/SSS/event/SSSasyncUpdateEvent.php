<?php
namespace robske_110\SSS\event;

use robske_110\SSS\SignServerStats;
use pocketmine\event\plugin\PluginEvent;

class SSSasyncUpdateEvent extends PluginEvent{
	/** @var int */
	private $lastUpdate;
	/** @var int */
	private $currUpdate;
	/** @var SignServerStats */
	private $sss;
	
	public static $handlerList = null;
	
	public function __construct(SignServerStats $sss, int $lastUpdate, int $currUpdate){
		parent::__construct($sss);
		$this->sss = $sss;
		$this->lastUpdate = $lastUpdate;
		$this->currUpdate = $currUpdate;
	}
	
	public function getSSS(): SignServerStats{
		return $this->sss;
	}
	
	public function getCurrUpdate(): int{
		return $this->currUpdate;
	}
	
	public function getLastUpdate(): int{
		return $this->lastUpdate;
	}
}