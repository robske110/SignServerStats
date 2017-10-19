# WarnOffline
A PocketMine plugin which displays a warning when a Server from the StatusList has gone offline.

## Usage:
**This Plugin depends on StatusList (and therefore on SignServerStats).**

Anyone with the permission `WarnOffline.notify ` will receive a message each time a server from StatusList goes down or recovers which will look like this:
```
[WarnOffline] Server ip:port went OFFLINE
[WarnOffline] Server ip:port went back ONLINE
```
The console will also be notified.

## API:
This plugin currently has no API.