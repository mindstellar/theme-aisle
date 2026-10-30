# Changelog

## 1.1.0

### New
- Aisle works as a parent theme for a child theme.
- A sold-out choice shows Notify me in place of Add to cart.

### Changed
- An applied coupon stays through a reload and a new sign-in.
- The address book's State is a list of the country's regions.
- A placed order opens its own order page.
- The maintenance page shows the owner's message, brand colour and colour scheme.

### Fixed
- A cart of downloads places its order without a delivery method.
- The cart drawer shows the site's reason when it refuses an item.

## 1.0.0

Ships with Shopclass 7.1 as its shop theme. Needs Shopclass 7.1.0 or newer.

### New

- A web manifest and app icons, so a phone can add the shop to its home screen.
- Page view transitions and a fly-to-cart motion; both are off under reduced motion.
- Shop events (product view, add to cart, checkout steps, purchase, search) go to a page queue and a browser event for analytics plugins. The theme itself sends nothing.
- The newsletter form shows a status line after sign-up and posts to the Shop emails plugin when it is on.
- The theme recommends the Shop emails and Analytics plugins.

### Changed

- "Your orders" in the account menu is added only when core has not added it already.
- The theme's styles are inlined in the page head, so the first paint waits on no stylesheet.
- Pages ask the API only for the listing members they show, and the category head reuses the page's list, so home, category and product pages run about half the database statements.
- The home hero is a smaller WebP with two sizes, and product photos use `srcset`.
- A failed API read shows a retry line, not an empty state or a 404.
- Long names, right-to-left pages, slow networks, hover, focus and tap targets are hardened.
- The cart reads its line and quantity limits from the site, not from its own copy.
- Product cards load the thumbnail on phones; desktop keeps the larger preview.
- Home sections store site paths for images and links, so a moved or renamed site keeps them.

### Fixed

- The customiser preview turns `*word*` into an em as the page does, and an empty heading shows its default.
- Link fields refuse backslashes and control characters.
- The home sections migration copies the hero photo, button labels and links.

## 0.5.0

### New

- Appearance, Customise: edit the home page with a live preview, as a draft until Publish.
- The home page is built from sections: hero, categories, products, promise, banner, featured product, reviews and newsletter.
- Hide, show and reorder sections here or on Manage widgets.
- Fonts: Fraunces + Inter and System fonts beside the default pair, served from your site.
- Logo, dark logo and logo height settings.
- Dev site seed: banner, reviews and newsletter sections, Deals hidden, logos, Fraunces + Inter and Marigold. Shots for the customiser.

### Changed

- The home hero and promise texts are now section fields. Existing values are copied on update.

## 0.4.0

### New

- Product page: PIN code box with the delivery date, free-delivery threshold and cash on delivery (needs the Shipping plugin).
- Checkout: coupon field with an applied row, Express delivery, the PIN sent with the quote, and PIN and coupon errors shown on their field.
- Checkout and orders: "Includes GST" note for tax inside the price; tax added on top shows as a row.
- Dev site seed: Shipping, Tax, Promotions and Tax GST plugins with an India zone, GST, WELCOME10 and a first-order offer.

### Changed

- The signed-in cart is saved by core's `account/cart`; the theme's own cart routes are gone.
- The theme recommends the shipping, tax, promotions, razorpay and digital-goods plugins.

## 0.3.0

### New

- Cart: header badge, drawer with a free-delivery meter, Add to cart, Buy now and quick add on cards; saved to the account when signed in.
- Checkout: contact, address, delivery and payment on one page, with a priced summary.
- Your orders and an order page: tracker, lines, tracking link, invoice, Buy again, Cancel and file downloads.
- Your orders in the account menu.
- Dev site seed: billing, three orders for the demo shopper and a PDF download.

### Changed

- The reviews link on the product page reads "Rate this product".

## 0.2.0

### New

- Variants: Colour and Size pickers with price, stock and photo per choice, and Notify me and price-drop alerts.
- Reviews: rating line, score and bars, review cards, Show more and a write-a-review form.
- Stars on cards and AggregateRating and Review JSON-LD.
- Wishlist: hearts on cards and the product page, header count and a Wishlist page.
- Filters: option swatches and chips, multi-select custom fields, one applied chip per value.
- Setting "Filters by option".
- Dev site seed: a tee with Colour and Size, reviews, a wishlist and a stock alert.

## 0.1.0

### New

- Theme set-up: shop mode manifest, design tokens, light and dark, header, footer, product card and settings page.
- Home page: hero, category row, deals, promises and new in.
- Category and search pages: filters, applied chips, sort, Show more and numbered pages.
- Product page: gallery with zoom, price and stock line, details, share, related and recently viewed.
- Search suggestions, scheme toggle and phone menu.
- Page head from the SEO route, JSON-LD and static pages.
- Dev site, seed data and screenshot scripts in `bin/`.
- English translation template.
