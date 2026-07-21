# Caching

Full-page caches can store a poll screen generated for one visitor and serve it to another. Democracy Poll has a compatibility mode that lets its front-end logic restore the correct visitor state.

The plugin detects several common page-cache plugins automatically. For an unsupported cache layer, enable **Force-enable compatibility mode for page cache plugins** under **Democracy Poll → Settings** or use:

```php
add_filter( 'dem_cachegear_status', '__return_true' );
```

After changing cache behavior:

1. purge the WordPress page cache;
2. purge any reverse-proxy or CDN cache;
3. test voting and result views in separate private browser sessions.

Do not cache the plugin's AJAX responses. If custom optimization removes or delays the Democracy Poll script, voting will not work because the current front end requires JavaScript.
