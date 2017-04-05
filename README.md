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

### API:
This plugin can also be used as a query API. You might want to simply look into SignServerStats.php, because all api functions are in there.
Small example to get the online state of the server someip.com:1234:
initial, for example onEnable
```php
/** @var $sss robske_110\SSS\SignServerStats */
$sss->addServer("someip.com", 1234);
```
IMPORTANT: You have to wait until the information is fetched asynchronously.
To check if the server is online simply do this:
```php
/** @var $sss robske_110\SSS\SignServerStats */
$serverOnlineArray = $sss->getServerOnline();
if(isset($serverOnlineArray["someip.com".1234])){
    $isOnline = $serverOnlineArray["someip.com".1234];
    //isOnline is now a bool true/false that reflects the online state of the server (if the server is online and this says false, query probably isn't enabled)
    //You can now also get additional data with getMODTs() and getPlayerData() in the same way.
}else{
    //You didn't wait long enough, the information didn't get here yet...
}
```

### TODO:

- [x] implement proper permissions

- [ ] sign style config

- [ ] playerlist?

- [ ] ~language system~ Makes no sense.
