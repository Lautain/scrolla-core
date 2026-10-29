// Grondige test van de kanarie (bp.scrolla.dev) na updates.
// Gebruik: node kanarie.js [--url https://bp.scrolla.dev] [--meting onderhoud.json] [--out map]
// Uitvoer: JSON op stdout met per pagina status, fouten en metingen; exitcode 1 bij fouten.
const { chromium } = require('playwright');
const fs = require('fs');
const arg = (k, d) => { const i = process.argv.indexOf('--' + k); return i > -1 ? process.argv[i + 1] : d; };
const BASE = arg('url', 'https://bp.scrolla.dev').replace(/\/$/, '');
const OUT = arg('out', '/tmp/kanarie');
const metingFile = arg('meting', '');
const vorige = metingFile && fs.existsSync(metingFile) ? (JSON.parse(fs.readFileSync(metingFile, 'utf8')).meting || {}) : {};
fs.mkdirSync(OUT, { recursive: true });

const PAGINAS = ['/', '/onderhoudstest/', '/contact/'];
const VIEWPORTS = { desktop: 1440, mobiel: 390 };
const FOUTTEKST = /critical error|kritieke fout|Fatal error|Parse error|Warning:<\/b>|Notice:<\/b>|Deprecated:<\/b>|wp-die-message/i;

(async () => {
  const res = { url: BASE, tijd: new Date().toISOString(), fouten: [], waarschuwingen: [], paginas: {}, meting: {} };
  const fout = (m) => res.fouten.push(m), let_op = (m) => res.waarschuwingen.push(m);

  // Basis-endpoints
  const b = await chromium.launch();
  const ctx = await b.newContext({ ignoreHTTPSErrors: false });
  const req = ctx.request;
  for (const [p, verwacht] of [['/wp-json/', 200], ['/wp-json/scrolla/v1/status', 200], ['/wp-login.php', 200], ['/bestaat-niet-' + Date.now() + '/', 404]]) {
    const r = await req.get(BASE + p, { maxRedirects: 3 }).catch(e => null);
    const s = r ? r.status() : 0;
    if (s !== verwacht) fout(`${p}: status ${s}, verwacht ${verwacht}`);
  }
  const st = await (await req.get(BASE + '/wp-json/scrolla/v1/status')).json().catch(() => ({}));
  res.status = st;
  if (st.fouten_7d) fout(`fatale fouten in 7 dagen: ${st.fouten_7d} (${(st.fouten_waar || []).join(', ')})`);
  if (st.mislukt_7d) fout(`mislukte updates in 7 dagen: ${st.mislukt_7d}`);

  for (const [vp, w] of Object.entries(VIEWPORTS)) {
    const page = await b.newPage({ viewport: { width: w, height: 900 } });
    const console_fouten = [];
    page.on('console', m => { if (m.type() === 'error') console_fouten.push(m.text().slice(0, 200)); });
    page.on('pageerror', e => console_fouten.push('JS: ' + String(e.message).slice(0, 200)));
    const kapot = [];
    page.on('response', r => { if (r.status() >= 400 && r.url().startsWith(BASE)) kapot.push(r.status() + ' ' + r.url().replace(BASE, '')); });
    for (const p of PAGINAS) {
      const key = `${p}|${vp}`;
      console_fouten.length = 0; kapot.length = 0;
      const t0 = Date.now();
      const r = await page.goto(BASE + p, { waitUntil: 'networkidle', timeout: 45000 }).catch(e => null);
      const html = r ? await page.content() : '';
      const m = await page.evaluate(() => {
        const q = s => document.querySelectorAll(s).length;
        return {
          hoogte: document.documentElement.scrollHeight,
          elementen: q('body *'),
          widgets: q('.elementor-widget'),
          elementor_css: [...document.querySelectorAll('link[rel=stylesheet]')].some(l => /elementor/.test(l.href)),
          header: q('header, .elementor-location-header') > 0,
          footer: q('footer, .elementor-location-footer') > 0,
          formulier: q('form.elementor-form'),
          menu: q('.elementor-nav-menu, nav'),
          leeg_widget: [...document.querySelectorAll('.elementor-widget')].filter(e => e.offsetHeight === 0 && !e.classList.contains('elementor-hidden-desktop') && !e.classList.contains('elementor-hidden-mobile')).length,
        };
      }).catch(() => ({}));
      m.ms = Date.now() - t0;
      res.paginas[key] = { status: r ? r.status() : 0, ...m, console: [...console_fouten], kapot: [...kapot] };
      res.meting[key] = { hoogte: m.hoogte, elementen: m.elementen, widgets: m.widgets };
      await page.screenshot({ path: `${OUT}/${p.replace(/\W+/g, '_') || 'home'}_${vp}.png`, fullPage: true }).catch(() => {});
      if (!r || r.status() !== 200) fout(`${key}: status ${r ? r.status() : 'geen antwoord'}`);
      if (FOUTTEKST.test(html)) fout(`${key}: PHP-fout of foutmelding in de pagina`);
      if (!m.elementor_css) fout(`${key}: Elementor-CSS ontbreekt`);
      if (console_fouten.length) fout(`${key}: JS-fouten: ${console_fouten.slice(0, 3).join(' | ')}`);
      if (kapot.length) fout(`${key}: kapotte bronnen: ${kapot.slice(0, 5).join(', ')}`);
      if (p === '/onderhoudstest/') {
        if (m.widgets < 8) fout(`${key}: ${m.widgets} widgets zichtbaar, verwacht 8`);
        if (!m.formulier) fout(`${key}: Elementor Pro-formulier ontbreekt`);
        if (m.leeg_widget) let_op(`${key}: ${m.leeg_widget} widget(s) zonder hoogte`);
      }
      const v = vorige[key];
      if (v) {
        const dh = Math.abs(m.hoogte - v.hoogte) / Math.max(v.hoogte, 1);
        const de = Math.abs(m.elementen - v.elementen) / Math.max(v.elementen, 1);
        if (dh > 0.15) fout(`${key}: paginahoogte ${v.hoogte} > ${m.hoogte} (${Math.round(dh * 100)}%)`);
        else if (dh > 0.05) let_op(`${key}: paginahoogte ${Math.round(dh * 100)}% anders`);
        if (de > 0.15) fout(`${key}: aantal elementen ${v.elementen} > ${m.elementen}`);
        if (v.widgets !== m.widgets) fout(`${key}: aantal widgets ${v.widgets} > ${m.widgets}`);
      }
    }
    await page.close();
  }

  // Formulier verzenden zonder geldige invoer: moet een validatie- of foutmelding geven, geen PHP-fout.
  const page = await b.newPage();
  await page.goto(BASE + '/onderhoudstest/', { waitUntil: 'networkidle' });
  const ajax = page.waitForResponse(r => r.url().includes('admin-ajax.php'), { timeout: 15000 }).catch(() => null);
  await page.fill('form.elementor-form input[type=email]', 'onderhoud@scrolla.nl').catch(() => {});
  await page.fill('form.elementor-form input[type=text]', 'Onderhoudstest').catch(() => {});
  await page.click('form.elementor-form button[type=submit]').catch(() => {});
  const ar = await ajax;
  if (!ar) fout('formulier: geen AJAX-verzoek na verzenden');
  else {
    const body = await ar.text().catch(() => '');
    if (ar.status() !== 200 || !/"success":(true|false)/.test(body)) fout(`formulier: onverwacht antwoord ${ar.status()} ${body.slice(0, 120)}`);
  }
  await b.close();
  res.ok = res.fouten.length === 0;
  console.log(JSON.stringify(res, null, 2));
  process.exit(res.ok ? 0 : 1);
})();
