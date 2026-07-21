# Markup and CSS

Use **Democracy Poll → Design** for presets and small adjustments. For theme-level customization, place CSS in the theme or a site-specific stylesheet rather than editing files inside the plugin.

## Useful presentation classes

| Element | Class |
| --- | --- |
| Poll wrapper | `.democracy` |
| Poll title | `.dem-poll-title` |
| Voting answer list | `.dem-vote` |
| Answer item | `.dem-answer-item` |
| Result answer list | `.dem-answers` |
| Result progress bar | `.dem-graph`, `.dem-fill` |
| Poll controls | `.dem-bottom` |
| Buttons | `.dem-button` |
| Links | `.dem-link` |
| Voter's answer | `.dem-voted-this` |
| Winning answer | `.dem-winner` |

```css
.article-poll .dem-poll-title {
	font-size: 1.35rem;
}

.article-poll .dem-winner .dem-fill {
	background: var(--wp--preset--color--accent-2);
}
```

Classes ending in `_js` exist for internal JavaScript behavior. Do not rename, hide indiscriminately, or target them as a stable styling API.

## Title markup

The global title template and PHP rendering argument must contain `{question}`:

```php
get_democracy_poll( [
	'poll'         => 12,
	'title_markup' => '<h2 class="poll-heading">{question}</h2>',
] );
```

Allowed markup is sanitized. Do not place untrusted HTML into rendering arguments or filters.
