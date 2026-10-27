/* FAQ search (pre-boarding guide and ship page).
   <input data-faq-search="#list" data-faq-empty="#message"> hides the .acc-item questions of #list that don't contain
   every typed word, and shows #message when none is left. Arabic diacritics, tatweel and hamza forms are ignored, so
   "تاشيرة" finds "تأشيرة". Reads the list on every keystroke, so it also works on lists drawn by a page script. */
(() => {
  const normalize = (text) => text.toLocaleLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ًͯ-ٰٟـ]/g, '');

  document.querySelectorAll('[data-faq-search]').forEach((input) => {
    const list = document.querySelector(input.dataset.faqSearch);
    const empty = input.dataset.faqEmpty ? document.querySelector(input.dataset.faqEmpty) : null;
    if (!list) return;

    input.addEventListener('input', () => {
      const words = normalize(input.value).split(/\s+/).filter(Boolean);
      let shown = 0;
      list.querySelectorAll('.acc-item').forEach((item) => {
        const text = normalize(item.textContent);
        const match = words.every((word) => text.includes(word));
        item.classList.toggle('hidden', !match);
        if (match) shown += 1;
      });
      empty?.classList.toggle('hidden', shown > 0);
    });
  });
})();
