/**
 * Page module — Consultation Details
 * Loaded by app.js dispatcher on turbo:load.
 */

/* -- Layout switch -- */
function applyLayout(){
  var w=window.innerWidth;
  var ds=document.getElementById('desktop-scroll');
  var tb=document.getElementById('tab-body');
  if(w<=1024){ds.style.display='none';tb.style.display='flex';tb.style.flexDirection='column';tb.style.flex='1';tb.style.overflow='hidden';}
  else{ds.style.display='block';tb.style.display='none';}
}

/* -- Tab switch -- */
function switchConsultTab(tab){
  ['clinique','notes','patient'].forEach(function(t){
    document.getElementById('tab-'+t).classList.toggle('active',t===tab);
    document.getElementById('btab-'+t).classList.toggle('active',t===tab);
  });
}

/* -- Toast -- */
function showToast(msg,color){
  var t=document.getElementById('toast');
  t.textContent=msg;t.style.background=color||'#16a34a';
  t.style.opacity='1';t.style.transform='translateX(-50%) translateY(0)';
  setTimeout(function(){t.style.opacity='0';t.style.transform='translateX(-50%) translateY(8px)';},2200);
}

/* -- Timer -- */
var mins=0,secs=0;
var _timerInterval=null;

/* -- Motif toggle -- */
function toggleMotif(el){
  var a=el.classList.contains('active-m');
  el.classList.toggle('active-m',!a);
  if(!a){el.style.borderColor='var(--brand-500)';el.style.background='var(--brand-50)';el.style.color='var(--brand-600)';}
  else{el.style.borderColor='var(--border-medium)';el.style.background='var(--surface-subtle)';el.style.color='var(--text-muted)';}
}
function toggleEditMotif(){
  var v=document.getElementById('motif-view'),e=document.getElementById('motif-edit'),b=document.getElementById('motif-edit-btn');
  var editing=e.style.display!=='none';
  v.style.display=editing?'block':'none';
  e.style.display=editing?'none':'block';
  b.innerHTML=editing?'<i data-lucide="pencil" style="width:10px;height:10px;"></i>Modifier':'<i data-lucide="check" style="width:10px;height:10px;"></i>Valider';
  lucide.createIcons();
}
function toggleEditVitals(){
  var v=document.getElementById('vitals-view'),e=document.getElementById('vitals-edit'),b=document.getElementById('vitals-edit-btn');
  var editing=e.style.display!=='none';
  v.style.display=editing?'block':'none';
  e.style.display=editing?'none':'block';
  b.innerHTML=editing?'<i data-lucide="pencil" style="width:10px;height:10px;"></i>Modifier':'<i data-lucide="check" style="width:10px;height:10px;"></i>Valider';
  lucide.createIcons();
}

/* -- Pain score -- */
var painColors=['#94a3b8','#22c55e','#22c55e','#84cc16','#eab308','#eab308','#f97316','#f97316','#ef4444','#ef4444','#dc2626'];
var painLabels=['Non évalué','Minime','Minime','Léger','Léger','Modéré','Modéré','Important','Important','Intense','Intense'];
var currentPainScore=null;
function setPainScore(el,score){
  currentPainScore=currentPainScore===score?null:score;
  document.querySelectorAll('[data-score]').forEach(function(c){c.style.borderColor='var(--border-medium)';c.style.background='';c.style.color='var(--text-muted)';});
  var label=document.getElementById('pain-label'),card=document.getElementById('pain-card'),cardVal=document.getElementById('pain-card-val');
  if(currentPainScore===null){if(label)label.textContent='Non évalué';if(label)label.style.color='var(--text-subtle)';if(cardVal)cardVal.textContent='—';}
  else{
    var col=painColors[score];
    document.querySelectorAll('[data-score="'+score+'"]').forEach(function(c){c.style.borderColor=col;c.style.background=col+'22';c.style.color=col;});
    if(label){label.textContent=score+'/10 — '+painLabels[score];label.style.color=col;label.style.fontWeight='var(--weight-medium)';}
    if(cardVal){cardVal.textContent=score+'/10';cardVal.style.color=col;}
    if(card)card.style.boxShadow='0 0 0 2px '+col;
  }
}

/* -- Diagnostics -- */
function cycleDiag(el){
  var svgS='<svg width="8" height="8" fill="none" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.3"/><path d="M5 3v2l1 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
  var svgC='<svg width="9" height="9" fill="none" viewBox="0 0 10 10"><path d="M2 5l2.5 2.5 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  var text=el.textContent.trim().replace(/[✓?]/g,'').trim();
  if(el.classList.contains('suspected')){el.classList.replace('suspected','confirmed');el.innerHTML=svgC+' '+text;}
  else if(el.classList.contains('confirmed')){el.classList.replace('confirmed','excluded');el.innerHTML=text;}
  else if(el.classList.contains('excluded')){el.classList.replace('excluded','suspected');el.innerHTML=svgS+' '+text;}
  else{el.classList.add('suspected');el.innerHTML=svgS+' '+text;}
}
function toggleDiagNotes(){
  var r=document.getElementById('diag-notes-row'),b=document.getElementById('diag-notes-btn');
  var v=r.style.display!=='none';
  r.style.display=v?'none':'block';
  b.innerHTML=v?'<svg width="8" height="8" fill="none" viewBox="0 0 12 12"><path d="M6 2v8M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>Ajouter des notes':'<svg width="8" height="8" fill="none" viewBox="0 0 12 12"><path d="M2 6h8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>Masquer les notes';
}
function showDiagInput(){document.getElementById('diag-add-btn').style.display='none';var w=document.getElementById('diag-input-wrapper');w.style.display='flex';document.getElementById('diag-input').focus();}
function hideDiagInput(){document.getElementById('diag-input-wrapper').style.display='none';document.getElementById('diag-add-btn').style.display='inline-flex';document.getElementById('diag-input').value='';}
function addDiagFromInput(){
  var input=document.getElementById('diag-input'),text=input.value.trim();
  if(!text){hideDiagInput();return;}
  var svgS='<svg width="8" height="8" fill="none" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4" stroke="currentColor" stroke-width="1.3"/><path d="M5 3v2l1 1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>';
  var tag=document.createElement('span');
  tag.className='diag-tag suspected';
  tag.onclick=function(){cycleDiag(this);};
  tag.innerHTML=svgS+' '+text;
  document.getElementById('diag-badges').insertBefore(tag,document.getElementById('diag-add-btn'));
  hideDiagInput();
}

/* -- Plan lines -- */
var lineTypes={med:{icon:'💊',label:'Médicament',bg:'#eff6ff',color:'#1d4ed8'},examen:{icon:'🔬',label:'Examen',bg:'#f0fdf4',color:'#15803d'},acte:{icon:'🧾',label:'Acte',bg:'#f0fdf4',color:'#15803d'},conseil:{icon:'💬',label:'Conseil',bg:'var(--surface-subtle)',color:'var(--text-muted)'},suivi:{icon:'📅',label:'Suivi',bg:'#fdf4ff',color:'#7e22ce'}};
function showPlanActions(row){var a=row.querySelector('.plan-actions');if(a)a.style.opacity='1';}
function hidePlanActions(row){var a=row.querySelector('.plan-actions');if(a)a.style.opacity='0';}
function editPlanLine(btn){
  var row=btn.closest('.plan-line'),textEl=row.querySelector('.plan-text');
  if(!textEl)return;
  var current=textEl.textContent.trim();
  var input=document.createElement('input');input.type='text';input.value=current;input.className='form-input';input.style.cssText='flex:1;border:1px solid var(--brand-400);background:var(--surface-card);padding:3px var(--space-2);font-size:var(--text-md);';
  textEl.replaceWith(input);input.focus();input.select();
  function confirm(){var newText=input.value.trim()||current;var span=document.createElement('span');span.className='plan-text';span.style.cssText='font-size:var(--text-md);color:var(--text-primary);flex:1;';span.textContent=newText;input.replaceWith(span);}
  input.addEventListener('blur',confirm);
  input.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();confirm();}if(e.key==='Escape'){input.value=current;confirm();}});
}
function deletePlanLine(btn){var row=btn.closest('.plan-line');row.style.opacity='0';row.style.transform='translateX(8px)';row.style.transition='all .18s';setTimeout(function(){row.remove();renumberPlanLines();},180);}
function renumberPlanLines(){document.querySelectorAll('#plan-lines .plan-line').forEach(function(r,i){var n=r.querySelector('span');if(n&&/^\d+\.$/.test(n.textContent.trim()))n.textContent=(i+1)+'.';});}
function addPlanLine(){
  var list=document.getElementById('plan-lines'),num=list.querySelectorAll('.plan-line').length+1;
  var wrapper=document.createElement('div');wrapper.style.cssText='display:flex;flex-direction:column;gap:3px;';
  var row=document.createElement('div');row.className='plan-line';row.dataset.type='';row.style.cssText='display:flex;align-items:center;gap:var(--space-2);padding:var(--space-1) var(--space-2);border-radius:var(--radius-md);background:var(--surface-card);border:1px solid var(--brand-400);';
  row.innerHTML='<span style="font-size:var(--text-sm);color:var(--text-muted);font-weight:var(--weight-medium);flex-shrink:0;min-width:16px;">'+num+'.</span><input type="text" class="form-input plan-input" placeholder="Décrire l\'action…" style="flex:1;border:none;background:transparent;padding:0 var(--space-1);font-size:var(--text-md);outline:none;"><div class="plan-actions" style="opacity:1;"><button onclick="deletePlanLine(this)" style="width:20px;height:20px;border-radius:var(--radius-sm);border:none;background:#fee2e2;cursor:pointer;display:flex;align-items:center;justify-content:center;"><svg width="8" height="8" fill="none" viewBox="0 0 8 8"><path d="M1 1l6 6M7 1L1 7" stroke="#dc2626" stroke-width="1.3" stroke-linecap="round"/></svg></button></div>';
  var typeRow=document.createElement('div');typeRow.style.cssText='display:flex;align-items:center;gap:var(--space-1);padding:var(--space-1) var(--space-2);background:var(--surface-subtle);border:1px solid var(--border-medium);border-radius:var(--radius-md);flex-wrap:wrap;';
  typeRow.innerHTML='<span style="font-size:var(--text-xs);color:var(--text-subtle);font-weight:var(--weight-medium);flex-shrink:0;">Type :</span>'+Object.entries(lineTypes).map(function(e){return'<button data-type-key="'+e[0]+'" style="display:flex;align-items:center;gap:3px;padding:2px var(--space-2);font-size:var(--text-xs);border:1px solid var(--border-medium);border-radius:var(--radius-sm);background:var(--surface-card);cursor:pointer;font-family:inherit;font-weight:var(--weight-medium);color:var(--text-muted);">'+e[1].icon+' '+e[1].label+'</button>';}).join('')+'<button id="validate-plan-btn" style="margin-left:auto;padding:2px var(--space-3);font-size:var(--text-xs);border:none;border-radius:var(--radius-sm);background:var(--brand-600);color:#fff;cursor:pointer;font-family:inherit;font-weight:var(--weight-medium);">Valider ↵</button>';
  wrapper.appendChild(row);wrapper.appendChild(typeRow);list.appendChild(wrapper);
  row.onmouseenter=function(){showPlanActions(row);};row.onmouseleave=function(){hidePlanActions(row);};
  var input=row.querySelector('.plan-input');input.focus();
  typeRow.querySelectorAll('[data-type-key]').forEach(function(btn){
    btn.addEventListener('mousedown',function(e){e.preventDefault();});
    btn.addEventListener('click',function(){
      var type=btn.dataset.typeKey,cfg=lineTypes[type];
      row.dataset.type=type;
      typeRow.querySelectorAll('[data-type-key]').forEach(function(b){b.style.borderColor='var(--border-medium)';b.style.background='var(--surface-card)';b.style.color='var(--text-muted)';});
      btn.style.borderColor=cfg.color;btn.style.background=cfg.bg;btn.style.color=cfg.color;input.focus();
    });
  });
  var validateBtn=typeRow.querySelector('#validate-plan-btn');
  validateBtn.addEventListener('mousedown',function(e){e.preventDefault();});
  validateBtn.addEventListener('click',finalizeLine);
  function finalizeLine(){
    var text=input.value.trim();
    if(!text){wrapper.remove();renumberPlanLines();return;}
    var type=row.dataset.type||'',cfg=lineTypes[type];
    var iconSpan=cfg?'<span style="font-size:var(--text-xs);background:'+cfg.bg+';color:'+cfg.color+';padding:1px var(--space-1);border-radius:var(--radius-sm);font-weight:var(--weight-medium);flex-shrink:0;">'+cfg.icon+'</span>':'';
    var actionBtn=type==='med'?'<button class="ordo-btn" style="font-size:var(--text-xs);font-weight:var(--weight-medium);color:var(--brand-600);background:var(--brand-50);border:none;border-radius:var(--radius-sm);padding:2px var(--space-2);cursor:pointer;white-space:nowrap;">→ Ordo</button>':'';
    var numSpan=row.querySelector('span').textContent;
    var finalRow=document.createElement('div');finalRow.className='plan-line';finalRow.dataset.type=type;finalRow.style.cssText='display:flex;align-items:center;gap:var(--space-2);padding:var(--space-1) var(--space-2);border-radius:var(--radius-md);background:var(--surface-subtle);border:1px solid var(--border-light);';
    finalRow.innerHTML='<span style="font-size:var(--text-sm);color:var(--text-muted);font-weight:var(--weight-medium);flex-shrink:0;min-width:16px;">'+numSpan+'</span>'+iconSpan+'<span class="plan-text" style="font-size:var(--text-md);color:var(--text-primary);flex:1;">'+text+'</span><div class="plan-actions" style="opacity:0;transition:opacity .12s;display:flex;align-items:center;gap:3px;">'+actionBtn+'<button onclick="editPlanLine(this)" style="width:20px;height:20px;border-radius:var(--radius-sm);border:none;background:var(--surface-hover);cursor:pointer;display:flex;align-items:center;justify-content:center;"><svg width="9" height="9" fill="none" viewBox="0 0 12 12"><path d="M8 2l2 2-6 6H2V8l6-6z" stroke="var(--text-muted)" stroke-width="1.3" stroke-linejoin="round"/></svg></button><button onclick="deletePlanLine(this)" style="width:20px;height:20px;border-radius:var(--radius-sm);border:none;background:#fee2e2;cursor:pointer;display:flex;align-items:center;justify-content:center;"><svg width="8" height="8" fill="none" viewBox="0 0 8 8"><path d="M1 1l6 6M7 1L1 7" stroke="#dc2626" stroke-width="1.3" stroke-linecap="round"/></svg></button></div>';
    finalRow.onmouseenter=function(){showPlanActions(finalRow);};finalRow.onmouseleave=function(){hidePlanActions(finalRow);};
    if(type==='med'){var ob=finalRow.querySelector('.ordo-btn');if(ob)ob.onclick=function(){addToOrdo(this,text,'');};}
    wrapper.replaceWith(finalRow);
  }
  input.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();finalizeLine();}if(e.key==='Escape'){wrapper.remove();renumberPlanLines();}});
}

/* -- Ordonnance -- */
var ordoColors=['#3b82f6','#22c55e','#a855f7','#f59e0b','#ef4444'];var ordoCount=0;
function addToOrdo(btn,name,detail){
  if(document.getElementById('ordo-col').style.display==='none'){showOrdonnance();}
  var list=document.getElementById('ordo-list');
  var ph=list.querySelector('p');if(ph)ph.remove();
  if(Array.from(list.querySelectorAll('span[data-med]')).find(function(s){return s.dataset.med===name;})){showToast(name+' déjà dans l\'ordonnance','#f59e0b');return;}
  var color=ordoColors[ordoCount%ordoColors.length];
  var row=document.createElement('div');row.style.cssText='display:flex;align-items:center;gap:var(--space-2);padding:var(--space-1) var(--space-2);background:#f0fdf4;border-radius:var(--radius-md);animation:fadeIn .2s ease;';
  row.innerHTML='<div style="width:5px;height:5px;border-radius:50%;background:'+color+';flex-shrink:0;"></div><span data-med="'+name+'" style="font-size:var(--text-md);font-weight:var(--weight-medium);color:var(--text-primary);flex:1;">'+name+'</span><span style="font-size:var(--text-xs);color:var(--text-muted);">'+(detail.split('·')[0]||'').trim()+'</span>';
  list.appendChild(row);ordoCount++;
  btn.textContent='✓ Ajouté';btn.style.background='#f0fdf4';btn.style.color='#15803d';btn.onclick=null;
  showToast(name+' ajouté à l\'ordonnance','#16a34a');
  setTimeout(function(){row.style.background='var(--surface-card)';row.style.border='1px solid var(--border-light)';},1000);
}

/* -- Plan grid -- */
function updatePlanGrid(){
  var oV=document.getElementById('ordo-col').style.display!=='none';
  var eV=document.getElementById('examens-col').style.display!=='none';
  var g=document.getElementById('plan-grid');
  if(oV&&eV)g.style.gridTemplateColumns='1fr 0.5fr 0.5fr';
  else if(oV||eV)g.style.gridTemplateColumns='1fr 0.65fr';
  else g.style.gridTemplateColumns='1fr';
}
function showOrdonnance(){document.getElementById('btn-create-ordo').style.display='none';var c=document.getElementById('ordo-col');c.style.display='flex';c.style.animation='fadeIn .2s ease';updatePlanGrid();}
function hideOrdonnance(){document.getElementById('ordo-col').style.display='none';document.getElementById('btn-create-ordo').style.display='flex';updatePlanGrid();}
function showExamensP(){document.getElementById('btn-create-examens').style.display='none';var c=document.getElementById('examens-col');c.style.display='flex';c.style.animation='fadeIn .2s ease';updatePlanGrid();}
function hideExamensP(){document.getElementById('examens-col').style.display='none';document.getElementById('btn-create-examens').style.display='flex';updatePlanGrid();}

/* -- Vitals chart -- */
var vitalData={poids:{label:'Poids (kg)',values:[4.2,4.1,4.0,4.0,3.9,3.8],color:'#22c55e',norm:[3.5,5.0]},temp:{label:'Température (°C)',values:[38.5,38.6,38.4,39.0,38.8,39.6],color:'#ef4444',norm:[38.0,39.2]},fc:{label:'FC (bpm)',values:[130,135,128,140,138,142],color:'#6366f1',norm:[100,160]},fr:{label:'FR (/min)',values:[24,26,22,28,26,28],color:'#f59e0b',norm:[15,30]}};
var visits=['sep.25','oct.25','nov.25','déc.25','jan.26','Auj.'];
var activeVital='poids',chartInst=null;
function initChart(){
  var ctx=document.getElementById('vitals-chart');if(!ctx)return;
  var d=vitalData[activeVital]||vitalData.poids;
  chartInst=new Chart(ctx,{type:'line',data:{labels:visits,datasets:[{data:d.values,borderColor:d.color,backgroundColor:d.color+'18',borderWidth:2,pointRadius:3,pointBackgroundColor:d.color,pointBorderColor:'#fff',pointBorderWidth:1.5,fill:true,tension:0.3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return c.parsed.y+(activeVital==='poids'?' kg':activeVital==='temp'?' °C':activeVital==='fc'?' bpm':'');}}}},scales:{x:{grid:{display:false},ticks:{font:{size:9},color:'#94a3b8'}},y:{grid:{color:'#f1f5f9'},ticks:{font:{size:9},color:'#94a3b8'},suggestedMin:d.norm[0]*.97,suggestedMax:d.norm[1]*1.03}}}});
}
function selectVital(el,vital){
  if(activeVital===vital&&el.style.boxShadow){closeChart();return;}
  activeVital=vital;
  document.querySelectorAll('.vital-pill').forEach(function(c){c.style.boxShadow='';c.classList.remove('active-vital');});
  var panel=document.getElementById('charts-and-score');if(panel)panel.style.display='block';
  if(vital==='douleur'){el.style.boxShadow='0 0 0 2px #f97316';el.classList.add('active-vital');var cl=document.getElementById('chart-label');if(cl)cl.textContent='Douleur — historique';if(!chartInst)setTimeout(function(){initChart();updateChartForPain();},50);else updateChartForPain();return;}
  el.style.boxShadow='0 0 0 2px '+vitalData[vital].color;el.classList.add('active-vital');
  var cl=document.getElementById('chart-label');if(cl)cl.textContent=vitalData[vital].label;
  if(!chartInst){setTimeout(initChart,50);return;}
  var d=vitalData[vital];
  chartInst.data.datasets[0].data=d.values;chartInst.data.datasets[0].borderColor=d.color;chartInst.data.datasets[0].backgroundColor=d.color+'18';chartInst.data.datasets[0].pointBackgroundColor=d.color;chartInst.options.scales.y.suggestedMin=d.norm[0]*.97;chartInst.options.scales.y.suggestedMax=d.norm[1]*1.03;chartInst.update();
}
function closeChart(){var p=document.getElementById('charts-and-score');if(p)p.style.display='none';document.querySelectorAll('.vital-pill').forEach(function(c){c.style.boxShadow='';c.classList.remove('active-vital');});activeVital=null;}
function updateChartForPain(){if(!chartInst)return;chartInst.data.datasets[0].data=[0,2,0,3,0,4];chartInst.data.datasets[0].borderColor='#f97316';chartInst.data.datasets[0].backgroundColor='#f9731618';chartInst.data.datasets[0].pointBackgroundColor='#f97316';chartInst.options.scales.y.suggestedMin=0;chartInst.options.scales.y.suggestedMax=10;chartInst.update();}

/* -- Payment -- */
function setPaiement(btn,mode){
  document.querySelectorAll('[id^="pay-"]').forEach(function(b){b.style.borderColor='var(--border-medium)';b.style.background='var(--surface-card)';b.style.color='var(--text-muted)';});
  btn.style.borderColor='var(--brand-500)';btn.style.background='var(--brand-50)';btn.style.color='var(--brand-600)';
}

/* -- Modals & Drawer -- */
function openDrawer(){document.getElementById('drawer-overlay').classList.add('open');document.getElementById('drawer').classList.add('open');}
function closeDrawer(){document.getElementById('drawer-overlay').classList.remove('open');document.getElementById('drawer').classList.remove('open');}
function closeConsult(){var o=document.getElementById('modal-overlay');o.classList.add('open');}
function closeModal(){var o=document.getElementById('modal-overlay');o.classList.remove('open');}
function signDoc(){var a=document.getElementById('sign-area'),l=document.getElementById('sign-label');a.classList.add('signed');a.style.borderColor='var(--brand-500)';a.style.borderStyle='solid';a.style.background='var(--brand-50)';a.style.cursor='default';l.innerHTML='<svg width="13" height="13" fill="none" viewBox="0 0 16 16" style="margin-right:5px;"><path d="M2 8l4 4 8-8" stroke="var(--brand-600)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span style="color:var(--brand-600);font-weight:var(--weight-medium);">Signé électroniquement — Dr. Rousseau</span>';l.style.display='flex';l.style.alignItems='center';a.onmouseenter=null;a.onmouseleave=null;a.onclick=null;}
function confirmClose(){var btn=document.getElementById('modal-confirm-btn');btn.innerHTML='<svg width="12" height="12" fill="none" viewBox="0 0 16 16"><path d="M2 8l4 4 8-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg> Enregistré !';btn.style.background='#16a34a';btn.style.pointerEvents='none';setTimeout(closeModal,1200);}
function openExamensModal(){document.getElementById('examens-modal').classList.add('open');lucide.createIcons();}
function closeExamensModal(){document.getElementById('examens-modal').classList.remove('open');}
function addExamen(){
  var type=document.getElementById('new-examen-type').value;
  if(!type){showToast("Choisir un type d\u2019examen",'#f59e0b');return;}
  var detail=document.getElementById('new-examen-detail').value;
  var list=document.getElementById('examens-modal-list');
  var row=document.createElement('div');
  row.className='examen-row';
  row.style.cssText='display:flex;align-items:center;gap:var(--space-3);padding:var(--space-3);background:var(--surface-card);border:1px solid var(--border-medium);border-radius:var(--radius-md);';
  var icon=document.createElement('div');
  icon.style.cssText='width:36px;height:36px;background:var(--surface-subtle);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:18px;';
  icon.textContent='\u{1F52C}';
  var info=document.createElement('div');info.style.cssText='flex:1;min-width:0;';
  var nm=document.createElement('p');nm.style.cssText='font-size:var(--text-base);font-weight:var(--weight-medium);color:var(--text-primary);';nm.textContent=type;info.appendChild(nm);
  if(detail){var sb=document.createElement('p');sb.style.cssText='font-size:var(--text-sm);color:var(--text-subtle);';sb.textContent=detail;info.appendChild(sb);}
  var ctrl=document.createElement('div');ctrl.style.cssText='display:flex;align-items:center;gap:var(--space-2);';
  var sel=document.createElement('select');sel.style.cssText='font-size:var(--text-sm);border:1px solid var(--border-medium);border-radius:var(--radius-md);padding:3px var(--space-2);background:var(--surface-card);font-family:inherit;outline:none;cursor:pointer;';
  ['Demandé','En cours','Résultats reçus'].forEach(function(o){var opt=document.createElement('option');opt.textContent=o;sel.appendChild(opt);});
  sel.onchange=function(){showToast('Statut mis à jour','#059669');};
  var del=document.createElement('button');del.style.cssText='width:20px;height:20px;border-radius:var(--radius-sm);border:none;background:#fee2e2;cursor:pointer;display:flex;align-items:center;justify-content:center;';
  del.innerHTML='<svg width="8" height="8" fill="none" viewBox="0 0 8 8"><path d="M1 1l6 6M7 1L1 7" stroke="#dc2626" stroke-width="1.3" stroke-linecap="round"/></svg>';
  del.onclick=function(){row.remove();};
  ctrl.appendChild(sel);ctrl.appendChild(del);
  row.appendChild(icon);row.appendChild(info);row.appendChild(ctrl);
  list.appendChild(row);
  document.getElementById('new-examen-type').value='';document.getElementById('new-examen-detail').value='';
  showToast(type+' ajouté','#16a34a');
}
function openRxModal(){var o=document.getElementById('rx-modal-overlay');o.classList.add('open');}
function closeRxModal(){document.getElementById('rx-modal-overlay').classList.remove('open');}
function openVaxModal(){var o=document.getElementById('vax-modal');o.classList.add('open');}
function closeVaxModal(){document.getElementById('vax-modal').classList.remove('open');}

// Event listeners stored for cleanup
let _keydownHandler = null;
let _resizeHandler = null;
let _diagInputHandler = null;

export function init() {
  lucide.createIcons();

  // Layout
  applyLayout();
  _resizeHandler = applyLayout;
  window.addEventListener('resize', _resizeHandler);

  // Timer
  mins=0;secs=0;
  _timerInterval=setInterval(function(){
    secs++;if(secs===60){secs=0;mins++;}
  },1000);

  // Diagnostic input keyboard handler
  var diagInput = document.getElementById('diag-input');
  if(diagInput){
    _diagInputHandler = function(e){if(e.key==='Enter'){e.preventDefault();addDiagFromInput();}if(e.key==='Escape')hideDiagInput();};
    diagInput.addEventListener('keydown', _diagInputHandler);
  }

  // Escape key handler for modals
  _keydownHandler = function(e){
    if(e.key==='Escape'){
      document.querySelectorAll('.modal-overlay.open').forEach(function(m){m.classList.remove('open');});
      closeDrawer();
    }
  };
  document.addEventListener('keydown', _keydownHandler);

  // Expose functions used by onclick attributes in HTML
  window.switchConsultTab = switchConsultTab;
  window.showToast = showToast;
  window.toggleMotif = toggleMotif;
  window.toggleEditMotif = toggleEditMotif;
  window.toggleEditVitals = toggleEditVitals;
  window.setPainScore = setPainScore;
  window.cycleDiag = cycleDiag;
  window.toggleDiagNotes = toggleDiagNotes;
  window.showDiagInput = showDiagInput;
  window.hideDiagInput = hideDiagInput;
  window.addDiagFromInput = addDiagFromInput;
  window.showPlanActions = showPlanActions;
  window.hidePlanActions = hidePlanActions;
  window.editPlanLine = editPlanLine;
  window.deletePlanLine = deletePlanLine;
  window.addPlanLine = addPlanLine;
  window.addToOrdo = addToOrdo;
  window.showOrdonnance = showOrdonnance;
  window.hideOrdonnance = hideOrdonnance;
  window.showExamensP = showExamensP;
  window.hideExamensP = hideExamensP;
  window.selectVital = selectVital;
  window.closeChart = closeChart;
  window.setPaiement = setPaiement;
  window.openDrawer = openDrawer;
  window.closeDrawer = closeDrawer;
  window.closeConsult = closeConsult;
  window.closeModal = closeModal;
  window.signDoc = signDoc;
  window.confirmClose = confirmClose;
  window.openExamensModal = openExamensModal;
  window.closeExamensModal = closeExamensModal;
  window.addExamen = addExamen;
  window.openRxModal = openRxModal;
  window.closeRxModal = closeRxModal;
  window.openVaxModal = openVaxModal;
  window.closeVaxModal = closeVaxModal;
}

export function cleanup() {
  if (_keydownHandler) {
    document.removeEventListener('keydown', _keydownHandler);
    _keydownHandler = null;
  }
  if (_resizeHandler) {
    window.removeEventListener('resize', _resizeHandler);
    _resizeHandler = null;
  }
  if (_timerInterval) {
    clearInterval(_timerInterval);
    _timerInterval = null;
  }
  if (_diagInputHandler) {
    var diagInput = document.getElementById('diag-input');
    if (diagInput) diagInput.removeEventListener('keydown', _diagInputHandler);
    _diagInputHandler = null;
  }
  // Destroy chart instance if present
  if (chartInst) {
    chartInst.destroy();
    chartInst = null;
  }
}
