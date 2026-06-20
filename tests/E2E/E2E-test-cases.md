# End-to-End Test Cases

Manual checklist for validating Democracy Poll in a real WordPress install.

## Test Data

Prepare at least these polls:

- Single-choice poll with 3+ answers, revote enabled, custom answers enabled.
- Single-choice poll with 3+ answers, revote disabled.
- Multiple-choice poll with 4+ answers and max answers set to 2.
- Poll restricted to registered users.
- Closed poll with existing votes.
- Poll with a future close date.

Run front-end checks as:

- Anonymous visitor in a clean browser/session.
- Logged-in subscriber or low-privileged user.
- Administrator.
- Same visitor after page reload.
- Same visitor after clearing cookies, where relevant.

## Front-End Voting

### Single-choice poll

1. Open a page with a single-choice poll.
2. Select one answer.
3. Click Vote.
4. Confirm the poll switches to results view.
5. Confirm the selected answer is marked as the user vote.
6. Reload the page and confirm the already-voted state is preserved.

Expected result: exactly one vote is saved, result totals update, and duplicate voting is blocked for the same visitor.

### Multiple-choice poll

1. Open a page with a multiple-choice poll.
2. Select two answers.
3. Click Vote.
4. Confirm both selected answers are saved and marked in results.
5. Try selecting more answers than allowed before voting.

Expected result: allowed selections can be voted; extra selections are prevented or rejected with an error.

### Empty vote

1. Open any active poll.
2. Click Vote without selecting or entering any answer.

Expected result: vote is not submitted and the button/area gives visible feedback.

## Custom Answers

### Add custom answer in regular single-choice poll

1. Open a single-choice poll with custom answers enabled.
2. Click Add your answer.
3. Enter a custom answer.
4. Submit with the Vote button.
5. Confirm the custom answer appears in results and receives the vote.
6. Open admin logs and confirm the answer is marked as NEW where applicable.

Expected result: custom answer is created, voted for, and visible in front-end/admin data.

### Add custom answer by pressing Enter

1. Open a poll with custom answers enabled.
2. Click Add your answer.
3. Enter a custom answer.
4. Press Enter in the input.

Expected result: the vote is submitted exactly as if the Vote button was clicked.

### Close custom answer input

1. Click Add your answer.
2. Confirm the text input and close button appear.
3. Click the close button.

Expected result: the input disappears, Add your answer returns, limits are recalculated, and no vote is submitted.

### Regression: custom answer with hidden vote button

1. Enable global Hide vote button.
2. Create or open a non-multiple poll with revote enabled and custom answers enabled.
3. Open the poll on the front end.
4. Confirm clicking an existing answer submits a vote immediately.
5. Click Add your answer.
6. Confirm the Vote button appears while the custom answer input is visible.
7. Enter a custom answer and click Vote.
8. Repeat with Enter instead of clicking Vote.
9. Click Add your answer again, then close the input.

Expected result: custom answers can be submitted both by Vote button and Enter. Closing the input hides the Vote button again.

### Custom answer in multiple-choice poll

1. Open a multiple-choice poll with custom answers enabled.
2. Select one existing answer.
3. Add a custom answer.
4. Submit the vote.

Expected result: both the selected answer and custom answer are saved when within max answer limits.

## Revote And Vote Blocking

### Revote enabled

1. Vote in a poll with revote enabled.
2. Click Revote.
3. Confirm the confirmation dialog appears.
4. Cancel the dialog.
5. Confirm the vote remains unchanged.
6. Click Revote again and confirm.
7. Vote for a different answer.

Expected result: cancelling keeps the vote; confirming removes the previous vote and allows voting again.

### Revote disabled

1. Vote in a poll with revote disabled.
2. Inspect the results controls.

Expected result: no Revote button is shown and the user cannot vote again from the same identity/session.

### Logs disabled

1. Disable vote logs in settings.
2. Vote in a poll.
3. Check revote availability.
4. Reload and try voting again from the same browser.

Expected result: revote is disabled when logs are disabled; cookie-based duplicate protection still works as configured.

### Registered users only

1. Open a registered-users-only poll as anonymous visitor.
2. Confirm voting is blocked and login notice is shown.
3. Log in as subscriber.
4. Vote successfully.
5. Log out and log in as another user.

Expected result: anonymous visitors cannot vote; logged-in users can vote; votes are tracked per user where applicable.

## Results Visibility

### Results link enabled

1. Open an active poll before voting.
2. Click Results.
3. Return to Vote view.

Expected result: results can be viewed without submitting a vote and the Vote view remains usable.

### Results link disabled

1. Enable the option that hides the results link.
2. Open an active poll before voting.

Expected result: Results link is not shown before voting.

### Results hidden while voting is open

1. Enable the option that hides poll results globally.
2. Open an active poll.
3. Vote in the poll.

Expected result: results stay hidden while the poll is open, according to the configured behavior.

## Poll State

### Closed poll

1. Open a closed poll on the front end.
2. Try to vote or revote.

Expected result: voting controls are not available and results/closed notice are shown.

### Poll close date

1. Create a poll with a future close date.
2. Confirm it is open before that date.
3. Set the close date to a past date or close it from admin.
4. Reload the front end.

Expected result: poll changes from vote view to closed/results view.

## Sorting And Display

### Answer order on vote screen

1. Set global answer order to Random.
2. Open the poll several times in separate sessions.
3. Set answer order to Alphabetically and reload.
4. Set answer order to By ID and reload.

Expected result: vote screen follows the configured order.

### Answer order on results screen

1. Add votes so answers have different totals.
2. Set results order to By winner.
3. Reload results.
4. Set results order to Alphabetically and reload.

Expected result: results screen follows the configured order.

### Max poll height

1. Configure max poll height.
2. Open a poll with many answers.
3. Expand and collapse the answer list.
4. Vote after expanding.

Expected result: content collapses/expands correctly and voting still works.

## Shortcodes, Widget, And Archive

### Shortcode with explicit ID

1. Add `[democracy id="POLL_ID"]` to a post.
2. Open the post.

Expected result: the selected poll is rendered and works normally.

### Shortcode with current poll

1. Attach a poll to a post via metabox if available.
2. Add `[democracy id="current"]` to the post.
3. Open the post.

Expected result: the attached poll is rendered.

### Random/default shortcode

1. Add `[democracy]` to a post.
2. Open the post multiple times.

Expected result: an active poll is rendered according to shortcode behavior.

### Poll archive

1. Configure or create a poll archive page.
2. Open the archive page.
3. Check active and closed polls.

Expected result: archive lists polls and links/screens render correctly.

### Widget

1. Enable widget support.
2. Add Democracy Poll widget to a widget area.
3. Select a specific poll.
4. Switch widget to random/active poll mode if available.

Expected result: widget renders the expected poll and voting works inside the widget.

## Admin Flows

### Create poll

1. Create a new poll with question, answers, note, and enabled options.
2. Save.
3. Open the poll list and edit page.
4. Open the front end.

Expected result: poll is saved, listed, editable, and rendered on the front end.

### Edit poll answers

1. Edit an existing poll.
2. Rename an answer.
3. Add a new answer.
4. Delete or empty an answer where supported.
5. Save and verify front-end output.

Expected result: answer changes are saved without corrupting vote counts.

### Activate and deactivate poll

1. Deactivate an active poll from the poll list.
2. Open a page that renders random/default poll.
3. Reactivate it.

Expected result: inactive poll is not selected as active/random; reactivated poll can appear again.

### Logs page

1. Vote as anonymous visitor.
2. Vote as logged-in user.
3. Open Logs.
4. Filter/search by poll, IP, or NEW answers where available.
5. Delete logs only.
6. Delete logs and votes.

Expected result: logs show correct user/IP/answer data; deletion actions update logs and vote counts correctly.

### Text customization

1. Open Text changes settings.
2. Change a front-end label, for example Vote.
3. Save.
4. Open front end.
5. Reset the text.

Expected result: customized text appears on front end and reset restores default text.

### Design settings

1. Change theme/design settings.
2. Save.
3. Open front end.
4. Check vote and results screens.

Expected result: selected styles are applied without breaking layout or controls.

## Cache Compatibility

### Cache mode rendering

1. Enable Force cache compatibility mode or activate a supported cache plugin.
2. Open a poll as a visitor who has not voted.
3. Vote.
4. Reload the page.
5. Open the same page in a different clean browser/session.

Expected result: voted visitor sees voted/results state; clean visitor sees vote state.

### Cached already-voted detection

1. Vote in a poll with logs enabled.
2. Clear only cookies if needed to simulate unclear state.
3. Reload or interact with the cached page.

Expected result: plugin resolves the real voted state and blocks duplicate voting.

## Security And Validation

### Invalid answer submission

1. Try to submit a manually modified request with an answer ID from another poll.
2. Try to submit too many answers in a multiple poll.
3. Try to submit empty custom answer text.

Expected result: invalid submissions are rejected or sanitized; no cross-poll vote is recorded.

### Custom answer sanitization

1. Submit a custom answer containing HTML/script-like text.
2. Check front-end result, admin answer list, and logs.

Expected result: unsafe markup is stripped or escaped according to allowed answer tags.

## Release Smoke

Before release, run this reduced smoke set:

- Create poll, edit poll, and render by shortcode.
- Single-choice vote.
- Multiple-choice vote.
- Custom answer vote.
- Hidden vote button custom answer regression.
- Revote enabled and disabled.
- Registered-users-only poll.
- Closed poll.
- Logs page check.
- Widget render.
- Archive render.
- Cache compatibility mode check.
