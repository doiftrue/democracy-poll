# Settings reference

The following options are found under **Democracy Poll → Settings**.

## Main options

| Setting | Effect |
| --- | --- |
| Allow multiple votes from the same IP address | Distinguishes guest browsers on a shared IP with a browser fingerprint. Each browser is still limited to one vote. |
| Cookie lifetime | Sets how many days browser and server voting state is remembered. |
| Poll title HTML template | Wraps the question; must contain `{question}`. |
| Polls archive page ID | Connects the results-screen archive link to a WordPress page. |

## Global poll defaults

| Setting | Effect |
| --- | --- |
| Answer order on vote screen | Sets the default order presented to voters. |
| Answer order on results screen | Sets the default order after voting. |
| Only registered users allowed | Requires a WordPress account for voting. |
| Prohibit users from adding answers | Disables visitor-submitted answers by default. |
| Remove revote possibility | Prevents users from changing their vote by default. |
| Don't show poll results | Hides results while voting is open. |
| Don't show poll results link | Hides the pre-vote results link; results still appear after voting. |
| Hide vote button | Votes immediately on answer click for compatible single-choice, revote-enabled polls. |
| Disable post metabox | Removes poll attachment controls from posts. |

## Integration and administration

| Setting | Effect |
| --- | --- |
| Force cache compatibility | Enables cache-safe rendering for an otherwise undetected page cache. |
| Toolbar menu | Shows plugin shortcuts in the WordPress admin toolbar. |
| TinyMCE button | Adds the classic-editor poll insert button. |
| Widget | Registers the classic Democracy Poll widget. |
| Alternate IP detection | Uses additional request sources when `REMOTE_ADDR` is wrong; may reduce spoofing resistance. |
| Access roles | Lets selected roles manage polls. Administrators always have access. |

Design controls are covered in [Design and appearance](/user-guide/design).
