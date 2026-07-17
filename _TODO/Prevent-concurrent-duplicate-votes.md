# Prevent concurrent duplicate votes

## Problem

Repeat-vote checks currently run before the vote counters and log row are written. Two or more requests for the same voter identity can arrive concurrently, perform the lookup before either request inserts its log, and all pass the check.

The normal UI makes this unlikely, but a client can intentionally send parallel requests. Depending on the active identity mode, the duplicated identity is:

- the WordPress `user_id` for a logged-in user;
- the guest IP address when `allow_same_ip_votes` is disabled;
- the browser fingerprint when `allow_same_ip_votes` is enabled;
- the guest IP address when fingerprint calculation fails.

This race can increment answer and voter counters more than once and create duplicate active log rows for one identity.

## Proposed solution

Add a nullable `active_vote_key` column to `democracy_log` and a unique index on `(qid, active_vote_key)`.

Build the key on the server from the effective voter identity:

```text
user:{user_id}
ip:{normalized_ip}
fingerprint:{fingerprint}
```

Hash the namespaced value before storing it. The namespace prevents equal-looking values of different identity types from colliding.

Before registering a vote, clear `active_vote_key` to `NULL` on matching expired rows. Historical rows remain available in the log, while MySQL permits multiple `NULL` values in the unique index.

The vote flow should then reserve the identity by inserting its log row. If the unique index rejects the insert, return the normal already-voted response without changing counters. If a later counter update fails, delete the reserved log row and roll back any created custom answer.

Revote should delete the active log row together with its key. Changing `allow_same_ip_votes` changes the effective key for future checks; existing, non-expired logs must still be queried by their stored IP or fingerprint before a new reservation is attempted.

## Tests

Use two independently created requests with the same effective identity and hold the first request between its check and insert. Verify that only one request succeeds, counters increase once, and only one active log row exists. Cover `user_id`, IP, fingerprint, expiration, and switching `allow_same_ip_votes` in both directions.
