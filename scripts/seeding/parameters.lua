require 'tools.helpers'

Parameters = {
	parameters = {},
	setter = nil,
}

function Parameters:new(o, pars, setter)
	o = o or {}
	setmetatable(o, self)
   	self.__index = self

	self.parameters = {}
	self.setter = setter

	for k, p in pairs(pars) do
		self.parameters[k] = { ['default'] = p }
	end

	--setter(self.parameters)
	return o
end

function Parameters:get_parameters()
	return self.parameters
end

function Parameters:parameter_values()
	local v = {}
	for k, p in pairs(self.parameters) do
		v[k] = p.value or p.default
	end
	return v
end

function Parameters:set_values(pars)
	for par, val in pairs(self.parameters) do
		if pars[par] ~= nil then
			if pars[par] ~= nil then
				if type(pars[par]) == 'table' then
					if pars[par].value ~= nil then
						val.value = pars[par].value
					elseif pars[par].default == nil then
						val.value = pars[par]
					end
				else
					val.value = pars[par]
				end
			end
		end
	end
	self.setter(self.parameters)
end
