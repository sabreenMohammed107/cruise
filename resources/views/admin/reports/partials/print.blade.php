{{-- Printed / PDF report (Print / PDF button, or the browser's own print): A4 sheets like the booking voucher
     (booking/confirmation.blade.php), each with the Menatours · Safer header banner, its share of the report, a line
     with the report and the page number, and the footer banner at the very bottom.

     The sheets are built off screen from #report-content (on load, and again just before printing so the charts are
     current): every top-level block is a piece that never breaks across sheets ([data-print-group] wrappers are
     opened up, so side-by-side cards print one under the other); a block with a table is filled row by row, the
     table's header row repeated on the next sheet, its totals row at the end. Breakpoints differ between screen and
     paper, so nothing in a sheet may depend on lg: / xl: classes ([data-print-cols] sets a grid's columns instead).
     Without the script, the page prints as it is (sidebar and filters left out). --}}
<style>
  .print-only { display: none; }
  #report-print { position: absolute; left: -10000px; top: 0; }
  .report-sheet { width: 210mm; height: 296mm; display: flex; flex-direction: column; overflow: hidden; background: #fff;
    -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .report-sheet + .report-sheet { break-before: page; }
  .report-sheet-banner { display: block; width: 100%; height: auto; flex: none; }
  .report-sheet-body { flex: 1 1 auto; min-height: 0; overflow: hidden; padding: 5mm 9mm 2mm; }
  /* The report at about 1100px wide on a 186mm column; tables tightened so their columns fit the page width */
  .report-sheet-zoom { zoom: .64; }
  .report-sheet table { width: 100%; }
  .report-sheet th { white-space: normal !important; vertical-align: bottom; }
  .report-sheet th, .report-sheet td { padding: 7px 8px !important; }
  .report-sheet td [class*="min-w-"], .report-sheet th[class*="min-w-"] { min-width: 0 !important; }
  .report-sheet-meta { flex: none; display: flex; justify-content: space-between; gap: 4mm; padding: 1.6mm 9mm; font-size: 8px; color: #5B6B7F; border-top: 1px solid #E2EAF2; }
  .report-sheet .print-only { display: block; }
  .report-sheet .no-print { display: none !important; }
  .report-sheet-zoom > * { margin: 0 0 16px !important; }
  .report-sheet .card { box-shadow: none !important; border: 1px solid #D5E1EC; }
  .report-sheet .overflow-x-auto { overflow: visible !important; }
  .report-sheet a { color: inherit !important; text-decoration: none !important; }
  .report-sheet [data-print-cols] { display: grid; grid-template-columns: repeat(var(--cols), minmax(0, 1fr)) !important; gap: 12px !important; }
  .report-sheet [data-print-cols] > * { margin: 0 !important; }
  .report-sheet .report-cont { font-size: 12px; font-weight: 600; color: #5B6B7F; padding: 14px 20px 6px; }

  @media print {
    @page { size: A4; margin: 0; }
    body { background: #fff !important; }
    html.report-sheets body > *:not(#report-print) { display: none !important; }
    html.report-sheets #report-print { position: static; }
    /* Fallback, without sheets: the page itself */
    html:not(.report-sheets) body > header, html:not(.report-sheets) #admin-sidebar, html:not(.report-sheets) #admin-drawer, html:not(.report-sheets) .no-print { display: none !important; }
    html:not(.report-sheets) #report-print { display: none; }
    html:not(.report-sheets) .print-only { display: block; }
  }
</style>

<div id="report-print" aria-hidden="true"
     data-head="{{ asset('images/voucher/voucher-header.jpg') }}" data-foot="{{ asset('images/voucher/voucher-footer.jpg') }}"
     data-title="{{ $printTitle }}"></div>

@push('scripts')
<script>
  (() => {
    const src = document.getElementById('report-content');
    const out = document.getElementById('report-print');
    if (!src || !out) return;
    // A direct child of <body>, so printing can leave everything else out
    document.body.append(out);

    let zoom; // the current sheet's content column
    let body; // and its clipping box
    let shell; // the current piece's copy on this sheet (pieces split over sheets)
    let sheets = [];

    const newSheet = () => {
      const s = document.createElement('section');
      s.className = 'report-sheet';
      s.innerHTML = `<img class="report-sheet-banner" src="${out.dataset.head}" alt="" width="1600" height="255">`
        + '<div class="report-sheet-body"><div class="report-sheet-zoom"></div></div>'
        + '<div class="report-sheet-meta"><span></span><span class="report-sheet-page"></span></div>'
        + `<img class="report-sheet-banner" src="${out.dataset.foot}" alt="" width="1600" height="255">`;
      s.querySelector('.report-sheet-meta span').textContent = out.dataset.title;
      out.append(s);
      sheets.push(s);
      body = s.querySelector('.report-sheet-body');
      zoom = s.querySelector('.report-sheet-zoom');
      zoom.dataset.fresh = '1';
      shell = null;
    };
    const full = () => body.scrollHeight > body.clientHeight + 1;
    const placed = () => { delete zoom.dataset.fresh; };

    // A copy of an element; a chart (canvas) becomes a picture of itself
    const copy = (el) => {
      const c = el.cloneNode(true);
      const from = el.querySelectorAll('canvas');
      c.querySelectorAll('canvas').forEach((cv, i) => {
        const img = new Image();
        img.src = from[i].toDataURL('image/png');
        img.style.cssText = 'width:100%;height:100%;object-fit:contain';
        cv.replaceWith(img);
      });
      return c;
    };

    // A whole piece: on this sheet if it fits, else on the next one
    const atomic = (el) => {
      const c = copy(el);
      zoom.append(c);
      if (full() && !zoom.dataset.fresh) {
        c.remove();
        newSheet();
        zoom.append(c);
      }
      placed();
    };

    // The piece's copy on the current sheet; on a later sheet it starts with "<its title> (continued)"
    const ensureShell = (piece, continued) => {
      if (shell && zoom.contains(shell)) return shell;
      shell = piece.cloneNode(false);
      zoom.append(shell);
      const h = piece.querySelector('h2, h3');
      if (continued && h) {
        const p = document.createElement('p');
        p.className = 'report-cont';
        p.textContent = h.textContent.trim().replace(/\s+/g, ' ') + ' (continued)';
        shell.append(p);
      }
      return shell;
    };

    // A table (inside its wrapper), filled row by row; its header row again on every sheet, its totals row last
    const placeTable = (piece, wrapper, table) => {
      const rows = [...table.tBodies].flatMap((b) => [...b.rows]);
      const open = (continued) => {
        const t = table.cloneNode(false);
        if (table.tHead) t.append(table.tHead.cloneNode(true));
        const tb = document.createElement('tbody');
        t.append(tb);
        let box = t;
        if (wrapper !== table) {
          box = wrapper.cloneNode(false);
          box.append(t);
        }
        ensureShell(piece, continued).append(box);
        return { box, t, tb };
      };
      let part = open(false);
      let n = 0;
      const next = () => {
        newSheet();
        part = open(true);
        n = 0;
      };
      for (const row of rows) {
        const r = row.cloneNode(true);
        part.tb.append(r);
        if (full() && !(n === 0 && zoom.dataset.fresh)) {
          r.remove();
          if (n === 0) {
            // Not even one row fits: the table starts on the next sheet, with the line just before it (its title)
            part.box.remove();
            const lead = shell.lastElementChild;
            const keepLead = lead && shell.children.length > 1 && !lead.classList.contains('report-cont');
            if (keepLead) lead.remove();
            if (!shell.children.length) shell.remove();
            newSheet();
            if (keepLead) ensureShell(piece, true).append(lead);
            part = open(true);
          } else {
            next();
          }
          part.tb.append(r);
        }
        n++;
        placed();
      }
      if (table.tFoot) {
        const f = table.tFoot.cloneNode(true);
        part.t.append(f);
        if (full() && !zoom.dataset.fresh) {
          // The totals row goes to the next sheet with the last row, never alone
          f.remove();
          const last = n > 1 ? part.tb.lastElementChild : null;
          if (last) last.remove();
          next();
          if (last) part.tb.append(last);
          part.t.append(f);
        }
      }
    };

    // A piece with tables: its other children whole, each table row by row, all in one copy of the piece per sheet
    const split = (piece) => {
      shell = null;
      for (const child of piece.children) {
        if (child.matches('.no-print, script, style')) continue;
        const table = child.tagName === 'TABLE' ? child : child.querySelector('table');
        if (table) {
          placeTable(piece, child, table);
          continue;
        }
        const c = copy(child);
        const s = ensureShell(piece, false);
        s.append(c);
        if (full() && !zoom.dataset.fresh) {
          c.remove();
          if (!s.children.length) s.remove();
          newSheet();
          ensureShell(piece, true).append(c);
        }
        placed();
      }
    };

    const pieces = (parent, list = []) => {
      for (const el of parent.children) {
        if (el.matches('.no-print, script, style, template')) continue;
        if (el.hasAttribute('data-print-group')) pieces(el, list);
        else list.push(el);
      }
      return list;
    };

    const build = () => {
      out.replaceChildren();
      sheets = [];
      newSheet();
      for (const piece of pieces(src)) {
        if (piece.querySelector('table')) split(piece);
        else atomic(piece);
      }
      sheets.forEach((s, i) => { s.querySelector('.report-sheet-page').textContent = `Page ${i + 1} of ${sheets.length}`; });
      document.documentElement.classList.add('report-sheets');
    };

    window.addEventListener('beforeprint', build);
    // Also ready in advance (printing from the browser menu), once the charts are drawn
    window.addEventListener('load', () => (document.fonts ? document.fonts.ready : Promise.resolve()).then(build));
  })();
</script>
@endpush
