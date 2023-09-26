require 'config'
require 'tools.build-e3'
auto = require 'eressea.autoseed'

local players = auto.read_players()
size =  #players*5
eressea.free_game()

pl = plane.create(0, -400, -400, 800, 800)

local rs, rs2 = 0, 0
gmtool.make_block(0,0, math.floor((math.sqrt(12*size/36)))+5)
for r in regions() do
	rs = rs + 1
end
gmtool.make_island(0, 0, size)
for r in regions() do
	rs2 = rs2 + 1
end
print(size, math.floor(math.sqrt(size)), rs, rs2)

auto.init(400, 1000, 200, 1)
gmtool.editor()
eressea.write_game("auto.dat")
-- write_reports()
write_map("export.cr")
