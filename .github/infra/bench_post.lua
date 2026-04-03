-- wrk Lua script for POST /echo benchmark
-- Usage:  wrk -t4 -c100 -d10s -s scripts/bench_post.lua http://127.0.0.1:8765/echo

wrk.method  = "POST"
wrk.body    = '{"email":"bench@quill.dev","name":"Quill Bench","role":"admin"}'
wrk.headers["Content-Type"]   = "application/json"
wrk.headers["Accept"]         = "application/json"

-- Count non-2xx responses as errors for the summary.
local non2xx = 0
function response(status, headers, body)
    if status < 200 or status >= 300 then
        non2xx = non2xx + 1
    end
end

function done(summary, latency, requests)
    io.write(string.format("Non-2xx responses: %d\n", non2xx))
end

