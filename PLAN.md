# SportStatsAPI Widgets for WordPress: plan

## Architecture

The plugin is a thin wrapper around the official embed script
(`https://api.sportstatsapi.com/api/widget/v1/embed.js`). It never draws a
widget itself: it prints the markup the script expects and loads the script.

```
<div class="sportstats-widget" data-widget="table" data-competition="england--premier-league"
     data-lang="en" data-theme="auto" [data-team] [data-key] [data-accent] ...></div>
<script src="https://api.sportstatsapi.com/api/widget/v1/embed.js" async></script>
```

- `sportstatsapi-widgets.php`: bootstrap.
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

- 6 widgets as blocks and shortcodes: league table, live scores, fixtures,
  results ticker, next match, team form.
- Settings: widget key (paste), Connect (see below), default theme, accent, language,
  server fallback toggle (off by default).
- Server fallback: the public `/api/widget/v1/data` JSON, fetched with
  `wp_remote_get`, cached 10 minutes in a transient, printed as `<noscript>` HTML.
- readme.txt (wordpress.org format, External services section), uninstall.php,
  i18n (text domain `sportstatsapi-widgets`), PHPCS (WordPress standard).

## Later

- The other 13 widgets (match centre, facts, race, top players, calendar, season,
  squad, injuries, h2h, preview, player, tennis, box score).
- Elementor widgets (same render function), classic widget for old themes.
- Ticker with several competitions (paid key), round/country pickers with data.
- Block patterns ("Matchday sidebar") and a block variation per sport.
- Translations (de, es, it, fr) of the plugin UI; wordpress.org assets
  (banner 1544x500, icon 256x256, screenshots).

## Connect: GoalGate endpoint needed

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

The plugin checks `state` (per user, 15 minutes, single use) and the key format,
then saves the key. To switch Connect on: set `SPORTSTATSAPI_CONNECT_URL` (or the
`sportstatsapi_connect_url` filter) to the URL above; default is empty (button hidden).

Audit: `free_widget_key.create` with `via: wordpress` and the site host.

## Other GoalGate / site changes (nice to have)

- Team by code everywhere: `data-team` takes a team code server-side (`team=`), but
  the table and scores highlight a row by the team's *name* slug in embed.js
  (`slug(r.team)`), and table rows carry no `code`. The plugin therefore stores the
  name slug (`manchester-city`). Fix: add `code` to `WidgetTableRow` / match rows and
  let embed.js match `o.team` against the code too; then the plugin switches to codes
  (stable when a club is renamed).
- A public competition search (`GET /api/widget/v1/competitions?q=`, CORS, cached)
  would spare the plugin the 244 KB `sportstatsapi.com/widgets/competitions.json`
  (it is fine for now: fetched server-side, cached 12 h).
- An unknown/revoked free key makes even keyless free widgets fail ("Widget not
  available"). Consider falling back to keyless free behaviour for free widgets.

## wordpress.org checklist

- [x] GPL-2.0-or-later, all code ours, no minified code without source
- [x] readme.txt: header fields, Description, Installation, FAQ, Screenshots,
      Changelog, External services (what, when, what is sent, terms + privacy links)
- [x] No tracking, no remote calls on admin pages except the user's own previews,
      the editor search (on typing) and Connect (on click)
- [x] External script only from the service itself (allowed for SaaS)
- [x] Nonces + capability checks on every write; sanitize input, escape output
- [x] Text domain = slug; `load_plugin_textdomain` not needed (WP 4.6+ loads it)
- [x] uninstall.php removes options and transients
- [x] Plugin Check plugin: no errors (on the .distignore'd build)
- [ ] Banner, icon, screenshots in `.wordpress-org/` (designer)
- [ ] Slug check: `sportstatsapi-widgets` free on wordpress.org
- [ ] Submit, answer the review (usually 1–4 weeks), then SVN deploy (GitHub Action later)

## Questions for the owner

1. Keyless free widgets work today (no key, "Data by SportStatsAPI" link). Should the
   plugin work without a key (simplest, as now) or require Connect, so every site has a
   free key and its 50k views a month are counted?
2. Team form is a paid widget: keep it in the MVP block list with a "needs a plan"
   notice (as now), or swap it for calendar (free)?
3. Plugin name and slug: "SportStatsAPI Widgets" / `sportstatsapi-widgets`. OK?
4. Contributors field for wordpress.org: which wordpress.org user (Modulout?)
5. Build the Connect endpoint in GoalGate (account service) as described above?
