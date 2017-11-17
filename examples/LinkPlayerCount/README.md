# LinkPlayerCount
A PocketMine plugin depending on SignServerStats which displays the playercount of the server it is installed on combined with other specified servers.

## Usage:
The servers can be added in servers.yml manually or can be simply added/removed by anyone with the permission `StatusList.manageList` through the commands `linkplayercount add <ip> [port]` / `linkplayercount rem <ip> [port]`.

If don't want that the max player count is also combined, please set `combine-max-slots` to false in the `config.yml`.

**WARNING: This plugin might create a wrong playercount (steadily increasing) when used in a circular way.**
This means that when you have a server1 that is combining from server2 and a server2 which combines with server1, the playercount would go steadily up to crazy levels.
To visualize here are some scenarios:
```
          |-----------LOBBY SERVER (65/175)-------------|
          |                     |                       |
          |                     |                       |
Spleef Server (10/40) |----SG LOBBY (40/90)----| Premium Server(10/20)
                      |         |              |
                      |         |              |
           SG GAME1 (10/20) SG GAME2 (10/20) SG GAME3 (10/20)
```
This example is totally **valid**, because no servers are pointing at each other in two directions.
Note:
- SG LOBBY has 30 player slots itself and 10 additional players.
- LOBBY SERVER has 25 player slots itself and 5 additional players.

```
LOBBY SERVER (INF/50)
     /\     ||
     ||     \/        
GAME SERVER (INF/30)
```
This example is **invalid**, because both servers have each other in servers.yml.

```
LOBBY SERVER (INF/50) <--- GAME SERVER (INF/30)
       ||                           /\       
       \/                           ||
WORLD SERVER (INF/50) ---> PREMIUM SERVER (INF/30)
```
This example is **invalid**, because all playercounts are linked over different servers together
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
$lpc->addServer("someip.com", 1234, $lpc->getSSS(), false); /* Last argument is whether to save the server to disk or not */
```

#### For further documentation of API functions check the source code.