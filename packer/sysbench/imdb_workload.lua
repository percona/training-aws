--
-- imdb_workload.lua
--
-- BASE_PARAMS="--mysql-host=10.11.82.242 --mysql-user=imdb --mysql-password=imdb --threads=16 --report-interval=1 --time=3600"
-- 
-- # InnoDB workload
-- sudo pmm-admin annotate "innodb-8thds"; sleep 120
-- sysbench imdb_workload.lua $BASE_PARAMS --mysql-db=imdb run; sleep 120
--
--

if sysbench.cmdline.command == nil then
   error("Command is required. Supported commands: run")
end

sysbench.cmdline.options = {
	num_count_stars = {"Number of COUNT(*) queries to run", 3},
	num_point_selects = {"Number of point SELECT queries to run", 10},
	num_string_selects = {"Number of SELECT .. LIKE '%' queries to run", 3},
	skip_trx = {"Do not use BEGIN/COMMIT; Use global auto_commit value", false}
}

local page_types = { "actor", "character", "movie" }
local select_counts = {
	"SELECT COUNT(*) FROM name",
	"SELECT COUNT(*) FROM users",
	"SELECT COUNT(*) FROM title",
	"SELECT COUNT(*) FROM comments",
	"SELECT COUNT(*) FROM favorites",
	"SELECT COUNT(*) FROM movie_ratings"
}
local select_points = {
	"SELECT * FROM title WHERE id = %d",
	"SELECT * FROM name WHERE id = %d",
	"SELECT * FROM char_name WHERE id = %d",
	"SELECT * FROM comments ORDER BY id DESC LIMIT 10",
	"SELECT * FROM comments WHERE type = 'movie' AND type_id = %d ORDER BY comment_time DESC",
	"SELECT * FROM favorites WHERE user_id = %d AND type = 'actor'",
	"SELECT * FROM favorites WHERE user_id = %d AND type = 'movie'",
	"SELECT * FROM person_info WHERE person_id = %d",
	"SELECT * FROM comments WHERE user_id = %d",
	"SELECT * FROM movie_info WHERE movie_id = %d",
	"SELECT * FROM cast_info WHERE person_role_id = %d",
	"SELECT AVG(rating) avg FROM movie_ratings WHERE movie_id = %d",
	"SELECT * FROM movie_ratings WHERE user_id = %d",
	"SELECT * FROM movie_ratings WHERE movie_id = %d",
	"SELECT user2 FROM user_friends WHERE user1 = %d",
	"SELECT * FROM comments WHERE type = 'actor' AND type_id = %d ORDER BY comment_time DESC",
	"SELECT cast_info.* FROM cast_info INNER JOIN title on (cast_info.movie_id = title.id) WHERE cast_info.person_id = %d AND title.kind_id = 1 ORDER BY title.production_year DESC, title.id DESC",
	"SELECT * FROM cast_info WHERE movie_id = %d AND role_id = 1 ORDER BY nr_order ASC",
	"SELECT * FROM users WHERE id = %d",
	"SELECT id, title FROM title t RIGHT JOIN (SELECT CEIL(RAND() * (SELECT MAX(id) FROM title WHERE kind_id = 1)) AS id) h USING (id)",
	"SELECT * FROM users WHERE last_login_date > NOW() - INTERVAL 10 MINUTE ORDER BY last_login_date DESC LIMIT 10",
	"SELECT DISTINCT type, viewed_id FROM page_views ORDER BY viewed_id DESC LIMIT 5"
}
local select_strings = {
	"SELECT * FROM title WHERE title LIKE '%s%%' AND kind_id = 1 LIMIT 100",
	"SELECT * FROM name WHERE name LIKE '%s%%' LIMIT 100",
	"SELECT * FROM char_name WHERE name LIKE '%s%%' LIMIT 100",
	"SELECT * FROM users WHERE first_name = '%s' OR last_name = '%s' OR email_address = '%s'",
	"SELECT id FROM users WHERE email_address = '%s'"
}
local inserts = {
	"INSERT INTO users (email_address, first_name, last_name) VALUES ('%s', '%s', '%s')",
	"INSERT INTO page_views (type, viewed_id, user_id) VALUES ('%s', %d, %d)",
	"INSERT INTO movie_ratings (user_id, movie_id, rating) VALUES (%d, %d, %d)",
	"INSERT INTO favorites (user_id, type_id, type) VALUES (%d, %d, 'actor')",
	"INSERT INTO favorites (user_id, type_id, type) VALUES (%d, %d, 'movie')",
	"INSERT INTO comments (user_id, type, type_id, comment) VALUES (%d, '%s', %d, '%s')"
}
local user_login_sql = "UPDATE users SET last_login_date = NOW() WHERE id = %d"


function user_login()

	-- simulate a user logging in by updating their last_login_date
	con:query(string.format(user_login_sql, sysbench.rand.special(1, 206000)))
end

function execute_selects()

	-- execute number of count(*)s
	for i = 1, sysbench.opt.num_count_stars do
		-- select random query
		con:query(select_counts[math.random(#select_counts)])
	end

	-- loop for however many the user wants to execute
	local splen = #select_points
	for i = 1, sysbench.opt.num_point_selects do

		-- select random query from list
		local randQuery = select_points[sysbench.rand.special(1, splen)]

		-- generate random ids and execute
		local id = sysbench.rand.special(1, 3000000)
		con:query(string.format(randQuery, id))
	end

	-- log in a user
	user_login()

	-- loop over 'LIKE' queries
	local sslen = #select_strings
	for i = 1, sysbench.opt.num_string_selects do

		local str = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
		local randQuery = select_strings[sysbench.rand.special(1, sslen)]

		-- support up to 3 string patterns
		con:query(string.format(randQuery, str, str, str))

		-- log in a user
		user_login()
	end
end


function create_random_email()
	local username = sysbench.rand.string(string.rep("@", sysbench.rand.special(5, 10)))
	local domain = sysbench.rand.string(string.rep("@", sysbench.rand.special(5, 10)))
	return username .. "@" .. domain .. ".com"
end


function execute_inserts()

	-- generate fake email/info
	local email = create_random_email()
	local firstname = sysbench.rand.string("first-" .. string.rep("@", sysbench.rand.special(2, 15)))
	local lastname = sysbench.rand.string("last-" .. string.rep("@", sysbench.rand.special(2, 15)))

	-- INSERT for new user
	con:query(string.format(inserts[1], email, firstname, lastname))

	-- INSERT for page_view
	local page = page_types[math.random(#page_types)]
	con:query(string.format(inserts[2],
		page, sysbench.rand.special(2, 500000), sysbench.rand.special(2, 500000)))

	-- INSERT for review (3M users, 18M movie_info)
	con:query(string.format(inserts[3],
		sysbench.rand.special(2, 3000000), sysbench.rand.special(2, 18000000), sysbench.rand.special(0, 10)))

	-- INSERT for 2 favorites
	con:query(string.format(inserts[4],
		sysbench.rand.special(2, 3000000), sysbench.rand.special(2, 18000000)))
	con:query(string.format(inserts[5],
		sysbench.rand.special(2, 3000000), sysbench.rand.special(2, 18000000)))

	-- INSERT for comment
	local commentType = page_types[math.random(#page_types)]
	local word1 = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
	local word2 = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
	local word3 = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
	local word4 = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
	local word5 = sysbench.rand.string(string.rep("@", sysbench.rand.special(2, 15)))
	local comment = string.format("%s %s %s %s %s", word1, word2, word3, word4, word5)
	con:query(string.format(inserts[6],
		sysbench.rand.special(2, 3000000), commentType, sysbench.rand.special(2, 18000000), comment))
end


-- Called by sysbench to initialize script
function thread_init()

	-- globals for script
	drv = sysbench.sql.driver()
	con = drv:connect()
end


-- Called by sysbench when tests are done
function thread_done()

	con:disconnect()
end


-- Called by sysbench for each execution
function event()

	if not sysbench.opt.skip_trx then
		con:query("BEGIN")
	end

	execute_selects()
	execute_inserts()

	if not sysbench.opt.skip_trx then
		con:query("COMMIT")
	end
end
