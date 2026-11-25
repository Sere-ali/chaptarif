// Tests automatiques ChapTarif : pages publiques (téléphone + ordinateur) et protections de sécurité.
// Usage : BASE=http://localhost:8097 node tests/smoke.mjs      (nécessite Playwright)
import { createRequire } from 'module';
const { chromium } = createRequire(import.meta.url)('playwright');
const BASE = process.env.BASE || 'http://localhost:8097';
const PAGES = ['/', '/transport', '/beaute', '/cars', '/covoiturage', '/immobilier', '/location-vehicule', '/chauffeur', '/menage', '/pressing', '/coiffeuse', '/maquilleuse', '/onglerie',
  '/comment-ca-marche', '/garantie-dommage', '/contact', '/connexion', '/devenir-prestataire', '/mentions-legales', '/sante'];
let fails = 0;
const bad = (m) => { fails++; console.log('  ✗ ' + m); };
const ok = (m) => console.log('  ✓ ' + m);

console.log('== Sécurité (sans connexion)');
const get = async (p, o = {}) => fetch(BASE + p, { redirect: 'manual', ...o });
for (const p of ['/.env', '/storage/chaptarif.sqlite', '/app/bootstrap.php', '/.git/config', '/Dockerfile', '/pages/login.php', '/f/../../etc/passwd']) {
  const r = await get(p); (r.status === 404 || r.status === 403) ? ok(`${p} inaccessible (${r.status})`) : bad(`${p} répond ${r.status}`);
}
for (const p of ['/admin', '/admin/clients', '/admin/finances', '/admin/document?id=1&k=cni_recto', '/admin/transfert-fichiers']) {
  const r = await get(p); (r.status === 302 && /\/admin\/login/.test(r.headers.get('location') || '')) ? ok(`${p} → connexion exigée`) : bad(`${p} accessible sans connexion (${r.status})`);
}
for (const p of ['/compte', '/espace-prestataire', '/chat']) {
  const r = await get(p); r.status === 302 ? ok(`${p} → connexion exigée`) : bad(`${p} accessible sans connexion (${r.status})`);
}
for (const p of ['/connexion', '/contact', '/reserver', '/devenir-prestataire', '/admin/messages']) {
  const r = await get(p, { method: 'POST', body: 'a=1', headers: { 'content-type': 'application/x-www-form-urlencoded' } }); r.status === 419 ? ok(`POST ${p} sans jeton refusé`) : bad(`POST ${p} sans jeton répond ${r.status}`);
}
const h = (await get('/')).headers;
for (const k of ['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy']) h.get(k) ? ok('en-tête ' + k) : bad('en-tête manquant : ' + k);

const b = await chromium.launch();
for (const [n, w, hh] of [['téléphone', 390, 844], ['ordinateur', 1440, 900]]) {
  console.log(`== Pages publiques — ${n}`);
  const c = await b.newContext({ viewport: { width: w, height: hh } });
  for (const p of PAGES) {
    const pg = await c.newPage(); const errs = [], reqs = [];
    pg.on('pageerror', (e) => errs.push(e.message));
    pg.on('response', (r) => { if (r.url().startsWith(BASE) && r.status() >= 400 && !/favicon/.test(r.url())) reqs.push(r.status() + ' ' + r.url().replace(BASE, '')); });
    const r = await pg.goto(BASE + p, { waitUntil: 'load' }).catch(() => null);
    const st = r ? r.status() : 0;
    const ovf = await pg.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1).catch(() => false);
    if (st >= 400 || !st) bad(`${p} : erreur ${st}`);
    else if (errs.length) bad(`${p} : erreur JavaScript ${errs[0]}`);
    else if (reqs.length) bad(`${p} : ressource manquante ${reqs[0]}`);
    else if (ovf) bad(`${p} : la page déborde sur le côté`);
    else ok(p);
    await pg.close();
  }
  await c.close();
}
await b.close();
console.log(fails ? `\n${fails} problème(s)` : '\nTout est OK');
process.exit(fails ? 1 : 0);
