<?php
declare(strict_types=1);

$pageTitle='Receipt Management';
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
.rm-page{display:grid;gap:16px}
.rm-page .page-title{font-size:28px;line-height:1.1}
.rm-page .page-subtitle{margin-top:4px}
.rm-message{display:none}.rm-message.show{display:block}
.rm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.rm-stat{border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.rm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.rm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.rm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.rm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.rm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.rm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.rm-stat-icon svg{width:25px;height:25px}
.rm-stat strong{display:block;font-size:25px;line-height:1}
.rm-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.rm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-nav{display:flex;gap:8px;overflow:auto;padding:10px}
.fee-nav a{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-main,#101a3b);font-size:11px;font-weight:800;text-decoration:none}
.fee-nav a.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.fee-nav svg{width:15px;height:15px}
.rm-card{border-radius:14px;overflow:hidden}
.rm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.rm-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.rm-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.3fr) repeat(5,minmax(135px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.rm-table-wrap{overflow:auto}
.rm-table{min-width:1280px}
.rm-table th{font-size:10px}
.rm-table td{font-size:11px;vertical-align:middle}
.rm-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.rm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.rm-badge.paid,.rm-badge.success,.rm-badge.active{color:#16834f;background:#e8f8ef}
.rm-badge.partial,.rm-badge.pending{color:#9a6700;background:#fff7d6}
.rm-badge.cancelled,.rm-badge.reversed,.rm-badge.failed{color:#dc2626;background:#fff0f1}
.rm-actions{display:flex;gap:5px;flex-wrap:wrap}
.rm-action{width:30px;height:30px;display:grid;place-items:center;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);color:#4f46e5}
.rm-action.danger{color:#dc2626}
.rm-action svg{width:13px;height:13px}
.rm-pagination{padding:14px 16px;border-top:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.rm-page-buttons{display:flex;gap:6px;flex-wrap:wrap}
.rm-page-button{min-width:34px;height:34px;border:1px solid var(--border-soft,#e7ebf3);background:#fff;border-radius:8px;font-size:11px;font-weight:800}
.rm-page-button.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,#6547e8,#315ed8)}
.rm-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.rm-detail-grid .full{grid-column:1/-1}
.rm-detail-box{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px}
.rm-detail-box small{display:block;font-size:9px;color:var(--text-muted,#64748b);font-weight:700}
.rm-detail-box strong{display:block;margin-top:4px;font-size:13px}
#receiptModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#receiptModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#receiptModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1200px){.rm-filter{grid-template-columns:repeat(3,1fr)}}
@media(max-width:900px){.rm-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.rm-stats,.rm-filter,.rm-detail-grid{grid-template-columns:1fr}.rm-detail-grid .full{grid-column:auto}.rm-card-head,.rm-pagination{align-items:flex-start;flex-direction:column}}
</style>

<div class="rm-page" data-page="receipts">
<div class="page-heading">
 <div><h1 class="page-title">Receipt Management</h1><p class="page-subtitle">View, print, duplicate and manage all student fee receipts.</p></div>
 <div class="page-actions">
  <a href="fee-collection.php" class="btn-ui btn-primary-ui"><i data-lucide="indian-rupee"></i> Collect Fee</a>
  <a id="exportBtn" class="btn-ui"><i data-lucide="download"></i> Export</a>
  <button id="refreshBtn" class="btn-ui" type="button"><i data-lucide="refresh-cw"></i> Refresh</button>
 </div>
</div>

<div id="rmMessage" class="alert rm-message"></div>

<section class="rm-stats">
 <article class="rm-stat purple"><span class="rm-stat-icon"><i data-lucide="receipt-text"></i></span><div><small>Total Receipts</small><strong id="statTotal">0</strong><div class="trend">All generated receipts</div></div></article>
 <article class="rm-stat green"><span class="rm-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Collected</small><strong id="statCollected">₹0</strong><div class="trend">Successful receipt value</div></div></article>
 <article class="rm-stat orange"><span class="rm-stat-icon"><i data-lucide="calendar-check-2"></i></span><div><small>Today's Receipts</small><strong id="statToday">0</strong><div class="trend">Generated today</div></div></article>
 <article class="rm-stat blue"><span class="rm-stat-icon"><i data-lucide="ban"></i></span><div><small>Cancelled Receipts</small><strong id="statCancelled">0</strong><div class="trend">Cancelled or reversed</div></div></article>
</section>

<section class="ui-card fee-nav">
<a href="fee-dashboard.php" data-key="dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a>
<a href="fee-setup.php" data-key="setup"><i data-lucide="settings-2"></i> Fee Setup</a>
<a href="fee-categories.php" data-key="categories"><i data-lucide="tags"></i> Fee Categories</a>
<a href="fee-structures.php" data-key="structures"><i data-lucide="list-tree"></i> Fee Structures</a>
<a href="fee-assignment.php" data-key="assignment"><i data-lucide="user-round-check"></i> Fee Assignment</a>
<a href="fee-collection.php" data-key="collection"><i data-lucide="indian-rupee"></i> Fee Collection</a>
<a href="receipt-management.php" data-key="receipts"><i data-lucide="receipt-text"></i> Receipts</a>
<a href="student-fees.php" data-key="student_fees"><i data-lucide="users"></i> Student Fees</a>
<a href="fee-reports.php" data-key="reports"><i data-lucide="chart-column"></i> Reports</a>
</section>

<section class="ui-card rm-card">
<div class="rm-card-head"><strong>Receipt List</strong><div class="rm-card-actions"><small id="recordInfo" class="text-muted">Loading...</small></div></div>

<div class="rm-filter">
 <input id="search" class="form-control" placeholder="Receipt no., admission no. or student...">
 <select id="yearFilter" class="form-select"><option value="all">All Academic Years</option></select>
 <input id="fromDate" class="form-control" type="date">
 <input id="toDate" class="form-control" type="date">
 <select id="methodFilter" class="form-select"><option value="all">All Payment Modes</option></select>
 <select id="statusFilter" class="form-select"><option value="all">All Statuses</option><option value="paid">Paid</option><option value="partial">Partial</option><option value="cancelled">Cancelled</option><option value="reversed">Reversed</option></select>
 <button id="resetBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Reset</button>
</div>

<div class="rm-table-wrap">
<table class="data-table rm-table">
<thead><tr><th>#</th><th>Receipt No.</th><th>Date</th><th>Student</th><th>Admission No.</th><th>Academic Year</th><th>Fee Structure</th><th>Payment Mode</th><th>Reference</th><th>Paid Amount</th><th>Status</th><th>Actions</th></tr></thead>
<tbody id="receiptBody"><tr><td colspan="12" class="rm-empty">Loading...</td></tr></tbody>
</table>
</div>

<div class="rm-pagination"><small id="pageInfo" class="text-muted"></small><div id="pagination" class="rm-page-buttons"></div></div>
</section>
</div>

<div class="modal fade" id="receiptModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered">
<div class="modal-content">
<div class="modal-header"><div><h5 class="modal-title">Receipt Details</h5><small id="receiptSubtitle" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
 <div id="receiptDetails" class="rm-detail-grid"></div>
 <div class="mt-3"><strong>Payment History</strong></div>
 <div class="rm-table-wrap mt-2"><table class="data-table rm-table"><thead><tr><th>Mode</th><th>Amount</th><th>Reference</th><th>Paid At</th><th>Status</th></tr></thead><tbody id="paymentHistory"></tbody></table></div>
</div>
<div class="modal-footer">
 <a id="modalPrint" target="_blank" class="btn-ui"><i data-lucide="printer"></i> Print</a>
 <a id="modalPdf" target="_blank" class="btn-ui"><i data-lucide="file-down"></i> PDF</a>
 <button type="button" class="btn-ui" data-bs-dismiss="modal">Close</button>
</div>
</div>
</div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/receipt-management.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
let currentPage=1;
let rows=[];
let permissions={cancel:false};
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const money=v=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0));
const badge=v=>`<span class="rm-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`;

async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){
  const url=new URL(apiUrl);url.searchParams.set('action',action);
  Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});
  response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
 }else{
  response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 }
 const text=await response.text();let result;
 try{result=JSON.parse(text)}catch{throw new Error(`Receipt API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function message(text,ok=false){const b=$('rmMessage');b.className='alert rm-message show '+(ok?'alert-success':'alert-danger');b.textContent=text}
function fill(id,items,key,label,first=''){const e=$(id);e.innerHTML=(first?`<option value="all">${esc(first)}</option>`:'')+items.map(x=>`<option value="${esc(x[key])}">${esc(x[label])}</option>`).join('')}
function renderStats(s={}){$('statTotal').textContent=Number(s.total||0).toLocaleString('en-IN');$('statCollected').textContent=money(s.collected||0);$('statToday').textContent=Number(s.today||0).toLocaleString('en-IN');$('statCancelled').textContent=Number(s.cancelled||0).toLocaleString('en-IN')}
function render(records){
 rows=records;
 $('receiptBody').innerHTML=records.map(r=>`<tr>
 <td>${esc(r.row_number)}</td><td><strong>${esc(r.receipt_no)}</strong></td><td>${esc(r.receipt_date)}</td><td>${esc(r.student_name)}</td><td>${esc(r.admission_no)}</td><td>${esc(r.year_name||'-')}</td><td>${esc(r.structure_names||'-')}</td><td>${esc(r.payment_methods||'-')}</td><td>${esc(r.reference_numbers||'-')}</td><td><strong>${money(r.paid_amount)}</strong></td><td>${badge(r.display_status)}</td>
 <td><div class="rm-actions">
  <button class="rm-action js-view" data-id="${r.id}" title="View"><i data-lucide="eye"></i></button>
  <a class="rm-action" target="_blank" href="${apiUrl}?action=print&id=${r.id}" title="Print"><i data-lucide="printer"></i></a>
  <a class="rm-action" target="_blank" href="${apiUrl}?action=pdf&id=${r.id}" title="PDF"><i data-lucide="file-down"></i></a>
  <button class="rm-action js-duplicate" data-id="${r.id}" title="Duplicate"><i data-lucide="copy"></i></button>
  ${permissions.cancel&&r.display_status!=='cancelled'&&r.display_status!=='reversed'?`<button class="rm-action danger js-cancel" data-id="${r.id}" title="Cancel"><i data-lucide="ban"></i></button>`:''}
 </div></td>
 </tr>`).join('')||'<tr><td colspan="12" class="rm-empty">No receipts found.</td></tr>';
 document.querySelectorAll('.js-view').forEach(b=>b.onclick=()=>viewReceipt(Number(b.dataset.id)));
 document.querySelectorAll('.js-duplicate').forEach(b=>b.onclick=()=>duplicateReceipt(Number(b.dataset.id)));
 document.querySelectorAll('.js-cancel').forEach(b=>b.onclick=()=>cancelReceipt(Number(b.dataset.id)));
 window.lucide?.createIcons();
}
function renderPagination(p={}){
 const total=Number(p.total||0),page=Number(p.page||1),per=Number(p.per_page||10),last=Math.max(1,Number(p.last_page||1)),start=total?((page-1)*per)+1:0,end=Math.min(page*per,total);
 $('recordInfo').textContent=`${total.toLocaleString('en-IN')} receipt${total===1?'':'s'}`;$('pageInfo').textContent=`Showing ${start}-${end} of ${total}`;
 let html=`<button class="rm-page-button" data-page="${page-1}" ${page<=1?'disabled':''}>‹</button>`;for(let i=Math.max(1,page-2);i<=Math.min(last,page+2);i++)html+=`<button class="rm-page-button ${i===page?'active':''}" data-page="${i}">${i}</button>`;html+=`<button class="rm-page-button" data-page="${page+1}" ${page>=last?'disabled':''}>›</button>`;$('pagination').innerHTML=html;
 document.querySelectorAll('.rm-page-button').forEach(b=>b.onclick=()=>{if(!b.disabled){currentPage=Number(b.dataset.page);load()}});
}
async function load(){
 try{
  const r=await request('list',{search:$('search').value.trim(),academic_year_id:$('yearFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value,payment_method_id:$('methodFilter').value,status:$('statusFilter').value,page:currentPage,per_page:10});
  permissions=r.data.permissions||permissions;render(r.data.records||[]);renderPagination(r.data.pagination||{});renderStats(r.data.stats||{});
  if($('yearFilter').options.length<=1)fill('yearFilter',r.data.meta?.years||[],'id','year_name','All Academic Years');
  if($('methodFilter').options.length<=1)fill('methodFilter',r.data.meta?.methods||[],'id','method_name','All Payment Modes');
  $('exportBtn').href=apiUrl+'?action=export&'+new URLSearchParams({search:$('search').value.trim(),academic_year_id:$('yearFilter').value,from_date:$('fromDate').value,to_date:$('toDate').value,payment_method_id:$('methodFilter').value,status:$('statusFilter').value});
 }catch(e){message(e.message)}
}
async function viewReceipt(id){
 try{
  const r=await request('detail',{id});const x=r.data.receipt,p=r.data.payments||[];
  $('receiptSubtitle').textContent=`${x.receipt_no} • ${x.student_name}`;
  $('receiptDetails').innerHTML=[
   ['Receipt Number',x.receipt_no],['Receipt Date',x.receipt_date],['Student',x.student_name],['Admission No.',x.admission_no],['Academic Year',x.year_name||'-'],['Fee Structure',x.structure_names||'-'],['Gross Amount',money(x.gross_amount)],['Discount',money(x.discount_amount)],['Fine',money(x.fine_amount)],['Paid Amount',money(x.paid_amount)],['Status',x.display_status],['Remarks',x.clean_notes||'-']
  ].map(([l,v])=>`<div class="rm-detail-box"><small>${esc(l)}</small><strong>${esc(v)}</strong></div>`).join('');
  $('paymentHistory').innerHTML=p.map(i=>`<tr><td>${esc(i.method_name)}</td><td>${money(i.amount)}</td><td>${esc(i.reference_no||'-')}</td><td>${esc(i.paid_at)}</td><td>${badge(i.status)}</td></tr>`).join('')||'<tr><td colspan="5" class="rm-empty">No payment history.</td></tr>';
  $('modalPrint').href=`${apiUrl}?action=print&id=${id}`;$('modalPdf').href=`${apiUrl}?action=pdf&id=${id}`;
  bootstrap.Modal.getOrCreateInstance($('receiptModal')).show();window.lucide?.createIcons();
 }catch(e){message(e.message)}
}
async function duplicateReceipt(id){
 if(!confirm('Generate a duplicate copy of this receipt?'))return;
 try{const r=await request('duplicate',{id},'POST');message(r.message,true);if(r.data?.duplicate_url)window.open(r.data.duplicate_url,'_blank')}catch(e){message(e.message)}
}
async function cancelReceipt(id){
 const reason=prompt('Enter cancellation reason:');if(reason===null)return;if(reason.trim().length<3){message('Cancellation reason must be at least 3 characters.');return}
 if(!confirm('Cancel this receipt and restore the linked fee balance?'))return;
 try{const r=await request('cancel',{id,reason:reason.trim()},'POST');message(r.message,true);await load()}catch(e){message(e.message)}
}
$('refreshBtn').onclick=load;$('resetBtn').onclick=()=>{$('search').value='';$('yearFilter').value='all';$('fromDate').value='';$('toDate').value='';$('methodFilter').value='all';$('statusFilter').value='all';currentPage=1;load()};
['yearFilter','fromDate','toDate','methodFilter','statusFilter'].forEach(id=>$(id).addEventListener('change',()=>{currentPage=1;load()}));let timer;$('search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{currentPage=1;load()},300)});
document.querySelector('.fee-nav a[data-key="receipts"]')?.classList.add('active');load();window.lucide?.createIcons();
})();
</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>
