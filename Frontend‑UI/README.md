# Frontend-UI

This folder contains all **shared frontend assets** for the Alumni Data Management System — the main stylesheet, additional styles, JavaScript entry point, and static images used across both portals.

---

## Responsibilities

- Provide the main shared CSS file defining the design system (colors, typography, spacing, components).
- Provide supplementary CSS for additional UI overrides or page-specific styles.
- Serve as the JavaScript entry point (`index.php`) for shared UI behavior.
- Store static assets: logo, COC image.

---

## File List

| File | Purpose |
|------|---------|
| `style.css` | Main shared stylesheet — CSS custom properties, layout, all components |
| `style_additions.css` | Supplementary styles — overrides or additions on top of `style.css` |
| `index.php` | JS entry point and shared UI behavior (sidebar toggle, image zoom, polling) |
| `logo.png` | ADMS / COC logo used in headers and login pages |
| `COC.jpeg` | College of Computer image used in UI |

---

## Linking Assets

From any portal page, link assets using a relative path back to `Frontend-UI/`:

```html
<link rel="stylesheet" href="../Frontend-UI/style.css">
<link rel="stylesheet" href="../Frontend-UI/style_additions.css">
<script src="../Frontend-UI/index.php" defer></script>
```

---

## CSS Design System (`style.css`)

The stylesheet uses **CSS custom properties** for all theme values. Always use variables — never hardcode colors or sizes.

### Color Tokens

```css
:root {
    --green:        #2d6a4f;   /* Primary brand color */
    --green-light:  #52b788;   /* Hover / accent */
    --green-dark:   #1b4332;   /* Active / pressed */
    --bg:           #f8f9fa;   /* Page background */
    --surface:      #ffffff;   /* Cards, panels */
    --border:       #dee2e6;   /* Borders and dividers */
    --text:         #212529;   /* Primary text */
    --text-muted:   #6c757d;   /* Secondary / placeholder text */
    --danger:       #dc3545;   /* Error, delete actions */
    --warning:      #ffc107;   /* Warnings, pending states */
}
```

### Key Components

| Class | Description |
|-------|-------------|
| `.sidebar` | Left navigation panel |
| `.sidebar.open` | Mobile off-canvas open state |
| `.hamburger` | Mobile menu toggle button |
| `.card` | Content panel with shadow and border |
| `.btn`, `.btn-primary`, `.btn-danger` | Action buttons |
| `.badge`, `.badge-unread` | Notification count indicators |
| `.table-responsive` | Scrollable table wrapper for mobile |
| `.announcement-img` | Announcement image with zoom cursor |
| `.zoom-overlay` | Full-screen image zoom overlay |

---

## Supplementary Styles (`style_additions.css`)

Use this file for:
- Page-specific styles that don't belong in the main design system.
- Minor overrides to `style.css` components without editing the source.
- Any styles added after the initial design was finalized.

Do not duplicate rules already defined in `style.css` — extend or override only what is needed.

---

## Shared JS (`index.php`)

### Sidebar Toggle (Mobile)

```javascript
document.querySelector('.hamburger').addEventListener('click', () => {
    document.querySelector('.sidebar').classList.toggle('open');
});
```

### Image Zoom

```javascript
function openZoom(src) {
    const overlay = document.getElementById('zoom-overlay');
    document.getElementById('zoom-img').src = src;
    overlay.style.display = 'flex';
}
document.getElementById('zoom-overlay').addEventListener('click', () => {
    document.getElementById('zoom-overlay').style.display = 'none';
});
```

### Unread Message Polling

```javascript
setInterval(() => {
    fetch('../Support/support_api.php?action=unread_count')
        .then(r => r.json())
        .then(data => {
            document.querySelector('.badge-unread').textContent = data.count || '';
        });
}, 15000);
```

---

## Mobile Responsiveness

The layout is responsive down to ~375px viewport width:

- Sidebar collapses off-canvas on screens narrower than `768px`.
- Hamburger button appears in the top nav bar on mobile.
- Tables wrap in `.table-responsive` for horizontal scroll.
- Cards stack vertically on small screens via Flexbox / Grid.

---

## Notes

- Keep all shared assets here. Do not copy `style.css` or `index.php` into `Admin/` or `Alumni/`.
- Images uploaded by users (announcement images, etc.) live in `uploads/` at the project root — not here. This folder is for static UI assets only.
- When adding new global styles, prefer `style_additions.css` to keep `style.css` as the stable base.
