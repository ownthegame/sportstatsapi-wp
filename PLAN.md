# Live Scores & League Tables – SportStatsAPI Widgets (WordPress): plan

Slug and text domain: `live-scores-league-tables` (PHP prefix stays `sportstatsapi_`).

## Architecture

The plugin is a thin wrapper around the official embed script
(`https://api.sportstatsapi.com/api/widget/v1/embed.js`). It never draws a
widget itself: it prints the markup the script expects and loads the script.

```
<div class="sportstats-widget" data-widget="table" data-competition="england--premier-league"
     data-lang="en" data-theme="auto" [data-team] [data-key] [data-accent] ...></div>
<script src="https://api.sportstatsapi.com/api/widget/v1/embed.js" async></script>
```

- `live-scores-league-tables.php`: bootstrap.
- `includes/widgets.php`: the widget list (one PHP array), the markup builder, the
  shortcode, dynamic block rendering, script enqueue, the optional server fallback.
- `includes/settings.php`: Settings → SportStatsAPI (key, Connect, defaults).
- `includes/rest.php`: two editor-only REST routes (`edit_posts`), cached proxies of
  public data: competition search (`sportstatsapi.com/widgets/competitions.json`,
  about 3,000 competitions, kept 12 h) and teams (`/api/widget/v1/teams?c=`, kept 12 h).
- `blocks/<widget>/block.json`: dynamic blocks (no `save`), rendered by the same PHP
  as the shortcode, so blocks and shortcodes never drift.
- `assets/editor.js`: one generic editor component in plain `wp.element` /
  `wp.components` (no build step). The preview is the real widget: the embed
  script is loaded into the editor's (iframe) document.

The script is enqueued from the render callback, so it loads once and only on
pages that contain a block or a shortcode.

## MVP (this PR)

- All 19 GoalGate widget kinds as blocks (inserter group "SportStatsAPI", free first)
  and shortcodes. Free: league table, live scores, fixtures, results ticker, next match,
  team calendar. Paid ("(paid plan)" in the inserter, a notice with a free key): match
  centre, match facts, preview, head to head, box score, team form, club season, squad,
  injuries, season race, top players, player card, tennis.
- Connect is required (owner, 2026-10-17): without a working key the front end shows
  nothing (editors see a "Connect SportStatsAPI to show this widget" link); the editor
  preview works keyless with a "Connect to publish" notice. A refused key hides the
  widgets and shows an admin notice.
- Settings: widget key (paste), Connect (see below), default theme, accent, language,
  server fallback toggle (off by default).
- Server fallback: the public `/api/widget/v1/data` JSON, fetched with
  `wp_remote_get`, cached 10 minutes in a transient, printed as `<noscript>` HTML.
- readme.txt (wordpress.org format, External services section), uninstall.php,
  i18n (text domain `live-scores-league-tables`), PHPCS (WordPress standard).

## Later

- Pickers with data for player (a players endpoint) and match (a match search);
  today these are text fields (player slug, match code or page path).
- Elementor widgets (same render function), classic widget for old themes.
- Ticker with several competitions (paid key), round/country pickers with data.
- Block patterns ("Matchday sidebar") and a block variation per sport.
- Translations (de, es, it, fr) of the plugin UI; wordpress.org assets
  (banner 1544x500, icon 256x256, screenshots).

## Connect (GoalGate PR #435)

Today a free widget key (`ssw_free_...`) is created only on the account page
(`POST /account/...` → `freeWidgetKey`, session + CSRF). The plugin side of Connect
is built and switched off until this exists:

`GET https://account.sportstatsapi.com/connect/wordpress?site=<home URL>&return=<URL>&state=<random>`

1. Not logged in: the normal login (magic link / password), then back here.
2. A confirm page: "Connect <site> to SportStatsAPI?" with the account's key
   (free key created with `db.NewFreeWidgetKey` if missing; a paid widget key
   `wk_live_` offered instead when the account has one; then also add the site's host
   to the key's domains).
3. On confirm (POST, CSRF): redirect `303` to `return` with `?state=<same>&key=<key>`.
   `return` must be `https:` (or `http://localhost` for testing) and its host must equal
   the host of `site`; anything else: an error page, no redirect.
4. Cancel: redirect to `return?state=<same>&error=denied`.

The plugin sends `return` = its settings page, checks `state` (per user, 15 minutes,
single use) and the key format, then saves the key; `error=denied` shows a notice.
The URL above is the default (`SPORTSTATSAPI_CONNECT_URL` or the
`sportstatsapi_connect_url` filter override it). The button shows only when the
endpoint answers something other than 404 (probe cached 1 h) and the return URL is
https (http on localhost) on the site's host and port.

Audit: `free_widget_key.create` with `via: wordpress` and the site host.

## Other GoalGate / site changes (nice to have)

- Team by code everywhere: `data-team` takes a team code server-side (`team=`), but
  the table and scores highlight a row by the team's *name* slug in embed.js
  (`slug(r.team)`), and table rows carry no `code`. The plugin therefore stores the
  name slug (`manchester-city`). Fix: add `code` to `WidgetTableRow` / match rows and
  let embed.js match `o.team` against the code too; then the plugin switches to codes
  (stable when a club is renamed).
- Competition search: `GET /api/widget/v1/competitions?q=&limit=20` (GoalGate PR #436),
  each query cached 10 min; while it answers 404 the plugin searches the full
  `competitions.json` (cached 12 h).
- A refused key (UNKNOWN_KEY, WRONG_WEBSITE) is checked by the plugin after a save and
  daily (WP-Cron): the widgets then go out without `data-key` and admins see a notice.

## wordpress.org checklist

- [x] GPL-2.0-or-later, all code ours, no minified code without source
- [x] readme.txt: header fields, Description, Installation, FAQ, Screenshots,
      Changelog, External services (what, when, what is sent, terms + privacy links)
- [x] No tracking, no remote calls on admin pages except the user's own previews,
      the editor search (on typing), the key check (on save, daily), the Connect
      probe (settings page, at most hourly) and Connect (on click)
- [x] External script only from the service itself (allowed for SaaS)
- [x] Nonces + capability checks on every write; sanitize input, escape output
- [x] Text domain = slug; `load_plugin_textdomain` not needed (WP 4.6+ loads it)
- [x] uninstall.php removes options and transients
- [x] Plugin Check plugin: no errors (on the .distignore'd build)
- [ ] Banner and icon in `.wordpress-org/` (designer)
- [ ] Slug check: `live-scores-league-tables` free on wordpress.org
- [x] Screenshots 1200x900 in `.wordpress-org/` (made from the test site)
- [ ] Submit, answer the review (usually 1–4 weeks), then SVN deploy (GitHub Action later)

## Owner decisions (2026-10-17)

- Connect required, no keyless mode on the front end.
- Calendar replaces team form among the free six; team form stays as a 7th, paid block.
- Name "Live Scores & League Tables – SportStatsAPI Widgets", slug `live-scores-league-tables`.

## Open questions

0. Tennis: GoalGate serves it keyless as a free widget (`widgetKinds["tennis"] = true`), the
   owner's list makes it paid; the plugin labels it paid. Make GoalGate paid too, or label it free?

1. Contributors field for wordpress.org: which wordpress.org user (currently `sportstatsapi`)?
2. Merge GoalGate #435 / #436 before the wordpress.org submission (Connect must work for
   new sites: with the endpoint missing, a new site can only paste a key).
