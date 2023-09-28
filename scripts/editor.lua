require 'config'

local turnfile = get_turn() .. ".dat"

io.write("Do you want to read the current turn (" .. turnfile .. ")? [y/N] ")
io.flush()
answer = io.read("*l")
if string.find(answer, "y") then
	eressea.read_game(turnfile)
	eressea.log.info("read " .. turnfile)
end

gmtool.editor()

io.write("Do you want to save? [y/N] ")
io.flush()
answer = io.read("*l")

if string.find(answer, "y") then
	io.write("File name [" .. get_turn() .. ".dat] ")
	io.flush()
	answer = io.read("*l")
	if answer == '' then answer = (get_turn() .. ".dat") end
	eressea.write_game(answer)
	eressea.log.info("wrote " .. answer)
end
