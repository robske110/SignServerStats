<?php
$repoPath = "/home/travis/build/robske110/SSS/";
$plugins = ["SignServerStats" => ["./SSS/", true], "StatusList" => ["./examples/StatusList/", true], "WarnOffline" => ["./examples/WarnOffline", true], "DumpInfo" => ["./examples/DumpInfo.php" , false]];

exec("cd ".$repoPath);
foreach($plugins as $info){
	if($info[1]){
		exec("cp -rf ".$info[0]." ./PocketMine-MP/plugins/");
		echo("exec: "."cp -rf ".$info[0]." ./PocketMine-MP/plugins/");
	}else{
		exec("cp -f ".$info[0]." ./PocketMine-MP/plugins/");
		echo("exec: "."cp -f ".$info[0]." ./PocketMine-MP/plugins/");
	}
}
exec("cd PocketMine-MP");

$server = proc_open(PHP_BINARY . " src/pocketmine/PocketMine.php --no-wizard --disable-readline", [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"]
], $pipes);

function sendCommand(string $cmd, $pipes){
	fwrite($pipes[0], $cmd."\n";
}

sendCommand("version", $pipes);
foreach($plugins as $pluginName => $info){
	if($info[1]){
		sendCommand("makeplugin ".$plugin, $pipes);
	}
}
sendCommand("stop", $pipes);

while(!feof($pipes[1])){
	echo(fgets($pipes[1]));
}

fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);

echo("\n\nReturn value: ". proc_close($server) ."\n");

$exit = 0;
foreach($plugins as $pluginName => $info){
	if(!$info[1]){
		continue;
	}
	if(count(glob("plugins/DevTools/".$pluginName."*.phar")) === 0){
		echo("Failed to create ".$pluginName." phar!\n");
		$exit = 1;
	}else{
		echo($pluginName." phar created!\n");
	}
}
exit($exit);