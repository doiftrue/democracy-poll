# Shortcodes

## `democracy`

Displays one poll.

| Attribute | Values | Behavior |
| --- | --- | --- |
| `id` | numeric poll ID | Displays that poll. |
| `id` | `last` | Displays the most recent open poll. |
| `id` | `current` | Displays the poll attached to the current post; falls back to a random poll. |
| omitted | — | Displays a random active poll. |

```text
[democracy id="12"]
```

## `democracy_archives`

Displays a paginated poll archive.

| Attribute | Default | Description |
| --- | --- | --- |
| `title_markup` | empty | Title HTML containing `{question}`. |
| `active` | unset | `1` for active or `0` for inactive polls. |
| `open` | unset | `1` for open or `0` for closed polls. |
| `screen` | `voted` | Initial screen: `vote` or `voted`. |
| `per_page` | `10` | Number of polls per page. |
| `add_from_posts` | `1` | Whether to show links to posts containing the poll. |
| `orderby` | empty | Database column name or `rand`. |

```text
[democracy_archives active="1" open="1" screen="vote" per_page="20"]
```

For complex archive queries and custom wrappers, use the [PHP functions](/developer/php-functions).
