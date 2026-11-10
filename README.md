# TideBites

**Catch a Wave of Flavor!**

TideBites is an ocean-themed campus dining website for students, featuring comforting meals and refreshing desserts.

## Features

- Ten dishes: seven meals and three desserts.
- ALL / MEALS / DESSERTS filters and up to six dishes per page.
- Dish details, quantity selection, subtotals, and enlarged photos.
- A browser-local cart with quantity controls, removal, notes, campus meeting-point selection, an order summary, and an ocean-themed simulated completion screen.
- Expandable FAQs, responsive navigation, and mobile menu.
- Opening sequence, dolphin and sea-life animations, background music, and bubble sounds.

## Run in Chrome

1. Download or clone this repository, keeping its folder structure.
2. Open the root `index.html` with Google Chrome.
3. Click or tap the opening screen to enter and start the music.

No build, npm installation, or server is required. Internet access is needed for external stylesheets and fonts. Browser audio and local-storage settings can affect playback and cart persistence. Cart contents stay in this browser when storage is available; notes and meeting points are not persisted across reloads.

## Structure

- `index.html`: page content, ten menu cards, dialogs, and resource links.
- `css/style.css`: layout, responsive rules, and animations.
- `js/script.js`: navigation, filters, pagination, dialogs, cart, FAQ, audio, and sea-life effects.
- `assets/images/`: food photos and decorative images.
- `assets/icons/`: navigation and category icons.
- `assets/audio/`: background music and bubble sound.
- `docs/TideBites_Web_Content_Package.md`: content rules, menu reference, and open decisions.

## Behavior and limits

This is a client-side HTML/CSS/JavaScript site. It has no ordering backend, payment processing, delivery booking, or student verification. Returning from the order summary preserves the cart, pickup location, and notes. Place Order completes a clearly disclosed local simulation, retains a summary on the completion screen, and only then clears the cart. No real order number or API request is generated.

Menu cards are the single source of truth: JavaScript reads their names, descriptions, images, categories, and NT$ prices. All ten dishes qualify for the automatic 10% Campus Discount, with no ID, account, or promo code. Subtotal is the sum of unit price times quantity; discount is `Math.round(subtotal * 0.1)`; total is subtotal minus discount. All amounts are integer NT dollars. Cart and review show all three amounts, and checkout requires a campus pickup location. Completion preserves the reviewed amounts, location, and notes as a local snapshot.

Background music is retained unchanged (88,641,552 bytes). `preload="none"` defers loading until entry; playback still starts on the opening-screen click. This does not reduce file size or guarantee instantaneous playback on slow connections.

## Resources and attribution

- [W3.CSS 6 stylesheet](https://www.w3schools.com/w3css/6/w3.css) and the preserved [Powered by w3.css](https://www.w3schools.com/w3css/default.asp) footer link.
- [Google Fonts](https://fonts.google.com/): Alex Brush, Lato, and Raleway.
- [Font Awesome 4.7](https://fontawesome.com/v4/): interface icons.
- Existing food photographs and sea-life/audio assets are retained. Team ownership, ingredient accuracy, image cleanup, and media licensing/source verification remain review items; this update makes no new ownership or license claims.

See the content document for decisions needed before real ordering is enabled.
