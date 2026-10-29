# Aisle logo source

These SVG files are the source of the Aisle logo. Copy one to make your own logo in the same style.

| File | What it is |
|---|---|
| `logo.svg` | Bag and word, for light backgrounds |
| `logo-dark.svg` | Bag and word, for dark backgrounds |
| `mark.svg` | The bag only |
| `icon.svg` | The app icon: the bag on the brand colour |

## Make your own

1. Copy `logo.svg` and open it in a text editor.
2. Change the word between `<text>` and `</text>`.
3. Change the colours. `#c2185b` is the bag, `#ffffff` is the aisle inside it, `#1d1724` is the word. A long word needs a larger `width` and `viewBox`.
4. To change the font, put another `.woff2` file in `fonts/` and point the `@font-face` rule at it.
5. Export a PNG. Open the file in Chrome, or run:

   ```sh
   chrome --headless --default-background-color=00000000 --force-device-scale-factor=3 \
     --window-size=186,64 --screenshot=logo.png file://$PWD/src/logo/logo.svg
   ```

6. Upload the PNG under **Appearance → Aisle → Logo**, and the dark one under **Logo on dark**.

The word is live text, so it shows in the Fraunces font only when the SVG opens from this folder. Upload the PNG, not the SVG.

The app icons `img/icon-192.png` and `img/icon-512.png` are exported from `icon.svg` at those sizes.
