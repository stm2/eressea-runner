## Requirements

    * git, php, , cmake, gcc ...

    * apt-get install php-mbstring; luarocks install json-lua --local

````shell
luarocks install lunajson
```


## Usage
`eressea-runner.sh [-f <configfile>] [-y] [-l <log level>] [-v <verbosity>] [-g <game-id>] [-t <turn>] <command> [<args> ...]`

`<configfile>` contains all the game settings. Defaults to config.ini in the script's directoy.

* -l \<log level\>
* -v \<verbosity\>

    0: quiet
    
    1: normal
    
    2: verbose
    
    3: debug
    
* -y
  
    do not ask for confirmation or user input whenever possible

These commands are available:

* **help**
  
    display help information

###   TODO

- **install** \[\<server directory\>\] \[\<games directory\>\]

    install the server

- **upgrade** \[\<branch\>\]
    
    recompile and install from source
    
- **newgame** \<id\> \<rules\>
  
     start a new game
     
- **gmtool**

    open the game editor
    
- **seed** \<algorithm\>
  
    seed new players
    
- **write_reports** \[\<faction id\> ...\]
  
    write reports for all or some factions
    
- **send** \[\<faction id\> ...\]
  
    send reports for all or some factions
    
- **fetch**

    fetch orders from mail server
    
- **create_orders**

    process all orders in queue
    
- **run**
   
    run a turn
    
- **run_all**
  
    do a complete cycle: fetch mail, create-orders, run, send
    
- **announce** \<subject\> \<textfile\> \[attachement ...\]
  
    send a message to all players
    
- **backup**
  
    backup relevant data for this turn


