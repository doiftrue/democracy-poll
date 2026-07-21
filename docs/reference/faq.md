# Frequently asked questions

## Can visitors select more than one answer?

Yes. Enable multiple answers on the poll edit screen and set the maximum allowed number.

## Can visitors add their own answers?

Yes. Enable **Allow users to add answers** for the poll and make sure the global setting does not prohibit custom answers.

## Can voters change their vote?

Enable revoting for the poll. Vote cancellation depends on the server-side vote log.

## Can several people behind one IP address vote?

Enable **Allow multiple votes from the same IP address**. Guest browsers will be distinguished by a lightweight fingerprint, while logged-in visitors are identified by their WordPress account.

## Does it work with page caching?

The plugin automatically detects several common cache plugins. For another page cache, force-enable compatibility mode and purge every cache layer.

## Does uninstall remove plugin data?

Yes. Deleting the plugin through WordPress removes its options and data. Back up the database first if the data may be needed later.

## Is JavaScript optional?

No. The current front end requires JavaScript and targets modern browsers with ES module support. Internet Explorer 11 is not supported.

## Where can I get help?

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/democracy-poll/) or report a reproducible code issue on [GitHub](https://github.com/doiftrue/democracy-poll/issues).
