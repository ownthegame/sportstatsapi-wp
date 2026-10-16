# SportStatsAPI Widgets for WordPress

Gutenberg blocks and shortcodes for the [SportStatsAPI](https://sportstatsapi.com/widgets/)
embeddable widgets: league tables, live scores, fixtures, results ticker, next match, team form.

See [PLAN.md](PLAN.md) for the architecture and roadmap, `readme.txt` for the
wordpress.org description.

## Development

```
composer install
composer lint   # PHPCS, WordPress coding standards
```

No JS build step: the editor script is plain `wp.element`.

License: GPL-2.0-or-later.
