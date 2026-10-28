# OceanJet single-page booking module

## Setup

1. Activate WooCommerce and configure the site's existing checkout and payment methods. Upload all plugin files together (version 1.5.244).
2. Open **Hotel Rates > OceanJet**. Add a route group with From/To ports and optional display codes. Each group has exactly two sections: A (From → To) and B (To → From). Set multiple accommodations and multiple departure times independently in each section. Both enabled directions populate the frontend From/To choices automatically. Saving creates the hidden, virtual, non-taxable WooCommerce booking product.
3. Enter Adult and Child fares per passenger in PHP for each accommodation. These fares apply to every departure time in that direction. Both fares are required; zero means free. Include intended supplier charges in the fares. Staff verify ages manually. Adult-only, Child-only and mixed bookings are accepted.
4. Set duration, operating weekdays and blocked dates per direction. The shared trip code is optional; leave it blank when unavailable. Add departure times individually. First/last operating dates are not used in the new settings; sailings operate on an ongoing basis. The booking cutoff still applies. All times use Asia/Manila. Arrival is calculated from the shared duration. Duplicate departure times or accommodation names within a direction are rejected.
5. Set the maximum **combined Adult + Child** quantity. Both quantity fields start at zero; the total must be at least one.
6. The optional ticketing charge is fixed per booking, once for round trips. The existing global convenience fee applies separately at checkout after WooCommerce discounts. Do not charge the same supplier fee in both fares and ticketing charge.
7. Insert `[oceanjet_booking]` on any page (including an Elementor Shortcode widget). The shortcode supplies only the booking form. Keep galleries, product information and maps in the page editor. Exclude the booking page from long-lived full-page caching to avoid expired nonces.
8. Verify the existing classic WooCommerce/CartFlows checkout and currency conversion on staging. The form labels fares in PHP; other required checkout fields still apply. Checkout Blocks integration is not validated.

## Customer flow

One page contains trip type, dependent From/To choices, departure date, optional return date, Adult quantity followed by Child quantity, sailing/accommodation cards, one passenger's details, calculated subtotal, terms and submit.

Schedules load automatically after route, dates and quantities are complete. Return sailings use the reverse route and appear only for round trips. Each card displays per-passenger Adult/Child fares. Changing search data clears affected selections and total, while entered passenger details are preserved. Return departures must follow outbound arrival.

The guest enters **one passenger's details only**, even for a group. A visible note explains that staff will follow up for other passengers and verify ages. Select that passenger's category from the booked categories. Contact number is required; email is optional here and remains subject to checkout requirements. The supplied name, phone and any supplied email prefill billing contact fields.

Submit validates the quote and passenger data on the server, adds one booking cart item and redirects directly to checkout. Fares, schedules, eligibility of the chosen category and cutoff are checked again before order creation. Changed fares require a new selection. Identical requests already in the cart are not added twice. Cart quantity is always one booking.

## Admin fulfilment and existing data

The WooCommerce order shows itinerary, accommodations, Adult/Child quantities, fare breakdown and the supplied passenger name. Private order-item metadata stores passenger birth date, nationality, contact details and the booking data. A follow-up note appears for bookings with missing passenger details.

**OceanJet ticket fulfilment** exposes the supplied details to staff. Collect the remaining details, verify ages, secure tickets manually and enter supplier references. References are order-level. Entering references does not automatically email ticket attachments. Payment confirmation does not issue a ticket; there is no supplier API, live inventory or seat hold.

Existing settings remain active until the first save of the simplified editor. The editor combines legacy accommodations and times into fixed A/B sections. The first record supplies each direction's shared fields and each accommodation's fare. An admin notice lists original records so differences in fares, weekdays, blocked dates, enabled status, codes and durations can be reviewed. Saving applies the shared settings to every accommodation/time combination and removes operating date limits. Existing Minor fares become Child fares. Complete both directions and review all fields before saving. Historical orders retain their original metadata; pre-migration cart bookings must be submitted again. Subsequent unchanged saves preserve the new sailing identifiers.

Limits: 50 route groups, 30 departure times and 20 accommodations per direction, and 1000 departure/accommodation combinations in total. A trailing marker prevents partial saves if PHP `max_input_vars` truncates the form. Follow the site's WooCommerce privacy and order-retention practices for passenger data.

## Development checks

- `php tests/oceanjet-domain.php`: prices, quantity/category rules, dates, schedule availability, grouping, migration and one-passenger validation.
- `php tests/oceanjet-workflow.php`: booking handler, cart pricing/revalidation and order metadata with isolated WooCommerce substitutes. Run with `save`, `save-duplicate`, `save-missing-fare`, `save-overlap`, `save-truncated`, `book-reverse`, `book-business`, `book-changed` or `book-no-consent` to check the other admin/booking cases.
- `node tests/oceanjet-browser.cjs`: single-page dependencies, Adult/Child defaults, child-only and mixed bookings, round trips, stale responses, retained passenger input, mobile layout and mocked checkout handoff. Also tests actual A/B PHP search/quote responses, reverse-origin choices and the responsive fixed-direction admin editor. Requires Playwright and Chrome. Set `PHP_BINARY` and optionally `CHROME_BINARY` when needed.

These checks do not replace staging validation with WordPress, WooCommerce, HPOS, active gateways and the site's currency plugin.
