/* OEM Vision Panel */
(() => {
  const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
  const toastEl = document.getElementById('toast');
  let toastTimer;

  function toast(msg, isError = false) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.toggle('is-error', isError);
    toastEl.classList.add('is-show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-show'), 3200);
  }

  async function api(action, payload = {}) {
    const res = await fetch('api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
      body: JSON.stringify({ action, csrf, ...payload }),
      credentials: 'same-origin',
    });
    let data;
    try { data = await res.json(); } catch { data = { ok: false, error: 'Sunucudan geçersiz yanıt geldi.' }; }
    if (res.status === 401) { window.location = 'login.php'; }
    return data;
  }

  // Mobil menü
  document.querySelector('[data-toggle-sidebar]')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('is-open');
  });

  /* ---------------- Görseller / dokümanlar ---------------- */
  const media = document.querySelector('[data-media]');
  if (media) {
    const type = media.dataset.type;
    const limit = Number(media.dataset.limit || 0);
    const fmt = (b) => b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.round(b / 1024) + ' KB';

    async function upload(path, file, holder) {
      if (limit && file.size > limit) { toast('Dosya çok büyük (' + fmt(file.size) + '). Sınır: ' + fmt(limit) + '.', true); return; }
      const fd = new FormData();
      fd.append('action', 'replace_media');
      fd.append('csrf', csrf);
      fd.append('type', type);
      fd.append('path', path);
      fd.append('file', file);
      holder.classList.add('is-busy');
      try {
        const res = await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF': csrf } });
        if (res.status === 401) { window.location = 'login.php'; return; }
        const data = await res.json().catch(() => ({ ok: false, error: 'Sunucudan geçersiz yanıt geldi.' }));
        if (!data.ok) { toast(data.error || 'Yüklenemedi.', true); return; }
        sessionStorage.setItem('admin_toast', data.warning ? 'Yüklendi. ' + data.warning : 'Yüklendi. Sitede yenisi görünüyor.');
        sessionStorage.setItem('admin_toast_err', data.warning ? '1' : '');
        window.location.reload();
      } finally {
        holder.classList.remove('is-busy');
      }
    }

    media.addEventListener('change', (e) => {
      const input = e.target.closest('[data-upload]');
      if (!input || !input.files[0]) return;
      const holder = input.closest('[data-path]');
      upload(holder.dataset.path, input.files[0], holder);
      input.value = '';
    });

    media.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-restore]');
      if (!btn) return;
      const holder = btn.closest('[data-path]');
      if (!confirm('Orijinal dosyaya dönülsün mü? Yüklediğiniz dosya silinecek.')) return;
      const data = await api('restore_media', { type, path: holder.dataset.path });
      if (!data.ok) { toast(data.error || 'Geri alınamadı.', true); return; }
      sessionStorage.setItem('admin_toast', 'Orijinal dosyaya dönüldü.');
      window.location.reload();
    });
  }

  /* ---------------- Gelen formlar ---------------- */
  const inbox = document.querySelector('[data-messages]');
  if (inbox) {
    inbox.addEventListener('toggle', async (e) => {
      const d = e.target;
      if (!d.matches('details.msg') || !d.open || !d.classList.contains('is-unread')) return;
      const r = await api('message_status', { id: d.dataset.id, status: 'read' });
      if (r.ok) { d.classList.remove('is-unread'); d.querySelector('[data-toggle-read]').textContent = 'Okunmadı işaretle'; }
    }, true);
    inbox.addEventListener('click', async (e) => {
      const d = e.target.closest('details.msg');
      if (!d) return;
      if (e.target.closest('[data-toggle-read]')) {
        const toUnread = !d.classList.contains('is-unread');
        const r = await api('message_status', { id: d.dataset.id, status: toUnread ? 'unread' : 'read' });
        if (!r.ok) return toast(r.error || 'İşlem yapılamadı.', true);
        d.classList.toggle('is-unread', toUnread);
        e.target.textContent = toUnread ? 'Okundu işaretle' : 'Okunmadı işaretle';
      }
      if (e.target.closest('[data-delete]')) {
        if (!confirm('Bu mesaj kalıcı olarak silinsin mi?')) return;
        const r = await api('message_delete', { id: d.dataset.id });
        if (!r.ok) return toast(r.error || 'Silinemedi.', true);
        d.remove();
        toast('Mesaj silindi.');
      }
    });
  }

  /* ---------------- Firma bilgileri ---------------- */
  const settingsForm = document.querySelector('[data-settings]');
  if (settingsForm) {
    let dirty = false;
    settingsForm.addEventListener('input', () => { dirty = true; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    settingsForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const values = {};
      settingsForm.querySelectorAll('[name*="["]').forEach((el) => {
        const m = el.name.match(/^(\w+)\[(\w+)\]$/);
        if (m) (values[m[1]] ??= {})[m[2]] = el.value;
      });
      const btn = settingsForm.querySelector('button[type=submit]');
      btn.disabled = true;
      const r = await api('save_company', { values, notification_email: settingsForm.notification_email.value });
      btn.disabled = false;
      if (!r.ok) return toast(r.error || 'Kaydedilemedi.', true);
      dirty = false;
      toast('Kaydedildi. Bilgiler sitede güncellendi.');
    });
  }

  /* ---------------- Sürüm geçmişi ---------------- */
  document.querySelector('[data-history]')?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-restore-backup]');
    if (!btn) return;
    if (!confirm('Metinler bu kayıttaki hâline döndürülsün mü? Şu anki hâl de yedeklenecek, istediğinizde geri dönebilirsiniz.')) return;
    const r = await api('restore_backup', { name: btn.closest('[data-name]').dataset.name });
    if (!r.ok) return toast(r.error || 'Geri yüklenemedi.', true);
    sessionStorage.setItem('admin_toast', 'Geri yüklendi. Site bu hâli gösteriyor.');
    window.location.reload();
  });

  // Sayfa yenilendikten sonra gösterilecek bildirim
  const pending = sessionStorage.getItem('admin_toast');
  if (pending) {
    toast(pending, sessionStorage.getItem('admin_toast_err') === '1');
    sessionStorage.removeItem('admin_toast');
    sessionStorage.removeItem('admin_toast_err');
  }

  /* ---------------- İçerik editörü ---------------- */
  const editor = document.querySelector('[data-editor]');
  if (!editor) return;

  const saveBtn = editor.querySelector('[data-save]');
  const dirtyCount = editor.querySelector('[data-dirty-count]');
  const fields = [...editor.querySelectorAll('[data-field]')];

  const autosize = (el) => {
    if (el.tagName !== 'TEXTAREA') return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 2 + 'px';
  };

  function refreshField(f) {
    const input = f.querySelector('[data-input]');
    const dirty = input.value !== input.dataset.initial;
    const changed = input.value !== input.dataset.original;
    f.classList.toggle('is-dirty', dirty);
    f.classList.toggle('is-changed', changed);
  }

  function refreshSave() {
    const n = fields.filter((f) => f.classList.contains('is-dirty')).length;
    saveBtn.disabled = n === 0;
    dirtyCount.textContent = n ? String(n) : '';
  }

  fields.forEach((f) => {
    const input = f.querySelector('[data-input]');
    autosize(input);
    input.addEventListener('input', () => { autosize(input); refreshField(f); refreshSave(); });
    f.querySelector('[data-reset]')?.addEventListener('click', () => {
      input.value = input.dataset.original;
      autosize(input); refreshField(f); refreshSave();
      input.focus();
    });
  });

  // Kaydet
  async function save() {
    const changes = {};
    fields.filter((f) => f.classList.contains('is-dirty')).forEach((f) => {
      const input = f.querySelector('[data-input]');
      if (input.value.trim() === '' ) return;
      (changes[f.dataset.lang] ??= {})[f.dataset.key] = input.value;
    });
    const empty = fields.some((f) => f.classList.contains('is-dirty') && f.querySelector('[data-input]').value.trim() === '');
    if (empty) { toast('Boş bırakılan metinler kaydedilmedi. Orijinale dönmek için ↺ butonunu kullanın.', true); }
    if (!Object.keys(changes).length) return;

    saveBtn.disabled = true;
    const data = await api('save_texts', { changes });
    if (!data.ok) { toast(data.error || 'Kaydedilemedi.', true); refreshSave(); return; }

    fields.forEach((f) => {
      const input = f.querySelector('[data-input]');
      if (changes[f.dataset.lang]?.[f.dataset.key] !== undefined) input.dataset.initial = input.value;
      refreshField(f);
    });
    refreshSave();
    toast('Kaydedildi. Değişiklikler sitede yayında.');
  }

  saveBtn.addEventListener('click', save);
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); if (!saveBtn.disabled) save(); }
  });

  window.addEventListener('beforeunload', (e) => {
    if (!saveBtn.disabled) { e.preventDefault(); e.returnValue = ''; }
  });

  // Arama ve "sadece değişenler" filtresi
  const search = editor.querySelector('[data-search]');
  const onlyChanged = editor.querySelector('[data-only-changed]');
  const rows = [...editor.querySelectorAll('[data-row]')];
  const sections = [...editor.querySelectorAll('.section')];
  const emptyMsg = editor.querySelector('[data-empty]');

  function filter() {
    const q = search.value.trim().toLocaleLowerCase('tr');
    const changedOnly = onlyChanged.checked;
    rows.forEach((row) => {
      const text = row.textContent.toLocaleLowerCase('tr') + ' ' +
        [...row.querySelectorAll('[data-input]')].map((i) => i.value.toLocaleLowerCase('tr')).join(' ');
      const isChanged = row.querySelector('.is-changed, .is-dirty');
      row.hidden = (q && !text.includes(q)) || (changedOnly && !isChanged);
    });
    let anyVisible = false;
    sections.forEach((s) => {
      const visible = [...s.querySelectorAll('[data-row]')].some((r) => !r.hidden);
      s.hidden = !visible;
      if (visible) { anyVisible = true; if (q || changedOnly) s.open = true; }
    });
    emptyMsg.hidden = anyVisible;
  }
  search.addEventListener('input', filter);
  onlyChanged.addEventListener('change', filter);
})();
