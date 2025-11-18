<?php
// bloomboard.php
// Compatible con PHP 8.2.29 — single-file para desplegar en Azure App Services
// Este archivo sirve una página estática + JavaScript que replica la lógica
// del componente React original pero sin React (usa DOM y localStorage).

// No hay lógica de servidor necesaria más que servir este HTML.
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>BloomBoard (PHP build)</title>
  <!-- Tailwind CDN para estilos rápidos -->
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Pequeñas animaciones para reemplazar framer-motion */
    .fade-in { animation: fadeIn .28s ease both; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(8px) } to { opacity: 1; transform: translateY(0) } }

    .float-plus { position:absolute; left:50%; transform:translateX(-50%); bottom:1.5rem; font-weight:700; color:#0f766e; text-shadow:0 2px 6px rgba(15,118,110,0.12); }
    .float-bubble { background: rgba(255,255,255,0.9); padding:0.2rem 0.5rem; border-radius:0.5rem; font-size:0.9rem; }
    .float-anim { animation: floatUp .9s forwards; }
    @keyframes floatUp { 0% { opacity:0; transform: translateY(10px) scale(.95) } 50% { opacity:1 } 100% { opacity:0; transform: translateY(-30px) scale(.95) } }

    /* modal backdrop */
    .modal-backdrop { background: rgba(0,0,0,0.4); position:fixed; inset:0; }
    .modal-center { position:fixed; inset:0; display:flex; align-items:center; justify-content:center; z-index:50; }
    .progress-inner { height:100%; background:linear-gradient(90deg,#34d399,#059669); }
    .plant-svg { display:block; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-sky-50 to-emerald-50 p-6 text-slate-800">
  <div class="max-w-5xl mx-auto">
    <header class="mb-6 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-extrabold">BloomBoard</h1>
        <p class="text-sm opacity-80">Convierte micro-hábitos en plantas que crecen. Planta, riega y observa su progreso.</p>
      </div>
      <div class="space-x-2">
        <!-- espacio para botones extra -->
      </div>
    </header>

    <main class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <section class="md:col-span-1 bg-white/70 p-4 rounded-2xl shadow">
        <h2 class="font-semibold">Plantar nuevo hábito</h2>
        <label class="block mt-3 text-xs">Título</label>
        <input id="title" class="w-full p-2 rounded mt-1 border" placeholder="Ej: 5-min estiramiento" />
        <label class="block mt-3 text-xs">Descripción (opcional)</label>
        <textarea id="desc" class="w-full p-2 rounded mt-1 border" rows="3" placeholder="Pequeña nota..."></textarea>
        <div class="mt-3 flex gap-2">
          <button id="plantBtn" class="flex-1 p-2 rounded bg-emerald-500 text-white font-semibold">Plantar</button>
          <button id="clearBtn" class="p-2 rounded bg-white border">Limpiar</button>
        </div>

        <div class="mt-6">
          <h3 class="text-sm font-medium">Ideas rápidas</h3>
          <ul id="ideas" class="mt-2 space-y-2 text-sm">
          </ul>
        </div>

        <div class="mt-6 text-xs opacity-75">
          <p><strong>Mecánica:</strong> cada riego añade <strong id="incrementPct"></strong>. Se necesitan <strong id="totalSteps"></strong> riegos para alcanzar <strong>100%</strong>.</p>
        </div>

        <div class="mt-4">
          <button id="downloadBtn" class="w-full p-2 rounded bg-indigo-600 text-white font-semibold">Descargar app móvil</button>
        </div>
      </section>

      <section class="md:col-span-2">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <select id="filter" class="p-2 rounded border bg-white/90">
              <option value="all">Todas</option>
              <option value="growing">En crecimiento</option>
              <option value="bloomed">Florecidas</option>
            </select>
            <span id="plantsCount" class="text-sm opacity-80"></span>
          </div>
          <div class="text-sm opacity-70">Última sincronización local: <span id="lastSync"></span></div>
        </div>

        <div id="list" class="grid grid-cols-1 sm:grid-cols-2 gap-4"></div>

        <div id="noPlants" class="col-span-full bg-white p-6 rounded-2xl text-center opacity-80 hidden">No hay plantas en esta vista — planta una en el panel izquierdo.</div>
      </section>
    </main>

    <footer class="mt-8 text-center text-sm opacity-80">
      <p>BloomBoard — Demo local. ¿Quieres que conecte a un backend o que genere un build para Netlify/Azure?</p>
    </footer>
  </div>

  <!-- Modal placeholder -->
  <div id="modalRoot"></div>

  <script>
  // Javascript que replica la lógica del componente React original
  (function(){
    const TOTAL_STEPS = 21;
    const INCREMENT = 100 / TOTAL_STEPS; // ≈ 4.7619

    // Ideas predefinidas
    const defaultIdeas = [
      { id: 1, text: '5-min meditación antes del desayuno' },
      { id: 2, text: 'Enviar 1 email de agradecimiento semanal' },
      { id: 3, text: 'Leer 10 páginas cada noche' }
    ];

    // Elementos del DOM
    const titleIn = document.getElementById('title');
    const descIn = document.getElementById('desc');
    const plantBtn = document.getElementById('plantBtn');
    const clearBtn = document.getElementById('clearBtn');
    const ideasEl = document.getElementById('ideas');
    const incrementPct = document.getElementById('incrementPct');
    const totalStepsEl = document.getElementById('totalSteps');
    const downloadBtn = document.getElementById('downloadBtn');
    const listEl = document.getElementById('list');
    const plantsCount = document.getElementById('plantsCount');
    const filterEl = document.getElementById('filter');
    const lastSyncEl = document.getElementById('lastSync');
    const noPlantsEl = document.getElementById('noPlants');
    const modalRoot = document.getElementById('modalRoot');

    incrementPct.textContent = Number(INCREMENT).toFixed(1) + '%';
    totalStepsEl.textContent = TOTAL_STEPS;

    // floats: pequeños indicadores temporales por planta
    let floats = [];

    // Cargar hábitos desde localStorage o hash
    function loadHabits(){
      let stored = [];
      try{
        const raw = localStorage.getItem('bb_habits');
        stored = raw ? JSON.parse(raw) : [];
      }catch(e){ stored = []; }

      // si hay hash #bb=base64 entonces usarlo (compatible con React impl)
      try{
        if(location.hash && location.hash.startsWith('#bb=')){
          const encoded = location.hash.replace('#bb=', '');
          // decodifica base64 sosteniendo compatibilidad de atob con UTF-8
          const decoded = decodeURIComponent(escape(atob(encoded)));
          const parsed = JSON.parse(decoded);
          if(Array.isArray(parsed)) return parsed;
        }
      }catch(e){ /* ignore */ }

      return stored || [];
    }

    let habits = loadHabits();

    // Guardar
    function saveHabits(){
      try{ localStorage.setItem('bb_habits', JSON.stringify(habits)); }catch(e){}
      lastSyncEl.textContent = new Date().toLocaleString();
    }

    // Render ideas
    function renderIdeas(){
      ideasEl.innerHTML = '';
      defaultIdeas.forEach(i => {
        const li = document.createElement('li');
        li.className = 'bg-slate-50 p-2 rounded';
        li.textContent = i.text;
        ideasEl.appendChild(li);
      });
    }

    // Create habit
    function createHabit(){
      const title = titleIn.value.trim();
      const desc = descIn.value.trim();
      if(!title) return;
      const newHabit = {
        id: Date.now(),
        title, desc,
        createdAt: new Date().toISOString(),
        score: 0,
        lastWatered: null,
        daysTracked: []
      };
      habits.unshift(newHabit);
      saveHabits();
      titleIn.value = ''; descIn.value = '';
      render();
    }

    // Push float notification for a habit id
    function pushFloat(id, label = `+${Number(INCREMENT).toFixed(1)}`){
      const key = Date.now() + Math.random();
      floats.push({ id, key, label });
      render();
      setTimeout(() => {
        floats = floats.filter(f => f.key !== key);
        render();
      }, 900);
    }

    // Water habit
    function waterHabit(id){
      const now = new Date().toISOString();
      habits = habits.map(h => {
        if(h.id !== id) return h;
        const raw = (h.score || 0) + INCREMENT;
        const capped = Math.min(100, raw);
        const rounded = Math.round(capped * 10) / 10;
        const newDays = (h.daysTracked||[]).concat([now]);
        return {...h, score: rounded, lastWatered: now, daysTracked: newDays };
      });
      saveHabits();
      pushFloat(id, `+${Number(INCREMENT).toFixed(1)}`);
    }

    
    // Water habit - single, robust implementation
    function waterHabit(id){
      const now = new Date().toISOString();
      habits = habits.map(h => {
        if(h.id !== id) return h;
        const raw = (h.score || 0) + INCREMENT;
        const capped = Math.min(100, raw);
        const rounded = Math.round(capped * 10) / 10;
        const newDays = (h.daysTracked||[]).concat([now]);
        return {...h, score: rounded, lastWatered: now, daysTracked: newDays };
      });
      saveHabits();
      pushFloat(id, `+${Number(INCREMENT).toFixed(1)}`);
      // ensure UI updates immediately
      render();
    }


    // Remove habit
    function removeHabit(id){
      habits = habits.filter(h => h.id !== id);
      saveHabits();
      render();
    }

    // Helpers for rendering plant visual (simple SVG)
    function createPlantSVG(progress, size=72, lastWatered=null){
      const viewW = 84, viewH = 96;
      const stage = progress >= 100 ? 4 : progress >= 70 ? 3 : progress >= 40 ? 2 : progress >= 10 ? 1 : 0;
      const wrapper = document.createElement('div');
      wrapper.style.width = size + 'px';
      wrapper.style.height = Math.round(size * (viewH/viewW)) + 'px';
      wrapper.className = 'flex items-center justify-center select-none';

      const ns = 'http://www.w3.org/2000/svg';
      const svg = document.createElementNS(ns, 'svg');
      svg.setAttribute('width', size);
      svg.setAttribute('height', Math.round(size * (viewH/viewW)));
      svg.setAttribute('viewBox', `0 0 ${viewW} ${viewH}`);
      svg.classList.add('plant-svg');

      // pot
      const gPot = document.createElementNS(ns, 'g');
      gPot.setAttribute('transform','translate(0,52)');
      const rect = document.createElementNS(ns,'rect'); rect.setAttribute('x',12); rect.setAttribute('y',18); rect.setAttribute('width',60); rect.setAttribute('height',18); rect.setAttribute('rx',4); rect.setAttribute('fill','#6b4226');
      gPot.appendChild(rect);
      const topShade = document.createElementNS(ns,'rect'); topShade.setAttribute('x',8); topShade.setAttribute('y',8); topShade.setAttribute('width',68); topShade.setAttribute('height',12); topShade.setAttribute('rx',3); topShade.setAttribute('fill','#52321f'); topShade.setAttribute('opacity','0.08');
      gPot.appendChild(topShade);
      svg.appendChild(gPot);

      if(stage === 0){
        const g = document.createElementNS(ns,'g'); g.setAttribute('transform','translate(42,52)');
        const ell = document.createElementNS(ns,'ellipse'); ell.setAttribute('cx',0); ell.setAttribute('cy',-2); ell.setAttribute('rx',6); ell.setAttribute('ry',4); ell.setAttribute('fill','#8b5e34');
        const c = document.createElementNS(ns,'circle'); c.setAttribute('cx',3); c.setAttribute('cy',-6); c.setAttribute('r',1.8); c.setAttribute('fill','#f7d9b3');
        g.appendChild(ell); g.appendChild(c); svg.appendChild(g);
      }

      if(stage >= 1){
        const g = document.createElementNS(ns,'g'); g.setAttribute('transform','translate(42,46)');
        const stalk = document.createElementNS(ns,'rect'); stalk.setAttribute('x',-1.5); stalk.setAttribute('y',-26); stalk.setAttribute('width',3); stalk.setAttribute('height',26); stalk.setAttribute('rx',2); stalk.setAttribute('fill','#6b4226');
        g.appendChild(stalk);
        if(stage >= 1){ const p = document.createElementNS(ns,'path'); p.setAttribute('d','M0 -14 C -10 -10, -14 -2, -6 2 C -2 6, 2 4, 6 2'); p.setAttribute('fill','#4caf50'); p.setAttribute('transform','translate(-6,0)'); g.appendChild(p); }
        if(stage >= 2){ const p2 = document.createElementNS(ns,'path'); p2.setAttribute('d','M0 -14 C 10 -10, 14 -2, 6 2 C 2 6, -2 4, -6 2'); p2.setAttribute('fill','#43a047'); p2.setAttribute('transform','translate(6,0)'); g.appendChild(p2); }
        if(stage >= 3){ const a = document.createElementNS(ns,'path'); a.setAttribute('d','M0 -28 C -14 -22, -18 -10, -8 -6 C -3 -4, 1 -6, 8 -6'); a.setAttribute('fill','#2e7d32'); g.appendChild(a); const b = document.createElementNS(ns,'path'); b.setAttribute('d','M0 -28 C 14 -22, 18 -10, 8 -6 C 3 -4, -1 -6, -8 -6'); b.setAttribute('fill','#2e7d32'); g.appendChild(b); }
        if(stage >= 4){ const gfl = document.createElementNS(ns,'g'); gfl.setAttribute('transform','translate(0,-36)'); const circ = document.createElementNS(ns,'circle'); circ.setAttribute('cx',0); circ.setAttribute('cy',0); circ.setAttribute('r',4.2); circ.setAttribute('fill','#ffca28'); const pet = document.createElementNS(ns,'path'); pet.setAttribute('d','M0 -6 C -4 -2, -4 2, 0 6 C 4 2, 4 -2, 0 -6 Z'); pet.setAttribute('fill','#ff5252'); gfl.appendChild(circ); gfl.appendChild(pet); g.appendChild(gfl); }
        svg.appendChild(g);
      }

      // watered today indicator
      if(lastWatered){
        try{
          const lw = new Date(lastWatered).toISOString().slice(0,10);
          const today = new Date().toISOString().slice(0,10);
          if(lw === today){
            const gw = document.createElementNS(ns,'g'); gw.setAttribute('transform','translate(60,16)'); const dropp = document.createElementNS(ns,'path'); dropp.setAttribute('d','M4 0 C 8 6, 8 10, 4 14 C 0 10, 0 6, 4 0 Z'); dropp.setAttribute('fill','#67c5ff'); gw.appendChild(dropp); svg.appendChild(gw);
          }
        }catch(e){}
      }

      wrapper.appendChild(svg);
      return wrapper;
    }

    // Render progress bar
    function createProgressBar(value){
      const outer = document.createElement('div'); outer.className = 'h-2 bg-slate-100 rounded-full mt-3 overflow-hidden';
      const inner = document.createElement('div'); inner.className = 'progress-inner';
      inner.style.width = Math.max(0, Math.min(100, value)) + '%';
      outer.appendChild(inner);
      return outer;
    }

    // Render single habit card
    function createHabitCard(h){
      const art = document.createElement('article'); art.className = 'bg-white p-4 rounded-2xl shadow fade-in';
      const flex = document.createElement('div'); flex.className = 'flex items-start gap-3';

      const left = document.createElement('div'); left.className = 'relative flex-none'; left.style.width = '72px';
      const plant = createPlantSVG(h.score,72,h.lastWatered);
      left.appendChild(plant);

      // floats for this habit
      floats.filter(f=>f.id===h.id).forEach(f=>{
        const fp = document.createElement('div'); fp.className = 'float-plus float-anim'; fp.style.pointerEvents='none'; const span = document.createElement('span'); span.className='float-bubble'; span.textContent = f.label; fp.appendChild(span); left.appendChild(fp);
      });

      const main = document.createElement('div'); main.className = 'flex-1';
      const title = document.createElement('h4'); title.className = 'font-semibold'; title.textContent = h.title;
      const pdesc = document.createElement('p'); pdesc.className = 'text-xs opacity-70'; pdesc.textContent = h.desc || '';

      const actions = document.createElement('div'); actions.className = 'mt-2 flex items-center gap-2';
      const btnWater = document.createElement('button'); btnWater.className='px-3 py-1 rounded bg-sky-100'; btnWater.textContent='Regar'; btnWater.onclick = ()=>{ waterHabit(h.id); };
      const btnRemove = document.createElement('button'); btnRemove.className='px-3 py-1 rounded bg-rose-100'; btnRemove.textContent='Eliminar'; btnRemove.onclick = ()=>{ if(confirm('Eliminar hábito?')) removeHabit(h.id); };
      const btnPreview = document.createElement('button'); btnPreview.className='px-3 py-1 rounded bg-amber-100'; btnPreview.textContent='Vista previa'; btnPreview.onclick = ()=>{ openPreview(h.id); };
      const meta = document.createElement('span'); meta.className='ml-auto text-xs opacity-60'; meta.textContent = Number(h.score).toFixed(1) + '% • ' + ((h.daysTracked||[]).length) + ' acciones';

      actions.appendChild(btnWater); actions.appendChild(btnRemove); actions.appendChild(btnPreview); actions.appendChild(meta);

      main.appendChild(title); main.appendChild(pdesc); main.appendChild(actions);

      flex.appendChild(left); flex.appendChild(main);
      art.appendChild(flex);
      art.appendChild(createProgressBar(h.score));
      return art;
    }

    // Render list
    function render(){
      // apply filter
      const filter = filterEl.value;
      const filtered = habits.filter(h => filter === 'all' ? true : filter === 'growing' ? (h.score||0) < 100 : (h.score||0)

 >= 100);

      listEl.innerHTML = '';
      if(filtered.length === 0){ noPlantsEl.classList.remove('hidden'); } else { noPlantsEl.classList.add('hidden'); }
      filtered.forEach(h => {
        listEl.appendChild(createHabitCard(h));
      });
      plantsCount.textContent = filtered.length + ' plantas';
    }

    // Preview modal
    function openPreview(id){
      const habit = habits.find(h=>h.id===id); if(!habit) return;
      modalRoot.innerHTML = '';
      const wrap = document.createElement('div'); wrap.className='modal-center';
      const backdrop = document.createElement('div'); backdrop.className='modal-backdrop'; backdrop.onclick = closeModal;
      const modal = document.createElement('div'); modal.className='relative z-10 max-w-3xl w-full mx-4 bg-white rounded-2xl shadow-lg p-6';

      const container = document.createElement('div'); container.className='flex items-start gap-6';
      const left = document.createElement('div'); left.className='flex-none relative'; left.style.width='220px'; left.appendChild(createPlantSVG(habit.score,220,habit.lastWatered));

      // floats
      floats.filter(f=>f.id===habit.id).forEach(f=>{ const fp = document.createElement('div'); fp.className='float-plus float-anim'; const span = document.createElement('span'); span.className='float-bubble'; span.textContent=f.label; fp.appendChild(span); left.appendChild(fp); });

      const right = document.createElement('div'); right.className='flex-1';
      const h3 = document.createElement('h3'); h3.className='text-xl font-bold'; h3.textContent = habit.title;
      const p = document.createElement('p'); p.className='mt-2 text-sm text-slate-700'; p.textContent = habit.desc || '— sin descripción —';

      const btns = document.createElement('div'); btns.className='mt-4 flex gap-3 items-center';
      const btnWater = document.createElement('button'); btnWater.className='px-4 py-2 rounded bg-emerald-500 text-white font-semibold shadow-md'; btnWater.textContent='Regar';
      btnWater.onclick = ()=>{ btnWater.disabled=true; waterHabit(habit.id); setTimeout(()=>btnWater.disabled=false,450); };
      const btnClose = document.createElement('button'); btnClose.className='px-4 py-2 rounded bg-white border'; btnClose.textContent='Cerrar'; btnClose.onclick = closeModal;
      const meta = document.createElement('div'); meta.className='ml-auto text-sm opacity-70'; meta.textContent = Number(habit.score).toFixed(1) + '% • ' + ((habit.daysTracked||[]).length) + ' acciones';
      btns.appendChild(btnWater); btns.appendChild(btnClose); btns.appendChild(meta);

      const created = document.createElement('div'); created.className='mt-4 text-xs text-slate-500'; created.innerHTML = '<div>Creado: ' + new Date(habit.createdAt).toLocaleString() + '</div><div>Último riego: ' + (habit.lastWatered ? new Date(habit.lastWatered).toLocaleString() : 'Nunca') + '</div>';

      right.appendChild(h3); right.appendChild(p); right.appendChild(btns); right.appendChild(created);
      container.appendChild(left); container.appendChild(right);
      modal.appendChild(container);
      wrap.appendChild(backdrop); wrap.appendChild(modal);
      modalRoot.appendChild(wrap);
    }

    function closeModal(){ modalRoot.innerHTML = ''; }

    // Initialize UI
    renderIdeas();
    render();
    saveHabits();

    // Events
    plantBtn.addEventListener('click', createHabit);
    clearBtn.addEventListener('click', ()=>{ titleIn.value=''; descIn.value=''; });
    filterEl.addEventListener('change', render);
    downloadBtn.addEventListener('click', ()=> alert('Descarga de app móvil no configurada — reemplaza este handler con el enlace o lógica de descarga que desees.'));

    // Expose some functions to the global scope for inline event handlers created earlier
    window.waterHabit = function(id){ waterHabit(id); render(); };
    window.removeHabit = function(id){ removeHabit(id); render(); };
    window.openPreview = function(id){ openPreview(id); };

    // Keep UI in sync after state changes
    const originalWaterHabit = waterHabit;
    // patch water to re-render and save
    function waterHabit(id){ originalWaterHabit(id); render(); }

    // small poll to update floats/animation layers (not strictly necessary)
    setInterval(()=>{
      // re-render minimal parts that depend on floats (quick hack)
      render();
    }, 1200);

    // expose habits for debugging
    window.__BB = { getHabits: ()=>habits, setHabits: (h)=>{ habits = h; saveHabits(); render(); } };

  })();
  </script>
</body>
</html>
