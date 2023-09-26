local self = {}

local my_logger

if logger == nil then
	my_logger = require 'tools.logger'
else
	my_logger = logger
end

local num_players = -1

local players_per_island = 11
local regions_per_player = 3
local island_distance = 5
local min_islands = 2
local mountains_per_faction = .5


local function set_parameters(parameters)
	local p = parameters['num_players']
	num_players = p.value or p.default
	local p = parameters['players_per_island']
	players_per_island = p.value or p.default
	local p = parameters['regions_per_player']
	regions_per_player = p.value or p.default
	local p = parameters['island_distance']
	island_distance = p.value or p.default
	local p = parameters['min_islands']
	min_islands = p.value or p.default
	local p = parameters['mountains_per_faction']
	mountains_per_faction = p.value or p.default
end

local parameters

function self.set_parameters(pars)
	parameters.set_values(pars)
end


parameters = require 'seeding.parameters'
parameters = parameters.create({
	['num_players'] = -1,
	['players_per_island'] = 11,
	['regions_per_player'] = 3,
	['island_distance'] = 5,
	['min_islands'] = 2,
	['mountains_per_faction'] = .5,
}, set_parameters)

-- debug variable
local bloat = 4


local function shuffle(tab)
	if type(tab) ~= 'table' then return end
	for j=1,#tab do
		k = math.random(j)
		if k ~= j then
			local temp = tab[j]
			tab[j] = tab[k]
			tab[k] = temp
		end
	end
	return tab
end


local function simple_spiral(x, y)
	local dist = distance(0, 0, x, y)
	if x > 0 and y == 0 then return x, y + 1 end
	if x > 0 and y > 0 then return x - 1, y + 1 end
	if y > 0 and -x < y then return x - 1, y end
	if y > 0 then return x, y - 1 end
	if x < 0 then return x + 1, y - 1 end
	if x < -y then return x + 1, y end
	return x, y + 1
end

local function next_spiral(x, y, xoff, yoff, radius)
	radius = radius or 1
	xoff = xoff or 0
	yoff = yoff or 0
	local x, y = simple_spiral((x - xoff) / radius, (y - yoff) / radius)
	return x * radius + xoff, y * radius + yoff
end

local function find_island(x, y)
	local current = get_region(x, y)
	local queue = {current}
	local visited = {current}

	while #queue > 0 do
		current = table.remove(queue, 1)
		local x, y = current.x, current.y
		for nbor = 1, 6 do
			x, y = next_spiral(x, y, current.x, current.y)
			local rn = get_region(x, y)
			if (rn ~= nil and rn.terrain ~= "ocean") then
				if not table.contains(visited, rn) then
					table.insert(queue, rn)
					table.insert(visited, rn)
				end
			end
		end
	end

	return visited
end

function table.contains(table, element)
	for _, value in pairs(table) do
		if value == element then
			return true
		end
	end
	return false
end

local function get_energy(r, rchanged, pos)
	local energy = 0
	local rold = null
	for _, r2 in ipairs(pos) do
		if rold == null and r2 == r then
			rold = r
		else
			local dist = distance(rchanged.x, rchanged.y, r2.x, r2.y)
			if dist == 0 then
				energy = energy + 10
			elseif dist == 1 then
				energy = energy + 4
			else
				energy = energy + 1 / dist
			end
		end
	end
	return energy
end

local function distributed_regions(x, y, num)
	if get_region(x, y) == nil then
		my_logger.error("no region at", x, y)
		return
	end
	local island = find_island(x, y)
	local pos = {}
	for i = 1, num do
		local index = rng_int() % #island
		table.insert(pos, island[index + 1])
		--my_logger.debug(i, #island, island[index + 1])
	end
	for it = 1, #pos do
		my_logger.debug("it", it)
		local change = 0
		local sum = 0
		for i, r in ipairs(pos) do
			local bestr, bestenergy = r, get_energy(r, r, pos)
			--my_logger.debug(r.x, r.y, bestr, bestenergy)
			local xn, yn = r.x, r.y
			--for _, rn in ipairs(island) do
			for nbor = 1, 6 do
        		xn, yn = next_spiral(xn, yn, r.x, r.y)
        		local rn = get_region(xn, yn)
        	if table.contains(island, rn) then
        		local energy = get_energy(r, rn, pos)
					--my_logger.debug(rn.x, rn.y, energy)
					if energy < bestenergy then
						bestr, bestenergy = rn, energy
					end
				end
			end
			--my_logger.debug("", bestr, bestenergy)
			sum = sum + bestenergy
			local rf = bestr
			if rf ~= nil and rf ~= r and table.contains(island, rf) then
				pos[i] = rf
				change = change + 1
				--my_logger.debug(r, "->", rf)
			end
		end
		my_logger.debug("energy", sum)
		if change == 0 then break end
	end
	return pos
end

local function get_faction_by_email(email)
	for f in factions() do
		if f.email == email then
			return f
		end
	end
	return nil
end

-- create a new faction
local function seed(r, email, race, lang)
	assert(r)
	local f = faction.create(race, email, lang)
	assert(f)
	local u = unit.create(f, r)
	assert(u)
	equip_unit(u, "seed_faction")
	equip_unit(u, "seed_unit")
    -- equip_unit(u, "seed_" .. race, 7)
    equip_unit(u, "first_unit", 7)
    f:set_origin(r)
    return f
end

local function move_faction(f, rfrom, rto)
	for u in f.units do
		if u.region == rfrom then
			local s = u.ship
			local b =  u.building
			u.region = rto
			if s ~= nil then
				s.region = rto
				u.ship = s
			end
			if b ~= nil then
				b.region = rto
				u.building = b
			end
		end
	end
	f:set_origin(rto)
end

local function count_neighbors(r, terrain)
	local x, y = r.x, r.y
	local count = 0
	for i = 1,6 do
		x, y = next_spiral(x, y, r.x, r.y)
		local rn = get_region(x, y)
		if rn ~= nil and rn.terrain == terrain then
			count = count + 1
		end
	end
	return count
end



local function swap_aquarians(r, start_regions)
	for u in r.units do
		if u.race == 'aquarian' then
			if count_neighbors(r, "ocean") == 0 then
				for _, r2 in ipairs(start_regions) do
					if count_neighbors(r2, "ocean") > 0 then
						if r2.units() == nil then
							my_logger.info("moving landlocked aquarian " .. tostring(u.faction) .. " from " .. tostring(r) .. " to " .. tostring(r2))
							move_faction(u.faction, r, r2)
							return true
						end
						if false then
						for u2 in r2.units do
							if u2.race ~= 'aquarian' then
								my_logger.info("switching landlocked aquarian " .. tostring(u.faction) .. " with " .. tostring(u2.faction) .. " from " .. tostring(r) .. " to ", tostring(r2))
								move_faction(u.faction, r, r2)
								move_faction(u2.faction, r2, r)
								return true
							end
						end
						end
					end
				end
			end
		end
	end
	return false
end

local function improve_start(start_regions)
	local r0 = nil
	for _, r in ipairs(start_regions) do
		r.terrain = 'plain'
		while swap_aquarians(r, start_regions) do end
		r0 = r
	end

	local island = find_island(r0.x, r0.y)

	local mountains = {}
	for _, r in ipairs(island) do
		if r.terrain == 'mountain' then
			table.insert(mountains, r)
		end
	end
	while #mountains / #start_regions < mountains_per_faction do
		local rbest, dist = 0
		local rmax, dmax = null, 0
		for _, r in ipairs(island) do
			if r.terrain ~= 'plain' and r.terrain ~= 'mountain' then
				local dist = 0
				for _, m in ipairs(mountains) do
					local d = distance(r.x, r.y, m.x, m.y)
					dist = dist + d
				end
				if dist > dmax then
					dmax = dist
					rmax = r
				end
			end
		end
		if rmax == nil then
			my_logger.info("could not create enough mountains: " .. #mountains .. " < " .. mountains_per_faction * #start_regions)
			break
		end
		my_logger.info("growing a mountain at " .. tostring(rmax))
		rmax.terrain = 'mountain'
		table.insert(mountains, rmax)
	end

end


function self.seed(newplayers)
	if newplayers == nil then newplayers = {} end
	if num_players == -1 then
		num_players = #newplayers
	end

	if num_players == 0 then
		my_logger.info("no players")
		return
	end

	-- maybe 0
	local num_islands = math.max(min_islands, math.floor(num_players / players_per_island))
	players_per_island = math.floor(num_players / num_islands)

	local remainder = num_players - num_islands * players_per_island

	local radius = math.floor((math.sqrt(players_per_island*regions_per_player / 3))) + 4 + island_distance

	my_logger.info(num_players, regions_per_player, players_per_island, num_islands)

	pl = plane.create(0, -400, -400, 800, 800)

	shuffle(newplayers)

	local x, y = 0, 0
	local seeded = 0
	for i = 1, num_islands do
		local players_here = players_per_island
		if remainder > 0 then players_here = players_here + 1; remainder = remainder - 1 end
		local size = players_here * regions_per_player

		gmtool.make_block(x, y, radius)
		gmtool.make_island(x, y, size)

		local start_regions = distributed_regions(x, y, players_here)
		for _, start in ipairs(start_regions) do
			if seeded >= #newplayers * bloat then break end

			local p = newplayers[seeded % #newplayers + 1]
			if bloat > 1 then
				p.email = p.email .. "1"
			end
			seeded = seeded + 1
			local dupe = get_faction_by_email(p.email)
			if dupe then
				eressea.log.warning("seed: duplicate email " .. p.email .. " already used by " .. tostring(dupe))
			else
				if seeded > #newplayers then p.email = p.email .. "1" end
				local f = seed(start, p.email, p.race or "human", p.lang or "de")
				my_logger.info("seeding " .. f.name .. " " .. f.email .. " " .. f.race .. " at ", start)
			end
		end

		improve_start(start_regions)

		x, y = next_spiral(x, y, 0, 0, radius)
	end
end

return self
