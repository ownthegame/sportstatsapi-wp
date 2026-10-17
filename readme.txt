=== Live Scores & League Tables ===
Contributors: modulout
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

All 19 SportStatsAPI widgets, each a block (in the SportStatsAPI group of the inserter) and a shortcode.

Free (with the free key from Connect):

* League table: standings with form and zones, one team highlighted if you like.
* Live scores: the current round, updated every 30 seconds.
* Fixtures: coming matches of a competition or one team, with TV channels by country.
* Results ticker: a compact strip of live scores and results.
* Next match: a team's next match with a countdown.
* Team calendar: a team's matches, for visitors to add to their calendar.

With a paid SportStatsAPI plan: match centre, match facts, match preview, head to head, box score, team form, club season, squad, injuries and suspensions, season race, top players, player card and tennis.

Connect your site once (Settings → SportStatsAPI → Connect) to get the free key: the six free widgets for the free competitions (Premier League, Bundesliga, LaLiga, Serie A, Ligue 1, MLS, NBA, NHL, MLB, NFL, EuroLeague, the Champions League and more), 25,000 views a month. A paid key opens every widget, about 3,000 competitions, your accent colour and no credit link. Until the site is connected, widgets show only in the editor preview.

= Free and paid =

The plugin is free and complete: all 19 blocks and the shortcode are in it, with no code that is switched off or unlocked by payment. The widgets are drawn and their data served by the SportStatsAPI service (https://sportstatsapi.com), and the service decides what a key may show:

* No key: nothing on your site; the editor shows a preview and a "Connect" notice. Every widget needs a key, the free ones too: the free key identifies your site and counts its free views (25,000 a month), so the service needs it.
* Free key (Connect, no payment): the six free widgets above, for the free competitions, 25,000 views a month. From 25,000 to 30,000 views the widgets keep working and you are told the free limit is reached; from 30,000 views until the month ends they show no data unless you move to a paid plan. To use the free key you tick "Yes, free widgets may show a small 'Data by SportStatsAPI' link" (at Connect or in the settings); without that yes free widgets are not shown. As part of the free plan's terms (https://sportstatsapi.com/legal/terms/) the SportStatsAPI script draws a small "Data by SportStatsAPI" credit link inside each widget; the plugin itself adds no links or credits to your site.
* Paid key (a SportStatsAPI plan, see https://sportstatsapi.com/pricing/): all 19 widgets, about 3,000 competitions, your accent colour, no credit link.

With a free key the 13 paid blocks are marked "(paid plan)" in the inserter, show a notice in the editor, and the service answers "Widget not available" for them. You can use the plugin forever with the free key.

Light, dark or automatic theme, in English, German, Spanish, Italian or French.

Every widget is also a shortcode:

`[sportstatsapi widget="table" competition="england--premier-league" team="arsenal" theme="dark" lang="de"]`

Shortcode options: `widget` (table, scores, fixtures, ticker, next-match, calendar, match-centre, match-facts, preview, head-to-head, box-score, team-form, club-season, squad, injuries, race, top-players, player, tennis), `competition` (the competition key, as on sportstatsapi.com), `team` (a team's name slug, e.g. arsenal or manchester-city: highlights the team in the table and scores, filters fixtures, picks the team of next match, calendar and team form), `theme` (light, dark, auto), `lang` (en, de, es, it, fr), `accent` (#RRGGBB, paid), `rows` (table), `size` (table: compact, standard, full), `view` (fixtures: round, date), `country` (fixtures and next match: TV channels), `round` (scores), `match` (match widgets: a match code or page path), `stat` (top players: goals, assists, cards, rating), `teams` (season race: names, comma separated), `player` (player card, tennis), `tournament` (tennis), `leagues` (ticker: more competitions), `style="one"` and `langs="true"` (match facts), `font="inherit"` (your theme's font), `radius` (0–20, corners).

== External services ==

This plugin shows data from SportStatsAPI (https://sportstatsapi.com), a sports data service. Without it the widgets cannot work.

* In the block editor, the preview of a block is the real widget: the editor loads the same script and data from api.sportstatsapi.com as visitors do (below), with your widget key if one is saved.
* Visitors' browsers load the widget script from https://api.sportstatsapi.com/api/widget/v1/embed.js on pages with a widget, and the script loads each widget's data, team logos and fonts from api.sportstatsapi.com and img.sportstatsapi.com. Sent: the visitor's IP address and browser details (as with any web request), the page's address (Origin), the widget's settings (widget, competition, team, language) and your widget key.
* In the block editor, when you type in the competition search, your server asks api.sportstatsapi.com for matching competitions (https://api.sportstatsapi.com/api/widget/v1/competitions; until that is available, the list at https://sportstatsapi.com/widgets/competitions.json), and for the teams of the chosen competition. Sent: the text typed, the competition chosen. Kept in a cache for 10 minutes (searches) and 12 hours (lists, teams).
* When you save a widget key, and once a day after that, your server asks api.sportstatsapi.com whether the key works. Sent: the key and your site's address. If the key is refused, widgets are hidden and admins see a notice.
* Only if you turn on "Search engines" in Settings → SportStatsAPI: your server loads each widget's data from api.sportstatsapi.com (sent: the widget's settings, and a paid key with your site's address) and keeps it 10 minutes.
* On Settings → SportStatsAPI, your server checks at most once an hour whether account.sportstatsapi.com offers Connect (nothing is sent but the request). When you click "Connect with SportStatsAPI", your browser goes to account.sportstatsapi.com with your site's address and the settings page address; there you sign in or create a free account with your email address, and your browser comes back with your widget key.

The plugin itself sets no cookies, does no tracking and adds no links or credits to your pages. With a free key, the SportStatsAPI script draws a credit link inside each widget, as the free plan's terms require (https://sportstatsapi.com/legal/terms/); a paid key removes it.

SportStatsAPI terms: https://sportstatsapi.com/legal/terms/
Privacy policy: https://sportstatsapi.com/legal/privacy/
Cookies: https://sportstatsapi.com/legal/cookies/

== Installation ==

1. Install and activate the plugin.
2. Settings → SportStatsAPI: click Connect (or paste your widget key), and pick the default theme, accent colour and language.
3. In the block editor, add a SportStatsAPI block (League table, Live scores, …) and choose a competition. Or put a shortcode in any post, page or widget area.

== Frequently Asked Questions ==

= Do I need an account? =

A free one: Connect creates it with your email address and brings the free key back (25,000 views a month; you agree that free widgets show a small "Data by SportStatsAPI" link). Without a key, widgets show only in the editor. A paid key opens every competition and widget.

= What is free and what needs a paid plan? =

Free, with the free key from Connect: league table, live scores, fixtures, results ticker, next match and team calendar, for the free competitions (25,000 views a month, up to 30,000 before they stop until the next month). Every other widget, every other competition, the accent colour and removing the credit link need a paid SportStatsAPI plan (https://sportstatsapi.com/pricing/). The plugin is the same in both cases: the service decides what a key shows.

= Does the plugin send data anywhere? =

Only to SportStatsAPI, the service that draws the widgets. See "External services" below for what is sent and when. The plugin sets no cookies and does no tracking.

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
6. All 19 widgets in the SportStatsAPI group of the block inserter.

== Changelog ==

= 0.1.0 =
* First version: all 19 widgets as blocks and shortcodes, Connect, settings, optional server fallback.
