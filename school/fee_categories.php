<?php
declare(strict_types=1);

$pageTitle='Fee Categories';
$pageKey='fee_management';

require dirname(__DIR__).'/includes/layout-start.php';

if(session_status()!==PHP_SESSION_ACTIVE){
    session_start();
}

if(empty($_SESSION['fee_csrf_token'])||!is_string($_SESSION['fee_csrf_token'])){
    $_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
}

$feeCsrf=$_SESSION['fee_csrf_token'];
?>
<style>
.fee-page{display:grid;gap:16px}
.fee-page .page-title{font-size:28px;line-height:1.1}
.fee-page .page-subtitle{margin-top:4px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.fee-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.fee-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.fee-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.fee-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.fee-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.fee-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.fee-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.fee-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fee-stat-icon svg{width:25px;height:25px}
.fee-stat strong{display:block;font-size:25px;line-height:1}
.fee-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fee-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(240px,1.4fr) minmax(150px,.6fr) minmax(150px,.6fr) minmax(150px,.6fr) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:900px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active{color:#16834f;background:#e8f8ef}
.fee-badge.inactive{color:#dc2626;background:#fff0f1}
.fee-actions{display:flex;gap:5px;flex-wrap:wrap}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action.danger{color:#dc2626}
.fee-action svg{width:13px;height:13px}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-message{display:none}
.fee-message.show{display:block}
.fee-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.fee-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);border-radius:8px;font-size:11px;font-weight:800;color:var(--text-main,#101a3b)}
.fee-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-page-button:disabled{opacity:.45;cursor:not-allowed}
#categoryModal .modal-dialog,#viewModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#categoryModal .modal-content,#viewModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#categoryModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#categoryModal .modal-body,#viewModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1100px){.fee-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.fee-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head,.fee-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="fee-page" data-page="categories">
<div class="page-heading">
    <div>
        <h1 class="page-title">Fee Categories</h1>
        <p class="page-subtitle">Create and manage fee categories used across fee structures and student billing.</p>
    </div>
    <div class="page-actions">
        <button id="addCategoryBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="plus"></i> Add Category</button>
        <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
    </div>
</div>

<section class="fee-stats">
<article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="tags"></i></span><div><small>Total Categories</small><strong id="statTotal">0</strong><div class="trend">All fee categories</div></div></article>
<article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="circle-check-big"></i></span><div><small>Active Categories</small><strong id="statActive">0</strong><div class="trend">Available for fee setup</div></div></article>
<article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="circle-pause"></i></span><div><small>Inactive Categories</small><strong id="statInactive">0</strong><div class="trend">Temporarily disabled</div></div></article>
<article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="link-2"></i></span><div><small>Used in Structures</small><strong id="statUsed">0</strong><div class="trend">Protected from deletion</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="student-fees.php" data-key="student_fees"><i data-lucide="users"></i> Student Fees</a>
<a href="fee-transactions.php" data-key="transactions"><i data-lucide="receipt-text"></i> Transactions</a>
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
<a href="fee-settings.php" data-key="settings"><i data-lucide="sliders-horizontal"></i> Settings</a>
</section>

<div id="feeMessage" class="alert fee-message" role="alert"></div>

<section class="ui-card fee-card">
    <div class="fee-card-head">
        <strong>Fee Category List</strong>
        <div class="fee-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div>
    </div>

    <div class="fee-filter">
        <input id="search" class="form-control" placeholder="Search category name or code...">
        <select id="statusFilter" class="form-select">
            <option value="all">All Statuses</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <select id="usageFilter" class="form-select">
            <option value="all">All Usage</option>
            <option value="used">Used in Structure</option>
            <option value="unused">Not Used</option>
        </select>
        <select id="sortFilter" class="form-select">
            <option value="name_asc">Name A-Z</option>
            <option value="name_desc">Name Z-A</option>
            <option value="code_asc">Code A-Z</option>
            <option value="newest">Newest First</option>
        </select>
        <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
    </div>

    <div class="fee-table-wrap">
        <table class="data-table fee-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category Code</th>
                    <th>Category Name</th>
                    <th>Refundable</th>
                    <th>Structures Used</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="categoryBody">
                <tr><td colspan="7" class="fee-empty">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="fee-pagination">
        <small id="pageInfo" class="text-muted"></small>
        <div id="pagination" class="fee-page-buttons"></div>
    </div>
</section>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="categoryForm">
                <div class="modal-header">
                    <h5 id="categoryModalTitle" class="modal-title">Add Fee Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body fee-form-grid">
                    <input id="categoryId" type="hidden">

                    <div>
                        <label class="form-label">Category Code *</label>
                        <input id="categoryCode" class="form-control" maxlength="40" placeholder="e.g. TUITION" required>
                        <small class="text-muted">Letters, numbers, underscore and hyphen only.</small>
                    </div>

                    <div>
                        <label class="form-label">Category Name *</label>
                        <input id="categoryName" class="form-control" maxlength="120" placeholder="e.g. Tuition Fee" required>
                    </div>

                    <div>
                        <label class="form-label">Refundable</label>
                        <select id="isRefundable" class="form-select">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Status</label>
                        <select id="categoryStatus" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-ui" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button id="saveCategoryBtn" class="btn-ui btn-primary-ui" type="submit">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Fee Category Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewContent"></div>
            <div class="modal-footer"><button class="btn-ui" type="button" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/fee-categories.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let currentPage=1;
let records=[];
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#039;'}[c]));
const badge=v=>`<span class="fee-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;
const categoryModal=()=>bootstrap.Modal.getOrCreateInstance($('categoryModal'));
const viewModal=()=>bootstrap.Modal.getOrCreateInstance($('viewModal'));

async function request(action,data={},method='GET'){
    let response;

    if(method==='GET'){
        const url=new URL(apiUrl);
        url.searchParams.set('action',action);
        Object.entries(data).forEach(([key,value])=>{
            if(value!==''&&value!==null&&value!==undefined){
                url.searchParams.set(key,String(value));
            }
        });
        response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
    }else{
        response=await fetch(apiUrl,{
            method:'POST',
            headers:{'Content-Type':'application/json',Accept:'application/json'},
            credentials:'same-origin',
            body:JSON.stringify({action,csrf_token:csrfToken,...data})
        });
    }

    const text=await response.text();
    let result;

    try{
        result=JSON.parse(text);
    }catch{
        const preview=text.replace(/\s+/g,' ').trim().slice(0,220);
        throw new Error(`Fee Categories API returned HTTP ${response.status}. ${preview||'Invalid server response.'}`);
    }

    if(!response.ok||!result.success){
        throw new Error(result.message||'Request failed.');
    }

    if(result.data?.csrf_token){
        csrfToken=result.data.csrf_token;
    }

    return result;
}

function message(text,success=false){
    const box=$('feeMessage');
    box.className='alert fee-message show '+(success?'alert-success':'alert-danger');
    box.textContent=text;
}

function render(rows){
    records=rows;

    $('categoryBody').innerHTML=rows.map((row,index)=>`<tr>
        <td>${esc(row.row_number)}</td>
        <td><strong>${esc(row.head_code)}</strong></td>
        <td>${esc(row.head_name)}</td>
        <td>${Number(row.is_refundable)?'Yes':'No'}</td>
        <td>${Number(row.structure_count||0).toLocaleString('en-IN')}</td>
        <td>${badge(row.status)}</td>
        <td><div class="fee-actions">
            <button class="fee-action js-view" data-id="${row.id}" type="button" title="View"><i data-lucide="eye"></i></button>
            <button class="fee-action js-edit" data-id="${row.id}" type="button" title="Edit"><i data-lucide="pencil"></i></button>
            <button class="fee-action danger js-delete" data-id="${row.id}" type="button" title="Delete" ${Number(row.structure_count)>0?'disabled':''}><i data-lucide="trash-2"></i></button>
        </div></td>
    </tr>`).join('')||'<tr><td colspan="7" class="fee-empty">No fee categories found.</td></tr>';

    document.querySelectorAll('.js-view').forEach(button=>{
        button.onclick=()=>openView(Number(button.dataset.id));
    });

    document.querySelectorAll('.js-edit').forEach(button=>{
        button.onclick=()=>openEdit(Number(button.dataset.id));
    });

    document.querySelectorAll('.js-delete').forEach(button=>{
        button.onclick=()=>deleteCategory(Number(button.dataset.id));
    });

    window.lucide?.createIcons();
}

function renderPagination(data){
    const total=Number(data.total||0);
    const page=Number(data.page||1);
    const perPage=Number(data.per_page||10);
    const lastPage=Math.max(1,Number(data.last_page||1));
    const start=total===0?0:((page-1)*perPage)+1;
    const end=Math.min(page*perPage,total);

    $('recordInfo').textContent=`${total.toLocaleString('en-IN')} categor${total===1?'y':'ies'}`;
    $('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;

    let html=`<button class="fee-page-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button>`;

    const first=Math.max(1,page-2);
    const last=Math.min(lastPage,page+2);

    for(let p=first;p<=last;p++){
        html+=`<button class="fee-page-button ${p===page?'active':''}" data-page="${p}">${p}</button>`;
    }

    html+=`<button class="fee-page-button" data-page="${page+1}" ${page>=lastPage?'disabled':''}>›</button>`;

    $('pagination').innerHTML=html;

    document.querySelectorAll('.fee-page-button[data-page]').forEach(button=>{
        button.onclick=()=>{
            if(button.disabled)return;
            currentPage=Number(button.dataset.page);
            load();
        };
    });
}

function updateStats(stats={}){
    $('statTotal').textContent=Number(stats.total||0).toLocaleString('en-IN');
    $('statActive').textContent=Number(stats.active||0).toLocaleString('en-IN');
    $('statInactive').textContent=Number(stats.inactive||0).toLocaleString('en-IN');
    $('statUsed').textContent=Number(stats.used||0).toLocaleString('en-IN');
}

async function load(){
    try{
        const response=await request('list',{
            search:$('search').value.trim(),
            status:$('statusFilter').value,
            usage:$('usageFilter').value,
            sort:$('sortFilter').value,
            page:currentPage,
            per_page:10
        });

        render(response.data.records||[]);
        renderPagination(response.data.pagination||{});
        updateStats(response.data.stats||{});
    }catch(error){
        message(error.message);
    }
}

function clearForm(){
    $('categoryId').value='';
    $('categoryCode').value='';
    $('categoryName').value='';
    $('isRefundable').value='0';
    $('categoryStatus').value='active';
}

function openCreate(){
    clearForm();
    $('categoryModalTitle').textContent='Add Fee Category';
    $('saveCategoryBtn').textContent='Save Category';
    categoryModal().show();
}

function openEdit(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;

    $('categoryId').value=row.id;
    $('categoryCode').value=row.head_code;
    $('categoryName').value=row.head_name;
    $('isRefundable').value=String(Number(row.is_refundable));
    $('categoryStatus').value=row.status;
    $('categoryModalTitle').textContent='Edit Fee Category';
    $('saveCategoryBtn').textContent='Update Category';
    categoryModal().show();
}

function openView(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;

    $('viewContent').innerHTML=`
        <div class="fee-form-grid">
            <div><label class="form-label">Category Code</label><input class="form-control" readonly value="${esc(row.head_code)}"></div>
            <div><label class="form-label">Category Name</label><input class="form-control" readonly value="${esc(row.head_name)}"></div>
            <div><label class="form-label">Refundable</label><input class="form-control" readonly value="${Number(row.is_refundable)?'Yes':'No'}"></div>
            <div><label class="form-label">Status</label><input class="form-control" readonly value="${esc(row.status)}"></div>
            <div><label class="form-label">Used in Structures</label><input class="form-control" readonly value="${Number(row.structure_count||0)}"></div>
        </div>`;
    viewModal().show();
}

async function deleteCategory(id){
    const row=records.find(item=>Number(item.id)===id);
    if(!row)return;

    if(Number(row.structure_count)>0){
        message('This fee category is already used in a fee structure and cannot be deleted.');
        return;
    }

    if(!confirm(`Delete fee category "${row.head_name}"?`))return;

    try{
        const response=await request('delete',{id},'POST');
        message(response.message,true);
        await load();
    }catch(error){
        message(error.message);
    }
}

$('categoryForm').onsubmit=async event=>{
    event.preventDefault();

    try{
        const response=await request('save',{
            id:Number($('categoryId').value||0),
            head_code:$('categoryCode').value.trim(),
            head_name:$('categoryName').value.trim(),
            is_refundable:Number($('isRefundable').value),
            status:$('categoryStatus').value
        },'POST');

        categoryModal().hide();
        message(response.message,true);
        await load();
    }catch(error){
        message(error.message);
    }
};

$('addCategoryBtn').onclick=openCreate;
$('refreshBtn').onclick=load;
$('resetBtn').onclick=()=>{
    $('search').value='';
    $('statusFilter').value='all';
    $('usageFilter').value='all';
    $('sortFilter').value='name_asc';
    currentPage=1;
    load();
};

['statusFilter','usageFilter','sortFilter'].forEach(id=>{
    $(id).addEventListener('change',()=>{
        currentPage=1;
        load();
    });
});

let searchTimer;
$('search').addEventListener('input',()=>{
    clearTimeout(searchTimer);
    searchTimer=setTimeout(()=>{
        currentPage=1;
        load();
    },300);
});

document.querySelector('.fee-nav a[data-key="categories"]')?.classList.add('active');
load();
window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>
