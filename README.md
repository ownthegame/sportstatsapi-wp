# Live Scores & League Tables (SportStatsAPI widgets for WordPress)

Gutenberg blocks and shortcodes for the [SportStatsAPI](https://sportstatsapi.com/widgets/)
embeddable widgets: league tables, live scores, fixtures, results ticker, next match, team calendar, team form.

See [PLAN.md](PLAN.md) for the architecture and roadmap, `readme.txt` for the
wordpress.org description.

## Development

```
composer install
composer lint   # PHPCS, WordPress coding standards
```

No JS build step: the editor script is plain `wp.element`.

## Release (no CI needed)

```
bin/build-zip.sh   # build/live-scores-league-tables.zip, plugin files only (.distignore)
```

No GitHub Actions workflow is used or required. `.wordpress-org/` holds the
wordpress.org assets (banners, icons, screenshots); they go to SVN `/assets`,
never into the zip.

First submission (wordpress.org account `modulout`): upload the zip at
https://wordpress.org/plugins/developers/add/ (expected slug
`live-scores-league-tables`, from the plugin name). After approval, with the SVN
credentials of that account:

```
svn co https://plugins.svn.wordpress.org/live-scores-league-tables svn
rsync -a --delete build/live-scores-league-tables/ svn/trunk/
rsync -a --delete .wordpress-org/ svn/assets/ --exclude README.txt
cd svn && svn add --force trunk assets && svn cp trunk tags/0.1.0
svn propset svn:mime-type image/png assets/*.png && svn propset svn:mime-type image/svg+xml assets/*.svg
svn ci -m "0.1.0" --username modulout
```

License: GPL-2.0-or-later.
