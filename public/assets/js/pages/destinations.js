/* =====================================================================
   Destinations — destinations.html  (#slug jumps to a port)
   Laravel (step 21): the ports and their sailings are rendered on the server (pages/destinations.blade.php),
   so this script only filters the ports by country and honours #slug links.
   ===================================================================== */
document.addEventListener('DOMContentLoaded', () => {
  const filter = document.getElementById('country-filter');
  const on = '!bg-white !text-ocean-800';
  const off = '!bg-white/10 !text-white';

  filter?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-country]');
    if (!btn) return;
    const country = btn.dataset.country;
    filter.querySelectorAll('[data-country]').forEach((b) => {
      const active = b === btn;
      b.setAttribute('aria-selected', active);
      b.classList.remove(...(active ? off : on).split(' '));
      b.classList.add(...(active ? on : off).split(' '));
    });
    document.querySelectorAll('#dest-list > article, #port-nav > a').forEach((el) => {
      el.hidden = country !== '' && el.dataset.country !== country;
    });
  });

  // Honour #slug links from the homepage slider (images above may still be loading)
  if (location.hash) setTimeout(() => document.getElementById(location.hash.slice(1))?.scrollIntoView(), 50);
});
