(function(){
  "use strict";

  const sellerForm = document.getElementById('sellerForm');
  const sellerFormWrap = document.getElementById('sellerFormWrap');
  const sellerThanks = document.getElementById('sellerThanks');
  const btnNewSubmission = document.getElementById('btnNewSubmission');
  const submitError = document.getElementById('submitError');
  const progressFill = document.getElementById('progressFill');
  const progressLabel = document.getElementById('progressLabel');

  function onlyDigits(s){ return String(s || '').replace(/\D/g,''); }

  /* ---------- Masks ---------- */
  function maskCNPJ(el){
    el.addEventListener('input', ()=>{
      let d = onlyDigits(el.value).slice(0,14), v = d;
      if(d.length > 12) v = d.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})$/, '$1.$2.$3/$4-$5');
      else if(d.length > 8) v = d.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})$/, '$1.$2.$3/$4');
      else if(d.length > 5) v = d.replace(/^(\d{2})(\d{3})(\d{0,3})$/, '$1.$2.$3');
      else if(d.length > 2) v = d.replace(/^(\d{2})(\d{0,3})$/, '$1.$2');
      el.value = v;
    });
  }
  function maskCPF(el){
    el.addEventListener('input', ()=>{
      let d = onlyDigits(el.value).slice(0,11), v = d;
      if(d.length > 9) v = d.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2})$/, '$1.$2.$3-$4');
      else if(d.length > 6) v = d.replace(/^(\d{3})(\d{3})(\d{0,3})$/, '$1.$2.$3');
      else if(d.length > 3) v = d.replace(/^(\d{3})(\d{0,3})$/, '$1.$2');
      el.value = v;
    });
  }
  function maskCEP(el){
    el.addEventListener('input', ()=>{
      let d = onlyDigits(el.value).slice(0,8), v = d;
      if(d.length > 5) v = d.replace(/^(\d{5})(\d{0,3})$/, '$1-$2');
      el.value = v;
    });
  }
  function maskPhone(el){
    el.addEventListener('input', ()=>{
      let d = onlyDigits(el.value).slice(0,11), v = d;
      if(d.length > 10) v = d.replace(/^(\d{2})(\d{5})(\d{0,4})$/, '($1) $2-$3');
      else if(d.length > 6) v = d.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
      else if(d.length > 2) v = d.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
      el.value = v;
    });
  }
  function uppercaseField(el){
    el.addEventListener('input', ()=>{ el.value = el.value.toUpperCase(); });
  }

  maskCNPJ(document.getElementById('cnpjInput'));
  maskCPF(document.getElementById('cpfInput'));
  maskCEP(document.getElementById('cepInput'));
  maskPhone(document.getElementById('telEmpresa'));
  maskPhone(document.getElementById('telResp'));
  uppercaseField(document.getElementById('estadoInput'));
  uppercaseField(document.getElementById('paisInput'));
  uppercaseField(document.getElementById('ufEmissoraInput'));

  /* File name display + visual state */
  sellerForm.addEventListener('change', (e)=>{
    if(e.target.matches('input[type="file"]')){
      const wrap = e.target.closest('.file-input');
      const span = wrap.querySelector('.file-name');
      const file = e.target.files[0];
      if(span) span.textContent = file ? file.name : t('Nenhum arquivo escolhido');
      wrap.classList.toggle('has-file', !!file);
    }
  });

  /* ---------- Progress tracking ---------- */
  function updateProgress(){
    const required = sellerForm.querySelectorAll('[required]:not(:disabled)');
    let total = 0, filled = 0;
    required.forEach(el=>{
      total++;
      if(el.value && String(el.value).trim() !== '') filled++;
    });
    const pct = total ? Math.round((filled/total)*100) : 0;
    progressFill.style.width = pct + '%';
    progressLabel.textContent = t('{pct}% preenchido', { pct: pct });
  }
  sellerForm.addEventListener('input', updateProgress);
  sellerForm.addEventListener('change', updateProgress);
  updateProgress();

  /* ---------- Section scrollspy ---------- */
  const railLinks = Array.from(document.querySelectorAll('.rail-nav a'));
  railLinks.forEach(a=>{
    a.addEventListener('click', (e)=>{
      e.preventDefault();
      const target = document.querySelector(a.getAttribute('href'));
      if(target) target.scrollIntoView({behavior:'smooth', block:'start'});
    });
  });
  const sectionObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if(entry.isIntersecting){
        const id = entry.target.id;
        railLinks.forEach(a=> a.classList.toggle('active', a.getAttribute('href') === '#'+id));
      }
    });
  }, {rootMargin:'-15% 0px -70% 0px', threshold:0});
  document.querySelectorAll('#sellerForm .card[id]').forEach(sec=>sectionObserver.observe(sec));

  /* ---------- Submit straight to the server ---------- */
  sellerForm.addEventListener('input', (e)=>{
    const wrap = e.target.closest && e.target.closest('.field[data-state="bad"]');
    if(wrap) wrap.removeAttribute('data-state');
  });

  sellerForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const submitBtn = sellerForm.querySelector('button[type=submit]');
    submitBtn.disabled = true;
    submitError.hidden = true;
    try{
      const resp = await fetch(sellerForm.getAttribute('action'), { method:'POST', body: new FormData(sellerForm), headers: { 'X-Requested-With': 'fetch' } });
      let result;
      try{ result = await resp.json(); }
      catch(parseErr){ throw { error: t('Resposta inesperada do servidor.') }; }
      if(!resp.ok || !result.ok){
        throw result;
      }
      sellerFormWrap.hidden = true;
      sellerThanks.hidden = false;
      window.scrollTo(0,0);
    }catch(err){
      console.error(err);
      submitError.textContent = (err && err.error) || t('Ocorreu um erro ao enviar. Tente novamente.');
      submitError.hidden = false;
      const input = err && err.field ? sellerForm.querySelector('[name="' + err.field + '"]') : null;
      if(input){
        const wrap = input.closest('.field');
        if(wrap) wrap.setAttribute('data-state', 'bad');
        input.scrollIntoView({behavior:'smooth', block:'center'});
        input.focus({preventScroll:true});
      } else {
        window.scrollTo({top:0, behavior:'smooth'});
      }
    }finally{
      submitBtn.disabled = false;
    }
  });

  if (btnNewSubmission) btnNewSubmission.addEventListener('click', ()=>{
    sellerForm.reset();
    document.getElementById('ddiEmpresa').value = '55';
    document.getElementById('ddiResp').value = '55';
    document.getElementById('paisInput').value = 'BR';
    sellerForm.querySelectorAll('.file-name').forEach(s=> s.textContent = t('Nenhum arquivo escolhido'));
    sellerForm.querySelectorAll('.file-input').forEach(w=> w.classList.remove('has-file'));
    updateProgress();
    sellerThanks.hidden = true;
    sellerFormWrap.hidden = false;
    window.scrollTo(0,0);
  });
})();
