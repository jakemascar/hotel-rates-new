const assert = require('node:assert/strict');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
let playwright; try { playwright=require('playwright'); } catch (_) { playwright=require('../.language-check/node_modules/playwright'); }
const {chromium}=playwright;
const php = process.env.PHP_BINARY || 'php';
(async()=>{
    const html=execFileSync(php,['tests/oceanjet-domain.php','--view'],{encoding:'utf8'});
    const browser=await chromium.launch(process.env.CHROME_BINARY ? {executablePath:process.env.CHROME_BINARY} : {channel:'chrome'});
    try {
        const page=await browser.newPage({viewport:{width:428,height:926}});
        const errors=[];page.on('pageerror',e=>errors.push(e.message));
        let submitted,delayDate='',searchCalls=0,failBooking=true;
        const makeLeg=(input,back=false,business=false)=>({id:back?'back':business?'business':'out',from:back?input.to:input.from,to:back?input.from:input.to,from_code:back?'SIQ':'DUM',to_code:back?'DUM':'SIQ',date:back?input.return:input.date,time:back?'12:00':'08:00',arrival:(back?input.return:input.date)+' '+(back?'13:00':'09:00'),duration:60,class:business?'Business Class':'Tourist Class',code:back?'OJ301':'OJ501',fares:{adult:business?90000:66000,child:business?45000:33000},cents:(input.counts.adult*(business?90000:66000)+input.counts.child*(business?45000:33000)),breakdown:[`Adult x ${input.counts.adult}`,`Child x ${input.counts.child}`]});
        await page.route('https://booking.test/**',async route=>{
            if(route.request().url().endsWith('/ajax')){
                const params=new URLSearchParams(route.request().postData()),input=JSON.parse(params.get('payload'));
                let data;
                if(params.get('mode')==='search'){
                    searchCalls++;
                    if(input.date===delayDate) await new Promise(resolve=>setTimeout(resolve,900));
                    data={outbound:[makeLeg(input),makeLeg(input,false,true)],inbound:input.return?[makeLeg(input,true)]:[]};
                } else if(params.get('mode')==='quote') {
                    const legs=[makeLeg(input,false,input.outbound==='business')];if(input.return)legs.push(makeLeg(input,true));
                    data={legs,cents:legs.reduce((sum,leg)=>sum+leg.cents,5000),ticket_fee:5000,signature:'server-'+input.date+'-'+input.outbound};
                } else {
                    if(failBooking){failBooking=false;return route.fulfill({status:400,contentType:'application/json',body:JSON.stringify({success:false,data:{message:'Fares changed.'}})});}
                    submitted=input;data={redirect:'https://booking.test/checkout'};
                }
                return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data})});
            }
            return route.fulfill({contentType:'text/html',body:'<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'+html});
        });
        await page.goto('https://booking.test/');
        await page.addStyleTag({content:fs.readFileSync('public/css/oceanjet.css','utf8')});
        await page.evaluate(()=>window.hrOceanjet={url:'https://booking.test/ajax',nonce:'test',routes:{Dumaguete:['Siquijor'],Siquijor:['Dumaguete']},categories:{adult:'Adult',child:'Child'},max:10,today:'2026-10-27'});
        await page.addScriptTag({content:fs.readFileSync('public/js/oceanjet.js','utf8')});
        const field=name=>page.locator('.oj-booking [name='+name+']');
        assert.equal(await field('to').isDisabled(),true);
        assert.equal(await field('adult').inputValue(),'0');assert.equal(await field('child').inputValue(),'0');
        assert.equal(await page.locator('.oj-person').isVisible(),false);
        await field('from').selectOption('Dumaguete');assert.equal(await field('to').isEnabled(),true);
        await field('to').selectOption('Siquijor');await field('date').fill('2099-01-02');
        await page.waitForTimeout(350);assert.equal(searchCalls,0,'zero passengers do not search');
        await field('child').fill('2');
        await page.locator('[data-leg=outbound] .oj-sailing-option').first().waitFor();
        assert.match(await page.locator('.oj-legs').innerText(),/Adult: PHP 660.00/);
        assert.equal(await page.locator('[data-leg=outbound] .oj-sailing-option').count(),2,'accommodations appear as separate cards');
        await page.locator('[data-leg=outbound] input[value=out]').check();await page.locator('.oj-person').waitFor({state:'visible'});
        assert.match(await page.locator('.oj-summary').innerText(),/710.00/,'child-only total');
        assert.equal(await page.locator('.oj-person').count(),1);assert.equal(await field('category').inputValue(),'child');
        for(const [name,value]of Object.entries({first:'Test',last:'Passenger',dob:'2018-01-01',nationality:'Canadian',phone:'+639123456789'}))await field(name).fill(value);
        await field('sex').selectOption('Female');
        await field('adult').fill('3');await field('child').fill('2');
        assert.equal(await page.locator('.oj-book').isDisabled(),true);
        await page.locator('[data-leg=outbound] input[value=out]').waitFor();await page.locator('[data-leg=outbound] input[value=out]').check();
        await page.locator('.oj-person').waitFor({state:'visible'});assert.equal(await field('first').inputValue(),'Test','passenger fields retained');
        assert.match(await page.locator('.oj-summary').innerText(),/2,690.00/,'mixed total');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'mobile form fits');
        await field('type').selectOption('round');assert.equal(await page.locator('.oj-return').isVisible(),true);
        await field('return').fill('2099-01-03');await page.locator('[data-leg=inbound] input').waitFor();
        await page.locator('[data-leg=outbound] input[value=out]').check();await page.locator('[data-leg=inbound] input').check();
        await page.locator('.oj-person').waitFor({state:'visible'});assert.match(await page.locator('.oj-summary').innerText(),/5,330.00/,'round trip fee once');
        await field('type').selectOption('one');assert.equal(await page.locator('.oj-return').isVisible(),false);
        await page.locator('[data-leg=outbound] input[value=out]').waitFor();assert.equal(await page.locator('[data-leg=inbound]').count(),0);
        delayDate='2099-01-04';await field('date').fill(delayDate);
        await page.waitForRequest(request=>request.url().endsWith('/ajax') && request.postData().includes('2099-01-04'));
        await field('date').fill('2099-01-05');await page.locator('[data-leg=outbound] input[value=out]').waitFor();
        await page.waitForTimeout(1000);assert.match(await page.locator('.oj-legs').innerText(),/2099-01-05/);assert.doesNotMatch(await page.locator('.oj-legs').innerText(),/2099-01-04/,'stale search ignored');
        await page.locator('[data-leg=outbound] input[value=business]').check();await page.locator('.oj-person').waitFor({state:'visible'});
        assert.equal(await field('first').inputValue(),'Test');assert.match(await page.locator('.oj-summary').innerText(),/3,650.00/);
        await page.setViewportSize({width:360,height:800});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'small mobile fits');
        await page.screenshot({path:'.language-check/oceanjet-mobile.tmp',type:'png',fullPage:true});
        await field('consent').check();await page.locator('.oj-book').click();
        await page.locator('.oj-error').waitFor({state:'visible'});
        assert.match(await page.locator('.oj-error').innerText(),/Fares changed/);
        await page.locator('[data-leg=outbound] input[value=business]').waitFor();
        assert.equal(await page.locator('.oj-book').isDisabled(),true,'failed booking needs a new selection');
        await page.locator('[data-leg=outbound] input[value=business]').check();await page.locator('.oj-person').waitFor({state:'visible'});
        assert.equal(await field('first').inputValue(),'Test','booking failure retains passenger details');
        await field('consent').check();await page.locator('.oj-book').click();await page.waitForURL('**/checkout');
        assert.equal(submitted.signature,'server-2099-01-05-business');assert.equal(submitted.passengers.length,1);assert.equal(submitted.passengers[0].email,'');assert.deepEqual(submitted.counts,{adult:3,child:2});assert.equal(submitted.return,'');assert.equal(submitted.consent,true);assert.deepEqual(errors,[]);
        console.log('PASS: one-page dependencies, zero defaults, child-only/mixed fares, accommodation cards, round trips, stale responses, retained details, mobile layout and checkout handoff.');
        // Exercise the actual shared-settings search/quote handlers in the browser.
        const configured=JSON.parse(execFileSync(php,['tests/oceanjet-workflow.php','--config-json'],{encoding:'utf8'}));
        configured.url='https://booking.test/real-ajax';
        const integration=await browser.newPage({viewport:{width:390,height:844}});
        integration.on('pageerror',e=>errors.push(e.message));
        await integration.route('https://booking.test/**',route=>{
            if(route.request().url().endsWith('/real-ajax')){
                const params=new URLSearchParams(route.request().postData());
                const data=JSON.parse(execFileSync(php,['tests/oceanjet-workflow.php',params.get('mode')==='search'?'--search-json':'--quote-json',params.get('payload')],{encoding:'utf8'}));
                return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data})});
            }
            return route.fulfill({contentType:'text/html',body:'<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'+html});
        });
        await integration.goto('https://booking.test/');
        await integration.addStyleTag({content:fs.readFileSync('public/css/oceanjet.css','utf8')});
        await integration.evaluate(value=>window.hrOceanjet=value,configured);
        await integration.addScriptTag({content:fs.readFileSync('public/js/oceanjet.js','utf8')});
        const realField=name=>integration.locator('.oj-booking [name='+name+']');
        await realField('from').selectOption('Siquijor');await realField('to').selectOption('Dumaguete');
        await realField('date').fill('2099-01-02');await realField('adult').fill('3');await realField('child').fill('2');
        await integration.locator('[data-leg=outbound] input').first().waitFor();
        assert.equal(await integration.locator('[data-leg=outbound] .oj-sailing-option').count(),4,'two accommodations at both B departure times');
        assert.match(await integration.locator('.oj-legs').innerText(),/Adult: PHP 700.00/,'B direction fare shown');
        const reverseCard=integration.locator('[data-leg=outbound] .oj-sailing-option').filter({hasText:'Tourist Class'}).last();
        assert.match(await reverseCard.innerText(),/2:00 PM/);
        await reverseCard.locator('input').check();await integration.locator('.oj-person').waitFor({state:'visible'});
        assert.match(await integration.locator('.oj-summary').innerText(),/2,850.00/,'real reverse quote');
        await realField('type').selectOption('round');await realField('return').fill('2099-01-03');
        await integration.locator('[data-leg=inbound] input').first().waitFor();
        assert.equal(await integration.locator('[data-leg=inbound] .oj-sailing-option').count(),4,'return uses A times and accommodations');
        await integration.locator('[data-leg=outbound] .oj-sailing-option').filter({hasText:'Tourist Class'}).first().locator('input').check();
        await integration.locator('[data-leg=inbound] .oj-sailing-option').filter({hasText:'Tourist Class'}).last().locator('input').check();
        await integration.locator('.oj-person').waitFor({state:'visible'});
        assert.match(await integration.locator('.oj-summary').innerText(),/5,490.00/,'real A/B round-trip total');
        assert.equal(await integration.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
        await integration.close();
        console.log('PASS: actual A/B settings feed reverse-origin dropdowns, all departure/accommodation choices and one-way/round-trip quotes.');
        const adminHtml=execFileSync(php,['tests/oceanjet-workflow.php','--view'],{encoding:'utf8'});
        await page.setContent('<meta name="viewport" content="width=device-width,initial-scale=1">'+adminHtml);
        assert.equal(await page.locator('.oj-route-group').count(),1);
        const existingGroup=page.locator('.oj-route-group').first();
        assert.equal(await existingGroup.evaluate(el=>el.open),false,'existing groups start collapsed');
        assert((await existingGroup.locator('.oj-route-toggle').evaluate(el=>getComputedStyle(el,'::before').content)).includes('↓'),'collapsed accordion uses down arrow');
        assert.equal(await existingGroup.locator('.oj-route-title').innerText(),'Dumaguete ↔ Siquijor');
        const collapsedValues=await page.locator('.hr-oj-admin form').evaluate(form=>[...new FormData(form).entries()]);
        await existingGroup.locator('.oj-route-heading').focus();await page.keyboard.press('Enter');
        assert.equal(await existingGroup.evaluate(el=>el.open),true,'accordion supports keyboard opening');
        assert((await existingGroup.locator('.oj-route-toggle').evaluate(el=>getComputedStyle(el,'::before').content)).includes('↑'),'expanded accordion uses up arrow');
        assert.equal(await existingGroup.locator('.oj-code-field input').first().evaluate(el=>el.required),false,'trip code optional in admin');
        assert.deepEqual(await page.locator('.hr-oj-admin form').evaluate(form=>[...new FormData(form).entries()]),collapsedValues,'collapsed groups retain all submitted values');
        assert.equal(await page.locator('.oj-direction').count(),2);
        assert.equal(await page.locator('[name*="[start]"],[name*="[end]"]').count(),0,'no first/last operating date inputs');
        assert.match(await page.locator('[data-direction=b] .oj-direction-label').innerText(),/Siquijor → Dumaguete/);
        assert.equal(await page.locator('[name="groups[0][directions][a][accommodations][0][fares][child]"]').inputValue(),'330.00');
        await page.locator('#oj-add-group').click();
        const group=page.locator('.oj-route-group').last();
        assert.equal(await group.evaluate(el=>el.open),true,'new groups open automatically');
        assert.equal(await group.locator('.oj-route-title').innerText(),'New route group');
        await group.locator('.oj-route-heading').click();
        assert.equal(await group.evaluate(el=>el.open),false,'accordion closes on click');
        assert.equal(await group.locator('[data-port=from]').evaluate(el=>el.reportValidity()),false);
        assert.equal(await group.evaluate(el=>el.open),true,'invalid field opens its group for correction');
        assert.equal(await group.locator('.oj-direction').count(),2,'new group has fixed A/B');
        await group.locator('[data-port=from]').fill('Cebu');await group.locator('[data-port=to]').fill('Dumaguete');
        assert.equal(await group.locator('.oj-route-title').innerText(),'Cebu ↔ Dumaguete','accordion heading follows edited ports');
        assert.equal(await group.locator('[data-direction=a] .oj-direction-label').innerText(),'Cebu → Dumaguete');
        assert.equal(await group.locator('[data-direction=b] .oj-direction-label').innerText(),'Dumaguete → Cebu');
        const a=group.locator('[data-direction=a]'),b=group.locator('[data-direction=b]');
        for(const section of [a,b]){
            assert.deepEqual(await section.locator('[name$="[name]"]').evaluateAll(els=>els.map(el=>el.value)),['Tourist Class','Open Air','Business Class'],'three default accommodations');
            assert.deepEqual(await section.locator('input[type=number][name*="[fares]"]').evaluateAll(els=>els.map(el=>el.value)),Array(6).fill('0.00'),'new fares start at zero');
        }
        const aRows=a.locator('.oj-accommodation-row');
        for(let i=0;i<3;i++){
            await aRows.nth(i).locator('[name$="[adult]"]').fill(String(1500+i*100));
            await aRows.nth(i).locator('[name$="[child]"]').fill(String(500+i*50));
        }
        await aRows.first().locator('[name$="[id]"]').evaluate(el=>el.value='source-tourist');
        await b.locator('.oj-accommodation-row').first().locator('[name$="[id]"]').evaluate(el=>el.value='return-tourist');
        await b.locator('[name$="[code]"]').fill('RETURN-CODE');
        await b.locator('[name$="[duration]"]').fill('90');
        await b.locator('[name$="[blocked]"]').fill('2099-01-02');
        await b.locator('.oj-time-hour').selectOption('12');await b.locator('.oj-time-period').selectOption('PM');
        await b.locator('[name$="[days][]"]').first().uncheck();
        const schedule=await b.evaluate(el=>[...el.querySelectorAll('input,select')].filter(input=>input.name&&!input.closest('.oj-accommodation-row')).map(input=>[input.name,input.value,input.checked]));
        await a.locator('.oj-copy-accommodations').click();
        assert.equal(await b.locator('.oj-accommodation-row').count(),3,'copy replaces B rows');
        assert.deepEqual(await b.locator('input[type=number][name*="[fares]"]').evaluateAll(els=>els.map(el=>el.value)),['1500','500','1600','550','1700','600']);
        assert.equal(await b.locator('.oj-accommodation-row').first().locator('[name$="[id]"]').inputValue(),'return-tourist','existing B IDs preserved');
        assert.deepEqual(await b.evaluate(el=>[...el.querySelectorAll('input,select')].filter(input=>input.name&&!input.closest('.oj-accommodation-row')).map(input=>[input.name,input.value,input.checked])),schedule,'B schedule fields preserved');
        assert((await b.locator('.oj-accommodations [name]').evaluateAll(els=>els.map(el=>el.name))).every(name=>name.startsWith('groups[1][directions][b]')),'copy posts to B in this group');
        assert.equal(await page.locator('[name="groups[0][directions][a][accommodations][0][fares][child]"]').inputValue(),'330.00','other groups unchanged');
        await aRows.first().locator('[name$="[adult]"]').fill('1900');
        assert.equal(await b.locator('.oj-accommodation-row').first().locator('[name$="[adult]"]').inputValue(),'1500','copied values remain separately editable');
        await a.locator('.oj-add-accommodation').click();await a.locator('.oj-add-time').click();
        assert.equal(await a.locator('.oj-accommodation-row').count(),4);assert.equal(await a.locator('.oj-time-row').count(),2);
        const bSettings=await b.evaluate(el=>[...el.querySelectorAll('[name]')].filter(input=>!input.closest('.oj-time-row')).map(input=>[input.name,input.value,input.checked]));
        await a.locator('.oj-time-row').last().locator('.oj-time-hour').selectOption('1');
        await a.locator('.oj-time-row').last().locator('.oj-time-minute').selectOption('35');
        await a.locator('.oj-time-row').last().locator('.oj-time-period').selectOption('PM');
        await a.locator('.oj-copy-times').click();await a.locator('.oj-copy-times').click();
        assert.deepEqual(await b.locator('.oj-time-value').evaluateAll(els=>els.map(el=>el.value)),['08:00','13:35'],'time copy replaces B and preserves AM/PM');
        assert.deepEqual(await b.evaluate(el=>[...el.querySelectorAll('[name]')].filter(input=>!input.closest('.oj-time-row')).map(input=>[input.name,input.value,input.checked])),bSettings,'time copy preserves B fares and direction settings');
        const timeNames=await b.locator('.oj-time-value').evaluateAll(els=>els.map(el=>el.name));
        assert(timeNames.every(name=>name.startsWith('groups[1][directions][b][times]')));assert.equal(new Set(timeNames).size,2,'copied times have unique B indexes');
        await a.locator('.oj-time-row').last().locator('.oj-time-hour').selectOption('2');
        assert.equal(await b.locator('.oj-time-value').last().inputValue(),'13:35','copied times remain separately editable');
        assert.equal(await page.locator('.oj-route-group').first().locator('[data-direction=b] .oj-time-value').inputValue(),'12:00','other route group times preserved');
        assert.equal(await b.locator('.oj-copy-times').count(),0,'copy button belongs to section A');
        const names=await group.locator('[name]').evaluateAll(els=>els.map(el=>el.name));
        assert(names.every(name=>name.startsWith('groups[1]')),'new inputs have independent group indexes');
        assert.equal(new Set(names.filter(name=>name.endsWith('[name]'))).size,7,'accommodation indexes differ');
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'admin editor fits small mobile');
        await page.screenshot({path:'.language-check/oceanjet-admin-mobile.tmp',type:'png',fullPage:true});
        await a.locator('.oj-remove-accommodation').last().click();await a.locator('.oj-remove-time').last().click();
        assert.equal(await a.locator('.oj-accommodation-row').count(),3);assert.equal(await a.locator('.oj-time-row').count(),1);
        page.on('dialog',dialog=>dialog.accept());
        await group.locator('.oj-remove-group').click();assert.equal(await page.locator('.oj-route-group').count(),1);
        assert.deepEqual(errors,[]);
        console.log('PASS: fixed A/B editor, migrated fares, live port labels, multiple accommodations/times, removal and mobile layout.');
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1)});
