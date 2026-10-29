# Cart module

Pages and JS only: the `checkout` and order pages, the cart drawer script and the checkout script.
The browser keeps the cart (localStorage, `js/aisle-cart.js`); signed in, it is saved by core's `account/cart`.
Signed out, or on a core without that route, the cart stays in the browser.
Prices, tax, delivery and discounts come from `GET cart/quote`, never from the page.
