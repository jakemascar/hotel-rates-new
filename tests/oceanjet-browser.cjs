const assert = require('node:assert/strict');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
let playwright; try { playwright=require('playwright'); } catch(e) { playwright=require('../.language-check/node_modules/playwright'); }
const {chromium}=playwright;
(async()=>{
    const html=execFileSync(process.env.PHP_BINARY || 'php',['tests/oceanjet-domain.php','--view'],{encoding:'utf8'});
    const browser=await chromium.launch((process.env.CHROME_BINARY ? {executablePath:process.env.CHROME_BINARY} : {channel:'chrome'}));
    const page=await browser.newPage({viewport:{width:428,height:926}});
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    const leg={id:'out',from:'Bacolod',to:'Iloilo',date:'2099-01-02',time:'08:00',arrival:'2099-01-02 09:00',class:'Tourist Class',code:'OJ101',cents:66000,breakdown:['Adult x 1 @ PHP 660.00']};
    let submitted;
    await page.route('https://booking.test/**',route=>{
        if(route.request().url().endsWith('/ajax')){
            const params=new URLSearchParams(route.request().postData());const payload=JSON.parse(params.get('payload'));
            let data;
            if(params.get('mode')==='search')data={outbound:[leg],inbound:[]};
            else if(params.get('mode')==='review'){assert.equal(payload.passengers[0].first,'Test');data={legs:[leg],cents:66000,ticket_fee:0,signature:'server-quote'};}
            else {submitted=payload;data={redirect:'https://booking.test/checkout'};}
            return route.fulfill({contentType:'application/json',body:JSON.stringify({success:true,data})});
        }
        return route.fulfill({contentType:'text/html',body:'<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">'+html});
    });
    await page.goto('https://booking.test/');
    await page.addStyleTag({content:fs.readFileSync('public/css/oceanjet.css','utf8')});
    await page.evaluate(()=>window.hrOceanjet={url:'https://booking.test/ajax',nonce:'test',routes:{Bacolod:['Iloilo'],Iloilo:['Bacolod']},categories:{adult:'Adult',minor:'Minor',infant:'Infant',student:'Student',pwd:'PWD',senior:'Senior Citizen'},max:10,infantMax:5,minorMax:11,seniorMin:60,today:'2099-01-01'});
    await page.addScriptTag({content:fs.readFileSync('public/js/oceanjet.js','utf8')});
    await page.locator('[name=from]').selectOption('Bacolod');await page.locator('[name=to]').selectOption('Iloilo');await page.locator('[name=date]').fill('2099-01-02');
    await page.getByRole('button',{name:'Search trips',exact:true}).click();
    await page.locator('.oj-accommodation').selectOption('Tourist Class');await page.locator('.oj-options input').check();await page.locator('.oj-to-passengers').click();
    for(const [name,value]of Object.entries({first:'Test',last:'Passenger',dob:'2070-01-01',nationality:'Filipino',phone:'+639123456789',email:'test@example.com'}))await page.locator('.oj-person [name='+name+']').fill(value);
    await page.locator('.oj-person [name=sex]').selectOption('Female');await page.locator('.oj-copy').click();await page.locator('[name=consent]').check();
    await page.getByRole('button',{name:'Review booking',exact:true}).click();await page.locator('.oj-review').waitFor({state:'visible'});
    assert.match(await page.locator('.oj-summary').innerText(),/660.00/);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,'mobile form fits');
    await page.getByRole('button',{name:'Edit passengers',exact:true}).click();assert.equal(await page.locator('.oj-person [name=first]').inputValue(),'Test');
    await page.getByRole('button',{name:'Review booking',exact:true}).click();await page.locator('.oj-review').waitFor({state:'visible'});await page.locator('.oj-book').click();await page.waitForURL('**/checkout');
    assert.equal(submitted.signature,'server-quote');assert.equal(submitted.contact.email,'test@example.com');assert.deepEqual(errors,[]);
    await browser.close();console.log('PASS: mobile wizard, contact copy, back navigation, server quote, and checkout handoff (mock transport).');
})().catch(e=>{console.error(e);process.exit(1)});