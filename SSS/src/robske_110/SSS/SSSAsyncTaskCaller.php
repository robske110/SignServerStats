<?php
/*          _         _                  __ __  ___  
           | |       | |                /_ /_ |/ _ \ 
  _ __ ___ | |__  ___| | _____           | || | | | |
 | '__/ _ \| '_ \/ __| |/ / _ \          | || | | | |
 | | | (_) | |_) \__ \   <  __/  ______  | || | |_| |
 |_|  \___/|_.__/|___/_|\_\___| |______| |_||_|\___/                      
*/
namespace robske_110\SSS;

use pocketmine\scheduler\PluginTask;

class SSSAsyncTaskCaller extends PluginTask{
	/** @var SignServerStats */
	private $SSS;
	
	public function __construct(SignServerStats $main){
		parent::__construct($main);
		$this->SSS = $main;
	}
	
	public function onRun(int $currentTick){
		if($this->SSS->isAllowedToStartAsyncTask()){
			$this->SSS->startAsyncTask($currentTick);
		}
	}
}
//Theory is when you know something, but it doesn"t work. Practice is when something works, but you don"t know why. Programmers combine theory and practice: Nothing works and they don"t know why!