=== Hotel Rates Manager ===
Contributors: islanddiaries
Tags: hotel, tours, booking, rentals, rates, travel
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.5.241
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage hotel, tour, transfer, and rental rates and connect them to Tripgo booking forms on WooCommerce products.

== Description ==

Hotel Rates Manager stores travel products and their prices in WordPress admin. It adds booking choices to products using the Tripgo theme's booking form and OVABRW rental hooks, then passes the selections and calculated prices through the WooCommerce cart and order.

Rates are entered in Philippine pesos (PHP). If YayCurrency is available, the plugin can convert displayed prices to the visitor's selected currency. Elementor integration is used for some package and tour display content when Elementor is present.

== Booking and Rental Modules ==

* Hotel Rates: Register regions, places, and hotels; enter rates for 1-8 nights, meal plans, and up to 12 guests. Meal plans include X (no breakfast), BB (bed and breakfast), HB (half board), and FB (full board).
* Day Tour: Enter tour prices for up to 30 guests, with optional add-ons, child pricing, and configurable booking fields including Address choices.
* Joiner Tour: Enter shared-tour prices for up to 12 guests, with optional add-ons, child pricing, and configurable Address choices.
* Airport Transfer: Set airport transfer routes and prices by direction and passenger count.
* Package Deal: Combine hotels, daily activities, an itinerary, and airport or seaport transfer fees into a multi-day booking.
* Motorbike Rental: Set bike models, stock, daily rates, available start times, rental durations, and delivery fees.
* Boat Rental: Set rates by passenger capacity, boarding times, an optional larger-boat upgrade, and a credit card fee.
* Self-Drive Vehicle Rental: Set vehicles, stock, daily rates, rental and return dates, delivery fees, and a credit card fee.
* Port Transfer: Set routes, transfer times, one-way or return details, add-ons, and a convenience fee.
* Destination Tour: Set destinations, durations, rates, pickup options, an editable first add-on dropdown option, and a credit card fee.
* Pick-up & Drop-off: Set locations, route rates, pickup times, passenger details, and add-ons.

The plugin also adds nationality, Phone / WhatsApp, and an optional special request to the WooCommerce checkout. Booking selections appear in cart and order details.

Set the global booking convenience fee under Hotel Rates > Set Convenience Fee (the last submenu). The default is 2%; enter 0 to disable. The fee applies to booking line amounts after discounts, excluding shipping, taxes, and other checkout fees. It is hidden from booking forms and appears as "Plus 2% Convenience Fee" (using the configured percentage) in checkout and order totals. Completed orders retain their original fee.

== Setup and Product Links ==

1. Open Hotel Rates > Register Hotels, Places, & Tours to add the region, place, and hotel hierarchy and register tours or package deals.
2. Use Hotel Rates and its submenu pages to enter hotel rates, tour rates, rentals, transfers, and services.
3. Edit a WooCommerce product and select the corresponding record in its Linked Package Deal, Linked Day Tour, Linked Joiner Tour, Linked Airport Transfer, Linked Motorbike Rental, Linked Boat Rental, Linked Self-Drive Vehicle, Linked Port Transfer, Linked Destination Tour, or Linked Pick-up & Drop-off Service box.
4. Save the product and check its Tripgo booking form on the front end. The linked module supplies its booking choices and rates.

The Hotel Rates and Tour Rates screens use the registered region and place hierarchy. Package Deal records are managed from the Register page; the other rental and service records have their own submenu pages.

== Rate Display Shortcodes ==

These shortcodes display rate tables or itinerary content. On a product page, the specific-record shortcodes use that product's linked record unless an ID is supplied. The booking form itself comes from the linked product and Tripgo integration.

* `[tour_rate_table city="Dumaguete" nights="2"]` displays hotel rates for a place and number of nights. `city` and `nights` are required. Optional: `max_pax` (default 8, maximum 12) and `title`.
* `[tour_day_rate_table city="Dumaguete" type="Day Tour"]` displays tour rates for a place and tour type. `city` is required. Optional: `type` (default `Day Tour`), `max_pax` (default 8, maximum 12), and `title`.
* `[package_deal_hotel_rates]` displays the hotels and rates in the linked package. Optional: `package_id`, `product_id`, and `max_pax` (default and maximum 12).
* `[package_deal_itinerary]` displays the linked package's day-by-day itinerary. Optional: `package_id`, `product_id`, `open_day` (default 1; use 0 to start with all days closed), and `title`.
* `[specific_tour_rates]` displays the linked Day Tour or Joiner Tour rates and package details. Optional: `tour_rate_id`, `product_id`, and `max_pax` (up to 30 for Day Tour and 12 for Joiner Tour).
* `[specific_port_transfer_rates]` displays the linked Port Transfer rates. Optional: `port_transfer_id` and `product_id`.
* `[specific_destination_tour_rates]` displays the linked Destination Tour rates. Optional: `destination_tour_id` and `product_id`.
* `[specific_self_drive_rates]` displays the linked Self-Drive Vehicle rates. Optional: `self_drive_rental_id` and `product_id`.
* `[specific_motorbike_rates]` displays the linked Motorbike Rental rates. Optional: `motorbike_rental_id` and `product_id`.
* `[specific_boat_rates]` displays the linked Boat Rental rates. Optional: `boat_rental_id` and `product_id`.

Day Tour and Joiner Tour records can include repeatable Details of the Package sections. These appear in the specific tour-rate table.

== Requirements and Integration ==

* WordPress 5.8 or newer and PHP 7.4 or newer, as listed in the plugin metadata.
* WooCommerce for products, cart, checkout, and orders.
* Tripgo theme booking forms and OVABRW rental hooks for the booking flows described above.
* YayCurrency is optional for converted price display. Elementor is optional for the supported package and tour display integrations.

== Installation ==

1. Upload the `hotel-rates` folder to `/wp-content/plugins/`.
2. Activate Hotel Rates Manager through the Plugins menu in WordPress.
3. Complete the setup steps above before publishing linked booking products.

== Changelog ==

= 1.5.241 =
* Add Set Convenience Fee settings with a default 2% global booking fee shown at checkout and saved on orders.

= 1.5.240 =
* Adjust mobile Inclusions top padding to 18px and refresh the stylesheet version.

= 1.5.239 =
* Override page-level zero padding above mobile Inclusions and apply the spacing to both Day Tour and Joiner Tour tables.

= 1.5.238 =
* Add extra space above Inclusions in mobile Day Tour rate tables.

= 1.5.237 =
* Lower only the header flag button on 428px portrait iOS screens with a 3x pixel ratio to align with the currency icon.

= 1.5.236 =
* Revert mobile currency and language alignment overrides to restore the original header positioning.

= 1.5.235 =
* Reserve mobile header space for the currency control and prevent switcher widgets from shrinking out of view.

= 1.5.234 =
* Add mobile spacing between package-details groups in Day Tour and Joiner Tour rate tables.

= 1.5.233 =
* Use the same smaller font size for all mobile Day Tour and Joiner Tour rate-table column headings.

= 1.5.232 =
* Prevent overlapping mobile Day Tour rate headings with wrapped labels and wider price columns.

= 1.5.231 =
* Align the mobile header currency and language controls with a centered flex layout instead of separate position offsets.

= 1.5.230 =
* Remove the mobile hamburger popup button background, border, and shadow while keeping its icon white.

= 1.5.229 =
* Keep Day Tour rate dividers visible using an inset cell line when collapsed borders disappear during hover repainting.

= 1.5.228 =
* Restore horizontal dividers between Day Tour guest and price rows, including the first two rates.

= 1.5.227 =
* Align the floating booking form bottom with the end of the tour or rental content by removing the boundary gap.

= 1.5.226 =
* Stop desktop floating booking forms at the end of their tour or rental content instead of the site footer.

= 1.5.225 =
* Added a per-tour setting for the first option in the Destination Tour pick-up add-on dropdown.

= 1.5.224 =
* Hide the child rate information row on Day Tour and Joiner Tour tables when no information lines are entered.

= 1.5.223 =
* Added editable child rate information per Day Tour and Joiner Tour for the specific tour rate table.

= 1.5.222 =
* Moved the Package Deal payment choice below the package selections and total in the booking form.

= 1.5.221 =
* Address booking field is hidden by default for Day Tour and Joiner Tour records until enabled.

= 1.5.220 =
* Added per-tour Address options for Day Tour and Joiner Tour booking forms.

= 1.5.219 =
* Added a per-tour Address booking field for Day Tour and Joiner Tour products, with a show/hide setting and editable label.

= 1.5.218 =
* Current plugin version with hotel, tour, package, transfer, and rental booking modules.

= 1.0.0 =
* Initial release.
