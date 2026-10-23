/**
 * OEM Vision — Enterprise Live Dynamic Sync Bridge
 * 
 * Fetches settings, product statistics, specs, images, navigation menu, CMS texts & docs
 * from /api/settings.php and seamlessly synchronizes the DOM across all pages.
 */

(function () {
  'use strict';

  function determineLang() {
    const path = window.location.pathname;
    if (path.startsWith('/en') || path.includes('/en/')) return 'en';
    return 'tr';
  }

  const lang = determineLang();
  const urlParams = new URLSearchParams(window.location.search);
  const isPreview = urlParams.get('preview') === '1';

  async function syncSite() {
    try {
      let apiUrl = '/api/settings.php?lang=' + lang;
      if (isPreview) apiUrl += '&preview=1';

      const res = await fetch(apiUrl, {
        headers: { 'Accept': 'application/json' },
        cache: 'no-cache'
      });
      if (!res.ok) return;

      const data = await res.json();
      if (!data || !data.success) return;

      applySettings(data.general || {});
      applyContent(data.content || {});
      applyDocsCacheBuster();

      if (data.is_preview || isPreview) {
        showPreviewBadge();
      }
    } catch (e) {
      // Sessiz hata (Statik sayfalar kesintisiz yayında)
    }
  }

  // 1. Genel İletişim Ayarları
  function applySettings(gen) {
    if (!gen) return;

    // Sabit Telefon
    if (gen.phone) {
      document.querySelectorAll('a[href^="tel:"]').forEach(el => {
        const href = el.getAttribute('href');
        if (href.includes('5772567') || href.includes('212')) {
          el.setAttribute('href', 'tel:' + (gen.phone_clean || gen.phone));
          if (el.textContent.includes('212') || el.textContent.includes('577')) {
            el.textContent = gen.phone;
          }
        }
        if (gen.gsm && (href.includes('6361200') || href.includes('544'))) {
          el.setAttribute('href', 'tel:' + (gen.gsm_clean || gen.gsm));
          if (el.textContent.includes('544') || el.textContent.includes('636')) {
            el.textContent = gen.gsm;
          }
        }
      });
    }

    // E-posta
    if (gen.email) {
      document.querySelectorAll('a[href^="mailto:"]').forEach(el => {
        if (el.getAttribute('href').includes('info@oemvision.com.tr')) {
          el.setAttribute('href', 'mailto:' + gen.email);
          if (el.textContent.includes('info@oemvision.com.tr')) {
            el.textContent = gen.email;
          }
        }
      });
    }

    // Adres
    if (gen.address) {
      document.querySelectorAll('address, p, td').forEach(el => {
        if (el.textContent.includes('İkitelli O.S.B') || el.textContent.includes('İpkas San')) {
          if (el.children.length === 0) {
            el.textContent = gen.address;
          }
        }
      });
    }
  }

  // 2. İçerikler, Menü, Ürünler & Sayfalar
  function applyContent(content) {
    if (!content) return;

    const path = window.location.pathname;

    // A) Menü Senkronizasyonu
    if (Array.isArray(content.menu)) {
      content.menu.forEach(item => {
        if (!item.visible) {
          // Menüyü gizle
          document.querySelectorAll(`header nav a[href="${item.url}"], header nav a[href="${item.url}/"]`).forEach(el => {
            el.style.display = 'none';
          });
        }
      });
    }

    // B) Ana Sayfa Hero
    const hero = content.hero;
    if (hero) {
      if (hero.badge) {
        const badgeEl = document.querySelector('.hero-badge span:last-child');
        if (badgeEl) badgeEl.textContent = hero.badge;
      }
      if (hero.title && hero.titleHighlight) {
        const titleEl = document.querySelector('.hero-title');
        if (titleEl) {
          titleEl.innerHTML = '<span class="text-oem-white">' + escapeHtml(hero.title) + '</span><br/><span class="text-oem-green glow-green-text">' + escapeHtml(hero.titleHighlight) + '</span>';
        }
      }
      if (hero.subtitle1) {
        const s1 = document.querySelector('.hero-subtitle1');
        if (s1) s1.textContent = hero.subtitle1;
      }
      if (hero.subtitle) {
        const s = document.querySelector('.hero-subtitle');
        if (s) s.textContent = hero.subtitle;
      }
      if (hero.subtitle2) {
        const s2 = document.querySelector('.hero-subtitle2');
        if (s2) s2.textContent = hero.subtitle2;
      }
    }

    // C) Ürün Kartları & Detayları
    let productList = [];
    if (content.products) {
      if (Array.isArray(content.products.list)) {
        productList = content.products.list;
      } else {
        // Eski nesne formatı desteği
        if (content.products.tuna) productList.push(Object.assign({ id: 'tuna', slug: 'tuna' }, content.products.tuna));
        if (content.products.altay) productList.push(Object.assign({ id: 'altay', slug: 'altay' }, content.products.altay));
        if (content.products.arasL) productList.push(Object.assign({ id: 'aras-l', slug: 'aras-l' }, content.products.arasL));
        if (content.products.arasT) productList.push(Object.assign({ id: 'aras-t', slug: 'aras-t' }, content.products.arasT));
      }
    }

    // Ürün Hızları ve Kart Alt Metinleri
    productList.forEach(prod => {
      const slug = prod.slug || prod.id;
      const card = document.querySelector(`a[href*="/products/${slug}"]`);
      if (card) {
        if (prod.speed) {
          const speedEl = card.querySelector('.text-oem-green.glow-green-text, .text-emerald-400');
          if (speedEl) speedEl.textContent = prod.speed;
        }
        if (prod.subtitle || prod.badge) {
          const subEl = card.querySelector('p.text-sm.text-oem-green, p.text-emerald-400');
          if (subEl) subEl.textContent = prod.subtitle || prod.badge;
        }
      }
    });

    // Aktif Ürün Detay Sayfası Senkronu
    const activeProd = productList.find(p => path.includes('/products/' + (p.slug || p.id)));
    if (activeProd) {
      // 4'lü İstatistik Metrikleri
      if (activeProd.stats) {
        let statData = [];
        if (Array.isArray(activeProd.stats)) {
          statData = activeProd.stats.map(s => ({ val: s.value, lbl: s.label }));
        } else {
          statData = [
            { val: activeProd.stats.stat1Value, lbl: activeProd.stats.stat1Label },
            { val: activeProd.stats.stat2Value, lbl: activeProd.stats.stat2Label },
            { val: activeProd.stats.stat3Value, lbl: activeProd.stats.stat3Label },
            { val: activeProd.stats.stat4Value, lbl: activeProd.stats.stat4Label }
          ];
        }

        const statBoxes = document.querySelectorAll('.grid.grid-cols-2.lg\\:grid-cols-4 > div, .grid-cols-2.md\\:grid-cols-4 > div');
        if (statBoxes && statBoxes.length >= 4) {
          statBoxes.forEach((box, idx) => {
            if (idx < 4 && statData[idx]) {
              const valEl = box.querySelector('.text-2xl, .text-3xl, .font-bold.text-oem-white, .font-bold.text-oem-green, .text-emerald-400');
              const lblEl = box.querySelector('.text-xs, .text-sm, .text-oem-gray, .text-gray-400');
              if (valEl && statData[idx].val) valEl.textContent = statData[idx].val;
              if (lblEl && statData[idx].lbl) lblEl.textContent = statData[idx].lbl;
            }
          });
        }
      }

      // Hero Görseli
      if (activeProd.hero_image) {
        const heroImg = document.querySelector('.tuna-hero-image img, main .aspect-\\[16\\/9\\] img');
        if (heroImg && !heroImg.src.includes(activeProd.hero_image)) {
          heroImg.src = activeProd.hero_image;
        }
      }
    }

    // D) CTA
    const cta = content.cta;
    if (cta) {
      if (cta.title) {
        const ctaTitle = document.querySelector('.cta-title');
        if (ctaTitle) ctaTitle.textContent = cta.title;
      }
      if (cta.subtitle) {
        const ctaSubtitle = document.querySelector('.cta-subtitle');
        if (ctaSubtitle) ctaSubtitle.textContent = cta.subtitle;
      }
    }
  }

  // 3. Doküman Linkleri (PDF İndirme Butonlarına Canlı Önbellek Kırıcı Ekleme)
  function applyDocsCacheBuster() {
    const timestamp = Math.floor(Date.now() / 60000);
    document.querySelectorAll('a[href$=".pdf"], a[href*=".pdf?"]').forEach(el => {
      let href = el.getAttribute('href');
      if (href) {
        const cleanHref = href.split('?')[0];
        el.setAttribute('href', cleanHref + '?v=' + timestamp);
      }
    });
  }

  // 4. Canlı Önizleme Modu Rozeti
  function showPreviewBadge() {
    if (document.getElementById('oem-preview-badge')) return;
    const badge = document.createElement('div');
    badge.id = 'oem-preview-badge';
    badge.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;background:#f59e0b;color:#000;font-weight:700;padding:10px 18px;border-radius:30px;box-shadow:0 8px 24px rgba(0,0,0,0.5);font-size:13px;display:flex;align-items:center;gap:8px;font-family:sans-serif;';
    badge.innerHTML = '<span>⚠️ CANLI ÖNİZLEME MODU</span><a href="' + window.location.pathname + '" style="color:#000;text-decoration:underline;margin-left:8px;font-size:11px;">Kapat</a>';
    document.body.appendChild(badge);
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  // Çalıştırma
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncSite);
  } else {
    syncSite();
  }

  // React hydration sonrası için 1 saniye sonra tekrar çalıştır
  setTimeout(syncSite, 1000);
})();
