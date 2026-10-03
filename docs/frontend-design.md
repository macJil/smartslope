# SmartSlope frontend design

The `su3` frontend uses the bundled Bootstrap 5, Leaflet, and existing jQuery refresh. `assets/css/frontend.css` holds the shared green and brown visual layer. No UI framework, icon pack, or new JavaScript dependency is added.

## Page structure

- A single PHP navigation renderer in `app/presentation.php` provides the desktop and Bootstrap-collapse mobile menu. Links vary by role; the current page is marked with `aria-current`.
- The dashboard keeps the Irisan map first, followed in one column by the reading summary, awareness analyzer, location reports/history, and recent readings.
- The report page keeps the map next to the form on wide screens and above it on phones. The selected-location message updates with the existing map/address logic.
- Login and registration have separate forms in one responsive card. The readings and admin pages keep their existing data tables and modal workflows. The methodology page shares the navigation and content styles.

## Controls and colors

- Deep green (`#205b43`) is for primary actions and links; brown (`#725238`) is a quiet heading/map accent. The background is light and cards stay white.
- Buttons gain a subtle hover shift. Cards gain a greener border and soft shadow without implying they are clickable. Focus remains visible; reduced-motion users get no hover translation.
- The existing semantic rainfall category colors, destructive red actions, and neutral outdated/unavailable states retain their meaning. Green on a low reading is a category color, never a safety guarantee.
- Text, phone, email, password, and textarea inputs use Bootstrap `.form-floating > .form-control`; selections use `.form-floating > .form-select`. Native datetime, file upload, checkboxes, and read-only values retain visible standard labels. Required and server-side checks remain authoritative.

## Applying this design patch

The patch for this design targets a clean `su3` checkout at commit `9425ea6`:

```sh
git switch su3
git status --short
git apply --check ~/Downloads/smartslope-su3-simple-design.patch
git apply ~/Downloads/smartslope-su3-simple-design.patch
```

If `git apply --check` reports conflicts, compare the local checkout to the patch base before proceeding. Existing PHP routes, database schema, weather refresh, map selection, and admin POST actions are unchanged. Verify login/registration, map selection, report submission, reading details, admin review, and the mobile menu in Herd or XAMPP after applying.
