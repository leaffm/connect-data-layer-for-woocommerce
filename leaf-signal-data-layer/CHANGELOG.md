# Changelog

All notable changes to Leaf Signal will be documented in this file.

---

## [2.1.0] - 2026-03-12

### Added
- `is_first_order()` method to determine if the current order is the customer's first. Returns a boolean — `true` for new customers, `false` for returning ones. Works for both registered and guest customers by querying completed and processing orders by billing email.
- Admin notice shown to administrators when the plugin is active but the tracking script URL has not been configured yet. The notice includes a direct link to the settings page and dismisses automatically once a URL is saved.

### Changed
- Plugin renamed from **Leaf Connect Data Layer** to **Leaf Signal** across the plugin header, admin menu, and settings page title.
- Settings page moved from **Settings → Leaf Data Layer** to a dedicated **top-level sidebar menu** entry with the Leaf logo as a custom SVG icon.
- Admin menu icon now uses the Leaf SVG logo embedded as inline base64, with `fill="#000000"` so it inherits the active WordPress admin color scheme.
- `logState` event field renamed from `customerType` (string) to `isFirstOrder` (boolean) for clearer semantics.

### Removed
- **Facebook Pixel ID** and **Google Analytics ID** fields from the settings page — these are no longer needed as plugin-level settings.
- `connect-fb` and `connect-ga` custom element injection from the `page_view` script block.

---

## [2.0.0] - 2026-03-12

### Added
- Settings page under **Settings → Leaf Data Layer** to configure tracking values without editing code:
  - Tracking Script URL
  - Facebook Pixel ID
  - Google Analytics ID
- `Leaf_CDL_Settings` class to manage plugin options via the WordPress Options API.
- `Leaf_CDL` class wrapping all event logic to avoid global function name collisions with other plugins.
- `map_address()` helper to normalize WooCommerce address arrays into a consistent shape, with a null-filled skeleton for missing addresses.
- `build_item()` helper to construct a standard product item array, used across all events for consistency.
- `get_image_url()` helper with a null-safe check before accessing the image URL.
- `get_variation_label()` helper to resolve a variation's display name from its ID.

### Changed
- **All PHP values echoed into JavaScript** now go through `wp_json_encode()` instead of raw string interpolation, eliminating XSS risk.
- **`add_to_cart` event** is now queued in the WooCommerce session on the `woocommerce_add_to_cart` hook, then flushed to the page on `wp_footer`. This makes the event reliable for both standard form submits and AJAX add-to-cart flows (mini cart, block cart).
- **Variation name resolution** now takes the first non-empty attribute value instead of the last one.
- **`logState` event** now correctly reflects login state using `is_user_logged_in()` instead of always outputting `"Logged Out"`.
- **`customerInfo`** now includes the `phone` field, which was missing in the previous version.
- Plugin is now initialized via `plugins_loaded` hook through a single `leaf_cdl_init()` bootstrap function.
- Plugin header updated with `Requires Plugins: woocommerce` declaration.
- Script and pixel IDs are no longer hardcoded — all configurable values moved to the settings page.

### Fixed
- `generate_initiate_checkout` no longer outputs an undefined `$data_layer` variable when the cart is empty. The function now returns early.
- `generate_purchase` no longer has an undefined `$data_layer` on repeat page loads after the WC session flag was set. Deduplication is now handled entirely by JS `sessionStorage`, removing the need for the PHP session guard around the data build.
- `generate_log_state` (now `build_log_state`) no longer causes a fatal error on guest checkouts. `$order->get_user()` returns `false` for guests — this is now guarded before calling customer methods.
- Image URL references no longer access `$image[0]` directly on a potentially falsy result; a null-safe helper is used instead.

### Removed
- Client-specific hardcoded values: Facebook Pixel ID, GA measurement ID, tracking script URL, specific SKU subscription check, and hardcoded gift card price.
- `generate_log_state` as a standalone hook-triggered function — it is now a private method (`build_log_state`) called internally within `generate_purchase`, and both events are pushed in a single script block.
- Bare global PHP functions (`get_store_id`, `get_page_type`, `get_product_brand`, `get_variation_name`, `remove_protocols_and_www`) — all moved into the `Leaf_CDL` class as private methods.

---

## [1.0.0] - Initial release

- Single-file plugin (`leaf-connect-dl.php`).
- Tracked events: `page_view`, `add_to_cart`, `view_item`, `initiate_checkout`, `logState`, `purchase`.
- Hardcoded tracking script URL, Facebook Pixel ID, and Google Analytics ID.
