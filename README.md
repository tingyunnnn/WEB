# TideBites

An ocean-inspired campus food delivery concept for a Web Programming class. This Phase I site is a menu preview; orders, payments and student verification are not active. Prices, discounts and delivery hours are proposals.

## Open locally
Extract the complete repository, then open the root index.html in Google Chrome. No npm, build, server, external fonts or stylesheet downloads are required.

## Structure
- index.html: entry page
- css/style.css: responsive coastal theme
- js/script.js: ten menu records, category filters, six-item pagination, mobile navigation and accessible native photo dialog
- assets/icons/: retained original seven icons
- assets/images/: ten cropped original food photographs; thumbnails and enlargement share these assets
- docs/TideBites_Web_Content_Package.md: complete content handoff

## Content and photography
Copy follows the supplied content package. Photos were supplied by the team; the team must confirm course-compliant ownership and the ingredient identifications noted in the handoff. No stock, W3 sample or AI-generated food images are used. Source photographs remain outside this checkout. Crops remove background distractions; the chilled noodle photograph is tightly framed to exclude the printed rim. Final visual review of all full-size crops remains required before publication.

## Verification
Passed: JavaScript syntax check (node --check), git diff --check. Menu data contains ten dishes and six FAQ entries. No runtime network dependencies are required.

Browser QA remains pending: the Chrome browser automation provider was unavailable in this session. Do not treat responsive layout, interaction behavior or file:// compatibility as browser-verified.

Review in Chrome at 375, 1024 and 1366 pixels: check horizontal overflow and mobile navigation; ALL shows 6+4, MEALS 6+1, DESSERTS 3; filters reset to page one; previous/next buttons disable at boundaries. Open every photo, inspect for branding and background people, close via the visible button, backdrop and Escape, and check focus returns to the initiating control. Confirm all images load offline.

Powered by w3.css attribution is preserved at the bottom of the page.
