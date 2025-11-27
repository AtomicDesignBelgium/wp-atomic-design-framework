/* global ADFNotes, ajaxurl */
(function(){
  function $(sel, root){ return (root||document).querySelector(sel); }
  function $all(sel, root){ return Array.from((root||document).querySelectorAll(sel)); }
  const ajax = (fd) => fetch((ADFNotes&&ADFNotes.ajaxurl) || (typeof ajaxurl!=='undefined'?ajaxurl:''), { method:'POST', body: fd });
  const nonce = ADFNotes && ADFNotes.nonce;

  function statusMeta(v){switch(v){case 'pending': return {text:'Pending',color:'#f39c12'};case 'in_progress': return {text:'In progress',color:'#3498db'};case 'approved': return {text:'Approved',color:'#2ecc71'};case 'done': return {text:'Done',color:'#2ecc71'};case 'rejected': return {text:'Rejected',color:'#e74c3c'};default: return {text:v,color:'#7f8c8d'};}}
  function priorityMeta(v){switch(v){case 'high': return {text:'High',color:'#e74c3c'};case 'medium': return {text:'Medium',color:'#f39c12'};case 'low': return {text:'Low',color:'#3498db'};default: return {text:'None',color:'#7f8c8d'};}}
  function icon(ext){ext=String(ext||'').toLowerCase();return ext==='pdf'?'📄':((ext==='doc'||ext==='docx')?'📝':((ext==='xls'||ext==='xlsx')?'📊':((ext==='jpg'||ext==='jpeg'||ext==='png'||ext==='gif'||ext==='webp')?'🖼️':'📎')));} 
  function trunc(n){n=String(n||'');return n.length>32?n.slice(0,18)+'…'+n.slice(-10):n;}

  document.addEventListener('DOMContentLoaded', function(){
    const boxBtn = $('#adfNoteCreate');
    if (boxBtn) {
      boxBtn.addEventListener('click', function(){
        const pidEl = $('#adfNotePostIdBox');
        const t = $('#adfNoteTitle')?.value || '';
        const c = $('#adfNoteContent')?.value || '';
        const fd = new FormData();
        fd.append('action','adf_create_note');
        fd.append('post_id', (pidEl && pidEl.value) || '0');
        fd.append('title', t); fd.append('content', c);
        fd.append('_wpnonce', nonce);
        ajax(fd).then(r=>r.json()).then(()=>location.reload());
      });
    }

    const openBtn = $('#adfOpenNewNote');
    const modal = $('#adfNewNoteModal');
    const modalClose = $('#adfModalClose');
    if (openBtn && modal) { openBtn.addEventListener('click', ()=>{ modal.style.display='flex'; }); }
    if (modalClose && modal) {
      modalClose.addEventListener('click', ()=>{ modal.style.display='none'; });
      document.addEventListener('keydown', function(e){ if (e.key==='Escape') modal.style.display='none'; });
      if (modal) { modal.addEventListener('click', function(e){ if (e.target===modal) { /* prevent backdrop close */ } }); }
    }

    const filtersForm = $('#adfFilters form');
    const applyBtn = $('#adfFiltersApply');
    if (filtersForm && applyBtn) {
      const initFp = filtersForm.querySelector('[name="adf_fp"]')?.value || '';
      const initPr = filtersForm.querySelector('[name="adf_pr"]')?.value || '';
      const initSt = filtersForm.querySelector('[name="adf_st"]')?.value || '';
      const initSo = filtersForm.querySelector('[name="adf_so"]')?.value || '';
      function refresh(){
        const cFp = filtersForm.querySelector('[name="adf_fp"]')?.value || '';
        const cPr = filtersForm.querySelector('[name="adf_pr"]')?.value || '';
        const cSt = filtersForm.querySelector('[name="adf_st"]')?.value || '';
        const cSo = filtersForm.querySelector('[name="adf_so"]')?.value || '';
        const changed = (cFp!==initFp || cPr!==initPr || cSt!==initSt || cSo!==initSo);
        applyBtn.className = changed ? 'button button-primary' : 'button';
      }
      ['adf_fp','adf_pr','adf_st','adf_so'].forEach(function(n){
        const el = filtersForm.querySelector('[name="'+n+'"]');
        if (el) el.addEventListener('change', refresh);
      });
    }

    $all('.adfNoteSave').forEach(function(btn){
      btn.addEventListener('click', function(){
        const id = this.getAttribute('data-note');
        const pg = $('.adfNoteProgress[data-note="'+id+'"]')?.value || 'pending';
        const vl = $('.adfNoteValidation[data-note="'+id+'"]')?.value || 'pending';
        const pr = $('.adfNotePriority[data-note="'+id+'"]')?.value || 'none';
        const fd = new FormData();
        fd.append('action','adf_update_note');
        fd.append('note_id', id);
        fd.append('progress', pg); fd.append('validation', vl); fd.append('priority', pr);
        fd.append('_wpnonce', nonce);
        ajax(fd).then(r=>r.json()).then(()=>location.reload());
      });
    });
    $all('.adfNoteProgress').forEach(function(sel){ sel.addEventListener('change', function(){ const id=this.getAttribute('data-note'); const b=$('.adfStatusBadge[data-note="'+id+'"]'); const m=statusMeta(this.value); if(b){ b.textContent=m.text; b.style.background=m.color; } }); });
    $all('.adfNoteValidation').forEach(function(sel){ sel.addEventListener('change', function(){ const id=this.getAttribute('data-note'); const b=$('.adfValidationBadge[data-note="'+id+'"]'); const c=this.value==='validated'?'#2ecc71':(this.value==='rejected'?'#e74c3c':'#f39c12'); const t=this.value==='validated'?'Validated':(this.value==='rejected'?'Rejected':'Validation pending'); if(b){ b.textContent=t; b.style.background=c; } }); });
    $all('.adfNotePriority').forEach(function(sel){ sel.addEventListener('change', function(){ const id=this.getAttribute('data-note'); const b=$('.adfPriorityBadge[data-note="'+id+'"]'); const m=priorityMeta(this.value); if(b){ b.textContent=m.text; b.style.background=m.color; } }); });

    const selected = {}; 
    function renderSelected(id){ const box = document.querySelector('.adf-selected-files[data-note="'+id+'"]'); if(!box) return; const arr = selected[id]||[]; let html=''; arr.forEach(function(f,i){ const e=(f.name.split('.').pop()||''); html += '<span data-i="'+i+'">'+icon(e)+' '+trunc(f.name)+' <a href="#" class="adfFileRemove">✖</a></span>'; }); box.innerHTML=html; $all('.adfFileRemove', box).forEach(function(a){ a.addEventListener('click', function(ev){ ev.preventDefault(); const p = this.parentElement; const idx = p && p.getAttribute('data-i'); if(idx!==null){ const arr = selected[id]||[]; arr.splice(parseInt(idx,10),1); selected[id]=arr; renderSelected(id); } }); }); }

    $all('.adfCommentFile').forEach(function(inp){ inp.addEventListener('change', function(){ const id=this.getAttribute('data-note'); const arr = selected[id]||[]; const add = Array.from(this.files||[]); const names = arr.map(function(f){ return f.name; }); add.forEach(function(f){ if(names.indexOf(f.name)===-1){ arr.push(f); } }); selected[id]=arr; this.value=''; renderSelected(id); }); });

    function uploadFiles(files, extra){
      const e = extra || {}; const tasks = Array.from(files||[]).map(function(f){ const fd = new FormData(); fd.append('action','adf_upload_note_file'); fd.append('file', f); if(e.note_id) fd.append('note_id', e.note_id); if(e.ref) fd.append('ref', e.ref); fd.append('_wpnonce', nonce); return ajax(fd).then(r=>r.json()).then(function(resp){ return (resp&&resp.success&&resp.data&&resp.data.filename)?resp.data.filename:null; }).catch(function(){ return null; }); });
      return Promise.all(tasks).then(function(names){ return names.filter(Boolean); });
    }

    $all('.adfCommentAdd').forEach(function(btn){ btn.addEventListener('click', function(){ const id = this.getAttribute('data-note'); const ta = $('.adfCommentText[data-note="'+id+'"]'); const text = (ta&&ta.value)||''; const files = selected[id]||[]; const done = function(names){ const fd = new FormData(); fd.append('action','adf_add_comment'); fd.append('note_id', id); fd.append('content', text); if(names && names.length){ fd.append('media', JSON.stringify(names)); } fd.append('_wpnonce', nonce); ajax(fd).then(r=>r.json()).then(()=>location.reload()); }; if(files.length){ uploadFiles(files).then(done); } else { done([]); } }); });

    $all('.adfCommentText').forEach(function(ta){ ta.addEventListener('focus', function(){ this.style.height='140px'; }); ta.addEventListener('blur', function(){ if((this.value||'').trim()===''){ this.style.height='40px'; } }); });

    const createBtn = $('#adfNoteCreateAdmin');
    const createFile = $('#adfNoteFile');
    const createList = $('#adfNoteSelected');
    if (createBtn && createFile) {
      let sel = []; const noteRef = Math.random().toString(36).slice(2,10);
      createFile.addEventListener('change', function(){ const add = Array.from(createFile.files||[]); const names = sel.map(function(f){ return f.name; }); add.forEach(function(f){ if(names.indexOf(f.name)===-1){ sel.push(f); } }); createFile.value=''; if(createList){ let html=''; sel.forEach(function(f,i){ const e=(f.name.split('.').pop()||''); html += '<span data-i="'+i+'">'+icon(e)+' '+trunc(f.name)+' <a href="#" class="adfFileRemove">✖</a></span>'; }); createList.innerHTML=html; $all('.adfFileRemove', createList).forEach(function(a){ a.addEventListener('click', function(ev){ ev.preventDefault(); const p = this.parentElement; const idx = p && p.getAttribute('data-i'); if(idx!==null){ sel.splice(parseInt(idx,10),1); if(createList) { let html=''; sel.forEach(function(f,i){ const e=(f.name.split('.').pop()||''); html += '<span data-i="'+i+'">'+icon(e)+' '+trunc(f.name)+' <a href="#" class="adfFileRemove">✖</a></span>'; }); createList.innerHTML=html; } } }); }); }
      });
      createBtn.addEventListener('click', function(){ const pid = $('#adfNotePostId')?.value || '0'; const t = $('#adfNoteTitleAdmin')?.value || ''; const c = $('#adfNoteContentAdmin')?.value || ''; const pr = $('#adfNotePriorityAdmin')?.value || 'none'; const pg = $('#adfNoteProgressAdmin')?.value || 'pending'; const vlSel = $('#adfNoteValidationAdmin'); const vl = vlSel ? (vlSel.value || 'pending') : 'pending'; const submit = function(mediaArr){ const fd = new FormData(); fd.append('action','adf_create_note'); fd.append('post_id', pid); fd.append('title', t); fd.append('content', c); fd.append('priority', pr); fd.append('progress', pg); fd.append('validation', vl); fd.append('ref', noteRef); if(mediaArr && mediaArr.length){ fd.append('media', JSON.stringify(mediaArr)); } fd.append('_wpnonce', nonce); ajax(fd).then(r=>r.json()).then(()=>location.reload()); }; if(sel.length){ uploadFiles(sel, { ref: noteRef }).then(submit); } else { submit([]); } });
    }
  });
})();

