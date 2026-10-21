(() => {
    const engines = new Map();
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const token = () => document.querySelector('input[name="_token"]')?.value || '';
    const csrf = () => `<input type="hidden" name="_token" value="${escape(token())}">`;
    const configs = {
        users:{type:'users',size:10,columns:5,keys:['name','email','affiliation','role'],search:'[name="search"]'},
        'bank-questions':{type:'questions',size:20,columns:6,keys:['question','answer','subjects'],search:'#questionSearch'},
        'premade-quizzes':{type:'quizzes',size:10,columns:6,keys:['title','description','type'],search:'#premadeSearchForm input'},
        'bank-enrollments':{type:'enrollments',size:10,columns:6,keys:['name','email','licensure','country','status'],search:'#enrollmentSearchForm [name="search"]'}
    };
    function rowHtml(id,r,meta) {
        const e=escape;
if(id==='users') return `<tr><td style="font-weight:600">${e(r.name)}</td><td>${e(r.email)}</td><td>${e(r.affiliation)}</td><td>${meta.canEditRoles ? `<form action="${e(r.roleUrl)}" method="POST" class="role-form ajax-role-form">${csrf()}<select name="role" class="role-select" data-original="${e(r.role)}">${['student','instructor','encoder','staff','assistant_admin','admin'].map(role=>`<option value="${role}" ${role===r.role?'selected':''}>${role==='assistant_admin'?'Asst. Admin':role[0].toUpperCase()+role.slice(1)}</option>`).join('')}</select></form>` : e(r.role==='assistant_admin'?'Asst. Admin':r.role)}</td><td><div class="actions"><form action="${e(r.toggleUrl)}" method="POST">${csrf()}<button class="btn-ghost" style="color:${r.active?'#dc2626':'#087f6d'}">${r.active?'Disable':'Activate'}</button></form></div></td></tr>`;
        if(id==='bank-questions') return `<tr data-question-id="${Number(r.id)}"><td class="tb-question"><span class="tb-question-text">${e(r.question)}</span></td><td><div class="tb-subject-list">${r.subjects.map(s=>`<span class="tb-tag" title="${e(s.title)}">${e(s.code)}</span>`).join('')}</div></td><td>${e(r.answer)}</td><td>${e(r.points)}</td><td><span class="tb-tag">${r.status==='active'?'Active':'Archived'}</span></td><td><div class="tb-actions"><button type="button" class="btn-ghost" onclick="openQuestionPreview(${Number(r.id)})">Preview</button><button type="button" class="btn-ghost" onclick="openQuestionEdit(${Number(r.id)})">Edit</button><form method="POST" action="${e(r.statusUrl)}">${csrf()}<button class="btn-ghost">${r.status==='active'?'Archive':'Restore'}</button></form></div></td></tr>`;
        if(id==='premade-quizzes') return `<tr><td><strong>${e(r.title)}</strong>${r.description?`<p>${e(r.description)}</p>`:''}<small>Randomized questions</small></td><td>${e(({warm_up:'Warm-up',mastery:'Mastery',simulation:'Simulation'})[r.type]||'Premade')}</td><td>${Number(r.items)}</td><td>${r.minutes?`${Number(r.minutes)} minutes`:'Untimed'}</td><td>${r.status==='active'?'Available to learners':'Inactive'}</td><td><div class="tb-premade-actions"><button type="button" class="btn-ghost" onclick="openModal('editQuizModal${Number(r.id)}')">Edit</button><form method="POST" action="${e(r.deleteUrl)}">${csrf()}<input type="hidden" name="_method" value="DELETE"><button class="btn-ghost" style="color:#dc2626">Delete</button></form></div></td></tr>`;
        return `<tr><td><strong>${e(r.name)}</strong><br><small>${e(r.email)}</small></td><td>${e(r.licensure)}</td><td>${e(r.country)}</td><td>${e(r.status)}</td><td>${e(r.enrolled)}</td><td>${e(r.expires)}</td></tr>`;
    }
    class ClientTable {
        constructor(id,root,config){
            this.id=id;this.root=root;this.config=config;this.rows=[];this.page=0;this.ready=false;this.size=config.size;this.query=document.querySelector(config.search)?.value||'';
            this.status=root.querySelector('#questionStatus')?.value||'';
            const size=document.querySelector('#enrollmentSearchForm [name="per_page"]');if(id==='bank-enrollments'&&size)this.size=Number(size.value);
            this.footer=document.createElement('div');this.footer.className='client-table-footer';this.footer.setAttribute('aria-live','polite');
            root.querySelector('table').parentElement.after(this.footer);
            const refresh=document.createElement('button');refresh.type='button';refresh.className='btn-ghost';refresh.textContent='Refresh';refresh.onclick=()=>this.load();
            const tools=id==='premade-quizzes'?document.getElementById('premadeSearchForm'):id==='bank-enrollments'?document.getElementById('enrollmentSearchForm'):root.querySelector('.toolbar,.tb-tools');
            tools?.append(refresh);
            this.load();
        }
        async load(){
            this.controller?.abort();const controller=new AbortController();this.controller=controller;
            this.root.setAttribute('aria-busy','true');
            try{
                const url=new URL(location.href);url.search='';url.searchParams.set('table_data',this.config.type);
                const response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',signal:controller.signal});
                if(!response.ok)throw new Error('Unable to refresh this list. Please try again.');
                const data=await response.json();if(controller.signal.aborted)return;
                this.meta=data;this.rows=data.rows.map(row=>({...row,_search:this.config.keys.map(key=>typeof row[key]==='object'?JSON.stringify(row[key]):row[key]||'').join(' ').toLocaleLowerCase()}));
                if(this.id==='premade-quizzes'){
                    this.root.querySelectorAll('.admin-modal').forEach(modal=>modal.remove());
                    const modals=document.createElement('div');modals.innerHTML=this.rows.map(row=>row.editHtml).join('');this.root.append(modals);
                }
                if(this.id==='bank-questions'){
                    const payload=this.root.querySelector('[data-question-payload]');if(payload)payload.textContent='{}';
                    this.root.querySelector('[data-question-total]').textContent=this.rows.length;
                }
                if(this.id==='premade-quizzes')this.root.querySelector('[data-premade-total]').textContent=this.rows.length;
                if(this.id==='users'){
                    const metrics=this.root.querySelectorAll('.metric-value');const active=this.rows.filter(row=>row.active).length;
                    [this.rows.length,active,this.rows.length-active].forEach((value,i)=>{if(metrics[i])metrics[i].textContent=value.toLocaleString();});
                }
                this.ready=true;this.render();
            }catch(error){if(error.name!=='AbortError')showAdminToast(error.message,'error');}
            finally{if(!controller.signal.aborted)this.root.removeAttribute('aria-busy');}
        }
        render(){
            if(!this.ready)return;
            let rows=this.rows.filter(row=>row._search.includes(this.query.trim().toLocaleLowerCase())&&(!this.status||row.status===this.status));
            if(this.sort)rows=rows.slice().sort((a,b)=>(typeof a[this.sort]==='number'?a[this.sort]-b[this.sort]:String(a[this.sort]??'').localeCompare(String(b[this.sort]??''),undefined,{numeric:true,sensitivity:'base'}))*this.direction);
            const pages=Math.max(1,Math.ceil(rows.length/this.size));this.page=Math.max(0,Math.min(this.page,pages-1));
            this.root.querySelector('tbody').innerHTML=rows.slice(this.page*this.size,(this.page+1)*this.size).map(row=>rowHtml(this.id,row,this.meta)).join('')||`<tr><td colspan="${this.config.columns}">No matching records.</td></tr>`;
            this.root.querySelectorAll('.tb-pagination,.enrollment-table-footer,.pagination').forEach(el=>{el.hidden=true;el.style.display='none';});
            const count=this.id==='bank-questions'?this.root.querySelector('.tb-tools>small'):null;if(count)count.textContent=`${rows.length} matching questions`;
            this.footer.innerHTML=`<span>Showing ${rows.length?this.page*this.size+1:0} to ${Math.min((this.page+1)*this.size,rows.length)} of ${rows.length} entries · Page ${this.page+1} of ${pages}</span><div><button type="button" class="btn-ghost" data-client-page="${this.page-1}" ${this.page===0?'disabled':''}>Previous</button><button type="button" class="btn-ghost" data-client-page="${this.page+1}" ${this.page+1===pages?'disabled':''}>Next</button></div>`;
            document.dispatchEvent(new CustomEvent('admin-table:updated',{detail:{tableId:this.id,element:this.root}}));
        }
    }
    function engineFor(target){
        const id=target.closest('[data-ajax-table]')?.dataset.ajaxTable;
        if(id&&engines.has(id))return engines.get(id);
        if(target.closest('#premadeSearchForm'))return engines.get('premade-quizzes');
        if(target.closest('#enrollmentSearchForm'))return engines.get('bank-enrollments');
    }
    const filter=event=>{
        const engine=engineFor(event.target);if(!engine)return;
        const target=event.target;
        if(!target.matches('input[type="search"],select[name="per_page"],#questionStatus'))return;
        event.stopImmediatePropagation();
        if(target.name==='per_page')engine.size=Number(target.value);else if(target.id==='questionStatus')engine.status=target.value;else engine.query=target.value;
        engine.page=0;engine.render();
    };
    document.addEventListener('input',filter,true);document.addEventListener('change',filter,true);
    document.addEventListener('submit',event=>{
        const engine=engineFor(event.target);if(engine&&event.target.method.toUpperCase()==='GET'){
            event.preventDefault();event.stopImmediatePropagation();engine.page=0;engine.query=event.target.querySelector('input[type="search"]')?.value||'';engine.render();
        }
    },true);
    document.addEventListener('click',event=>{
        const engine=engineFor(event.target);if(!engine)return;
        const page=event.target.closest('[data-client-page]');
        const sort=event.target.closest('th');
        const clear=event.target.closest('.toolbar a,.tb-filters a');
        if(page){event.preventDefault();event.stopImmediatePropagation();engine.page=Number(page.dataset.clientPage);engine.render();}
        else if(sort){
            event.preventDefault();event.stopImmediatePropagation();
            const index=[...sort.parentElement.children].indexOf(sort);
            const keys=({users:['name','email','affiliation','role'], 'bank-questions':['question',null,'answer','points','status'], 'premade-quizzes':['title','type','items','minutes','status'],'bank-enrollments':['name','licensure','country','status','enrolled','expires']})[engine.id];
            if(keys[index]){engine.direction=engine.sort===keys[index]?-engine.direction:1;engine.sort=keys[index];engine.render();}
        }else if(clear){event.preventDefault();event.stopImmediatePropagation();engine.query='';engine.status='';const input=document.querySelector(engine.config.search);if(input)input.value='';const status=engine.root.querySelector('#questionStatus');if(status)status.value='';engine.page=0;engine.render();}
    },true);
    document.addEventListener('DOMContentLoaded',()=>{
        const style=document.createElement('style');style.textContent='.client-table-footer{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;padding:18px 0;font-size:.8rem}.client-table-footer>div{display:flex;gap:8px}[data-ajax-table][aria-busy="true"] .client-table-footer{opacity:.6}';document.head.append(style);
        for(const [id,config]of Object.entries(configs)){const root=document.querySelector(`[data-ajax-table="${id}"]`);if(root)engines.set(id,new ClientTable(id,root,config));}
        const serverLoader=window.loadAdminTable;
        window.loadAdminTable=(id,url,push)=>engines.has(id)?engines.get(id).load():serverLoader(id,url,push);
    });
})();
