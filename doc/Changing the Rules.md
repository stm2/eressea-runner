TODO

## Changing the rules

If you want to make changes to the rules of the game, you should probably copy one of the existing rulesets, and give it your own name. Let's say we want to name our game Arda, give it the game id 2, and use the e2 rules as a base. We start by copying the configuration from e2 to a directory a1:

	cd $ERESSEA/server/conf
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