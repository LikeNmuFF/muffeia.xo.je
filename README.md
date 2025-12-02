Frontend tweaks (developer notes)
--------------------------------
- Avatars: Global CSS updated so profile/post avatars are perfectly circular and use object-fit: cover for crisp rendering. Added explicit width/height attributes where appropriate (`index.php`, `pages/profile.php`) to improve browser scaling.
- Login screen: the single icon was replaced by a logo-switcher that alternates between two logo images (fades every 3s). Images used: `logo/muffeia.png`, `logo/m-blues.png`, `logo/m-light.png`.
 - Login screen: the single icon was replaced by a single central logo-switcher inside the auth card (removed duplicated placements) and the CSS rules were moved into `css/modern-theme.css` for global reuse.

If you want different logo images or timing, edit `auth/login.php` (the <img> tags inside the `.logo-switcher`) and adjust the `logoCrossfade` animation in `css/modern-theme.css` (duration/offset) for timing customization.

