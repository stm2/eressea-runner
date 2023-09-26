local self = {}

function self.read_newfactions(filename)
    local factions =  {}
    local input = io.open(filename or "newfactions", "r")
    if input then
        local line = input:read("*line")
        while line do
            if string.sub(line, 1, 1) ~= '#' then
            	local npar = 0
            	local email, race, lang
            	local pars = {}
            	for line in string.gmatch(line, "([^ ]+)") do
	            	if npar == 0 then email = line end
    	        	if npar == 1 then race = line end
        	    	if npar == 2 then lang = line end
            		table.insert(pars, line)
            		npar = npar + 1
	            end
    	        if email then
        	        table.insert(factions, { race = race, lang = lang, email = email, pars = pars })
            	end
	        end
            line = input:read("*line")
        end
        input:close()
    end
    return factions
end


return self