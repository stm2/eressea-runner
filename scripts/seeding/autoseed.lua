require 'config'

logger = require 'tools.logger'
logger.set_level(logger.DEBUG)
logger.set_printer(logger.ERESSEA_PRINTER)

myseeder = require 'seeding.spiral_seeder'
myseeder.set_parameters({regions_per_player = 2, players_per_island = 100, min_islands = 1})

local players = require 'seeding.players'

local newplayers = players.read_newfactions()
myseeder.set_parameters({num_players = 100})
myseeder.seed(newplayers)

gmtool.editor()

eressea.write_game("auto.dat")
write_reports()
write_map("export.cr")
