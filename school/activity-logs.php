<?php
declare(strict_types=1);

$pageTitle = 'Activity Logs';
$pageKey = 'activity_logs';
require dirname(__DIR__) . '/includes/layout-start.php';
?>
<style>
*{box-sizing:border-box}
.al-page{display:grid;gap:16px;width:100%;min-width:0}
.al-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
.al-head h1{margin:0;font-size:clamp(24px,2vw,30px)}
.al-head p{margin:5px 0 0}
.al-actions{display:flex;gap:9px;flex-wrap:wrap;align-items:center}
.al-actions .btn-ui{min-height:40px;white-space:nowrap}
.al-live{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:0 12px;border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;color:#15803d;font-size:10px;font-weight:800;white-space:nowrap}
.al-live-dot{width:8px;height:8px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 0 rgba(34,197,94,.45);animation:alLivePulse 1.8s infinite}
@keyframes alLivePulse{0%{box-shadow:0 0 0 0 rgba(34,197,94,.45)}70%{box-shadow:0 0 0 7px rgba(34,197,94,0)}100%{box-shadow:0 0 0 0 rgba(34,197,94,0)}}

.al-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.al-stat{position:relative;min-height:112px;padding:17px;border-radius:15px;overflow:hidden;color:#fff;display:flex;align-items:center;gap:14px;box-shadow:0 10px 26px rgba(15,23,42,.06)}
.al-stat:after{content:"";position:absolute;right:-25px;top:-28px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.09)}
.al-stat-icon{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:rgba(255,255,255,.16);flex:0 0 auto;position:relative;z-index:1}
.al-stat-icon svg{width:23px;height:23px}
.al-stat-copy{position:relative;z-index:1;min-width:0}
.al-stat-copy small{display:block;font-weight:700;opacity:.92}
.al-stat-copy strong{display:block;font-size:27px;line-height:1.15;margin-top:4px}
.al-stat-copy span{display:block;font-size:10px;margin-top:3px;opacity:.86}
.al-stat.total{background:linear-gradient(135deg,#6d45e8,#4f32d4)}
.al-stat.today{background:linear-gradient(135deg,#37bd7d,#149d62)}
.al-stat.users{background:linear-gradient(135deg,#f44271,#e9295d)}
.al-stat.modules{background:linear-gradient(135deg,#ffae32,#ff8b18)}

.al-card{border-radius:14px;overflow:hidden;min-width:0}
.al-card-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.al-card-head>div{display:flex;align-items:center;gap:10px;min-width:0}
.al-card-icon{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;color:#4f46e5;background:#eef2ff;flex:0 0 auto}
.al-card-head strong{display:block;font-size:13px}
.al-card-head small{display:block;margin-top:2px;color:var(--text-muted,#64748b)}
.al-card-body{padding:16px}
.al-message{display:none;margin:0}.al-message.show{display:block}
.al-loading{display:none;align-items:center;gap:8px;color:var(--text-muted,#64748b);font-size:10px}.al-loading.show{display:flex}
.al-spinner{width:15px;height:15px;border:2px solid #d8def0;border-top-color:#4f46e5;border-radius:50%;animation:alSpin .7s linear infinite}
@keyframes alSpin{to{transform:rotate(360deg)}}

.al-hierarchy{padding:14px;border:1px solid #e4e8f2;border-radius:12px;background:linear-gradient(180deg,#fbfcff,#f8faff);margin-bottom:14px}
.al-hierarchy-title{display:flex;align-items:center;gap:8px;margin-bottom:11px;font-size:11px;font-weight:800;color:#4338ca}
.al-scope-grid{display:grid;grid-template-columns:1.35fr 1fr 1fr 1.2fr;gap:11px;align-items:end}
.al-field{min-width:0}
.al-field label{display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:var(--text-muted,#64748b)}
.al-field .form-control,.al-field .form-select{width:100%;min-width:0}
.al-school-readonly{background:#f8fafc!important;font-weight:700;color:#172554!important}
.al-branch-required{border-color:#c7d2fe!important}
.al-path{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:10px;font-size:10px;color:#64748b}
.al-path span{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;background:#fff;border:1px solid #e5e7eb}

.al-filter-grid{display:grid;grid-template-columns:minmax(220px,1.6fr) repeat(5,minmax(135px,1fr));gap:11px;align-items:end}
.al-filter-grid .al-search{grid-column:span 2}
.al-filter-actions{display:flex;gap:9px;justify-content:flex-end;flex-wrap:wrap;margin-top:12px}
.al-quick-range{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}
.al-quick-range button{border:1px solid var(--border-soft,#e7ebf3);background:var(--card-bg,#fff);color:var(--text-color,#1e293b);padding:7px 10px;border-radius:8px;font-size:10px;font-weight:700}
.al-quick-range button:hover{border-color:#6366f1;color:#4f46e5}

.al-table-wrap{width:100%;overflow:auto}
.al-table{width:100%;min-width:1420px;border-collapse:collapse}
.al-table th{padding:11px 12px;text-align:left;font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted,#64748b);background:rgba(248,250,252,.72);border-bottom:1px solid var(--border-soft,#e7ebf3);white-space:nowrap}
.al-table td{padding:11px 12px;border-bottom:1px solid var(--border-soft,#edf0f5);font-size:11px;vertical-align:middle}
.al-table tbody tr:hover{background:rgba(99,102,241,.035)}
.al-user{display:flex;align-items:center;gap:9px;min-width:170px}
.al-avatar{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:linear-gradient(135deg,#6950ea,#3f57dc);color:#fff;font-weight:800;font-size:11px;flex:0 0 auto}
.al-user strong{display:block;font-size:11px}.al-user small{display:block;color:var(--text-muted,#64748b);margin-top:2px}
.al-school{min-width:150px}.al-school strong{display:block}.al-school small{display:block;margin-top:2px;color:#64748b}
.al-module{font-weight:700}.al-description{max-width:330px;white-space:normal;line-height:1.45;color:var(--text-muted,#475569)}
.al-record{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:10px;color:#475569}
.al-action-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize;white-space:nowrap;background:#eef2ff;color:#4f46e5}
.al-action-badge.create{background:#e7f8ef;color:#14834f}.al-action-badge.update,.al-action-badge.edit{background:#fff5dd;color:#b76a00}.al-action-badge.delete,.al-action-badge.remove{background:#ffe9ee;color:#c72850}.al-action-badge.login,.al-action-badge.view{background:#e8f3ff;color:#176ac2}
.al-branch{white-space:nowrap}.al-branch small{display:block;color:#64748b;margin-top:2px}
.al-ip{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--text-muted,#64748b);font-size:10px}
.al-view-btn{width:34px;height:34px;border:1px solid var(--border-soft,#dfe5ef);border-radius:9px;background:var(--card-bg,#fff);color:#334155;display:grid;place-items:center}.al-view-btn:hover{color:#4f46e5;border-color:#aeb8ff;background:#f3f4ff}.al-view-btn svg{width:15px;height:15px}
.al-empty{padding:46px 18px;text-align:center;color:var(--text-muted,#64748b)}.al-empty svg{width:36px;height:36px;margin-bottom:10px}
.al-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-top:1px solid var(--border-soft,#e7ebf3)}
.al-result-copy{font-size:10px;color:var(--text-muted,#64748b)}.al-pagination{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
.al-page-btn{min-width:34px;height:34px;padding:0 9px;border:1px solid var(--border-soft,#dfe5ef);border-radius:9px;background:var(--card-bg,#fff);color:var(--text-color,#1e293b);font-size:10px;font-weight:700}.al-page-btn.active{background:#4f46e5;color:#fff;border-color:#4f46e5}.al-page-btn:disabled{opacity:.45;cursor:not-allowed}
.al-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}.al-detail-item{padding:11px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;min-width:0}.al-detail-item.full{grid-column:1/-1}.al-detail-item small{display:block;color:var(--text-muted,#64748b);font-size:9px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}.al-detail-item strong,.al-detail-item span{display:block;overflow-wrap:anywhere;font-size:11px}
.al-change-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}.al-json-box{min-height:180px;max-height:350px;overflow:auto;margin:0;padding:13px;border-radius:10px;background:#0f172a;color:#dbeafe;font:10px/1.6 ui-monospace,SFMono-Regular,Menlo,monospace;white-space:pre-wrap;overflow-wrap:anywhere}.al-change-title{display:flex;align-items:center;gap:7px;margin-bottom:7px;font-size:11px;font-weight:800}

@media(max-width:1250px){.al-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.al-scope-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.al-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.al-filter-grid .al-search{grid-column:span 2}}
@media(max-width:767px){.al-head{flex-direction:column;align-items:stretch}.al-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}.al-actions .btn-ui,.al-live{width:100%;justify-content:center}.al-stats{grid-template-columns:1fr}.al-scope-grid,.al-filter-grid{grid-template-columns:1fr 1fr}.al-filter-grid .al-search{grid-column:1/-1}.al-footer{flex-direction:column;align-items:stretch}.al-pagination{justify-content:center}.al-result-copy{text-align:center}.al-detail-grid,.al-change-grid{grid-template-columns:1fr}.al-detail-item.full{grid-column:auto}}
@media(max-width:480px){.al-actions,.al-scope-grid,.al-filter-grid{grid-template-columns:1fr}.al-filter-grid .al-search{grid-column:auto}.al-card-body{padding:13px}}
@media print{#sidebar,.sidebar-backdrop,.top-header,.main-header,.al-actions,.al-filters-card,.al-view-btn,.al-pagination,.al-loading{display:none!important}body{background:#fff!important}.main-content,.page-content{margin:0!important;padding:0!important;width:100%!important}.al-page{gap:10px}.al-stats{grid-template-columns:repeat(4,1fr)}.al-stat{min-height:84px;color:#111!important;background:#fff!important;border:1px solid #ddd}.al-stat-icon{background:#f1f5f9;color:#111}.al-card{box-shadow:none!important;border:1px solid #ddd}.al-table{min-width:100%;font-size:8px}.al-table th,.al-table td{padding:6px}}
</style>

<div class="al-page">
    <div class="al-head">
        <div>
            <h1>Activity Logs</h1>
            <p class="text-muted">School-wise and branch-wise audit trail. Each branch is loaded separately without mixing another branch's activity.</p>
        </div>
        <div class="al-actions">
            <span class="al-live" title="The selected branch refreshes automatically"><span class="al-live-dot"></span><span id="liveStatusText">Live</span></span>
            <button id="refreshLogsBtn" class="btn-ui" type="button" data-permission-action="view"><i data-lucide="refresh-cw"></i> Refresh</button>
            <button id="printLogsBtn" class="btn-ui" type="button" data-permission-action="view"><i data-lucide="printer"></i> Print</button>
            <button id="exportLogsBtn" class="btn-ui btn-primary-ui" type="button" data-permission-action="view"><i data-lucide="download"></i> Export CSV</button>
        </div>
    </div>

    <div id="logsMessage" class="alert al-message"></div>

    <section class="al-stats">
        <article class="al-stat total"><span class="al-stat-icon"><i data-lucide="history"></i></span><div class="al-stat-copy"><small>Branch Activities</small><strong id="statTotal">0</strong><span>Activities in the selected branch</span></div></article>
        <article class="al-stat today"><span class="al-stat-icon"><i data-lucide="calendar-clock"></i></span><div class="al-stat-copy"><small>Today</small><strong id="statToday">0</strong><span>Today's selected-branch actions</span></div></article>
        <article class="al-stat users"><span class="al-stat-icon"><i data-lucide="users"></i></span><div class="al-stat-copy"><small>Active Users</small><strong id="statUsers">0</strong><span>Users in the filtered activity</span></div></article>
        <article class="al-stat modules"><span class="al-stat-icon"><i data-lucide="boxes"></i></span><div class="al-stat-copy"><small>Modules</small><strong id="statModules">0</strong><span>Modules used in this branch</span></div></article>
    </section>

    <section class="ui-card al-card al-filters-card">
        <div class="al-card-head">
            <div><span class="al-card-icon"><i data-lucide="network"></i></span><div><strong>School → Branch → Role → User → Activity</strong><small>Select the branch first. Roles and users are then limited to the same school and selected branch.</small></div></div>
            <div id="logsLoading" class="al-loading"><span class="al-spinner"></span> Loading...</div>
        </div>
        <div class="al-card-body">
            <div class="al-hierarchy">
                <div class="al-hierarchy-title"><i data-lucide="shield-check"></i> Strict Activity Scope</div>
                <div class="al-scope-grid">
                    <div class="al-field"><label for="schoolFilter">School</label><input id="schoolFilter" class="form-control al-school-readonly" type="text" value="Loading..." disabled></div>
                    <div class="al-field"><label for="branchFilter">Branch</label><select id="branchFilter" class="form-select al-branch-required"><option value="">Select Branch</option></select></div>
                    <div class="al-field"><label for="roleFilter">Role</label><select id="roleFilter" class="form-select"><option value="">All Roles in Branch</option></select></div>
                    <div class="al-field"><label for="userFilter">User</label><select id="userFilter" class="form-select"><option value="">All Users in Branch</option></select></div>
                </div>
                <div id="scopePath" class="al-path"><span>School</span><i data-lucide="chevron-right"></i><span>Branch</span><i data-lucide="chevron-right"></i><span>Role</span><i data-lucide="chevron-right"></i><span>User</span><i data-lucide="chevron-right"></i><span>Activity</span></div>
            </div>

            <div class="al-filter-grid">
                <div class="al-field al-search"><label for="searchLogs">Search Activity</label><input id="searchLogs" class="form-control" type="search" placeholder="Module, action, description, table, record or IP..."></div>
                <div class="al-field"><label for="moduleFilter">Module</label><select id="moduleFilter" class="form-select"><option value="">All Modules</option></select></div>
                <div class="al-field"><label for="actionFilter">Action</label><select id="actionFilter" class="form-select"><option value="">All Actions</option></select></div>
                <div class="al-field"><label for="dateFrom">From Date</label><input id="dateFrom" class="form-control" type="date"></div>
                <div class="al-field"><label for="dateTo">To Date</label><input id="dateTo" class="form-control" type="date"></div>
                <div class="al-field"><label for="perPageFilter">Rows Per Page</label><select id="perPageFilter" class="form-select"><option value="10">10</option><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option></select></div>
            </div>

            <div class="al-quick-range"><button type="button" data-range="today">Today</button><button type="button" data-range="7">Last 7 Days</button><button type="button" data-range="30">Last 30 Days</button><button type="button" data-range="month">This Month</button><button type="button" data-range="all">All Time</button></div>
            <div class="al-filter-actions"><button id="clearFiltersBtn" class="btn-ui" type="button"><i data-lucide="rotate-ccw"></i> Clear Filters</button><button id="applyFiltersBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="search"></i> Apply Filters</button></div>
        </div>
    </section>

    <section class="ui-card al-card">
        <div class="al-card-head"><div><span class="al-card-icon"><i data-lucide="activity"></i></span><div><strong id="activityHeading">Branch Activity History</strong><small id="tableSubtitle">Select a branch to load activity.</small></div></div></div>
        <div class="al-table-wrap">
            <table class="al-table">
                <thead><tr><th>Date & Time</th><th>School</th><th>Branch</th><th>Role</th><th>User</th><th>Module</th><th>Action</th><th>Description</th><th>Record</th><th>IP Address</th><th>Details</th></tr></thead>
                <tbody id="logsTableBody"><tr><td colspan="11"><div class="al-empty">Loading activity logs...</div></td></tr></tbody>
            </table>
        </div>
        <div class="al-footer"><div id="resultCopy" class="al-result-copy">Showing 0 records</div><div id="logsPagination" class="al-pagination"></div></div>
    </section>
</div>

<div class="modal fade" id="activityLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title">Activity Log Details</h5><small id="detailSubtitle" class="text-muted">Complete audit information</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div id="detailLoading" class="al-empty">Loading details...</div><div id="detailContent" style="display:none">
            <div class="al-detail-grid">
                <div class="al-detail-item"><small>School</small><strong id="detailSchool">-</strong></div>
                <div class="al-detail-item"><small>School ID</small><strong id="detailSchoolId">-</strong></div>
                <div class="al-detail-item"><small>Branch</small><strong id="detailBranch">-</strong></div>
                <div class="al-detail-item"><small>Branch ID</small><strong id="detailBranchId">-</strong></div>
                <div class="al-detail-item"><small>Role</small><strong id="detailRole">-</strong></div>
                <div class="al-detail-item"><small>User</small><strong id="detailUser">-</strong></div>
                <div class="al-detail-item"><small>Date & Time</small><strong id="detailDate">-</strong></div>
                <div class="al-detail-item"><small>Module</small><strong id="detailModule">-</strong></div>
                <div class="al-detail-item"><small>Action</small><strong id="detailAction">-</strong></div>
                <div class="al-detail-item"><small>Table</small><strong id="detailTable">-</strong></div>
                <div class="al-detail-item"><small>Record ID</small><strong id="detailRecord">-</strong></div>
                <div class="al-detail-item"><small>IP Address</small><strong id="detailIp">-</strong></div>
                <div class="al-detail-item full"><small>Description</small><span id="detailDescription">-</span></div>
                <div class="al-detail-item full"><small>User Agent</small><span id="detailAgent">-</span></div>
            </div>
            <div class="al-change-grid"><div><div class="al-change-title"><i data-lucide="history"></i> Previous Values</div><pre id="detailOldValues" class="al-json-box">No previous values.</pre></div><div><div class="al-change-title"><i data-lucide="sparkles"></i> New Values</div><pre id="detailNewValues" class="al-json-box">No new values.</pre></div></div>
        </div></div>
    </div></div>
</div>

<script>
(function(){
'use strict';

const apiUrl=new URL('../api/activity-logs.php',window.location.href).href;
const $=id=>document.getElementById(id);
const state={page:1,perPage:20,totalPages:1,busy:false,meta:null,latestId:0,lastMetaAt:0};
const LIVE_REFRESH_MS=5000;
const META_REFRESH_MS=30000;
let searchTimer=null;
let liveTimer=null;

function esc(value){return String(value??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;')}
function showMessage(message,success=false){const box=$('logsMessage');box.className='alert al-message show '+(success?'alert-success':'alert-danger');box.textContent=message}
function clearMessage(){const box=$('logsMessage');box.className='alert al-message';box.textContent=''}
function setBusy(busy){state.busy=busy;$('logsLoading').classList.toggle('show',busy);['refreshLogsBtn','applyFiltersBtn','clearFiltersBtn','exportLogsBtn'].forEach(id=>{const button=$(id);if(button)button.disabled=busy})}
function setLiveStatus(text='Live'){if($('liveStatusText'))$('liveStatusText').textContent=text}
function formatDate(value){if(!value)return '-';const date=new Date(String(value).replace(' ','T'));if(Number.isNaN(date.getTime()))return String(value);return new Intl.DateTimeFormat('en-IN',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}).format(date)}
function initials(name){return String(name||'S').trim().split(/\s+/).filter(Boolean).slice(0,2).map(word=>word.charAt(0).toUpperCase()).join('')||'S'}
function formatAction(action){return String(action||'-').replace(/[_-]+/g,' ').replace(/\b\w/g,char=>char.toUpperCase())}

async function request(action,params={}){const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(params).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value))});const response=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Activity Logs API returned HTTP ${response.status}.`)}if(!response.ok||!result.success)throw new Error(result.message||'Activity Logs request failed.');return result}

function selectValue(id){return String($(id)?.value||'').trim()}
function fillSelect(id,rows,placeholder,preferred=''){const select=$(id);const before=preferred||select.value;select.innerHTML=`<option value="">${esc(placeholder)}</option>`+(rows||[]).map(row=>`<option value="${esc(row.value??row.id)}">${esc(row.label??row.branch_name??row.value)}</option>`).join('');if([...select.options].some(option=>option.value===String(before)))select.value=String(before)}
function selectedText(id,fallback){const select=$(id);return select?.selectedOptions?.[0]?.textContent?.trim()||fallback}

function metaParams(){return{branch_id:selectValue('branchFilter'),role_id:selectValue('roleFilter'),user_id:selectValue('userFilter')}}
async function loadMeta({preserve=true}={}){
    const old={branch:selectValue('branchFilter'),role:selectValue('roleFilter'),user:selectValue('userFilter'),module:selectValue('moduleFilter'),action:selectValue('actionFilter')};
    const result=await request('meta',preserve?metaParams():{});state.meta=result.data||{};state.lastMetaAt=Date.now();
    const school=state.meta.school||{};$('schoolFilter').value=`${school.school_name||'Current School'}${school.tenant_code?' ('+school.tenant_code+')':''}`;
    const branches=(state.meta.branches||[]).map(row=>({value:row.id,label:`${row.branch_name}${row.branch_code?' ('+row.branch_code+')':''}`}));
    fillSelect('branchFilter',branches,'Select Branch',state.meta.selected_branch_id||old.branch);
    $('branchFilter').disabled=branches.length<=1;
    fillSelect('roleFilter',state.meta.roles||[],'All Roles in Branch',state.meta.selected_role_id||old.role);
    fillSelect('userFilter',state.meta.users||[],'All Users in Branch',state.meta.selected_user_id||old.user);
    fillSelect('moduleFilter',state.meta.modules||[],'All Modules',old.module);
    fillSelect('actionFilter',state.meta.actions||[],'All Actions',old.action);
    updateScopePath();
}

function updateScopePath(){const school=state.meta?.school?.school_name||'School';const branch=selectedText('branchFilter','Branch');const role=selectValue('roleFilter')?selectedText('roleFilter','Role'):'All Roles';const user=selectValue('userFilter')?selectedText('userFilter','User'):'All Users';$('scopePath').innerHTML=`<span>${esc(school)}</span><i data-lucide="chevron-right"></i><span>${esc(branch)}</span><i data-lucide="chevron-right"></i><span>${esc(role)}</span><i data-lucide="chevron-right"></i><span>${esc(user)}</span><i data-lucide="chevron-right"></i><span>Activity</span>`;$('activityHeading').textContent=`${branch} Activity History`;window.lucide?.createIcons()}

function filterParams(includePaging=true){const params=new URLSearchParams();params.set('action','list');if(includePaging){params.set('page',String(state.page));params.set('per_page',String(state.perPage))}const map={branch_id:'branchFilter',role_id:'roleFilter',user_id:'userFilter',search:'searchLogs',module:'moduleFilter',action_key:'actionFilter',date_from:'dateFrom',date_to:'dateTo'};Object.entries(map).forEach(([key,id])=>{const value=selectValue(id);if(value)params.set(key,value)});return params}

function renderStats(summary={}){$('statTotal').textContent=Number(summary.filtered_total||0).toLocaleString('en-IN');$('statToday').textContent=Number(summary.today_total||0).toLocaleString('en-IN');$('statUsers').textContent=Number(summary.user_total||0).toLocaleString('en-IN');$('statModules').textContent=Number(summary.module_total||0).toLocaleString('en-IN')}

function renderRows(rows){const body=$('logsTableBody');if(!rows.length){body.innerHTML='<tr><td colspan="11"><div class="al-empty"><i data-lucide="inbox"></i><br>No activity logs found for the selected School → Branch → Role → User scope.</div></td></tr>';window.lucide?.createIcons();return}body.innerHTML=rows.map(row=>{const record=row.table_name?`${row.table_name}${row.record_id?' #'+row.record_id:''}`:(row.record_id?'#'+row.record_id:'-');const userSub=row.username?'@'+row.username:'System activity';return `<tr><td><strong>${esc(formatDate(row.created_at))}</strong></td><td><div class="al-school"><strong>${esc(row.school_name||'-')}</strong><small>School ID: ${esc(row.school_id||'-')}</small></div></td><td class="al-branch"><strong>${esc(row.branch_name||'-')}</strong><small>Branch ID: ${esc(row.branch_id||'-')}</small></td><td>${esc(row.role_name||'School Role')}</td><td><div class="al-user"><span class="al-avatar">${esc(initials(row.user_name))}</span><div><strong>${esc(row.user_name||'System')}</strong><small>${esc(userSub)}</small></div></div></td><td class="al-module">${esc(row.module_name||'-')}</td><td><span class="al-action-badge ${esc(row.action_group||'')}">${esc(row.action_label||formatAction(row.action_key))}</span></td><td class="al-description">${esc(row.description||'No description')}</td><td class="al-record">${esc(record)}</td><td class="al-ip">${esc(row.ip_address||'-')}</td><td><button class="al-view-btn" type="button" data-view-log="${Number(row.id)}" title="View details"><i data-lucide="eye"></i></button></td></tr>`}).join('');window.lucide?.createIcons()}

function pageButtons(current,total){const pages=[];const start=Math.max(1,current-2);const end=Math.min(total,current+2);if(start>1){pages.push(1);if(start>2)pages.push('...')}for(let i=start;i<=end;i++)pages.push(i);if(end<total){if(end<total-1)pages.push('...');pages.push(total)}return pages}
function renderPagination(pagination={}){state.page=Number(pagination.page||1);state.totalPages=Number(pagination.total_pages||1);state.perPage=Number(pagination.per_page||state.perPage);$('perPageFilter').value=String(state.perPage);$('resultCopy').textContent=pagination.total?`Showing ${pagination.from} to ${pagination.to} of ${Number(pagination.total).toLocaleString('en-IN')} logs`:'Showing 0 logs';$('tableSubtitle').textContent=pagination.total?`${Number(pagination.total).toLocaleString('en-IN')} activities in ${selectedText('branchFilter','the selected branch')}.`:`No activities found in ${selectedText('branchFilter','the selected branch')}.`;const buttons=[];buttons.push(`<button class="al-page-btn" data-page="${state.page-1}" ${state.page<=1?'disabled':''}>‹</button>`);pageButtons(state.page,state.totalPages).forEach(page=>{if(page==='...')buttons.push('<button class="al-page-btn" disabled>…</button>');else buttons.push(`<button class="al-page-btn ${Number(page)===state.page?'active':''}" data-page="${page}">${page}</button>`)});buttons.push(`<button class="al-page-btn" data-page="${state.page+1}" ${state.page>=state.totalPages?'disabled':''}>›</button>`);$('logsPagination').innerHTML=buttons.join('')}

async function loadLogs(silent=false){if(!selectValue('branchFilter')){renderRows([]);return}if(!silent){clearMessage();setBusy(true)}try{const params=Object.fromEntries(filterParams(true));delete params.action;const result=await request('list',params);const previous=state.latestId;state.latestId=Number(result.data.latest_id||0);renderStats(result.data.summary||{});renderRows(result.data.rows||[]);renderPagination(result.data.pagination||{});if(silent){setLiveStatus(previous>0&&state.latestId>previous?'New activity':'Live');window.setTimeout(()=>setLiveStatus('Live'),1200)}}catch(error){if(!silent){showMessage(error.message,false);$('logsTableBody').innerHTML='<tr><td colspan="11"><div class="al-empty">Unable to load activity logs.</div></td></tr>'}else setLiveStatus('Retrying…')}finally{if(!silent)setBusy(false)}}

function prettyJson(value,emptyText){const text=String(value||'').trim();if(!text)return emptyText;try{return JSON.stringify(JSON.parse(text),null,2)}catch{return text}}
async function openDetail(id){const modalElement=$('activityLogModal');const modal=window.bootstrap?.Modal.getOrCreateInstance(modalElement);$('detailLoading').style.display='block';$('detailContent').style.display='none';modal?.show();try{const result=await request('detail',{id,branch_id:selectValue('branchFilter')});const log=result.data.log||{};$('detailSubtitle').textContent=`Log #${log.id||id}`;$('detailSchool').textContent=log.school_name||'-';$('detailSchoolId').textContent=log.school_id||'-';$('detailBranch').textContent=log.branch_name||'-';$('detailBranchId').textContent=log.branch_id||log.effective_branch_id||'-';$('detailRole').textContent=log.role_name||'School Role';$('detailUser').textContent=`${log.user_name||'System'}${log.username?' (@'+log.username+')':''}${log.user_email?' · '+log.user_email:''}`;$('detailDate').textContent=formatDate(log.created_at);$('detailModule').textContent=log.module_name||'-';$('detailAction').textContent=log.action_label||formatAction(log.action_key);$('detailTable').textContent=log.table_name||'-';$('detailRecord').textContent=log.record_id||'-';$('detailIp').textContent=log.ip_address||'-';$('detailDescription').textContent=log.description||'No description';$('detailAgent').textContent=log.user_agent||'-';$('detailOldValues').textContent=prettyJson(log.old_values,'No previous values.');$('detailNewValues').textContent=prettyJson(log.new_values,'No new values.');$('detailLoading').style.display='none';$('detailContent').style.display='block';window.lucide?.createIcons()}catch(error){$('detailLoading').textContent=error.message}}

function localDate(date){const offset=date.getTimezoneOffset();return new Date(date.getTime()-offset*60000).toISOString().slice(0,10)}
function applyRange(range){const today=new Date();let from='';let to='';if(range==='today'){from=to=localDate(today)}else if(range==='month'){from=localDate(new Date(today.getFullYear(),today.getMonth(),1));to=localDate(today)}else if(range==='7'||range==='30'){const days=Number(range);const start=new Date(today);start.setDate(today.getDate()-(days-1));from=localDate(start);to=localDate(today)}$('dateFrom').value=from;$('dateTo').value=to;state.page=1;loadLogs()}

async function refreshHierarchy(level){if(level==='branch'){ $('roleFilter').value='';$('userFilter').value='';$('moduleFilter').value='';$('actionFilter').value='' }if(level==='role'){ $('userFilter').value='';$('moduleFilter').value='';$('actionFilter').value='' }if(level==='user'){ $('moduleFilter').value='';$('actionFilter').value='' }state.page=1;setBusy(true);try{await loadMeta({preserve:true});await loadLogs()}catch(error){showMessage(error.message,false)}finally{setBusy(false)}}
async function clearFilters(){['searchLogs','dateFrom','dateTo','roleFilter','userFilter','moduleFilter','actionFilter'].forEach(id=>{if($(id))$(id).value=''});state.page=1;state.perPage=20;$('perPageFilter').value='20';setBusy(true);try{await loadMeta({preserve:true});await loadLogs()}catch(error){showMessage(error.message,false)}finally{setBusy(false)}}
function exportCsv(){if(!selectValue('branchFilter')){showMessage('Select a branch before exporting.',false);return}const url=new URL(apiUrl);const params=filterParams(false);params.set('action','export');params.forEach((value,key)=>url.searchParams.set(key,value));window.location.href=url.href}

async function liveRefresh(){if(document.hidden||state.busy||state.page!==1||!selectValue('branchFilter'))return;if(Date.now()-state.lastMetaAt>=META_REFRESH_MS){try{await loadMeta({preserve:true})}catch(error){console.warn('Activity log meta refresh:',error)}}await loadLogs(true)}
function startLiveRefresh(){if(liveTimer)window.clearInterval(liveTimer);liveTimer=window.setInterval(liveRefresh,LIVE_REFRESH_MS)}
document.addEventListener('visibilitychange',()=>{if(!document.hidden&&state.page===1)liveRefresh()});

$('branchFilter').addEventListener('change',()=>refreshHierarchy('branch'));
$('roleFilter').addEventListener('change',()=>refreshHierarchy('role'));
$('userFilter').addEventListener('change',()=>refreshHierarchy('user'));
['moduleFilter','actionFilter','dateFrom','dateTo'].forEach(id=>$(id).addEventListener('change',()=>{state.page=1;loadLogs()}));
$('perPageFilter').addEventListener('change',()=>{state.page=1;state.perPage=Number($('perPageFilter').value||20);loadLogs()});
$('searchLogs').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{state.page=1;loadLogs()},450)});
$('applyFiltersBtn').addEventListener('click',()=>{state.page=1;state.perPage=Number($('perPageFilter').value||20);loadLogs()});
$('clearFiltersBtn').addEventListener('click',clearFilters);
$('refreshLogsBtn').addEventListener('click',async()=>{setBusy(true);try{await loadMeta({preserve:true});await loadLogs()}catch(error){showMessage(error.message,false)}finally{setBusy(false)}});
$('printLogsBtn').addEventListener('click',()=>window.print());$('exportLogsBtn').addEventListener('click',exportCsv);
document.querySelectorAll('[data-range]').forEach(button=>button.addEventListener('click',()=>applyRange(button.dataset.range)));
$('logsPagination').addEventListener('click',event=>{const button=event.target.closest('[data-page]');if(!button||button.disabled)return;state.page=Math.min(state.totalPages,Math.max(1,Number(button.dataset.page||1)));loadLogs()});
$('logsTableBody').addEventListener('click',event=>{const button=event.target.closest('[data-view-log]');if(button)openDetail(Number(button.dataset.viewLog))});

(async function init(){setBusy(true);try{await loadMeta({preserve:false});await loadLogs();startLiveRefresh();window.lucide?.createIcons()}catch(error){showMessage(error.message,false)}finally{setBusy(false)}})();
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
