<?php
$repoPath = "/home/travis/build/robske110/SignServerStats/";
$plugins = ["SignServerStats" => ["SSS/", true], "StatusList" => ["examples/StatusList/", true], "WarnOffline" => ["examples/WarnOffline", true], "LinkPlayerCount" => ["examples/LinkPlayerCount", true], "DumpInfo" => ["examples/DumpInfo.php" , false]];

foreach($plugins as $info){
	$info[0] = $repoPath.$info[0];
	if($info[1]){
		echo("exec: "."cp -rf ".$info[0]." ./plugins/\n");
		exec("cp -rf ".$info[0]." ./plugins/");
	}else{
		echo("exec: "."cp -f ".$info[0]." ./plugins/");
		exec("cp -f ".$info[0]." ./plugins/\n");
	}
}

$server = proc_open(PHP_BINARY . " src/pocketmine/PocketMine.php --no-wizard --disable-readline --settings.enable-dev-builds=true", [
	0 => ["pipe", "r"],
	1 => ["pipe", "w"],
	2 => ["pipe", "w"]
], $pipes);

function sendCommand(string $cmd, $pipes){
	fwrite($pipes[0], $cmd."\n");
}

sendCommand("version", $pipes);
foreach($plugins as $pluginName => $info){
	if($info[1]){
		sendCommand("makeplugin ".$pluginName, $pipes);
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
	if(count(glob("plugin_data/DevTools/".$pluginName."*.phar")) === 0){
		echo("Failed to create ".$pluginName." phar!\n");
		$exit = 1;
	}else{
		echo($pluginName." phar created!\n");
	}
}
exit($exit);