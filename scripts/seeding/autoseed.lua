require 'config'

require 'tools.logger'
require 'tools.helpers'
logger = ELogger:new()
-- logger:set_level(logger.DEBUG)
logger:set_printer(logger.ERESSEA_PRINTER)

require 'seeding.spiral_seeder'

local json = require 'JSON'
local configfile = io.open("autoseed.json", "r")
local auto_config = nil
if configfile then
	local configraw = configfile:read("*a")
	auto_config = json:decode(configraw)
	io.close(configfile)
end

if auto_config == nil then
	print("could not read autoseed.json")
	return
end

local module = 'seeding.' .. auto_config.algo
local algo = require (module)

seeder = algo:new()

seeder:set_parameters(auto_config.parameters or {})
--seeder:set_parameters({regions_per_player = 2, players_per_island = 100, min_islands = 1})

os.rename("autoseed.json", "autoseed.json~")
local configfile = io.open("autoseed.json", "w")
if configfile then
	auto_config.parameters = seeder:get_parameters()
	configfile:write(json:encode_pretty(auto_config, nil, {pretty=true, indent="    "}))
	io.close(configfile)
end

local players = require 'seeding.players'

local newplayers = players.read_newfactions()
seeder:seed(newplayers)

gmtool.editor()

-- write_reports also changes the passwords of new factions
write_reports()
eressea.write_game("auto.dat")
print("game saved as auto.dat")
write_map("export.cr")
print("map saved as export.cr")
