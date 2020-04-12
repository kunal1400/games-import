# games-import
1) This plugin pull the data from api and import in wordpress custom post type as post in every 1 minute.
2) Import is working by using wp-schedule function for performance and non-blocking code.
3) Setting Page: Go to Settings>Rawg Settings> then choose option yes/no.
4) There are three shortcodes for current post and should use on single page:
	4.a) [get_games taxonomy="platforms"] return an anchor tag with comma seprated.
	4.b) [get_games taxonomy="genres"] return an anchor tag with comma seprated.
	4.c) [get_game_stores] return an buttons html.