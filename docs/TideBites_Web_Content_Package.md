# TideBites content reference

## Brand and content

TideBites is an online restaurant brand for campus life with a playful ocean and summer style. Use **Catch a Wave of Flavor!** consistently. Keep English friendly, direct, and accurate. The canonical published copy lives in `index.html`; this document avoids duplicating entire sections that can drift out of date.

The current site includes menu filtering, six-item pagination, dish details, photo enlargement, a local cart and order summary, interactive FAQs, responsive navigation, and sea-life animations. These features are already implemented.

## Menu

Names and prices must match the menu cards. Details and cart calculations read those cards directly.

| Category | Dish | Price |
| --- | --- | --- |
| Meals | Thai Basil Pork Rice | NT$129 |
| Meals | Chicken Oyakodon Bowl | NT$139 |
| Meals | Taiwanese Fish Ball Noodle Soup | NT$139 |
| Meals | Roasted Chicken Set Meal | NT$169 |
| Meals | Tomato Udon Soup | NT$159 |
| Meals | Beef and Egg Noodles | NT$179 |
| Meals | Chilled Pork Noodle Bowl | NT$149 |
| Desserts | Watermelon Snow Ice | NT$99 |
| Desserts | Mango Snow Ice | NT$109 |
| Desserts | Peanut Tang Yuan | NT$79 |

## Transaction copy

- Review Order opens a local summary. Back to Cart preserves selections, notes, and pickup location. Place Order shows a simulated completion screen with a retained summary, then clears the cart. Continue Shopping closes that screen.
- Never claim an order was sent, payment was taken, or food is being prepared.
- The website does not submit orders or process payments; this is stated in the FAQ, checkout confirmation, and completion screen.
- Use Campus Pickup Location consistently. Existing campus choices are summary selections, not confirmed service coverage.
- Apply the 10% Campus Discount automatically to all ten meals and desserts. No identity check, account, code, or opt-in is needed. Use `Math.round(subtotal * 0.1)` for the discount and subtract it from the full food subtotal. Require a campus pickup location before review. Show Subtotal, Campus Discount (10%), and Total consistently.
- Existing delivery-hour values are unchanged. The footer states that the website does not book deliveries.
- Preserve 14 footer tags in the original styling: Food, Desserts, Rice Bowls, Noodles, Snow Ice, Tang Yuan, Comfort Food, Student Deals, Lunch, Dinner, Campus Life, Takeaway, Sweet Treats, Ocean Vibes.

## Open team decisions

1. Verify existing hours and campus meeting points before enabling real ordering. No operational availability has been independently established.
2. Agree on backend, payment, fulfillment, and delivery requirements before adding a submission action.
3. Review compression/encoding of the 88.6 MB background track with its owner. This update only defers loading and does not replace media.
4. Confirm rights and source records for existing photos, illustrations, and audio, and verify dish ingredients. Preserve third-party attribution and the W3.CSS footer credit.

## Technical notes

Preserve the existing ocean design, animation behavior, responsive rules, audio, and dialog structure. The legacy local-storage key remains unchanged to retain existing carts. See README for resources and local Chrome instructions.
