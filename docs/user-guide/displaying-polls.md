# Display polls

## In post or page content

Insert a shortcode block and use a poll ID:

```text
[democracy id="12"]
```

Other selectors are available:

```text
[democracy]             Random active poll
[democracy id="last"]   Most recent open poll
[democracy id="current"] Poll attached to the current post, or a random poll as fallback
```

See the complete [shortcode reference](/reference/shortcodes).

## In a widget area

Enable the widget under **Democracy Poll → Settings**, then add **Democracy Poll** under **Appearance → Widgets**. See [Widgets](./widgets).

## In a theme template

Use the public rendering function and guard against plugin deactivation:

```php
<?php if ( function_exists( 'democracy_poll' ) ) : ?>
	<div class="sidebar-poll">
		<?php democracy_poll( [ 'poll' => 12 ] ); ?>
	</div>
<?php endif; ?>
```

Use `get_democracy_poll()` when you need the HTML as a string instead of printing it directly.

## Poll archive

Create or select an archive page under **Democracy Poll → Settings**, or insert:

```text
[democracy_archives]
```

The archive shows result screens by default and paginates at 10 polls per page. See [Shortcodes](/reference/shortcodes#democracy-archives).
