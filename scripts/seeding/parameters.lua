local self = {}

function self.create(pars, setter)
	local self = {}

	local parameters = {}
	local setter = setter

	for k, p in pairs(pars) do
		parameters[k] = { ['default'] = p }
	end


	function self.parameters()
		return parameters
	end

	function self.parameter_values()
		local v = {}
		for k, p in pairs(parameters) do
			v[k] = p.value or p.default
		end
		return v
	end

	function self.set_values(pars)
		for par, val in pairs(parameters) do
			if pars[par] ~= nil then val.value = pars[par] end
		end
		setter(parameters)
	end

	setter(parameters)
	return self
end

return self
