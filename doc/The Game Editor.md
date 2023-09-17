With 

```shell
eressea
E> require('config')
E> gmtool.editor()
```

You can start the text-based map editor. Here are most of the keyboard shorcuts you can use.

### Getting around

* Use the arrow keys and position keys to move the cursor on the map. The highlighted region's details are displayed on the right side of the window.                                 
* `g` moves the cursor to the region with the coordinates you enter
* `/` searche for ...
    * r: a region by name
    * u: a unit by id
    * f: a faction by id
    * F: afaction from a list of factions
* `n` finds the next element according to last search
* `SPACE` selects or unselects the current region
* `TAB` jumps to next selected region (by space or t)
* `p` jumps between planes
* `a` jumps to corresponding astral region / real region

### Display

* `I` shows / hides (in the window on the right) info about ... f: factions, u: units, s: ships, b: buildings
* `d` lets you choose between the map modes `t`: show terrains and 'l': show luxuries
* `O` opens a data file in the `data` subdirectory
* `S` saves the data to a file in the `data` subdirectory
* `f` or `Ctrl-t` terraforms the region under the cursor; it lets you choose a terrain
* `CTRL+b` adds a block of oceans without overwriting existing regions
* `B` builds an island using an algorithm used for the E3 game (PRO TIP: You can change the size of the created islands by setting `eressea.settings.set("editor.island.min", 20))` from the LUA console
* `s` seeds the next player from the `newfactions` file at the current region (see below)
* `A` resets an area: any region that can be reached by an uninterrupted path from the current region (not crossing empty regions, walls, or firewalls) has their region age reset to 0.
* `c` clears the region under the cursor, randomly resetting all resources
* `C` clears a rectangular region (2 regions up and to the right)
* `h` marks regions: `n`: none, `i`: island under the cursor, `t`: terrain type, `s`: with ships, `u`: with units, `p`: with player units, `m`: with monsters, `f`: with units of a faction,`c` chaos regions, `v`: new regions with age 0
* `H` unmarks regions (as above)
* `t` selects regions (for batch commands, as above)
* `T` un-selects regions (as above)
* `;` runs a batch command for all selected regions
  `r`: resets the regions, `t` terraforms, `f`: fixes the regions (a very special operation you are not likely to need)
  ### Other
  * `Ctrl+L` redraws the screen
  * `L` opens a lua prompt; you can enter lua commands most likely calling other scripts with `loadfile('xyz.lua')`
  * `Q` quit the editor

### The newfactions file

The file `newfactions` (in the current directory, most likely the game directory) is read when the editor starts. It contains one new faction per line and is used by the `s` command. Existing factions (identified by email) are ignored. A line can contain up to 5 fields separated by one or more spaces:

1. an email (there can be no duplicat emails, at most 54 characters long)
2. a race
3. a locale (either 'de' or 'en' currently)
4. a password (15 characters)
5. an alliance (a number); this is not used by standard eressea, but was used in a High Speed Eressea game to put players into fixed alliances (not to be confused with E3 alliances)

The last two values are optional.

## Read on
* [[Starting a Game]]
* [[World Creation and Seeding]]
