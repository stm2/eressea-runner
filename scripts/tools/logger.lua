local self = {}

self.DEBUG = 1
self.INFO = 2
self.ERROR = 3
self.NONE = 99

local log_level = 2

self.STDOUT_PRINTER = function(level, msg)
	print(msg)
end

self.ERESSEA_PRINTER = function(level, msg)
	if self.DEBUG == level then
		eressea.log.debug(msg)
	elseif self.INFO == level then
		eressea.log.info(msg)
	elseif self.ERROR == level then
		eressea.log.error(msg)
	end
end

self.PLAIN_FORMATTER = function(...)
	local text
	for _, s in ipairs({...}) do
		if text == nil then
			text = s
		else
			text = text .. "\t" .. tostring(s)
		end
	end
	return text
end

local printer = self.STDOUT_PRINTER
local formatter = self.PLAIN_FORMATTER

function self.set_printer(p)
	printer = p
end

function self.set_formatter(f)
	formatter = f
end

function self.log(level, ...)
	if level >= log_level then
		printer(level, formatter(...))
	end
end

function self.set_level(l)
	log_level = l
end

function self.debug(...)
	self.log(self.DEBUG, ...)
end

function self.info(...)
	self.log(self.INFO, ...)
end

function self.error(...)
	self.log(self.ERROR, ...)
end

return self