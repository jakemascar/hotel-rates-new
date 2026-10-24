<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="hr-oj">
    <h2>OceanJet booking</h2>
    <p>Choose your trip and send a booking request. Sailings and fares are subject to supplier confirmation. Payment confirmation is not an issued ferry ticket.</p>
    <ol class="oj-progress" aria-label="Booking progress"><li>Search</li><li>Schedule</li><li>Passengers</li><li>Review</li><li>Checkout</li></ol>
    <p class="oj-error" role="alert" hidden></p>
    <form class="oj-search">
        <div class="oj-grid">
            <label>Trip type<select name="type"><option value="one">One-way</option><option value="round">Round trip</option></select></label>
            <label>From<select name="from" required><option value="">Choose port</option></select></label>
            <label>To<select name="to" required><option value="">Choose destination</option></select></label>
            <label>Departure date<input type="date" name="date" required></label>
            <label class="oj-return" hidden>Return date<input type="date" name="return"></label>
        </div>
        <fieldset><legend>Passengers</legend><div class="oj-counts"></div></fieldset>
        <p>Discounted student, PWD and senior fares require Filipino nationality and staff verification of eligibility. A category is available only where an admin has entered its fare.</p>
        <button type="submit">Search trips</button>
    </form>
    <section class="oj-schedule" hidden tabindex="-1">
        <h3>Choose accommodation, then departure time</h3><p>All times are Philippine time. Results are configured sailings, not live seat availability.</p>
        <div class="oj-legs"></div>
        <button type="button" data-back="search">Edit search</button> <button type="button" class="oj-to-passengers">Passenger details</button>
    </section>
    <form class="oj-passengers" hidden tabindex="-1">
        <h3>Passenger details</h3><p>Enter names as shown on travel documents. Ages are checked on each travel date. Adult-age passengers need a contact number; minors and infants can use the booking contact.</p>
        <div class="oj-people"></div>
        <fieldset class="oj-contact"><legend>Booking contact</legend>
            <button type="button" class="oj-copy">Use first passenger's details</button>
            <div class="oj-grid">
                <label>First name<input name="first" required maxlength="150" autocomplete="given-name"></label>
                <label>Last name<input name="last" required maxlength="150" autocomplete="family-name"></label>
                <label>Contact number<input name="phone" type="tel" required maxlength="30" autocomplete="tel"></label>
                <label>Alternative number (optional)<input name="alternative" type="tel" maxlength="30"></label>
                <label>Email<input name="email" type="email" required autocomplete="email"></label>
                <label>Confirm email<input name="confirm" type="email" required></label>
            </div>
        </fieldset>
        <label class="oj-consent"><input type="checkbox" name="consent" required> I accept the booking terms and privacy policy and understand this request is subject to supplier availability and ticket confirmation.</label>
        <p><?php $terms = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('terms') : ''; if($terms): ?><a href="<?php echo esc_url($terms); ?>" target="_blank" rel="noopener">Booking terms</a><?php endif; ?> <?php $privacy = get_privacy_policy_url(); if($privacy): ?> <a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener">Privacy policy</a><?php endif; ?></p>
        <button type="button" data-back="schedule">Back to schedules</button> <button type="submit">Review booking</button>
    </form>
    <section class="oj-review" hidden tabindex="-1"><h3>Review booking request</h3><div class="oj-summary"></div><p>The configured convenience fee will appear separately at checkout. Your ticket will be confirmed by our team after supplier verification.</p><button type="button" data-back="passengers">Edit passengers</button> <button type="button" class="oj-book">Continue to checkout</button></section>
    <noscript>Please enable JavaScript to use the OceanJet booking form.</noscript>
</div>