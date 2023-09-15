# Manual for Game Masters -- WIP (September 2017)

## Table of Contents

* Installing the server
* Setting up your game
* Creating the world
* Mail handling
* Initial reports
* Running the turn
* Automated configuration
* Changing the Rules

## Installing the server

### Linux system and prerequisites

Eressea was designed to run on a UNIX system. While the code can also be built on any other flavor of UNIX including OS X, as well as Windows, Linux is probably the easiest to use and most widely available operating system, and this guide will focus on that.

If you are not already a Linux user, you will first need a machine. If you have an old PC somewhere you can use it for example. [Debian](https://www.debian.org/download) or [Ubuntu](https://ubuntu.com/download/desktop) Linux are for free and easy to install. We will assume that you are familiar with the basics like opening a terminal and installing software.

After you have a fully functional Linux, you need to install some extra packages in order to install Eressea. In the Terminal window, type the following commands ([1]):

	sudo apt-get -y update
	sudo apt-get -y install gcc make git cmake liblua5.2-dev libtolua-dev libncurses5-dev libsqlite3-dev libexpat1-dev
	echo export ERESSEA=~/eressea >> .bash_aliases
	echo export LANG=en_US.UTF-8 >> .bash_aliases
	source .bash_aliases

### Checking out the code

Eressea is an open-source project hosted on the social coding site github. We need to clone a copy of the source code before we can build it. Type:

	mkdir -p eressea/server
	cd eressea
	git clone https://github.com/eressea/server.git git
	cd git
	git checkout master

([2])

### Building and installing the code

The game is distributed as platform-independent source code, and we need to compile it into an executable for our platform to make it usable:

	cd ~/eressea/git
	git submodule update --init
	ln -sf conf/eressea.ini
	s/cmake-init
	s/build && s/runtests && s/install

If all went well, you should now have a lot of files installed into the ~/eressea/server directory.

## Setting up Your Game

Every game of Eressea needs a data file that contains the map data, player positions and complete state of the world. You can choose to run a single game world, or have multiple worlds side-by-side if you want to operate more than one game.

The game data and all files for a world will reside in a per-game directory. Let's create it now:

	cd ~/eressea/git
	s/setup -g 1 -r e3
	cd ../game-1

This tells the setup tool that we want to set the game "id" to 1, since this is the first (and so far only) game we want to host, and use the E3 rules. Each game is defined by a set of rules, and there are currently two rulesets in the distribution, e2 and e3, which describe the rules for the original Eressea, or the E3 and Deveron game, respectively.

I recommend you try to avoid naming your game Eressea, as that name is already associated with my own games, and the name of the game system itself. Let's call our game "Arda".

We need to set this and our email address by editing the file `game-1/eressea.ini`. Open this file in a text editor ([3]), find the `[game]` section and add these lines (change the values to match your email and game name):

    email = eressea-server@example.com
    name = Arda

You may also add a custom name for your "From" address:

    sender = Arda Server

There are a lot of other moving pieces to the game that you could configure at this point, but we will defer this to the section "Changing the rules" below.

### A Note about Email

Notice that I've replaced the email address of the server with a custom one. I recommend using an account on an external mail server that has POP3 or IMAP support. If you have a small domain somewhere that comes with a limited number of mail boxes, using one of those works great, too. You could of course run your own mail server, but since that is a lot of hassle, and this machine is not permanently on the Internet, I would advise against it and definitely won't go into it. I would also advise against using your private email address. You may get a lot of Spam and, more seriously, your provider or other institutions might even block you, because they mistake the report mails as Spam. 

See the section later in this document for how to set up automatic email handling for the game.

## Creating the world

The next step is to create a map for your world. There are several ways to do this, but the most straightforward is to edit the map by hand using the built-in editor. To run it, first start the game in interactive mode:

	cd ~/eressea/game-1
	./eressea -t 1

This will give you an interactive prompt, with a cursor prefixed by `E> `. Type the following commands at the prompt to start the editor:
	
	require 'config'
	gmtool.editor()

Now you see the (empty) map, and a cursor that you can use to navigate with the arrow keys. Notice the coordinates at the bottom left changing when you do so, select some regions with the space bar, and get a feel for how the hex layout of the map is represented in this textual representation.

You can press 'f' to terraform the region under the cursor, or chain ';' and 'f' to terraform all currently selected regions at once, and 'Ctrl + b' creates a block of ocean regions. With just these tools and plenty of patience, you can build yourself a map.

TODO: insert a link to advanced world generation and scripting here.
TODO: create torus

Press F2 to save your world to a file. Since we are in turn 0, you should call it "1.dat". It will be stored in the ~/eressea/game-1/data/ directory, and each future turn will produce a new file. Press 'Q' to exit the editor.

TODO: insert link to advanced editor commands here.

### Adding Players

At this point, you should have an email address and a choice of playable race for each of your players, and you'll want to seed them on the map so your game can get started. There are several schools of thoughts for the best methods of placement, I personally like putting multiple factions in the same starting hex to encourage cooperation early on, but some GMs prefer to liberally scatter factions across the entire world to encourage exploration first.

Whatever your choice, edit the file ~/eressea/game-1/newfactions so it contains one line for each player:

	enno@hotmail.com elf de
	foo@example.com orc en
	[...]

The entries, separated by one or more white space characters, are email, race, and language. The language is either "de" for German or "en" for English. This file is read automatically when the game editor starts.

By pressing 's' you can now place a faction in the current region. The next time you press 's' places the second faction from the file, and so on.

If you walk around the map with the cursor keys, you will see the player's units appear in the region info box on the right hand side of the editor. To find the hexes where players are, chain the keys 'h' and 'p' to highlight all player hexes.

Again, remember to save your world with F2 after players have been seeded, so you don't lose your work. Leave the editor with 'Q', then leave the interactive console with Ctrl-D to return to the terminal.

TODO add starting equipment

## Mail handling

We are going to use fetchmail and procmail to retrieve and filter orders, and mutt to send reports.

	sudo apt-get install -y sendmail procmail fetchmail mutt libsasl2-modules zip

Configuration depends on your server. The best case is that you have a complete server with a domain name and static ip address. Configuring this is beyond the scope of this guide, but when it works, sending a mail is as simple as typing `echo "Mail body" | mutt -s "Subjectline" -- recipient@example.com`.

If you just have a webhosting solution or use a public mail service like hotmail, gmail or gmx, you have to adapt the configuration files to tell the mail tools about your server.

Go to `~/eressea/server/etc` and edit the files fetchmailrc, procmailrc, and muttrc (templates are provided in this directory).

Check that sending email works by sending yourself a message from the command line:

	echo "Hello World" | mutt -F ~/eressea/server/etc/muttrc  -s "testing" arda-server@example.com

At this point you should also edit the files `server/etc/report-mail.*`. The content goes into the mail body of your report mails.

## Initial reports

> This section is wrong and needs to be rewritten. It mentions a run-turn.sh script that does not exist in the repository.

Before your game starts, you have to send an initial set of reports to all of your players. These can be generated using the run-turn script:

	cd ~/eressea/game-1
        run-turn.sh -g 1 reports

It's a good idea to verify that the report files have the correct server data. Check that the .cr files in reports have your server address as `;mailto` tag and "<game name> <game id> BEFEHLE" (or ORDERS) as `;mailcmd` tag.
   
You can now send your reports to your players by executing

    run-turn.sh -g 1 send

This should send the zipped report files to all players.

### Getting mails

We will use fetchmail to retrieve your mails. Find the file `server/etc/fetchmailrc.template`, copy it to `server/etc/fetchmailrc` and open it in an editor. Adjust the server name, user name, and password to your data. `fetchmail` will use `procmail` to find order mails and store them in the game directory. Therefore, you should copy procmailrc.template to procmailrc and change the GAME_NAME variable to "Arda" and change the game id, if necessary. This will filter every mail with a subject like "Arda 1 BEFEHLE" and pass them to the orders-accept script.

    fetchmail -d 300 -f ~/eressea-server/server/etc/fetchmailrc

will start fetchmail in "daemon" mode, retrieving your mails every 5 minutes. To restart fetchmail after your server reboots, you can put it into your crontab, which is explained later.

## Running the turn

In an unattended server that is permanently connected to the Internet, Eressea can run as an automatically scheduled cronjob. When we are using a virtual machine that's only online whenever we use it, starting this job manually is the best solution. To run a turn for game 1, type:

	cd ~/eressea/game-1
        ./run-turn.sh -g 1 all

This will run the turn and create reports, compress them, and mail them to the players, all in one.

## Automated configuration

If your server is permanently connected to the Internet (you could do this with a Raspberry Pi, for example) you can configure your server to send order receipts, answer report requests and automagically evaluate turns at given times. You do this using a `cron` service.

    crontab -e

This will open an editor that edits your user's `crontab`, the table that tells cron what to do. There is a template in `server/etc`

    # Crontab for Eressea server

    # your regular environment variables are not visible by cron; adjust these value according to your system
    # setting variables like this might not work for your crontab implementation; if this is the case, you could start a custom script here and set variables there
    PATH=/home/eressea/bin:/opt/bin:/usr/local/bin:/usr/bin:/bin
    ERESSEA=/home/eressea/eressea
    FETCHMAILRC=/home/eressea/server/etc/fetchmailrc

    # enables the game server
    ENABLED=yes
    # enables order confirmation
    CONFIRM=yes

    # m h  dom mon dow   command
    # starts the fetchmail daemon; note that only one daemon process is permitted per user
    00 *  * * *         [ -f $FETCHMAILRC ] && fetchmail --quit -d 300 -f $FETCHMAILRC >> $ERESSEA/server/fetchmail.log 2>&1

    # run the server for game 1 at 9:15 pm every Saturday
    15 21 * * Sat        [ "$ENABLED" = "yes" ] && $ERESSEA/server/bin/run-turn.sh -g 1 -v 3 all
    # run order confirmation for game 1 every 5 minutes
    */5 * * * *          [ "$CONFIRM" = "yes" ] && $ERESSEA/server/bin/orders.cron 1

This example makes sure that the `fetchmail` daemon is running, starts the `orders.cron` script every 5 minutes to check for orders that have arrived and send confirmation emails, and finally runs the eressea server at 9:15 pm every Saturday.

All you have to do is adjust the `ERESSEA` and `FETCHMAILRC` variables according to your system, set the game number(s) corresponding to your game(s) for the run-turn and orders.cron scripts. Refer to the crontab manual for an explanation of the timing options.

To run a syntax checker like `echeck`, you have to configure it you need to set the `checker` option in the `game` section in your eressea.ini file to `/home/eressea/server/bin/checker.sh`. By default that script does nothing. To use [echeck](https://github.com/eressea/echeck), you first have to [install it](https://github.com/eressea/echeck). Then, set the `echeck_dir` variable in `checker.sh` to the path where echeck is installed and remove the `#` from the last line of the file. You also have to tell echeck which ruleset to use for your game by adding `rules=e3` bevor the echeck line.

## Changing the rules

If you want to make changes to the rules of the game, you should probably copy one of the existing rulesets, and give it your own name. Let's say we want to name our game Arda, give it the game id 2, and use the e2 rules as a base. We start by copying the configuration from e2 to a directory a1:

	cd ~/eressea/server/conf
	cp -r e2 a1

Now open `a1/config.json` in a text editor. Find any lines that contain the name "Eressea", and change them to your game's name. Let's also change the game ID to 2. Finally, look out for references to "e2" that you might have to change to a1:


    "include": [
        "keywords.json",
        "calendar.json",
        "prefixes.json",
        "a1/terrains.json"
    ],

...

    "settings": {
        "game.name" : "Arda",
        "game.id" : 2,
        "orders.default": "work",
...

You would now have to create the game directory with a modified command:

	cd ~/eressea/git
	s/setup -g 2 -r a1

You should also edit `~/eressea/game-2/eressea.ini`. Make sure that it contains the following lines:

    [game]
    locales = de,en
    id = 2
    start = 0
    email = yourserver@example.com
    name = Arda

    [lua]
    install = /home/eressea/eressea-server/server
    paths = /home/eressea/eressea-server/server/scripts:/home/eressea/eressea-server/server/lunit
    rules = a1

Be aware that settings in eressea.ini override those in conf/a1/config.json.

You can now start changing the configuration files in server/conf/a1. This is beyond the scope of this guide, however.

### Notes

[1]: You can install the server anywhere you want, but we assume that it is in $HOME/eressea, i.e., in the subdirectory eressea of your home directory. It might be a good idea to create a separate user "eressea" for installing an running the server.

[2] The repository is configured to check out the develop branch by default. It contains the newest changes to the sources. The master branch contains a stable version, the one that is also used on the official Eressea server.

[3] Choose your favorite text editor. If you work in a text console, choose pico or vi. If you have a graphical system, you might prefer gedit, emacs or gvim.


TODO: 
 - backup script 

 - (run-eressea.cron and ENABLED=yes)

 - Upgrading
   * template files

 - game name and Magellan
