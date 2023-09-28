ELogger = {
	DEBUG = 1,
	INFO = 2,
	ERROR = 3,
	NONE = 99,
}

function ELogger:new(o, pars, setter)
	o = o or {}
	setmetatable(o, self)
   	self.__index = self

	self.STDOUT_PRINTER = function(level, msg)
		print(msg)
	end

	self.ERESSEA_PRINTER = function(level, msg)
		if ELogger.DEBUG == level then
			eressea.log.debug(msg)
		elseif ELogger.INFO == level then
			eressea.log.info(msg)
		elseif ELogger.ERROR == level then
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

	self.log_level = 2
	self.printer = ELogger.STDOUT_PRINTER
	self.formatter = ELogger.PLAIN_FORMATTER

   	return o
end


function ELogger:set_printer(p)
	self.printer = p
end

function ELogger:set_formatter(f)
	self.formatter = f
end

function ELogger:log(level, ...)
	if level >= self.log_level then
		self.printer(level, self.formatter(...))
	end
end

function ELogger:set_level(l)
	self.log_level = l
end

function ELogger:debug(...)
	self:log(self.DEBUG, ...)
end

function ELogger:info(...)
	self:log(self.INFO, ...)
end

function ELogger:error(...)
	self:log(self.ERROR, ...)
end
