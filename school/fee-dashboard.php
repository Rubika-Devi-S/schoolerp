<?php
declare(strict_types=1);
$pageTitle='Fee Management Dashboard';
$pageKey='fee_management';
require dirname(__DIR__).'/includes/layout-start.php';
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['fee_csrf_token']))$_SESSION['fee_csrf_token']=bin2hex(random_bytes(32));
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
.fee-stat.pink{background:linear-gradient(135deg,#ff527c,#ed2f63)}
.fee-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.fee-stat-icon svg{width:25px;height:25px}
.fee-stat strong{display:block;font-size:25px;line-height:1}
.fee-stat small{display:block;font-size:11px;font-weight:700;opacity:.95;margin-bottom:6px}
.fee-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.fee-grid{display:grid;grid-template-columns:1fr;gap:16px;align-items:start}
.fee-card{border-radius:14px;overflow:hidden}
.fee-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;justify-content:space-between;align-items:center;gap:10px}
.fee-card-head strong{font-size:14px}
.fee-card-actions{display:flex;gap:8px;flex-wrap:wrap}
.fee-filter{padding:14px 16px;display:grid;grid-template-columns:minmax(220px,1.4fr) repeat(3,minmax(140px,.7fr)) auto;gap:10px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.fee-table-wrap{overflow:auto}
.fee-table{min-width:900px}
.fee-table th{font-size:10px}
.fee-table td{font-size:11px;vertical-align:middle}
.fee-empty{padding:40px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.fee-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize}
.fee-badge.active,.fee-badge.paid,.fee-badge.success{color:#16834f;background:#e8f8ef}
.fee-badge.partial,.fee-badge.pending,.fee-badge.draft{color:#b96b00;background:#fff4df}
.fee-badge.unpaid,.fee-badge.overdue,.fee-badge.failed,.fee-badge.reversed,.fee-badge.inactive{color:#dc2626;background:#fff0f1}
.fee-action{width:30px;height:30px;border:1px solid #d7def1;border-radius:7px;background:var(--card-bg,#fff);display:grid;place-items:center;color:#4f46e5}
.fee-action svg{width:13px;height:13px}
.fee-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.fee-form-grid .full{grid-column:1/-1}
.fee-message{display:none}
.fee-message.show{display:block}
#feeModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#feeModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#feeModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#feeModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:1100px){.fee-grid{grid-template-columns:1fr}.fee-filter{grid-template-columns:repeat(2,1fr)}}
@media(max-width:900px){.fee-stats{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.fee-stats,.fee-filter,.fee-form-grid{grid-template-columns:1fr}.fee-form-grid .full{grid-column:auto}.fee-card-head{align-items:flex-start;flex-direction:column}}
</style>

<div class="fee-page" data-page="dashboard">
<div class="page-heading"><div><h1 class="page-title">Fee Management Dashboard</h1><p class="page-subtitle">Monitor fee collections, outstanding balances and recent activity.</p></div><div class="page-actions" id="pageActions"></div></div>

<div id="feeMessage" class="alert fee-message" role="alert"></div>

<section class="fee-stats">
<article class="fee-stat green"><span class="fee-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Total Fee Collected</small><strong id="totalFeeCollected">₹0</strong><div class="trend">Successful collections for the academic year</div></div></article>
<article class="fee-stat orange"><span class="fee-stat-icon"><i data-lucide="clock-3"></i></span><div><small>Pending Fees</small><strong id="pendingFees">₹0</strong><div class="trend">Outstanding student fee balance</div></div></article>
<article class="fee-stat blue"><span class="fee-stat-icon"><i data-lucide="calendar-check-2"></i></span><div><small>Today's Collection</small><strong id="todayCollection">₹0</strong><div class="trend">Non-reversed receipts collected today</div></div></article>
<article class="fee-stat purple"><span class="fee-stat-icon"><i data-lucide="users"></i></span><div><small>Students with Fee Assigned</small><strong id="studentsAssigned">0</strong><div class="trend">Distinct students with fee assignments</div></div></article>
</section>

<section class="fee-grid">
<section class="ui-card fee-card"><div class="fee-card-head"><strong>Recent Fee Collections</strong><a class="btn-ui" href="school-transactions.php">View All</a></div><div class="fee-table-wrap"><table class="data-table fee-table"><thead><tr><th>Receipt</th><th>Student</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody id="recentBody"><tr><td colspan="5" class="fee-empty">Loading...</td></tr></tbody></table></div></section>
</section>
<script>
document.addEventListener('DOMContentLoaded',async()=>{
 const {request,money,message,badge,esc,$}=window.feeApp;
 try{
  const r=await request('dashboard');
  const d=r.data;
  const stats=d.stats||{};
  const setText=(id,value)=>{
   const element=$(id);
   if(element)element.textContent=value;
  };
  setText('totalFeeCollected',money(stats.total_fee_collected||0));
  setText('pendingFees',money(stats.pending_fees||0));
  setText('todayCollection',money(stats.today_collection||0));
  setText('studentsAssigned',Number(stats.students_fee_assigned||0).toLocaleString('en-IN'));
  const recentBody=$('recentBody');
  if(recentBody){
   recentBody.innerHTML=(d.recent||[]).map(x=>`<tr><td>${esc(x.receipt_no)}</td><td>${esc(x.student_name)}</td><td>${esc(x.receipt_date)}</td><td><strong>${money(x.paid_amount)}</strong></td><td>${badge(x.payment_status)}</td></tr>`).join('')||'<tr><td colspan="5" class="fee-empty">No collections found.</td></tr>';
  }
  const summaryList=$('summaryList');
  if(summaryList){
   summaryList.innerHTML=(d.summary||[]).map(x=>`<div class="d-flex justify-content-between py-2 border-bottom"><span>${esc(x.payment_status)}</span><strong>${money(x.amount)}</strong></div>`).join('')||'<div class="fee-empty">No summary available.</div>';
  }
 }catch(e){message(e.message)}
});
</script>
</div>
<script>
(function(){
'use strict';
const pageKey=document.querySelector('.fee-page')?.dataset.page||'dashboard';
document.querySelector(`.fee-nav a[data-key="${pageKey}"]`)?.classList.add('active');
const apiUrl=new URL('../api/fee-dashboard.php',window.location.href).href;
let csrfToken=<?=json_encode($feeCsrf)?>;
const $=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function request(action,data={},method='GET'){
 let response;
 if(method==='GET'){
  const url=new URL(apiUrl);
  url.searchParams.set('action',action);
  Object.entries(data).forEach(([k,v])=>{if(v!==''&&v!==null&&v!==undefined)url.searchParams.set(k,String(v))});
  response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
 }else{
  response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
 }
 const text=await response.text();
 let result;
 try{
  result=JSON.parse(text);
 }catch{
  const preview=text.replace(/\s+/g,' ').trim().slice(0,200);
  throw new Error(`Fee API returned HTTP ${response.status}. ${preview||'Invalid server response.'}`);
 }
 if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');
 if(result.data?.csrf_token)csrfToken=result.data.csrf_token;
 return result;
}
function money(v){return new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(v||0))}
function message(text,ok=false){
 const b=$('feeMessage');
 if(!b){
  console.error(text);
  return;
 }
 b.className='alert fee-message show '+(ok?'alert-success':'alert-danger');
 b.textContent=text;
}
function badge(v){return `<span class="fee-badge ${esc(String(v||'').toLowerCase())}">${esc(v||'-')}</span>`}
window.feeApp={request,money,message,badge,esc,$};
window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>
