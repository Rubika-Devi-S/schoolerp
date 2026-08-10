<?php
declare(strict_types=1);

$pageTitle = 'Fees Settings';
$pageKey = 'fee_settings';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['fee_csrf_token']) || !is_string($_SESSION['fee_csrf_token'])) {
    $_SESSION['fee_csrf_token'] = bin2hex(random_bytes(32));
}
$feeCsrf = $_SESSION['fee_csrf_token'];
?>
<style>
.fst-page{display:grid;gap:16px}
.fst-page .page-title{font-size:28px;line-height:1.1}
.fst-page .page-subtitle{margin-top:4px}
.fst-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.fst-stat{border-radius:14px;min-height:105px;padding:17px 19px;color:#fff;display:flex;align-items:center;gap:13px;position:relative;overflow:hidden}
.fst-stat::after{content:"";position:absolute;width:105px;height:105px;border-radius:50%;right:-35px;top:-38px;background:rgba(255,255,255,.08)}
.fst-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.fst-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fst-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fst-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.fst-stat-icon{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;background:rgba(255,255,255,.16)}
.fst-stat-icon svg{width:22px;height:22px}
.fst-stat small{display:block;font-size:10px;font-weight:700;margin-bottom:5px}
.fst-stat strong{display:block;font-size:23px;line-height:1}
.fst-card{border-radius:14px;overflow:hidden}
.fst-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.fst-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(250px,1.4fr) minmax(170px,.6fr) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fst-table-wrap{overflow:auto}
.fst-table{min-width:1160px}
.fst-table th{font-size:10px}
.fst-table td{font-size:11px;vertical-align:middle}
.fst-actions{display:flex;gap:5px;flex-wrap:wrap}
.fst-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fst-action.danger{color:#dc2626}
.fst-action svg{width:13px;height:13px}
.fst-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800}
.fst-badge.enabled,.fst-badge.mandatory{color:#16834f;background:#e8f8ef}
.fst-badge.disabled{color:#dc2626;background:#fff0f1}
.fst-badge.optional{color:#7c3aed;background:#f3e8ff}
.fst-badge.structure{color:#1d4ed8;background:#e8f0ff}
.fst-badge.extra{color:#b45309;background:#fff4db}
.fst-badge.both{color:#6d28d9;background:#f1e9ff}
.fst-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fst-message{display:none}.fst-message.show{display:block}
.fst-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fst-form-grid .full{grid-column:1/-1}
#feeTypeModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#feeTypeModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#feeTypeModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#feeTypeModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:900px){.fst-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fst-stats,.fst-filter,.fst-form-grid{grid-template-columns:1fr}.fst-form-grid .full{grid-column:auto}.fst-card-head{align-items:flex-start;flex-direction:column}}
</style>

<div class="fst-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Fees Settings</h1>
            <p class="page-subtitle">Manage dynamic fee types used by Fee Structures.</p>
        </div>
        <div class="page-actions">
            <button id="addFeeTypeBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Fee Type</button>
            <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
        </div>
    </div>

    <div id="feeTypeMessage" class="alert fst-message"></div>

    <section class="fst-stats">
        <article class="fst-stat purple"><span class="fst-stat-icon"><i data-lucide="list-tree"></i></span><div><small>Total Fee Types</small><strong id="statTotal">0</strong></div></article>
        <article class="fst-stat green"><span class="fst-stat-icon"><i data-lucide="circle-check"></i></span><div><small>Enabled</small><strong id="statEnabled">0</strong></div></article>
        <article class="fst-stat orange"><span class="fst-stat-icon"><i data-lucide="circle-pause"></i></span><div><small>Disabled</small><strong id="statDisabled">0</strong></div></article>
        <article class="fst-stat blue"><span class="fst-stat-icon"><i data-lucide="badge-check"></i></span><div><small>Mandatory</small><strong id="statMandatory">0</strong></div></article>
    </section>

    <section class="ui-card fst-card">
        <div class="fst-card-head"><strong>Fee Type Management</strong><small id="recordInfo" class="text-muted">Loading...</small></div>
        <div class="fst-filter">
            <input id="search" class="form-control" placeholder="Search fee type, code or description...">
            <select id="statusFilter" class="form-select">
                <option value="all">All Statuses</option>
                <option value="enabled">Enabled</option>
                <option value="disabled">Disabled</option>
            </select>
            <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
        </div>
        <div class="fst-table-wrap">
            <table class="data-table fst-table">
                <thead><tr><th>Order</th><th>Fee Type</th><th>Code</th><th>Frequency</th><th>Requirement</th><th>Available For</th><th>Status</th><th>Used In Structures</th><th>Actions</th></tr></thead>
                <tbody id="feeTypeBody"><tr><td colspan="9" class="fst-empty">Loading...</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="feeTypeModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content">
<form id="feeTypeForm" novalidate>
    <div class="modal-header">
        <div><h5 id="feeTypeModalTitle" class="modal-title">Add Fee Type</h5><small class="text-muted">Enabled fee types appear automatically according to the selected Use In option.</small></div>
        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">
        <input id="feeTypeId" type="hidden">
        <div class="fst-form-grid">
            <div><label class="form-label">Fee Type Name *</label><input id="feeTypeName" class="form-control" maxlength="120" required></div>
            <div><label class="form-label">Fee Type Code *</label><input id="feeTypeCode" class="form-control" maxlength="50" placeholder="TUITION_FEE" required></div>
            <div><label class="form-label">Display Order *</label><input id="displayOrder" class="form-control" type="number" min="0" max="9999" value="10" required></div>
            <div><label class="form-label">Requirement *</label><select id="mandatory" class="form-select"><option value="1">Mandatory</option><option value="0">Optional</option></select></div>
            <div><label class="form-label">Use In *</label>
                <select id="usageScope" class="form-select" required>
                    <option value="structure">Fee Structure Only</option>
                    <option value="extra">Extra Fees Only</option>
                    <option value="both">Fee Structure &amp; Extra Fees</option>
                </select>
            </div>
            <div><label class="form-label">Default Frequency *</label>
                <select id="frequency" class="form-select">
                    <option value="one_time">One Time</option>
                    <option value="monthly">Monthly</option>
                    <option value="term">Term-wise</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="half_yearly">Half-Yearly</option>
                    <option value="yearly">Yearly</option>
                    <option value="annual">Annual</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div><label class="form-label">Occurrences per Academic Year *</label><input id="occurrenceCount" class="form-control" type="number" min="1" max="36" value="1" required></div>
            <div><label class="form-label">Status *</label><select id="enabled" class="form-select"><option value="1">Enabled</option><option value="0">Disabled</option></select></div>
            <div class="full"><label class="form-label">Description <span class="text-muted">(Optional)</span></label><textarea id="description" class="form-control" rows="3" maxlength="500"></textarea></div>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
        <button id="saveFeeTypeBtn" class="btn-ui btn-primary-ui" type="submit"><i data-lucide="save"></i> Save Fee Type</button>
    </div>
</form>
</div>
</div>
</div>

<script>
(function(){
'use strict';
const apiUrl=new URL('../api/fee-settings.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let records=[];
let searchTimer=null;
const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const modal=()=>bootstrap.Modal.getOrCreateInstance($('feeTypeModal'));

async function request(action,data={},method='GET'){
    let response;
    if(method==='GET'){
        const url=new URL(apiUrl);
        url.searchParams.set('action',action);
        Object.entries(data).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value));});
        response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin',cache:'no-store'});
    }else{
        response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',cache:'no-store',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
    }
    const text=await response.text();
    let result;
    try{result=JSON.parse(text)}catch{throw new Error(`Fees Settings API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid response.'}`)}
    if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
    if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
    return result;
}
function message(text,success=false){
    const box=$('feeTypeMessage');
    box.className='alert fst-message show '+(success?'alert-success':'alert-danger');
    box.textContent=text;
    clearTimeout(box._timer);
    box._timer=setTimeout(()=>box.className='alert fst-message',6500);
}
function statusBadge(enabled){return `<span class="fst-badge ${Number(enabled)===1?'enabled':'disabled'}">${Number(enabled)===1?'Enabled':'Disabled'}</span>`}
function requirementBadge(mandatory){return `<span class="fst-badge ${Number(mandatory)===1?'mandatory':'optional'}">${Number(mandatory)===1?'Mandatory':'Optional'}</span>`}
function frequencyLabel(value){return ({one_time:'One Time',monthly:'Monthly',term:'Term-wise',quarterly:'Quarterly',half_yearly:'Half-Yearly',yearly:'Yearly',annual:'Annual',custom:'Custom'})[value]||value}
function usageLabel(value){return ({structure:'Fee Structure',extra:'Extra Fees',both:'Both'})[value]||'Fee Structure'}
function usageBadge(value){const scope=['structure','extra','both'].includes(value)?value:'structure';return `<span class="fst-badge ${scope}">${esc(usageLabel(scope))}</span>`}
function render(){
    $('feeTypeBody').innerHTML=records.map(row=>`<tr>
        <td><strong>${Number(row.display_order)}</strong></td>
        <td><strong>${esc(row.fee_type_name)}</strong><small class="d-block text-muted">${esc(row.description||'')}</small></td>
        <td>${esc(row.fee_type_code)}</td>
        <td>${esc(frequencyLabel(row.default_frequency))} × ${Number(row.default_occurrence_count)}</td>
        <td>${requirementBadge(row.is_mandatory)}</td>
        <td>${usageBadge(row.usage_scope)}</td>
        <td>${statusBadge(row.is_enabled)}</td>
        <td>${Number(row.structure_count||0).toLocaleString('en-IN')} structure(s)</td>
        <td><div class="fst-actions">
            <button class="fst-action js-edit" data-id="${row.id}" type="button" title="Edit"><i data-lucide="pencil"></i></button>
            <button class="fst-action js-toggle" data-id="${row.id}" type="button" title="${Number(row.is_enabled)===1?'Disable':'Enable'}"><i data-lucide="${Number(row.is_enabled)===1?'circle-pause':'circle-play'}"></i></button>
            <button class="fst-action danger js-delete" data-id="${row.id}" type="button" title="Delete" ${Number(row.structure_count)>0?'disabled':''}><i data-lucide="trash-2"></i></button>
        </div></td>
    </tr>`).join('')||'<tr><td colspan="9" class="fst-empty">No fee types found.</td></tr>';
    $('recordInfo').textContent=`${records.length.toLocaleString('en-IN')} fee type${records.length===1?'':'s'}`;
    document.querySelectorAll('.js-edit').forEach(button=>button.onclick=()=>openEdit(Number(button.dataset.id)));
    document.querySelectorAll('.js-toggle').forEach(button=>button.onclick=()=>toggleType(Number(button.dataset.id)));
    document.querySelectorAll('.js-delete').forEach(button=>button.onclick=()=>deleteType(Number(button.dataset.id)));
    window.lucide?.createIcons();
}
function clearForm(){
    $('feeTypeForm').reset();
    $('feeTypeForm').classList.remove('was-validated');
    $('feeTypeId').value='';
    $('displayOrder').value='10';
    $('mandatory').value='0';
    $('usageScope').value='structure';
    $('frequency').value='one_time';
    $('occurrenceCount').value='1';
    $('enabled').value='1';
}
function openCreate(){clearForm();$('feeTypeModalTitle').textContent='Add Fee Type';$('saveFeeTypeBtn').innerHTML='<i data-lucide="save"></i> Save Fee Type';modal().show();window.lucide?.createIcons()}
function openEdit(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;
    clearForm();
    $('feeTypeId').value=row.id;
    $('feeTypeName').value=row.fee_type_name||'';
    $('feeTypeCode').value=row.fee_type_code||'';
    $('displayOrder').value=Number(row.display_order||0);
    $('mandatory').value=String(Number(row.is_mandatory||0));
    $('usageScope').value=row.usage_scope||'structure';
    $('frequency').value=row.default_frequency||'one_time';
    $('occurrenceCount').value=Number(row.default_occurrence_count||1);
    $('enabled').value=String(Number(row.is_enabled||0));
    $('description').value=row.description||'';
    $('feeTypeModalTitle').textContent='Edit Fee Type';
    $('saveFeeTypeBtn').innerHTML='<i data-lucide="save"></i> Update Fee Type';
    modal().show();
    window.lucide?.createIcons();
}
async function load(){
    try{
        const result=await request('list',{search:$('search').value.trim(),status:$('statusFilter').value});
        records=result.data.records||[];
        const stats=result.data.stats||{};
        $('statTotal').textContent=Number(stats.total||0).toLocaleString('en-IN');
        $('statEnabled').textContent=Number(stats.enabled||0).toLocaleString('en-IN');
        $('statDisabled').textContent=Number(stats.disabled||0).toLocaleString('en-IN');
        $('statMandatory').textContent=Number(stats.mandatory||0).toLocaleString('en-IN');
        render();
    }catch(error){message(error.message)}
}
async function toggleType(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;
    const next=Number(row.is_enabled)===1?0:1;
    if(!confirm(`${next?'Enable':'Disable'} "${row.fee_type_name}"?`))return;
    try{
        const result=await request('edit',{id,is_enabled:next,toggle_only:1},'POST');
        message(result.message,true);
        await load();
    }catch(error){message(error.message)}
}
async function deleteType(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;
    if(Number(row.structure_count)>0){message('This fee type is already used in Fee Structures. Disable it instead of deleting it.');return}
    if(!confirm(`Delete fee type "${row.fee_type_name}"?`))return;
    try{
        const result=await request('delete',{id},'POST');
        message(result.message,true);
        await load();
    }catch(error){message(error.message)}
}
$('feeTypeForm').onsubmit=async event=>{
    event.preventDefault();
    const form=event.currentTarget;
    if(!form.checkValidity()){form.classList.add('was-validated');return}
    try{
        $('saveFeeTypeBtn').disabled=true;
        const id=Number($('feeTypeId').value||0);
        const result=await request(id?'edit':'create',{
            id,
            fee_type_name:$('feeTypeName').value.trim(),
            fee_type_code:$('feeTypeCode').value.trim(),
            description:$('description').value.trim(),
            default_frequency:$('frequency').value,
            default_occurrence_count:Number($('occurrenceCount').value||1),
            is_mandatory:Number($('mandatory').value),
            usage_scope:$('usageScope').value,
            is_enabled:Number($('enabled').value),
            display_order:Number($('displayOrder').value||0)
        },'POST');
        modal().hide();
        message(result.message,true);
        await load();
    }catch(error){message(error.message)}
    finally{$('saveFeeTypeBtn').disabled=false}
};
$('frequency').onchange=()=>{
    const defaults={monthly:12,term:3,quarterly:4,half_yearly:2,yearly:1,annual:1,one_time:1};
    if($('frequency').value==='custom')return;
    $('occurrenceCount').value=String(defaults[$('frequency').value]||1);
};
$('addFeeTypeBtn').onclick=openCreate;
$('refreshBtn').onclick=load;
$('statusFilter').onchange=load;
$('resetBtn').onclick=()=>{$('search').value='';$('statusFilter').value='all';load()};
$('search').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(load,300)};
load();
window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
