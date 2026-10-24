# OceanJet admin-managed module

## Setup

1. Activate WooCommerce and configure the existing checkout/payment methods. Upload the plugin's new files and updated bootstrap together (version 1.5.242).
2. Open **Hotel Rates > OceanJet**. Enter a sailing per direction, time, accommodation and effective date range. Save creates a hidden, virtual, non-taxable simple WooCommerce product for the booking requests. Do not assign this product to Tripgo's rental modules.
3. Use consistent port names. Add return-direction records explicitly. Classes are editable (e.g. Tourist Class, Open Air, Business Class). Enter actual verified fares; no supplier rates have been seeded.
4. Configure operating weekdays, optional inclusive effective dates, blocked dates and cutoff in minutes. Both dates empty means ongoing; first only means from that date onward; last only means through that date; both means within that range. Enabled, weekdays, blocked dates and cutoff still apply. All times are Asia/Manila. Arrival is calculated from duration. Avoid overlapping records for the same route/time/class; the save handler rejects them.
5. Enter final fares per passenger in PHP, including any supplier taxes/charges intended in the fare. Adult fare is required; blank other fares disable that category, while zero means free. Discounted fares are explicit amounts, not assumed percentage discounts. Staff must verify eligibility before fulfilment. Student/PWD/senior categories currently require Filipino nationality; infants are under 2, minor maximum and senior minimum are editable. One adult-age passenger is required, and counts are limited by admin settings.
6. Optional ticketing charge is a fixed amount **per booking**, once for round trips, default zero. It is included in the booking subtotal. The existing global convenience fee applies to that subtotal after WooCommerce discounts, once at checkout. Do not include a supplier fee in both the fares and ticketing charge. Configure tax treatment with the business before changing the dedicated product's tax status.
7. Create an Elementor page and add a **Shortcode** widget containing `[oceanjet_booking]`. Link menus/buttons to that page. Do not use the hidden product's default add-to-cart form. Exclude the booking page from long-lived full-page caching to avoid expired WordPress nonces.
8. Use the site's existing classic WooCommerce/CartFlows checkout. Contact details are prefilled. Other required checkout fields remain in effect. Currency conversion at checkout remains under the site's existing currency plugin; the OceanJet search/review deliberately labels amounts as PHP.

## Customer flow

Search (one way/round trip, ports, dates, six passenger counts) → choose accommodation and sailing for each leg → passenger and booking-contact details → server-calculated review → WooCommerce checkout → order acknowledgement awaiting supplier confirmation.

No automatic OceanJet inventory, seat hold, or official ticket is created. Treat every order as a request subject to supplier confirmation. Define a customer-facing refund/alternative-sailing policy in the site's booking terms. The form links to the configured WooCommerce terms page and WordPress privacy page.

Changing earlier steps invalidates the review signature. Prices, enabled schedules, cutoff and eligibility are rechecked before order creation. A changed fare blocks checkout and asks the customer to make a new search rather than silently changing the agreed amount. The cart quantity is always one booking; passenger counts are part of the booking itself. Identical requests already in the cart are not added twice.

## Staff fulfilment

Open the WooCommerce order. **OceanJet ticket fulfilment** shows private passenger/contact details. Confirm the actual sailing and discounted passenger IDs through the business's existing process. Obtain tickets from OceanJet, enter supplier reference(s), and update the order. The customer order view then shows the reference. Send actual ticket files and any customer notification using your established process; entering a reference does not automatically email a ticket attachment. Payment status and ticket issuance are separate.

Birth dates/contact/nationality are stored as private item metadata, not in the public item summary. The public summary contains passenger names, category and itinerary. Access and retention follow the order lifecycle; deleting an order deletes its metadata. No identity-document uploads are collected by this version. Review your WooCommerce retention/privacy policy for passenger manifests.

## Scope and limits

- Manual schedules and fares only; no supplier API or live capacity claims.
- No automatic ID verification, tickets, refunds, or rebooking.
- Price overrides use separate, non-overlapping effective date records.
- Maximum 200 records per settings form; a trailing marker prevents accidental partial saves when PHP `max_input_vars` is exceeded. Increase that limit for large forms.
- Supplier ticket references are order-level; include all references if one order contains multiple requests.
- Full WordPress/WooCommerce checkout, active payment gateways, YayCurrency conversion and HPOS admin saving must be verified on staging before production release. The development workspace is not a running WooCommerce site. Checkout Blocks integration has not been validated.

## Development checks

`php tests/oceanjet-domain.php` exercises fares, round trips, blocked/expired/disabled sailings, malformed dates/counts, changed prices, nationality, and age transitions.

`node tests/oceanjet-browser.cjs` uses Playwright with Chrome and a mocked transport to exercise the mobile wizard, preserved input on back navigation, contact copy, quote and checkout redirect. Install Playwright or use the existing `.language-check/node_modules` installation. Set `PHP_BINARY` to a PHP executable path if `php` is not executable directly (Windows batch wrappers), and optionally `CHROME_BINARY` to Chrome's executable.