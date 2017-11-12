# LinkPlayerCount
A PocketMine plugin depending on SignServerStats which displays the playercount of the server it is installed on combined with other specified servers.

## Usage:
The servers can be added in servers.yml manually or can be simply added/removed by anyone with the permission `StatusList.manageList` through the commands `linkplayercount add <ip> [port]` / `linkplayercount rem <ip> [port]`.

## API:
**This plugin provides an API which can temporarily or permanently add or remove Servers.**

_You should always check if your plugin is compatible with the version of LinkPlayerCount present on the current server with the help of the isCompatible function_

Example:
```php
/** @var robske_110\LPC\LinkPlayerCount $linkPlayerCount */
if(!$linkPlayerCount->isCompatible("1.0.0")){
   	$this->getLogger()->critical("Your version of LinkPlayerCount is not compatible with this plugin");
	$this->getServer()->getPluginManager()->disablePlugin($this);
	return;
}
```
#### An example of API usage:
Adding the server `someip.com:1234` temporarily:
```php
/** @var $lpc robske_110\LPC\LinkPlayerCount */
$lpc->addServer("someip.com", 1234, $sl->getSSS(), false); /* Last argument is whether to save the server to disk or not */
```

#### For further documentation of API functions check the source code.