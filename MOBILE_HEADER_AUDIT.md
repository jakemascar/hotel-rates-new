## Rollback - 15 October 2026

The mobile icon-alignment override was removed from `public/css/shortcodes.css` at the user's request. The real iPhone 14 Plus issue remains unresolved; the earlier engine-test results below are historical, not confirmation of a device fix. Keep these findings for a different approach.

The removed override reset the language wrapper's positioning, used flex for the flag link, fixed its image to 24x24, and removed its text/arrow spans from layout. The hamburger styling and rate-table styling remain unchanged. No older iPhone translate/offset workaround was restored.

Previously observed live-site rules include `.gtranslate_wrapper { right: 10px; top: 0; position: relative; }` and mobile `left: 28px`, emitted in an unnamed inline style block. The exact WordPress editor/snippet source remains unverified. Currency positioning also used legacy offsets and negative margins. Inspect those original sources and both controls' actual layout before the next fix.

Removed CSS for reference only:

```css
/* Keep the mobile header flag clear of legacy offsets and label spans. */
@media (max-width: 767px) {
	.elementor-23001 .header_sticky .gtranslate_wrapper {
		display: flex;
		align-items: center;
		position: static;
		left: auto;
		right: auto;
		top: auto;
	}

	.elementor-23001 .header_sticky .gtranslate_wrapper > a.gt_switcher-popup {
		display: inline-flex;
		align-items: center;
		vertical-align: middle;
		white-space: nowrap;
		line-height: 1;
		translate: none;
	}

	.elementor-23001 .header_sticky .gtranslate_wrapper > a.gt_switcher-popup > img {
		display: block;
		flex-shrink: 0;
		width: 24px;
		height: 24px;
	}

	.elementor-23001 .header_sticky .gtranslate_wrapper > a.gt_switcher-popup > span {
		display: none;
	}
}
```

# Mobile header investigation — 12 October 2026

## Result and root cause

The live homepage's GTranslate flag is a normal inline anchor containing a 24px image, a language-name span, and an arrow span. Site custom CSS hides the spans with `.glink span { font-size: 0px !important; }`, but they retain an inherited 24.32px line-height and GTranslate's 5px right margins. The anchor allows wrapping. WebKit lays this out over two lines, making the language widget 51.14px tall. That widget expands the flex header and leaves the flag above the other controls.

This was reproduced against the live homepage in Playwright WebKit on Windows and compared with installed Chrome, using the same 428 × 926 CSS viewport and 3x scale. These are browser-engine tests, not a physical iPhone test.

At 428px before the patch:

| Measurement (CSS px) | Chrome | WebKit |
| --- | ---: | ---: |
| Header height | 65.39 | 77.14 |
| Logo width | 174.41 | 174.41 |
| Flag center Y | 33.02 | 26.48 |
| Currency center Y | 33.19 | 39.06 |
| Menu center Y | 32.69 | 38.56 |

The reported larger logo was not reproduced: its measured width and height were identical. Screenshots have different display scales, so they cannot establish a CSS-width difference by themselves. The extra header height and flag displacement were reproduced.

After the patch, both engines have a 65.39px header, 174.41px logo, flag/menu center Y 32.69px, and currency center Y 33.19px. The small currency offset already exists in the site's CSS.

## Header ownership and structure

The header is an Elementor template, ID **23001**, post type `ova_framework_hf_el`, rendered through the Tripgo header/footer system. The relevant live HTML is:

```text
.elementor-23001
  .elementor-element-7bd1da5f.header_sticky
    .e-con-inner
      .elementor-element-5e942771                  logo container
        .elementor-element-3fa83971 img           logo, intrinsic 912 × 206
      .elementor-element-2809976                  right container
        .elementor-element-80fe68c                mobile controls flex row
          .elementor-element-fd9879f              currency shortcode
            .yay-currency-single-page-switcher
              .yay-currency-custom-select
                .yay-currency-custom-select__trigger
          .elementor-element-202ab6c              language shortcode
            .gtranslate_wrapper
              a.gt_switcher-popup.glink
                img + span + span
          .elementor-element-e191b1a              popup button
            a.elementor-button .e-fas-bars
```

Sources inspected: local plugin CSS; live homepage HTML and CSSOM; Elementor's inline `style#elementor-post-23001`; Tripgo and Elementor stylesheets; GTranslate's generated inline styles; the site's additional unnamed inline style blocks. The repository contains the plugin, not the theme or WordPress database. The physical PHP/database source of the unnamed inline custom CSS cannot be determined from the public HTML alone.

## Relevant cascade

Specificity is shown as IDs / classes and pseudo-classes / elements.

| Selector | Source and media | Relevant rule | Specificity; important |
| --- | --- | --- | --- |
| `.glink span` | Homepage unnamed inline custom CSS; all widths | `font-size:0px` hides glyphs but retains line boxes | 0/1/1; yes |
| `.gt_container--… a.glink span` | GTranslate-generated inline CSS; all widths | `margin-right:5px; font-size:15px; vertical-align:middle` | 0/2/2; no |
| `a.glink img` | GTranslate-generated inline CSS | `display:inline; vertical-align:middle` | 0/1/2; no |
| `.elementor-23001 .elementor-element.elementor-element-3fa83971 img` | `style#elementor-post-23001`; max 767px | `width:100%; max-width:90%` | 0/3/1; no |
| `.elementor-23001 .elementor-element.elementor-element-5e942771` | Same; max 767px | `--width:50%` | 0/3/0; no |
| `.elementor-23001 .elementor-element.elementor-element-7bd1da5f` | Same; max 767px | padding variables: 13px vertical, 10px horizontal | 0/3/0; no |
| `.elementor-23001 .elementor-element.elementor-element-80fe68c` | Same; max 767px | row, nowrap, space-between, width 100% | 0/3/0; no |
| `.yay-currency-single-page-switcher` | Homepage custom CSS; max 767px | `position:relative; top:4px; right:120px` | 0/1/0; no |
| `.yay-currency-custom-select` | Homepage custom CSS; mobile | `margin:-7px -300px 0 155px` | 0/1/0; yes |
| `.gtranslate_wrapper` | Homepage custom CSS; max 767px | `position:relative; left:28px` | 0/1/0; no |
| `.elementor .header_sticky .gtranslate_wrapper > a.glink` | Old local `shortcodes.css`; 428px portrait, 3dppx, coarse pointer, WebKit support test | `translate:0 12px` | 0/4/1; no; removed |

The language spans' important font-size wins over GTranslate's font size. The missing line-height/wrapping constraints are what matter. Chrome's inline layout happened to stay on one line. The new flex anchor makes that behavior explicit in both engines. Its selectors are 0/4/1 for the anchor and 0/4/2 for the image; no new `!important` is needed.

The existing currency offsets and percentage widths are fragile, but changing them is unnecessary for the reproduced defect and previously caused a currency regression. The minimal patch therefore fixes the language button's own layout. It does not rebuild the header.

The live page has one normal viewport tag: `width=device-width, initial-scale=1, shrink-to-fit=no`. No viewport or safe-area change is needed. No global text-size-adjust override was added. The 460/520/660px rules for `.gt_switcher.notranslate` target a different GTranslate layout, not this direct popup anchor. No 420–440px boundary explains the reproduced issue. The old 428px-only workaround is removed.

## Exact change

Only production file changed for this task: `public/css/shortcodes.css`, starting at line 21. PHP and plugin version were left untouched as requested. The patch applies only at widths up to 767px, inside template 23001's sticky header. Popup contents are not selected by the direct-child anchor/image rules.

```diff
-/* iPhone 14 Plus default portrait viewport: adjust only the header flag button. */
-@media (width: 428px) and (orientation: portrait) and (resolution: 3dppx) and (pointer: coarse) {
-	@supports (-webkit-touch-callout: none) {
-		.elementor .header_sticky .gtranslate_wrapper > a.glink,
-		.elementor .header_sticky .gtranslate_wrapper .gt_switcher > a.glink,
-		.elementor .header_sticky .gtranslate_wrapper .gt_switcher .gt_selected > a {
-			translate: 0 12px;
-		}
+/* Keep the header flag and its visually hidden labels on one line in WebKit. */
+@media (max-width: 767px) {
+	.elementor-23001 .header_sticky .gtranslate_wrapper {
+		display: flex;
+		align-items: center;
+	}
+
+	.elementor-23001 .header_sticky .gtranslate_wrapper > a.gt_switcher-popup {
+		display: inline-flex;
+		align-items: center;
+		vertical-align: middle;
+		white-space: nowrap;
+		line-height: 1;
+		translate: none;
+	}
+
+	.elementor-23001 .header_sticky .gtranslate_wrapper > a.gt_switcher-popup > img {
+		display: block;
+		flex-shrink: 0;
 	}
 }
```

## Verification and deployment

Chrome and WebKit measurements cover 360, 375, 390, 393, 412, 414, 428, 429, 430, 431 and 767px. All mobile controls remain visible, inside the viewport, and vertically aligned within 0.5px. Logo widths are unchanged. The new CSS does not match at 768px and above. Tests compare baseline and patched header geometry at 768, 1024 and 1440px. Native language, currency and hamburger controls are also checked by opening their existing UI without submitting a currency/language change.

Upload the CSS file, then purge any WordPress optimization/page cache, server cache and CDN cache that serves it. The stylesheet URL still has `ver=1.5.241`, so purge that asset explicitly and reload on the phone. Elementor regeneration is not required for this plugin stylesheet change; regenerate its CSS only if a cache/optimization layer has incorporated the old rules into generated assets.

Physical-device acceptance checks: on the real iPhone 14 Plus, check portrait at initial load and after scrolling the sticky header; open/close language, currency and menu; rotate and return to portrait; repeat on a smaller iPhone and Android. Check a tour product page using this header as well as the homepage. Browser-engine tests do not replace this final physical-device check.

Git was present. Before this task, `hotel-rates.php` and `readme.txt` were modified and `includes/class-hotel-rates-convenience-fee.php` was untracked. Those existing changes were preserved. No commit or deployment was performed.
