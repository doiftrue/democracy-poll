# Hooks and filters

Use hooks from a theme's `functions.php` or, preferably, a site-specific plugin.

## Voting events

### `dem_voted`

Runs after the vote totals and log are saved and the browser cookie is set.

```php
add_action( 'dem_voted', function ( string $answer_ids, DemocracyPoll\Poll $poll ): void {
	// $answer_ids is a comma-separated list of saved answer IDs.
}, 10, 2 );
```

### `dem_vote_deleted`

Runs after a voter uses revoting to remove their saved vote. Receives the current `DemocracyPoll\Poll` object.

## Front-end rendering filters

| Filter | Arguments | Purpose |
| --- | --- | --- |
| `dem_vote_screen` | `$html, $poll` | Change the complete voting-screen markup. |
| `dem_result_screen` | `$html, $poll` | Change the complete result-screen markup. |
| `dem_vote_screen_answer` | `$answer` | Change an answer object before voting markup is built. |
| `dem_result_screen_answer` | `$answer` | Change an answer object before result markup is built. |
| `dem_result_screen_answers` | `$answers, $poll` | Filter or reorder all result answers. |
| `dem_get_poll` | `$poll_data` | Filter raw poll data loaded from storage. |
| `dem_set_answers` | `$answers, $poll` | Filter answer objects assigned to a poll. |
| `democracy__allowed_tags` | `$allowed_tags` | Extend the HTML allowlist used by the plugin. |
| `dem_sanitize_answer_data` | `$data, $context` | Filter sanitized answer data. |

::: danger Preserve behavior hooks
When replacing complete vote or result markup, preserve the plugin's `_js` classes, required `data-*` attributes, input values, and controls. Removing them breaks voting. Prefer targeted answer or CSS filters when possible.
:::

## Query, access, and infrastructure

### `get_dem_polls_sql_clauses`

Filters `where`, `orderby`, and `limit` clauses used by `get_dem_polls()`. Values are inserted into SQL; return only trusted, correctly prepared clauses.

### `dem_get_ip`

Filters the detected voter IP address. Do not trust arbitrary forwarded headers without validating a trusted proxy chain.

### `dem_cachegear_status`

Return `true` to force page-cache mode or `false` to disable automatic detection. Leave the value `null` to use the plugin's built-in detection.

```php
add_filter( 'dem_cachegear_status', '__return_true' );
```

### `dem_super_access`

May grant full plugin access to a user who does not already inherit it. It cannot revoke access already granted by plugin roles or `manage_options`.

## Administrative extension points

The plugin also exposes hooks for specialized admin integrations:

- `dem_poll_inserted( $poll_id, $update )`;
- `dem_answers_deleted( $answer_ids, $poll_id )`;
- `demadmin_sanitize_poll_data( $data, $original_data )`;
- `demadmin_after_question( $html, $poll )`;
- `demadmin_edit_poll_answer( $answer )`;
- `demadmin_after_answer( $html, $answer )`;
- `dem_admin_polls_list_answers_column_row( $html, $answer )`;
- `dem_delete_only_logs( $log_ids, $result )`;
- `dem_delete_logs_and_votes( $log_ids, $result, $answer_total, $voter_total )`.
