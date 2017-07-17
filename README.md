# SignServerStats
A PocketMine plugin which can display player count and most from any server with query enabled.

## Info:
### Usage:
Anyone with the permission SSS.signs can create a sign with the following content:
```
[SSS]
<serverIP>
<serverPort>
```

The plugin will recognize that sign and fill it with colorful stats!

*Note: Due to 1.1 not telling the server when the sign is finished, you need to tap the sign once to activate it after setting it up.*

### API:
##### This plugin can also be used as a query API. You might want to look into SignServerStats.php, because all the API functions are in there.
Example plugins are provided in /examples/:
- DumpInfo.php - Dumps all availible info about a server.

Because the following two examples may also be useful for users, so they are also provided as phars in every release:
- WarnOffline/ - Warns if a server has gone offline. (Currently WIP)
- StatusList/ - Lists online status and player count of multiple servers in a List. (TODO)

#### If you prefer just a quick introduction, here is one for getting the the online status of the server `someip.com:1234`:

Initial, for example onEnable:
```php
/** @var $sss robske_110\SSS\SignServerStats */
$sss->addServer("someip.com", 1234);
```
This tells SSS that it should query that server in its next query.

##### IMPORTANT: You have to wait until the information is fetched asynchronously.

To check if the server is online simply do this, it is recommended to do this in a task.
```php
/** @var $sss robske_110\SSS\SignServerStats */
$serverOnlineArray = $sss->getServerOnline();
if(isset($serverOnlineArray["someip.com".'1234'])){
	$isOnline = $serverOnlineArray["someip.com".'1234'];
    //isOnline is now a bool (true/false) that reflects the online state of the server (if the server is online and this says false, it probably doesn't have query enabled)
    //You can now also get additional data with getMODTs() and getPlayerData() in the same way.
}else{
    //You didn't wait long enough, the information didn't get here yet...
}
```

## TODO:

- [ ] Fire event onAsyncCallBack for easier API use

- [ ] API should be able to also get other data (playernamelist, pluginlist)

- [ ] sign style config

- [x] implement proper permissions
