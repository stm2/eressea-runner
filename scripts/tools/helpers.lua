function var_dump(o, noprint)
	if type(o) == 'table' then
		local s = '{ '
			for k,v in pairs(o) do
				if type(k) ~= 'number' then k = '"'..k..'"' end
				s = s .. '['..k..'] = ' .. var_dump(v, false) .. ','
			end
		s = s .. '} '
		if noprint == nil then print(s) end
		return s
	else
		if noprint == nil then print(o) end
		return tostring(o)
	end
end