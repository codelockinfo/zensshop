# Admin Button Loading State Guide

## Global Function Available

A global `setBtnLoading()` function is now available on all admin pages (defined in `includes/admin-footer.php`).

## Usage

### Basic Example

```javascript
// Get the button element
const saveBtn = document.querySelector('button[type="submit"]');

// Show loading state
setBtnLoading(saveBtn, true);

// After save completes
setBtnLoading(saveBtn, false);
```

### With Form Submit

```javascript
document.querySelector("form").addEventListener("submit", function (e) {
  e.preventDefault();

  const submitBtn = this.querySelector('button[type="submit"]');
  setBtnLoading(submitBtn, true);

  // Your save logic here
  fetch("/api/save", {
    method: "POST",
    body: new FormData(this),
  })
    .then((response) => response.json())
    .then((data) => {
      // Success
    })
    .finally(() => {
      setBtnLoading(submitBtn, false);
    });
});
```

### With onclick Handler

```javascript
function saveSettings() {
  const btn = event.target;
  setBtnLoading(btn, true);

  // Your save logic
  setTimeout(() => {
    setBtnLoading(btn, false);
  }, 2000);
}
```

## What It Does

**When Loading (true):**

- Disables the button (prevents double-clicks)
- Shows spinning icon: `🔄 Saving...`
- Reduces opacity to 0.7
- Stores original button HTML

**When Done (false):**

- Re-enables the button
- Restores original button text/icon
- Restores full opacity
- Cleans up stored data

## Pages Already Using It

- ✅ `admin/homepage_products_settings.php`

## Pages That Should Use It

Add `setBtnLoading()` to these pages:

- `admin/header_info.php` - Save Changes button
- `admin/footer_info.php` - Save Changes button
- `admin/banner_settings.php` - Save buttons
- `admin/special_offers_settings.php` - Save buttons
- `admin/homepage_videos_settings.php` - Save buttons
- `admin/products/add.php` - Add Product button
- `admin/products/edit.php` - Update Product button
- `admin/settings.php` - Save Settings button
- Any other page with save/update buttons

## Implementation Pattern

1. Find the form submit handler or button click handler
2. Get the button element
3. Call `setBtnLoading(button, true)` before the save operation
4. Call `setBtnLoading(button, false)` in the `.finally()` block or after completion
