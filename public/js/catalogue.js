// Fonction principale ticketForm - gestion du formulaire catalogue
function ticketForm(){
  return {
    lastTotal: 0,
    pending: [],
    bikeSales: [],
    fmt(n){ const v = Number(n); return Number.isFinite(v) ? v.toFixed(2) : '0.00'; },
    recalc(){
      this.pending = [];
      let total = 0;
      document.querySelectorAll('.prestation-card').forEach((row)=>{
        const moCb = row.querySelector('input.prest-check');
        const pieceCb = row.querySelector('input.piece-check');
        const includeRow = ((moCb && moCb.checked) || (pieceCb && pieceCb.checked));
        if (!includeRow) return;

        // MO
        let qty = 0, price = 0;
        if (moCb && moCb.checked) {
          const qtyEl = row.querySelector("select[name='qty[]']") || row.querySelector("input[name='qty[]']");
          const ovEl  = row.querySelector("input[name='price_override[]']");
          qty = parseFloat(qtyEl?.value || '0'); if (isNaN(qty) || qty < 0) qty = 0;
          price = parseFloat(ovEl?.value || '');
          if (isNaN(price)) price = parseFloat(row.dataset.price || '0');
        }

        // Pièce
        let pqty = 0, pprice = 0;
        if (pieceCb && pieceCb.checked) {
          const pQtyEl = row.querySelector("select[name='piece_qty[]']");
          const pOvEl  = row.querySelector("input[name='piece_price_override[]']");
          pqty = parseFloat(pQtyEl?.value || '0'); if (isNaN(pqty) || pqty < 0) pqty = 0;
          pprice = parseFloat(pOvEl?.value || '');
          if (isNaN(pprice)) pprice = parseFloat(row.dataset.piecePrice || '0');
        }

        const lineTotal = (qty * price) + (pqty * pprice);
        total += lineTotal;

        // Calcul pour le récap (type ticket) : unitaire / sous-total
        const labelEl = row.querySelector('.card-title');
        const label = labelEl ? labelEl.textContent.trim() : '';
        const pieceLabel = (row.dataset.pieceLabel || 'Pièce').slice(0, 19);
        const totalQty = (qty || 0) + (pqty || 0);
        const unit = totalQty > 0 ? (lineTotal / totalQty) : 0;

        this.pending.push({
          id: row.dataset.id,
          label: label,
          pieceLabel: pieceLabel,
          // quantité totale (MO + Pièce)
          qty: totalQty,
          // détails conservés pour d'autres usages
          moQty: qty,
          pieceQty: pqty,
          moUnit: price,
          pieceUnit: pprice,
          moTotal: qty * price,
          pieceTotal: pqty * pprice,
          // pour le tableau unifié
          unit: unit,
          subtotal: lineTotal,
          total: lineTotal
        });
      });

      // Intégrer les ventes de vélo (bikeSales) dans pending + total
      if (Array.isArray(this.bikeSales)) {
        this.bikeSales.forEach((sale, idx) => {
          const unit = Number(sale.unit || 0);
          const qty = Number(sale.qty || 1);
          const subtotal = Number(
            sale.subtotal !== undefined && sale.subtotal !== null
              ? sale.subtotal
              : unit * qty
          );
          total += subtotal;
          this.pending.push({
            type: 'bike',
            id: sale.id || ('bike-' + idx),
            label: sale.label || 'Vente de vélo',
            qty: qty,
            moQty: 0,
            pieceQty: 0,
            moUnit: 0,
            pieceUnit: 0,
            moTotal: 0,
            pieceTotal: 0,
            unit: unit,
            subtotal: subtotal,
            total: subtotal
          });
        });
      }

      const fmt = (n) => Number(n).toFixed(2);
      const totalEl = document.getElementById('t-total');
      if (totalEl) {
        totalEl.textContent = fmt(total) + ' €';
        // Inject CSS bounce once
        if (!document.getElementById('css-total-bounce')) {
          const st = document.createElement('style');
          st.id = 'css-total-bounce';
          st.textContent = '@keyframes totalBounce{0%{transform:scale(1)}50%{transform:scale(1.06)}100%{transform:scale(1)}} .total-pill.bounce{animation:totalBounce .32s ease-in-out}';
          document.head.appendChild(st);
        }
        const pill = document.querySelector('.panel-total');
        if (pill && total !== this.lastTotal) {
          pill.classList.add('bounce');
          setTimeout(()=>pill.classList.remove('bounce'), 360);
          this.lastTotal = total;
        }
      }
    },

    // Nouvelle méthode Vente de vélo (Alpine)
    addBikeSale(detail){
      const model = String(detail?.model || '').trim();
      let price = Number(String(detail?.price || '').replace(',', '.'));

      if (!model) { alert('Saisir le modèle du vélo'); return; }
      if (!Number.isFinite(price) || price < 0) { alert('Saisir un prix valide'); return; }

      if (!Array.isArray(this.bikeSales)) this.bikeSales = [];
      this.bikeSales.push({
        label: 'Vente de vélo — ' + model,
        qty: 1,
        unit: price,
        subtotal: price
      });

      // Sauvegarder le dernier modèle de vélo pour pré-remplir le formulaire client
      try {
        localStorage.setItem('lastBikeModel', model);
      } catch(_e) {}

      try {
        let draft;
        try {
          draft = JSON.parse(localStorage.getItem('devisDraft') || 'null') || {};
        } catch(_e) {
          draft = {};
        }
        if (!draft.custom) draft.custom = {};
        if (!Array.isArray(draft.custom.prest)) draft.custom.prest = [];
        draft.custom.prest.push({
          label: 'Vente de vélo — ' + model,
          price: price
        });
        localStorage.setItem('devisDraft', JSON.stringify(draft));
      } catch(_e) {}

      if (typeof this.recalc === 'function') this.recalc();
      if (typeof this.toast === 'function') this.toast('Vente de vélo ajoutée ✓');
    },
    removePending(idx){
      if (idx >= 0 && idx < this.pending.length) {
        this.pending = this.pending.filter((_, i) => i !== idx);
        this.recalc();
        const t=document.createElement('div'); t.className='toast'; t.textContent='Ligne supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
      }
    },
    removeMo(id){
      // Décocher seulement la checkbox MO 
      const row = document.querySelector(`.prestation-card[data-id="${id}"]`);
      if (row) {
        const moCb = row.querySelector('input.prest-check');
        if (moCb) moCb.checked = false;
      }
      this.recalc();
      const t=document.createElement('div'); t.className='toast'; t.textContent='Ligne supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
    },
    removePiece(id){
      // Décocher seulement la checkbox Pièce
      const row = document.querySelector(`.prestation-card[data-id="${id}"]`);
      if (row) {
        const pieceCb = row.querySelector('input.piece-check');
        if (pieceCb) pieceCb.checked = false;
      }
      this.recalc();
      const t=document.createElement('div'); t.className='toast'; t.textContent='Ligne supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
    },
    removeBikeById(id){
      // Supprimer du tableau bikeSales
      const initialLen = this.bikeSales.length;
      this.bikeSales = this.bikeSales.filter((s) => s.id !== id);
      if (this.bikeSales.length < initialLen) {
        this.recalc();
        const t=document.createElement('div'); t.className='toast'; t.textContent='Vente de vélo supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
      }
    },
    removePendingById(id){
      const initialLen = this.pending.length;
      this.pending = this.pending.filter((p) => p.id !== id);
      if (this.pending.length < initialLen) {
        // Décocher la checkbox correspondante dans le catalogue
        const row = document.querySelector(`.prestation-card[data-id="${id}"]`);
        if (row) {
          const moCb = row.querySelector('input.prest-check');
          const pieceCb = row.querySelector('input.piece-check');
          if (moCb) moCb.checked = false;
          if (pieceCb) pieceCb.checked = false;
        }
        this.recalc();
        const t=document.createElement('div'); t.className='toast'; t.textContent='Ligne supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
      }
    },
    removeBikeSale(idx){
      if (idx >= 0 && idx < this.bikeSales.length) {
        this.bikeSales = this.bikeSales.filter((_, i) => i !== idx);
        this.recalc();
        const t=document.createElement('div'); t.className='toast'; t.textContent='Vente de vélo supprimée ✓'; document.body.appendChild(t); setTimeout(()=>{ t.remove(); }, 3000);
      }
    },
  }
}

// Sauvegarde locale du brouillon avant navigation (pour "Nouveau client")
function saveDevisDraft(){
  let draft;
  try{
    draft = JSON.parse(localStorage.getItem('devisDraft') || 'null') || {};
  }catch(_e){
    draft = {};
  }
  draft.rows = [];
  document.querySelectorAll('.prestation-card').forEach((row)=>{
    const id = row.getAttribute('data-id');
    const moCb = row.querySelector('input.prest-check');
    const pieceCb = row.querySelector('input.piece-check');
    const moQty = row.querySelector("select[name='qty[]']")?.value || '1';
    const moInput = row.querySelector("input[name='price_override[]']")?.value || '';
    const pieceQty = row.querySelector("select[name='piece_qty[]']")?.value || '1';
    const pieceInput = row.querySelector("input[name='piece_price_override[]']")?.value || '';
    draft.rows.push({
      id, checked: !!moCb?.checked, moQty, moInput,
      pieceChecked: !!pieceCb?.checked, pieceQty, pieceInput
    });
  });
  // Conserver aussi les ventes de vélo en custom.prest
  try{
    if (!draft.custom) draft.custom = {};
    if (!Array.isArray(draft.custom.prest)) draft.custom.prest = [];
  }catch(_e){}
  localStorage.setItem('devisDraft', JSON.stringify(draft));
}

function loadDevisDraft(){
  try{
    const raw = localStorage.getItem('devisDraft');
    if(!raw) return;
    const draft = JSON.parse(raw);
    if(Array.isArray(draft.rows)){
      draft.rows.forEach((r)=>{
        const row = document.querySelector(`.prestation-card[data-id='${r.id}']`);
        if(!row) return;
        const moCb = row.querySelector('input.prest-check');
        if(moCb){ moCb.checked = !!r.checked; }
        const moQty = row.querySelector("select[name='qty[]']");
        if(moQty){ moQty.value = r.moQty || '1'; }
        const moOv = row.querySelector("input[name='price_override[]']");
        if(moOv){ moOv.value = r.moInput || ''; }

        const pieceCb = row.querySelector('input.piece-check');
        if(pieceCb){ pieceCb.checked = !!r.pieceChecked; }
        const pQty = row.querySelector("select[name='piece_qty[]']");
        if(pQty){ pQty.value = r.pieceQty || '1'; }
        const pOv = row.querySelector("input[name='piece_price_override[]']");
        if(pOv){ pOv.value = r.pieceInput || ''; }
      });
    }
    // Restaurer les ventes de vélo dans l'état Alpine si présentes
    if (draft && draft.custom && Array.isArray(draft.custom.prest)) {
      const form = document.querySelector('.catalogue-container');
      const data = form && form.__x ? form.__x.$data : null;
      if (data) {
        draft.custom.prest.forEach(l=>{
          const lab = String(l?.label || '').trim();
          const pr = Number(String(l?.price || '').replace(',', '.'));
          if (lab && Number.isFinite(pr) && pr >= 0) {
            if (!Array.isArray(data.bikeSales)) data.bikeSales = [];
            data.bikeSales.push({ label: lab, qty: 1, unit: pr, subtotal: pr });
          }
        });
        if (typeof data.recalc === 'function') data.recalc();
      }
    }
    // on recalcule une fois chargé
    if (typeof ticketForm === 'function') {
      const totalEl = document.getElementById('t-total');
      if (totalEl) {
        const e = new Event('input');
        document.body.dispatchEvent(e);
      }
    }
  }catch(e){ console.warn('Devis draft restore error', e); }
}

// Vérifier s'il y a des lignes sélectionnées
function hasSelectedLines(){
  try{
    const d = JSON.parse(localStorage.getItem('devisDraft') || 'null');
    if (d && Array.isArray(d.rows)) {
      return d.rows.some(r => !!r?.checked || !!r?.pieceChecked);
    }
  }catch(_e){}
  // fallback DOM scan
  let any = false;
  document.querySelectorAll('.prestation-card').forEach((row)=>{
    const mo = row.querySelector('input.prest-check');
    const pc = row.querySelector('input.piece-check');
    if ((mo && mo.checked) || (pc && pc.checked)) any = true;
  });
  return any;
}

// Bouton "Nouveau client" en bas:
// - si client déjà sélectionné: aller au dashboard et auto-créer le ticket
// - sinon: aller sur création client pré-remplie puis auto-création du ticket après save
function goNewClient(){
  const autostart = hasSelectedLines();
  // Récupérer le dernier modèle de vélo si disponible
  let bikeModel = '';
  try {
    bikeModel = localStorage.getItem('lastBikeModel') || '';
  } catch(_e) {}
  
  // Toujours créer un nouveau client (ne pas réutiliser un client_id existant)
  const live = readClientFields();
  const hasLive = !!(live && (live.name || live.address || live.email || live.phone));
  let f = null;
  if (hasLive) {
    f = live;
    try{ localStorage.setItem('clientDraft', JSON.stringify(f)); }catch(_e){}
  } else {
    const validated = (sessionStorage.getItem('clientDraftValidated') === '1');
    if (validated) {
      try{
        f = JSON.parse(localStorage.getItem('clientDraft') || 'null');
      }catch(_e){}
    }
  }
  if (f && f.name) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '/clients/0/edit';
    const add = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
    add('return','/catalogue');
    add('auto_create','1');
    if (autostart) {
      add('autostart','1');
      // Joindre les lignes sélectionnées au POST pour création serveur du ticket
      try{
        const raw = localStorage.getItem('devisDraft');
        const draft = raw ? JSON.parse(raw) : null;
        if (draft && Array.isArray(draft.rows)) {
          draft.rows.forEach(r=>{
            if (r.checked || r.pieceChecked) {
              add('prest_id[]', r.id || '');
              add('qty[]', r.moQty || '1');
              add('price_override[]', r.moInput || '');
              add('piece_qty[]', r.pieceQty || '0');
              add('piece_price_override[]', r.pieceInput || '');
            }
          });
        }
        // Indicateur pour le serveur
        add('_create_ticket_from_builder','1');
      }catch(_e){}
    }
    add('name', f.name || '');
    add('address', f.address || '');
    add('email', f.email || '');
    add('phone', f.phone || '');
    if (bikeModel) {
      add('bike_model', bikeModel);
      // Nettoyer le localStorage après utilisation pour éviter la persistance
      try {
        localStorage.removeItem('lastBikeModel');
      } catch(_e) {}
    }
    document.body.appendChild(form);
    form.submit();
    return false;
  }
  // Rien saisi → bascule sur l'édition client (vierge)
  const params = ['return=' + encodeURIComponent('/catalogue')];
  if (bikeModel) {
    params.push('bike_model=' + encodeURIComponent(bikeModel));
  }
  window.location.href = '/clients/0/edit?' + params.join('&');
  return false;
}

// Lecture des champs coordonnées client (si saisis)
function readClientFields(){
  const name = (document.getElementById('client_name')?.value || '').trim();
  const address = (document.getElementById('client_address')?.value || '').trim();
  const email = (document.getElementById('client_email')?.value || '').trim();
  const phone = (document.getElementById('client_phone')?.value || '').trim();
  return { name, address, email, phone };
}

// Modal Devis PDF
function openDevisModal(){
  const m = document.getElementById('devis-modal');
  if (m) { m.style.display = 'flex'; }
  return false;
}
function closeDevisModal(){
  const m = document.getElementById('devis-modal');
  if (m) { m.style.display = 'none'; }
  return false;
}
function submitDirectPdf(){
  // Vérifier qu'au moins une ligne est sélectionnée
  if (!hasSelectedLines()) { alert('Veuillez sélectionner au moins une prestation.'); return false; }
  const form = document.querySelector('form[action="/devis/pdf/direct"]') || document.querySelector('form');
  if (!form) return false;
  // Nettoyer anciens inputs temporaires
  ['name','phone','brand'].forEach((n)=>{ const old = form.querySelector('input[name="'+n+'"]'); if (old) old.remove(); });
  // Ajouter les inputs depuis le modal
  const add = (n,v)=>{ const i=document.createElement('input'); i.type='hidden'; i.name=n; i.value=v; form.appendChild(i); };
  add('name', document.getElementById('m_name')?.value || '');
  add('phone', document.getElementById('m_phone')?.value || '');
  add('brand', document.getElementById('m_brand')?.value || '');
  // Ouvrir dans un nouvel onglet
  form.setAttribute('target','_blank');
  form.submit();
  // Reset page (/catalogue) : vider brouillon et décocher
  try{ localStorage.removeItem('devisDraft'); }catch(_e){}
  document.querySelectorAll('.prestation-card input.prest-check, .prestation-card input.piece-check').forEach(el=>{ el.checked=false; });
  document.querySelectorAll("select[name='qty[]']").forEach(el=>{ el.value='1'; });
  document.querySelectorAll("select[name='piece_qty[]']").forEach(el=>{ el.value='1'; });
  document.querySelectorAll("input[name='price_override[]']").forEach(el=>{ el.value=''; });
  document.querySelectorAll("input[name='piece_price_override[]']").forEach(el=>{ el.value=''; });
  // Recalculer total
  const e = new Event('input'); document.body.dispatchEvent(e);
  closeDevisModal();
  return false;
}

// Modal Sélection Client
function openClientSelectModal(){
  const m = document.getElementById('client-select-modal');
  if(m){ m.style.display='flex'; const i=document.getElementById('client-search-input'); if(i){ i.value=''; i.focus(); } const r=document.getElementById('client-search-results'); if(r){ r.innerHTML=''; } }
  return false;
}
function closeClientSelectModal(){ const m=document.getElementById('client-select-modal'); if(m){ m.style.display='none'; } return false; }

function clientSearch(q){
  q=(q||'').trim();
  const box=document.getElementById('client-search-results');
  if(!box) return;
  if (q.length < 2) {
    box.innerHTML = '<div class="text-muted">Tapez au moins 2 caractères…</div>';
    return;
  }
  fetch('/search?q='+encodeURIComponent(q), { credentials: 'include', headers: { 'Accept': 'text/html' } })
    .then(r=>r.text())
    .then(html=>{
      // Si on a été redirigé vers la page de login (middleware), prévenir l'utilisateur
      if (html.includes('<form') && html.toLowerCase().includes('login')) {
        box.innerHTML = '<div style="color:#b00020">Session expirée — veuillez vous reconnecter.</div>';
        return;
      }
      const parser=new DOMParser();
      const doc=parser.parseFromString(html,'text/html');
      const links=[...doc.querySelectorAll('a[href^="/clients/"]')];
      const items=[];
      links.forEach(a=>{ const m=a.getAttribute('href').match(/^\/clients\/(\d+)/); if(m){ const id=m[1]; const name=(a.textContent||'').trim(); items.push({id,name}); }});
      if(items.length===0){ box.innerHTML='<div class="text-muted">Aucun client</div>'; return; }
      box.innerHTML='';
      items.forEach(it=>{ const row=document.createElement('div'); row.style.cssText='padding:8px; border-bottom:1px solid #f1f5f9; cursor:pointer;'; row.textContent=it.name; row.onclick=()=>selectExistingClient(it.id); box.appendChild(row); });
    })
    .catch(()=>{ box.innerHTML='<div style="color:#b00020">Erreur de recherche</div>'; });
}
let _clientSearchTimer=null;
function debouncedClientSearch(){ clearTimeout(_clientSearchTimer); _clientSearchTimer = setTimeout(()=>{ const i=document.getElementById('client-search-input'); clientSearch(i?i.value:''); }, 250); }
function selectExistingClient(clientId){
  try{
    if(!localStorage.getItem('devisDraft')){ if(typeof saveDevisDraft==='function'){ saveDevisDraft(); } }
    const raw = localStorage.getItem('devisDraft');
    const draft = raw ? JSON.parse(raw) : null;
    if(!draft){ alert('Aucun brouillon de devis trouvé.'); return; }
    
    // Ouvrir modale de validation au lieu de soumettre directement
    fetch('/clients/' + clientId + '/info')
      .then(r => r.json())
      .then(client => {
        // Pré-remplir la modale
        document.getElementById('tv_client_name').value = client.name || '';
        document.getElementById('tv_email').value = client.email || '';
        document.getElementById('tv_phone').value = client.phone || '';
        document.getElementById('tv_bike_model').value = '';
        document.getElementById('tv_note').value = '';
        
        // Stocker clientId et draft pour la soumission
        window._pendingClientId = clientId;
        window._pendingDraft = draft;
        
        // Ouvrir modale
        openTicketValidationModal();
        closeClientSelectModal();
      })
      .catch(err => {
        console.error(err);
        alert('Erreur lors du chargement des infos client.');
      });
  }catch(e){ console.error(e); alert('Impossible de sélectionner ce client.'); }
}

// Modal Validation Ticket
function openTicketValidationModal(){
  const m = document.getElementById('ticket-validation-modal');
  if(m){ m.style.display='flex'; }
}
function closeTicketValidationModal(){
  const m = document.getElementById('ticket-validation-modal');
  if(m){ m.style.display='none'; }
  window._pendingClientId = null;
  window._pendingDraft = null;
}
function submitTicketValidation(){
  const clientId = window._pendingClientId;
  const draft = window._pendingDraft;
  if(!clientId || !draft){ alert('Données manquantes.'); return; }
  
  const bikeModel = document.getElementById('tv_bike_model').value.trim();
  const email = document.getElementById('tv_email').value.trim();
  const phone = document.getElementById('tv_phone').value.trim();
  const note = document.getElementById('tv_note').value.trim();
  
  // Créer et soumettre le formulaire
  const form = document.createElement('form');
  form.method = 'post';
  form.action = '/clients/' + String(clientId) + '/select';
  
  const add = (n, v) => { 
    const i = document.createElement('input'); 
    i.type = 'hidden'; 
    i.name = n; 
    i.value = v; 
    form.appendChild(i); 
  };
  
  // Lignes de prestations
  if(Array.isArray(draft.rows)){
    draft.rows.forEach(r=>{
      if(r.checked || r.pieceChecked){
        add('prest_id[]', r.id || '');
        add('qty[]', r.moQty || '1');
        add('price_override[]', r.moInput || '');
        add('piece_qty[]', r.pieceQty || '0');
        add('piece_price_override[]', r.pieceInput || '');
      }
    });
  }
  
  // Prestations/pièces custom
  if(draft && draft.custom){
    try{
      if(Array.isArray(draft.custom.prest)){
        draft.custom.prest.forEach(l=>{ 
          const lab=(l?.label||'').trim(); 
          const pr=Number(String(l?.price??'').replace(',','.')); 
          if(lab!=='' && Number.isFinite(pr) && pr>=0){ 
            add('custom_prest_label[]', lab); 
            add('custom_prest_price[]', String(pr)); 
          } 
        });
      }
      if(Array.isArray(draft.custom.piece)){
        draft.custom.piece.forEach(l=>{ 
          const lab=(l?.label||'').trim(); 
          const pr=Number(String(l?.price??'').replace(',','.')); 
          if(lab!=='' && Number.isFinite(pr) && pr>=0){ 
            add('custom_piece_label[]', lab); 
            add('custom_piece_price[]', String(pr)); 
          } 
        });
      }
    }catch(_e){}
  }
  
  // Champs de validation
  add('bike_model', bikeModel);
  add('email', email);
  add('phone', phone);
  add('note', note);
  
  document.body.appendChild(form);
  form.submit();
  closeTicketValidationModal();
}

// Initialisation au chargement
(function(){
  try{
    // Réinitialiser le flag de validation à chaque arrivée sur /catalogue
    sessionStorage.setItem('clientDraftValidated','0');
  }catch(_e){}
})();