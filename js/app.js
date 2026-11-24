// MAMPIR KOPI — shared interactions

document.addEventListener('DOMContentLoaded', () => {

  // Pill option selection (ukuran / gula / es / metode bayar)
  document.querySelectorAll('.option-pills, .pay-method-list').forEach(group => {
    group.addEventListener('click', (e) => {
      const pill = e.target.closest('.pill, .pay-method-row');
      if (!pill || !group.contains(pill)) return;
      group.querySelectorAll('.pill, .pay-method-row').forEach(p => {
        p.classList.remove('selected');
        if (p.hasAttribute('aria-checked')) p.setAttribute('aria-checked', 'false');
        const r = p.querySelector('.radio');
        if (r) r.classList.remove('on');
      });
      pill.classList.add('selected');
      if (pill.hasAttribute('aria-checked')) pill.setAttribute('aria-checked', 'true');
      const radio = pill.querySelector('.radio');
      if (radio) radio.classList.add('on');
    });
  });

  document.querySelectorAll('.pay-method-list[role="radiogroup"]').forEach(group => {
    group.addEventListener('keydown', e => {
      const options = [...group.querySelectorAll('[role="radio"]')];
      const current = options.indexOf(e.target.closest('[role="radio"]'));
      if (current < 0) return;
      let next = current;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') next = (current + 1) % options.length;
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') next = (current - 1 + options.length) % options.length;
      else if (e.key !== ' ' && e.key !== 'Enter') return;
      e.preventDefault();
      options[next].focus();
      options[next].click();
    });
  });

  // Quantity stepper
  document.querySelectorAll('.qty-row').forEach(row => {
    const [minus, value, plus] = row.querySelectorAll('.qty-btn, .qty-value');
    if (!minus || !value || !plus) return;
    minus.addEventListener('click', () => {
      const n = Math.max(1, parseInt(value.textContent || value.value, 10) - 1);
      setQty(value, n);
    });
    plus.addEventListener('click', () => {
      const n = Math.min(99, parseInt(value.textContent || value.value, 10) + 1); // L-m9: qty maks 99
      setQty(value, n);
    });
  });

  function setQty(el, n) {
    n = Math.max(1, Math.min(99, n)); // L-m9: clamp 1–99 di semua jalur stepper
    if (el.tagName === 'INPUT') el.value = n; else el.textContent = n;
  }

  // Riwayat status tabs
  document.querySelectorAll('.riwayat-tabs').forEach(tabs => {
    tabs.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn) return;
      tabs.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const status = btn.dataset.status;
      document.querySelectorAll('tbody tr, .order-row-card, .riwayat-card').forEach(row => {
        if (status === 'semua' || row.dataset.status === status) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  });

  // Cart quantity +/- with trash removal (Keranjang page)
  document.querySelectorAll('.cart-item').forEach(item => {
    const minus = item.querySelector('.cart-qty button:first-child');
    const plus = item.querySelector('.cart-qty button:last-child');
    const value = item.querySelector('.cart-qty span');
    if (minus && plus && value) {
      minus.addEventListener('click', () => {
        let n = Math.max(1, parseInt(value.textContent, 10) - 1);
        value.textContent = n;
      });
      plus.addEventListener('click', () => {
        let n = Math.min(99, parseInt(value.textContent, 10) + 1); // L-m9: qty maks 99
        value.textContent = n;
      });
    }
    const trash = item.querySelector('.trash-btn');
    if (trash) trash.addEventListener('click', () => item.remove());
  });

});
