# StatusList
A PocketMine plugin depending on SignServerStats which displays a list of servers in chat/console with their online status and player stats associated with them.

## Usage:
Anyone with the permission `StatusList.seeList` can use the command `statuslist show` to prompt a response which will look like this:
```
All StatusList servers:
robskebueba.no-ip.biz:3114 | ONLINE (5/50)
pe.cuboss.net:19132 | OFFLINE
FallenTech.tk:19132 | ONLINE
ecpehub.net:19132 | LOADING
Total: 4 Online: 2 Offline: 1 Last Refresh: 13.7s ago
```

Anyone with the permission `StatusList.manageList` can use the command `statuslist add <ip> [port]` or `statuslist add <ip> [port]` to add/remove servers.

## API:
**This plugin provides an API which can temporarily or permanently add or remove Servers to/from the StatusList and get online&playercount information from all StatusServers**

_You should always check if your plugin is compatible with the version of StatusList present on the current server with the help of the isCompatible function_

Example:
```php
/** @var robske_110\SL\StatusList $statusList */
if(!$statusList->isCompatible("1.0.0")){
   	$this->getLogger()->critical("Your version of StatusList is not compatible with this plugin");
	$this->getServer()->getPluginManager()->disablePlugin($this);
	return;
}
```
#### Some examples of API usage:
There is an example plugin in /examples/WarnOffline/ which warns if a server from StatusList has gone offline, showcasing most of this plugin's API.

Adding the server `someip.com:1234` temporarily to the StatusList:
```php
/** @var $sl robske_110\SL\StatusList */
$sl->addStatusServer("someip.com", 1234, $sl->getSSS(), false); /* Last argument is whether to save the server to disk or not */
```
Checking if there the server `someip.com:1234` is online:
```php
/** @var $sl robske_110\SL\StatusList */
if(($status = $sl->getStatusListManager()->getStatusServers()["someip.com"."@".1234][2]) === true){
    //online
}elseif($status === false){
    //offline
}else{
    //unknown
}
```

#### For further documentation of API functions check the source code.