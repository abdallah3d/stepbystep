// منطق مشترك لنسخة العرض: البيانات محفوظة في متصفح الجهاز فقط
const SBS = (() => {
  const GOAL = 5;            // ٥ خطوات، والكوب السادس مجاني
  const KEY = "sbs-demo-v1";
  const DAY = 86400000;

  // عملاء أمثلة للعرض
  function seed() {
    const now = Date.now();
    return {
      customers: {
        "0551234567": { steps: 3, total: 8,  free: 1, updated: now - 1000 },
        "0539876543": { steps: 5, total: 10, free: 1, updated: now - 2000 },
        "0501112233": { steps: 1, total: 13, free: 2, updated: now - DAY }
      },
      log: []
    };
  }

  function load() {
    try {
      const s = localStorage.getItem(KEY);
      if (s) return JSON.parse(s);
    } catch (e) {}
    const db = seed();
    save(db);
    return db;
  }
  function save(db) { try { localStorage.setItem(KEY, JSON.stringify(db)); } catch (e) {} }
  function reset() { const db = seed(); save(db); return db; }

  const ar = n => Number(n).toLocaleString("ar-SA");

  function norm(v) {
    let d = String(v).replace(/[٠-٩]/g, c => "٠١٢٣٤٥٦٧٨٩".indexOf(c)).replace(/\D/g, "");
    if (d.startsWith("00966")) d = "0" + d.slice(5);
    else if (d.startsWith("966")) d = "0" + d.slice(3);
    else if (/^5\d{8}$/.test(d)) d = "0" + d;
    return /^05\d{8}$/.test(d) ? d : null;
  }

  const POLY = "30,4 70,4 96,30 96,70 70,96 30,96 4,70 4,30";
  function oct(glyph) {
    return `<svg viewBox="0 0 100 100" aria-hidden="true"><polygon class="fill" points="${POLY}"/>` +
      `<polygon class="oct" points="${POLY}"/><text class="glyph" x="50" y="58" text-anchor="middle">${glyph}</text></svg>`;
  }
  function renderSteps(el, n, just) {
    let h = "";
    for (let i = 1; i <= GOAL; i++) {
      const on = i <= n;
      h += `<div class="step${on ? " on" : ""}${i === just ? " just" : ""}">${oct(on ? "✦" : ar(i))}<span>خطوة ${ar(i)}</span></div>`;
    }
    h += `<div class="step free${n >= GOAL ? " ready" : ""}${just === GOAL ? " just" : ""}">${oct("مجاني")}<span>الكوب ${ar(GOAL + 1)}</span></div>`;
    el.innerHTML = h;
  }

  function statusText(n) {
    if (n >= GOAL) return "كوبك القادم مجاني ☕ اطلبه الحين";
    if (n === 0) return "بطاقة جديدة، أول خطوة مع طلبك القادم";
    const left = GOAL - n;
    return left === 1 ? "باقي خطوة وحدة على كوبك المجاني" : `باقي ${ar(left)} خطوات على كوبك المجاني`;
  }

  let tt;
  function toast(msg) {
    const t = document.getElementById("toast");
    if (!t) return;
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(tt);
    tt = setTimeout(() => t.classList.remove("show"), 2200);
  }

  return { GOAL, load, save, reset, ar, norm, renderSteps, statusText, toast };
})();
