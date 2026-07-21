# Troubleshooting

## The poll does not appear

- Confirm the plugin is active and the shortcode contains a valid poll ID.
- Make sure the poll has not been deleted.
- For a random poll, confirm at least one poll is active.
- Clear WordPress, page-cache, CDN, and browser caches.

## Voting returns stale content

Enable **Force-enable compatibility mode for page cache plugins** under **Democracy Poll → Settings**, purge all caches, and test in a private browser window.

## A visitor is already marked as voted

Visitors may share an IP address. Consider enabling **Allow multiple votes from the same IP address**, which distinguishes guest browsers by fingerprint. Also check the cookie lifetime and existing vote logs.

## Results are unavailable

Check both the global and per-poll options for showing results. Results may intentionally remain hidden until voting closes.

## IP addresses are incorrect

Correct the trusted-proxy configuration at the server or CDN first. The plugin's alternate IP-detection option is a fallback and may make spoofing easier.

## Styles do not match the preview

Purge caches and inspect **Democracy Poll → Design → All CSS styles currently in use**. Theme or site CSS with more specific selectors may override the generated rules.

For unresolved problems, search or open a topic in [WordPress.org support](https://wordpress.org/support/plugin/democracy-poll/) and include the plugin version, WordPress version, reproduction steps, and relevant browser-console or PHP errors. Do not publish real voter IP addresses.
