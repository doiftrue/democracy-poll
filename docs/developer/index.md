# Integration overview

Democracy Poll exposes WordPress-style integration points for themes and site plugins:

- global PHP rendering and query functions;
- `[democracy]` and `[democracy_archives]` shortcodes;
- actions fired after voting and poll administration events;
- filters for poll data, rendered screens, answer objects, access, and cache detection;
- stable presentation classes for CSS customization.

Load integrations after plugins are available and guard calls with `function_exists()` or `has_filter()` as appropriate. Prefer the documented functions and hooks over direct database queries or internal service classes.

::: warning Compatibility
Namespaced classes and internal services may change between releases. Review `COMPAT:` entries in the plugin changelog before upgrading a site that uses PHP integrations.
:::

The front end requires the plugin's JavaScript. There is currently no documented public JavaScript API; classes ending in `_js` and `data-dem-act` attributes are internal behavior hooks and should not be used as an integration contract.
