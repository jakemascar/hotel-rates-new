<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="hr-oj">
    <h2>OceanJet Ferry Tickets</h2>
    <p>All times are Philippine time. Bookings are subject to supplier confirmation; payment does not issue a ferry ticket.</p>
    <form class="oj-booking">
        <div class="oj-grid oj-search-fields">
            <label>Trip type<select name="type"><option value="one">One-way</option><option value="round">Round Trip</option></select></label>
            <label>From<select name="from" required><option value="">Choose port</option></select></label>
            <label>To<select name="to" required disabled><option value="">Choose destination</option></select></label>
            <label>Departure date<input type="date" name="date" required disabled></label>
            <label class="oj-return" hidden>Return date<input type="date" name="return" disabled></label>
        </div>
        <fieldset class="oj-counts" disabled>
            <legend>Passengers</legend>
            <label>Adults<input type="number" name="adult" min="0" step="1" value="0" required></label>
            <label>Children<input type="number" name="child" min="0" step="1" value="0" required></label>
            <p>Enter 0 when no passengers travel in that category. Staff will verify ages.</p>
        </fieldset>
        <p class="oj-status" role="status" aria-live="polite">Choose your route, travel dates and passenger quantities to see sailings.</p>
        <p class="oj-error" role="alert" hidden></p>
        <div class="oj-legs"></div>
        <fieldset class="oj-person" disabled hidden>
            <legend>Passenger details</legend>
            <p>Enter details for one passenger only. Our team will contact you to collect the other passengers’ details and verify ages. For a child-only booking, provide a contact number our team can reach.</p>
            <div class="oj-grid">
                <label>Passenger category<select name="category" required></select></label>
                <label>First name<input name="first" required maxlength="150" autocomplete="given-name"></label>
                <label>Last name<input name="last" required maxlength="150" autocomplete="family-name"></label>
                <label>Middle name (optional)<input name="middle" maxlength="150" autocomplete="additional-name"></label>
                <label>Birth date<input type="date" name="dob" required></label>
                <label>Sex<select name="sex" required><option value="">Choose</option><option>Male</option><option>Female</option></select></label>
                <label>Nationality<input name="nationality" required maxlength="150"></label>
                <label>Contact number<input name="phone" type="tel" required maxlength="30" autocomplete="tel"></label>
                <label>Email (optional)<input name="email" type="email" maxlength="150" autocomplete="email"></label>
            </div>
        </fieldset>
        <div class="oj-summary" aria-live="polite" hidden></div>
        <fieldset class="oj-confirm" disabled hidden>
            <label class="oj-consent"><input type="checkbox" name="consent" required> I accept the booking terms and privacy policy and understand this request is subject to supplier availability and ticket confirmation.</label>
            <p><?php $terms = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('terms') : ''; if($terms): ?><a href="<?php echo esc_url($terms); ?>" target="_blank" rel="noopener">Booking terms</a><?php endif; ?> <?php $privacy=get_privacy_policy_url(); if($privacy): ?> <a href="<?php echo esc_url($privacy); ?>" target="_blank" rel="noopener">Privacy policy</a><?php endif; ?></p>
        </fieldset>
        <button class="oj-book" type="submit" disabled>Continue to checkout</button>
    </form>
    <noscript>Please enable JavaScript to use the OceanJet booking form.</noscript>
</div>
