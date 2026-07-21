# PHP functions

The following global functions are intended for theme and site-plugin integrations.

## Render one poll

### `democracy_poll( array $args = [] ): void`

Prints a poll. It accepts:

| Argument | Type | Default | Description |
| --- | --- | --- | --- |
| `poll` | `int\|object` | `0` | Poll ID or poll object. `0` selects a random active poll. |
| `from_post` | `int\|object` | `0` | Post on which the poll is displayed. |
| `title_markup` | `string` | `''` | Title HTML containing the required `{question}` placeholder. |

```php
if ( function_exists( 'democracy_poll' ) ) {
	democracy_poll( [
		'poll'         => 12,
		'from_post'    => get_the_ID(),
		'title_markup' => '<h2 class="survey-title">{question}</h2>',
	] );
}
```

### `get_democracy_poll( array $args = [] ): string`

Accepts the same arguments and returns the markup. It returns `Poll not found` when the requested poll does not exist.

### `get_democracy_poll_results( array $args = [] ): string`

Returns a poll's result screen. Supported arguments are `poll` and `title_markup`. When an open poll hides results, the function returns a translated notice instead.

## Retrieve a poll

### `democracy_get_poll( int|object $poll_id ): DemocracyPoll\Poll`

Creates a poll domain object for a poll ID or database object. Check its `id` property before using the result.

### `get_post_poll_id( int $post_id = 0 ): int|string`

Returns the poll attached to a post. With `0`, it uses the current post. A stored selector may be a numeric ID, `last`, or `rand`.

## Render or query multiple polls

### `democracy_archives( array $args = [] ): void`

Prints the archive returned by `get_democracy_archives()`.

### `get_democracy_archives( array $args = [] ): string`

Returns a paginated archive. Common arguments:

| Argument | Default | Description |
| --- | --- | --- |
| `title_markup` | `''` | Poll title HTML containing `{question}`. |
| `active` | `null` | `1` for active, `0` for inactive, `null` for either. |
| `open` | `null` | `1` for open, `0` for closed, `null` for either. |
| `screen` | `'voted'` | `vote` or `voted`. |
| `per_page` | `10` | Polls per page. |
| `add_from_posts` | `true` | Include links to posts where each poll appeared. |
| `orderby` | `[]` | Column/order definition or `rand`. |

### `get_dem_polls( array|string $args = [] ): string|array|int`

Provides the lower-level list query. In addition to the arguments above it supports `paged`, `wrap`, and `return`. Set `return` to `objects` to receive `Poll[]`; otherwise it returns HTML. After a paginated call, `get_dem_polls( 'get_found_rows' )` returns the total matching count.

```php
$open_polls = get_dem_polls( [
	'active' => 1,
	'open'   => 1,
	'return' => 'objects',
	'orderby' => [ 'id DESC' ],
] );
```

Legacy positional rendering arguments remain supported for compatibility, but new integrations should use argument arrays.
