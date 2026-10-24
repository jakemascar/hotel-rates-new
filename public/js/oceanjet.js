(function () {
    'use strict';
    if (!window.hrOceanjet) return;
    const cfg = window.hrOceanjet;
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = cents => 'PHP ' + (cents / 100).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
    document.querySelectorAll('.hr-oj').forEach((root, instance) => {
        const find = s => root.querySelector(s);
        const search = find('.oj-search'), peopleForm = find('.oj-passengers');
        let state = {}, results = {}, peopleKey = '', busy = false;
        const error = message => { const el=find('.oj-error'); el.textContent=message; el.hidden=!message; if(message) el.scrollIntoView({block:'nearest'}); };
        function show(step) {
            error('');
            ['search','schedule','passengers','review'].forEach((name,i)=>{
                find('.oj-'+name).hidden=name!==step;
                const li=root.querySelectorAll('.oj-progress li')[i];
                if(name===step)li.setAttribute('aria-current','step');else li.removeAttribute('aria-current');
            });
            find('.oj-'+step).focus();
        }
        async function request(mode, payload) {
            const response = await fetch(cfg.url, {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'hr_oceanjet',nonce:cfg.nonce,mode,payload:JSON.stringify(payload)})});
            let json; try { json=await response.json(); } catch(e) { throw new Error('Unable to reach booking service. Refresh this page and try again.'); }
            if(!response.ok || !json.success) throw new Error(json.data && json.data.message || 'The booking session expired. Refresh this page and try again.');
            return json.data;
        }
        async function run(task) {
            if(busy)return; busy=true; error('');root.setAttribute('aria-busy','true');
            root.querySelectorAll('button').forEach(b=>b.disabled=true);
            try { await task(); } catch(e) {error(e.message);} finally {busy=false;root.removeAttribute('aria-busy');root.querySelectorAll('button').forEach(b=>b.disabled=false);}
        }
        Object.keys(cfg.routes).forEach(port=>search.elements.from.add(new Option(port,port)));
        search.elements.from.addEventListener('change',()=>{
            search.elements.to.replaceChildren(new Option('Choose destination',''));
            (cfg.routes[search.elements.from.value]||[]).forEach(port=>search.elements.to.add(new Option(port,port)));
        });
        search.elements.type.addEventListener('change',()=>{
            const round=search.elements.type.value==='round';find('.oj-return').hidden=!round;search.elements.return.required=round;
        });
        search.elements.date.min=cfg.today;
        search.elements.date.addEventListener('change',()=>search.elements.return.min=search.elements.date.value||cfg.today);
        const hints={adult:'Ages '+(Number(cfg.minorMax)+1)+'+',minor:'Ages 2–'+cfg.minorMax,infant:'Under 2',student:'Student, '+(Number(cfg.minorMax)+1)+'+',pwd:'PWD, '+(Number(cfg.minorMax)+1)+'+',senior:'Ages '+cfg.seniorMin+'+'};
        find('.oj-counts').innerHTML=Object.entries(cfg.categories).map(([key,label])=>'<label>'+esc(label)+' <small>'+esc(hints[key])+'</small><input type="number" name="'+key+'" min="0" max="'+(key==='infant'?cfg.infantMax:cfg.max)+'" step="1" value="'+(key==='adult'?1:0)+'" required></label>').join('');
        function renderLeg(key,label,rows) {
            const box=document.createElement('fieldset');box.dataset.leg=key;
            box.innerHTML='<legend>'+esc(label)+'</legend><label>Accommodation<select class="oj-accommodation"><option value="">Choose accommodation</option></select></label><div class="oj-options"></div>';
            const select=box.querySelector('select'), options=box.querySelector('.oj-options');
            [...new Set(rows.map(row=>row.class))].forEach(name=>select.add(new Option(name,name)));
            const render=()=>{
                delete state[key]; delete state.signature;
                const filtered=rows.filter(row=>row.class===select.value);
                options.innerHTML=filtered.map(row=>'<label class="oj-sailing-option"><input type="radio" name="oj-'+instance+'-'+key+'" value="'+esc(row.id)+'"><span><strong>'+esc(row.time)+' → '+esc(row.arrival)+'</strong><br>'+esc(row.from)+' → '+esc(row.to)+' · '+esc(row.code)+'<br>'+esc(row.class)+' · '+money(row.cents)+' for your passengers</span></label>').join('');
                if(!rows.length)options.textContent='No configured trips match this date, route and passenger mix. Try another date or contact us.';
            };
            select.addEventListener('change',render);
            options.addEventListener('change',event=>{state[key]=event.target.value;delete state.signature;});
            render();return box;
        }
        search.addEventListener('submit',event=>{event.preventDefault();
            const counts={};Object.keys(cfg.categories).forEach(key=>counts[key]=Number(search.elements[key].value));
            const input={from:search.elements.from.value,to:search.elements.to.value,date:search.elements.date.value,return:search.elements.type.value==='round'?search.elements.return.value:'',counts};
            run(async()=>{results=await request('search',input);state=input;const box=find('.oj-legs');box.replaceChildren(renderLeg('outbound','Departure: '+state.from+' → '+state.to,results.outbound));if(state.return)box.append(renderLeg('inbound','Return: '+state.to+' → '+state.from,results.inbound));show('schedule');});
        });
        function passengerFields(category,index) {
            const field=(key,label,type='text',required=true)=>'<label>'+label+'<input name="'+key+'" type="'+type+'" '+(required?'required':'')+' maxlength="150" '+(key==='dob'?'max="'+esc(state.date)+'"':'')+'></label>';
            return '<fieldset class="oj-person" data-category="'+category+'"><legend>Passenger '+(index+1)+' — '+esc(cfg.categories[category])+'</legend><div class="oj-grid">'+field('first','First name')+field('last','Last name')+field('middle','Middle name (optional)','text',false)+field('dob','Birth date','date')+'<label>Sex<select name="sex" required><option value="">Choose</option><option>Male</option><option>Female</option></select></label>'+field('nationality','Nationality')+field('phone','Contact number'+(['minor','infant'].includes(category)?' (optional)':''),'tel',!['minor','infant'].includes(category))+field('email','Email (optional)','email',false)+'</div><p class="oj-age"></p></fieldset>';
        }
        function updateAges() {
            root.querySelectorAll('.oj-person').forEach(fieldset=>{
                const dob=fieldset.querySelector('[name=dob]').value;
                if(!dob){fieldset.querySelector('.oj-age').textContent='';return;}
                const age=date=>{let years=Number(date.slice(0,4))-Number(dob.slice(0,4));if(date.slice(5)<dob.slice(5))years--;return years;};
                fieldset.querySelector('.oj-age').textContent='Age on departure: '+age(state.date)+(state.return?' · Age on return: '+age(state.return):'');
            });
        }
        find('.oj-to-passengers').addEventListener('click',()=>{
            if(!state.outbound || state.return&&!state.inbound){error('Choose an accommodation and sailing for each leg.');return;}
            const key=JSON.stringify(state.counts);
            if(key!==peopleKey){let index=0;find('.oj-people').innerHTML=Object.entries(state.counts).map(([category,count])=>Array.from({length:count},()=>passengerFields(category,index++)).join('')).join('');peopleKey=key;}
            updateAges();show('passengers');
        });
        peopleForm.addEventListener('input',updateAges);
        const values=fieldset=>Object.fromEntries([...fieldset.querySelectorAll('input,select')].filter(el=>el.name).map(el=>[el.name,el.value]));
        find('.oj-copy').addEventListener('click',()=>{
            const first=find('.oj-person');if(!first)return;const data=values(first);
            ['first','last','phone','email'].forEach(key=>find('.oj-contact [name='+key+']').value=data[key]||'');find('.oj-contact [name=confirm]').value=data.email||'';
        });
        peopleForm.addEventListener('submit',event=>{event.preventDefault();
            state.passengers=[...root.querySelectorAll('.oj-person')].map(values);state.contact=values(find('.oj-contact'));state.consent=peopleForm.elements.consent.checked;
            run(async()=>{const quote=await request('review',state);state.signature=quote.signature;
                find('.oj-summary').innerHTML=quote.legs.map((leg,i)=>'<h4>'+(i?'Return':'Departure')+'</h4><p>'+esc(leg.from)+' → '+esc(leg.to)+'<br>'+esc(leg.date)+' '+esc(leg.time)+' · '+esc(leg.class)+' · '+esc(leg.code)+'<br>Arrival '+esc(leg.arrival)+' (Philippine time)</p><ul>'+leg.breakdown.map(line=>'<li>'+esc(line)+'</li>').join('')+'</ul>').join('')+'<h4>Passengers</h4><ul>'+state.passengers.map((person,i)=>'<li>'+esc(person.first+' '+person.last)+' — '+esc(root.querySelectorAll('.oj-person')[i].dataset.category)+'</li>').join('')+'</ul><p>Booking contact: '+esc(state.contact.first+' '+state.contact.last)+' · '+esc(state.contact.email)+' · '+esc(state.contact.phone)+'</p><p>Ticketing charge: '+money(quote.ticket_fee)+'</p><p><strong>Booking subtotal: '+money(quote.cents)+'</strong></p>';show('review');
            });
        });
        find('.oj-book').addEventListener('click',()=>run(async()=>{
            const result=await request('book',state);window.location.assign(result.redirect);
        }));
        root.querySelectorAll('[data-back]').forEach(button=>button.addEventListener('click',()=>{delete state.signature;show(button.dataset.back);}));
        show('search');
    });
}());