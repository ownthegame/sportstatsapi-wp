=== Live Scores & League Tables – SportStatsAPI Widgets ===
Contributors: sportstatsapi
Tags: live scores, football, soccer, league table, fixtures
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Live scores, league tables and fixtures for football, soccer, NBA, NFL and more: free widgets as blocks and shortcodes, with live data.

== Description ==

Add live sports data to your site in a few clicks. Pick a widget block, type a competition, and the editor shows the real widget with live data.

Widgets:

* League table: standings with form and zones, one team highlighted if you like.
* Live scores: the current round, updated every 30 seconds.
* Fixtures: coming matches of a competition or one team, with TV channels by country.
* Results ticker: a compact strip of live scores and results.
* Next match: a team's next match with a countdown.
* Team calendar: a team's matches, for visitors to add to their calendar.
* Team form: a team's form and statistics (needs a paid plan).

Free: connect your site once (Settings → SportStatsAPI → Connect) and get a free key: the first six widgets for the free competitions (Premier League, Bundesliga, LaLiga, Serie A, Ligue 1, MLS, NBA, NHL, MLB, NFL, EuroLeague, the Champions League and more), 50,000 views a month. A paid key opens about 3,000 competitions, every widget, your accent colour and no credit link. Until the site is connected, widgets show only in the editor preview.

Light, dark or automatic theme, in English, German, Spanish, Italian or French.

Every widget is also a shortcode:

`[sportstatsapi widget="table" competition="england--premier-league" team="arsenal" theme="dark" lang="de"]`

Shortcode options: `widget` (table, scores, fixtures, ticker, next-match, calendar, team-form), `competition` (the competition key, as on sportstatsapi.com), `team` (a team's name slug, e.g. arsenal or manchester-city: highlights the team in the table and scores, filters fixtures, picks the team of next match, calendar and team form), `theme` (light, dark, auto), `lang` (en, de, es, it, fr), `accent` (#RRGGBB, paid), `rows` (table), `size` (table: compact, standard, full), `view` (fixtures: round, date), `country` (fixtures and next match: TV channels), `round` (scores), `font="inherit"` (your theme's font), `radius` (0–20, corners).

== External services ==

This plugin shows data from SportStatsAPI (https://sportstatsapi.com), a sports data service. Without it the widgets cannot work.

* Visitors' browsers load the widget script from https://api.sportstatsapi.com/api/widget/v1/embed.js on pages with a widget, and the script loads each widget's data, team logos and fonts from api.sportstatsapi.com and img.sportstatsapi.com. Sent: the visitor's IP address and browser details (as with any web request), the page's address (Origin), the widget's settings (widget, competition, team, language) and your widget key.
* In the block editor, when you type in the competition search, your server asks api.sportstatsapi.com for matching competitions (https://api.sportstatsapi.com/api/widget/v1/competitions; until that is available, the list at https://sportstatsapi.com/widgets/competitions.json), and for the teams of the chosen competition. Sent: the text typed, the competition chosen. Kept in a cache for 10 minutes (searches) and 12 hours (lists, teams).
* When you save a widget key, and once a day after that, your server asks api.sportstatsapi.com whether the key works. Sent: the key and your site's address. If the key is refused, widgets fall back to free mode.
* Only if you turn on "Search engines" in Settings → SportStatsAPI: your server loads each widget's data from api.sportstatsapi.com (sent: the widget's settings, and a paid key with your site's address) and keeps it 10 minutes.
* On Settings → SportStatsAPI, your server checks at most once an hour whether account.sportstatsapi.com offers Connect (nothing is sent but the request). When you click "Connect with SportStatsAPI", your browser goes to account.sportstatsapi.com with your site's address and the settings page address, and comes back with your widget key.

The plugin itself sets no cookies and does no tracking.

SportStatsAPI terms: https://sportstatsapi.com/legal/terms/
Privacy policy: https://sportstatsapi.com/legal/privacy/
Cookies: https://sportstatsapi.com/legal/cookies/

== Installation ==

1. Install and activate the plugin.
2. Settings → SportStatsAPI: click Connect (or paste your widget key), and pick the default theme, accent colour and language.
3. In the block editor, add a SportStatsAPI block (League table, Live scores, …) and choose a competition. Or put a shortcode in any post, page or widget area.

== Frequently Asked Questions ==

= Do I need an account? =

A free one: Connect creates it with your email address and brings the free key back (50,000 views a month). Without a key, widgets show only in the editor. A paid key opens every competition and widget.

= Where do I find competition keys and team names? =

The blocks have a search. For shortcodes, use the widget builder on https://sportstatsapi.com/widgets/builder/ : its embed code shows the competition key. A team is its name in lower case with hyphens (manchester-city).

= Does it slow down my site? =

The widget script loads once, asynchronously, only on pages with a widget, and widgets load their data when they scroll into view.

== Screenshots ==

1. The block editor: search a competition, see the real widget.
2. League table with a team highlighted, on a page.
3. Live scores and fixtures on a page.
4. Next match and results ticker.
5. Settings → SportStatsAPI with Connect.

== Changelog ==

= 0.1.0 =
* First version: seven widgets as blocks and shortcodes, Connect, settings, optional server fallback.
