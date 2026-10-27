(function () {
    'use strict';
    if (!window.hrOceanjet) return;
    const cfg = window.hrOceanjet;
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = cents => 'PHP ' + (cents / 100).toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    const time = value => { const [h,m] = value.split(':'); return (Number(h)%12 || 12) + ':' + m + (Number(h)<12 ? ' AM' : ' PM'); };
    document.querySelectorAll('.hr-oj').forEach((root, instance) => {
        const find = selector => root.querySelector(selector), form = find('.oj-booking'), fields = form.elements;
        let state = {}, quote = null, version = 0, timer, submitting = false;
        const error = message => { find('.oj-error').textContent = message; find('.oj-error').hidden = !message; };
        const status = message => { find('.oj-status').textContent = message; };
        async function request(mode, payload) {
            const response = await fetch(cfg.url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'hr_oceanjet',nonce:cfg.nonce,mode,payload:JSON.stringify(payload)})});
            let json;
            try { json = await response.json(); } catch (_) { throw new Error('Unable to reach booking service. Refresh the page and try again.'); }
            if (!response.ok || !json.success) throw new Error(json.data && json.data.message || 'The booking session expired. Refresh the page and try again.');
            return json.data;
        }
        function selectionReady() { return !!state.outbound && (!state.return || !!state.inbound); }
        function updateDetails() {
            const ready = !!quote && selectionReady();
            find('.oj-person').hidden = !ready; find('.oj-person').disabled = !ready;
            find('.oj-confirm').hidden = !ready; find('.oj-confirm').disabled = !ready;
            find('.oj-book').disabled = !ready || !fields.consent.checked || submitting;
        }
        function clearQuote() {
            quote = null; delete state.signature; fields.consent.checked = false;
            find('.oj-summary').hidden = true; updateDetails();
        }
        function showQuote(value) {
            quote = value; state.signature = value.signature;
            find('.oj-summary').innerHTML = '<p><strong>Passengers: ' + state.counts.adult + ' Adult(s), ' + state.counts.child + ' Child(ren)</strong></p>' + value.legs.map(leg => '<p>' + esc(leg.from) + ' → ' + esc(leg.to) + ' · ' + esc(leg.class) + '<br>' + leg.breakdown.map(esc).join('<br>') + '</p>').join('') + '<p>Ticketing charge: ' + money(value.ticket_fee) + '</p><p><strong>Booking subtotal: ' + money(value.cents) + '</strong></p><p>The convenience fee appears separately at checkout. Staff will confirm your tickets.</p>';
            find('.oj-summary').hidden = false; updateDetails();
        }
        async function selectLeg(key, id) {
            state[key] = id; clearQuote(); const current = ++version;
            if (!selectionReady()) return;
            status('Calculating booking total…');
            try {
                const value = await request('quote', state);
                if (current !== version) return;
                showQuote(value); status('Complete one passenger’s details and accept the terms to continue.');
            } catch(e) { if (current === version) { error(e.message); status('Please check your sailing selections.'); } }
        }
        function renderLeg(key, label, rows) {
            const box = document.createElement('fieldset'); box.className = 'oj-leg'; box.dataset.leg = key;
            const validRows = rows.filter(row => key !== 'inbound' || !state.outbound || row.date + ' ' + row.time > (outboundRows.find(r => r.id === state.outbound) || {}).arrival);
            box.innerHTML = '<legend>' + esc(label) + '</legend><div class="oj-options">' + validRows.map(row => '<label class="oj-sailing-option"><input type="radio" name="oj-' + instance + '-' + key + '" value="' + esc(row.id) + '"><span class="oj-time"><strong>' + time(row.time) + '</strong><span>' + esc(row.from_code || row.from) + '</span><small>' + esc(row.date) + '</small></span><span class="oj-duration">→<small>' + esc(row.duration) + ' mins</small></span><span class="oj-time"><strong>' + time(row.arrival.slice(11)) + '</strong><span>' + esc(row.to_code || row.to) + '</span><small>' + esc(row.arrival.slice(0,10)) + '</small></span><span class="oj-fares"><span class="oj-class">' + esc(row.class) + '</span>' + Object.entries(row.fares).filter(([,fare]) => fare !== null).map(([category,fare]) => '<span>' + esc(cfg.categories[category]) + ': <strong>' + money(fare) + '</strong></span>').join('') + '<small>Per passenger</small></span><span class="oj-code">' + esc(row.code) + '</span><span class="oj-select">Select</span></label>').join('') + '</div>';
            if (!validRows.length) box.querySelector('.oj-options').textContent = 'No sailings match this route, date and passenger mix. Try another date or contact us.';
            box.addEventListener('change', event => {
                box.querySelectorAll('.oj-sailing-option').forEach(card => { const selected = card.querySelector('input').checked; card.classList.toggle('is-selected',selected); card.querySelector('.oj-select').textContent = selected ? 'Selected' : 'Select'; });
                if (key === 'outbound' && state.return) {
                    delete state.inbound;
                    const old = find('[data-leg=inbound]');
                    if (old) old.replaceWith(renderLeg('inbound','Return: ' + state.to + ' → ' + state.from, inboundRows));
                }
                selectLeg(key, event.target.value);
            });
            return box;
        }
        let outboundRows = [], inboundRows = [];
        function inputState() {
            return {type:fields.type.value, from:fields.from.value, to:fields.to.value, date:fields.date.value, return:fields.type.value === 'round' ? fields.return.value : '', counts:{adult:Number(fields.adult.value),child:Number(fields.child.value)}};
        }
        function refresh() {
            clearTimeout(timer); ++version; error(''); state = inputState(); clearQuote(); find('.oj-legs').replaceChildren();
            fields.return.min = fields.date.value || cfg.today;
            const counts = state.counts, total = counts.adult + counts.child;
            const previousCategory = fields.category.value;
            fields.category.replaceChildren();
            Object.entries(cfg.categories).forEach(([key,label]) => { if (counts[key] > 0) fields.category.add(new Option(label,key)); });
            if ([...fields.category.options].some(option => option.value === previousCategory)) fields.category.value = previousCategory;
            if (!state.from || !state.to || !state.date || (fields.type.value === 'round' && !state.return)) { status('Choose your route and travel dates to see sailings.'); return; }
            if (![counts.adult,counts.child].every(n => Number.isInteger(n) && n >= 0) || total < 1 || total > cfg.max) { status('Enter at least one passenger, up to ' + cfg.max + ' in total.'); return; }
            if (!fields.date.validity.valid || (state.return && !fields.return.validity.valid)) { status('Check your travel dates.'); return; }
            const current = version; status('Loading sailings…');
            timer = setTimeout(async () => {
                try {
                    const result = await request('search',state);
                    if (current !== version) return;
                    outboundRows = result.outbound; inboundRows = result.inbound;
                    find('.oj-legs').append(renderLeg('outbound','Departure: ' + state.from + ' → ' + state.to,outboundRows));
                    if (state.return) find('.oj-legs').append(renderLeg('inbound','Return: ' + state.to + ' → ' + state.from,inboundRows));
                    status('Choose a sailing and accommodation for each direction. Fares shown are per passenger.');
                } catch(e) { if (current === version) { error(e.message); status('Change your selections to try again.'); } }
            }, 250);
        }
        Object.keys(cfg.routes).forEach(port => fields.from.add(new Option(port,port)));
        fields.date.min = cfg.today; fields.dob.max = cfg.today;
        fields.adult.max = cfg.max; fields.child.max = cfg.max;
        fields.from.addEventListener('change', () => {
            fields.to.replaceChildren(new Option('Choose destination',''));
            (cfg.routes[fields.from.value] || []).forEach(port => fields.to.add(new Option(port,port)));
            fields.to.disabled = !fields.from.value; dependencies(); refresh();
        });
        function dependencies() {
            const enabled = !!fields.from.value && !!fields.to.value, round = fields.type.value === 'round';
            fields.date.disabled = !enabled; find('.oj-counts').disabled = !enabled;
            find('.oj-return').hidden = !round; fields.return.disabled = !enabled || !round; fields.return.required = round;
        }
        fields.to.addEventListener('change', () => { dependencies(); refresh(); });
        fields.type.addEventListener('change', () => { dependencies(); refresh(); });
        ['date','return','adult','child'].forEach(name => fields[name].addEventListener('input',refresh));
        fields.consent.addEventListener('change',updateDetails);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (submitting || !quote || !selectionReady() || !form.reportValidity()) return;
            const person = Object.fromEntries([...find('.oj-person').querySelectorAll('input,select')].map(el => [el.name,el.value]));
            const payload = {...state, passengers:[person], consent:fields.consent.checked};
            submitting = true; const controls = [...form.querySelectorAll('input,select,button')].map(el => [el,el.disabled]); controls.forEach(([el]) => { el.disabled = true; });
            error(''); status('Submitting booking…');
            try { const result = await request('book',payload); window.location.assign(result.redirect); }
            catch(e) { refresh(); error(e.message + ' Please select your sailing again; your passenger details have been kept.'); }
            finally { submitting = false; controls.forEach(([el,disabled]) => { el.disabled = disabled; }); updateDetails(); }
        });
    });
}());
