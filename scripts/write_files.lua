local function join_path(a, b)
    if a then return a .. '/' .. b end
    return b
end

local function write_htpasswd()
    local out = io.open(join_path(config.basepath, "htpasswd"), "w")
    if out then
        for f in factions() do
            if f.password then
                out:write(itoa36(f.id) .. ":" .. f.password .. "\n")
            end
        end
        out:close()
    end
end


function write_files(turn, generate)
    eressea.read_game(turn .. '.dat')

    init_reports()
    update_scores()
    write_summary()
    if generate then
        write_reports()
        eressea.write_game(turn .. '.dat')
    else
        for f in factions() do
            write_report(f)
        end
    end
    write_database()
    write_passwords()
    write_htpasswd()
end