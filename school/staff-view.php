<?php
declare(strict_types=1);

$pageTitle = 'Staff Profile';
$pageKey = 'teachers';
require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['staff_csrf_token']) || !is_string($_SESSION['staff_csrf_token'])) {
    $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
}
$staffCsrf = $_SESSION['staff_csrf_token'];
$staffId = max(0, (int) ($_GET['id'] ?? 0));
$section = strtolower(trim((string) ($_GET['section'] ?? '')));
$printProfile = (int) ($_GET['print'] ?? 0) === 1;
?>
<style>
.sm-page{display:grid;gap:16px}
.sm-page .page-title{font-size:28px;line-height:1.1}
.sm-page .page-subtitle{margin-top:4px}
.sm-page .page-actions{gap:10px}
.sm-message{display:none}.sm-message.show{display:block}
.sm-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.sm-stat{border:0;border-radius:14px;min-height:110px;padding:18px 20px;display:flex;align-items:center;gap:14px;color:#fff;position:relative;overflow:hidden;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.sm-stat::after{content:"";position:absolute;width:110px;height:110px;border-radius:50%;right:-38px;top:-40px;background:rgba(255,255,255,.08)}
.sm-stat.purple{background:linear-gradient(135deg,#7158e8,#4f46d9)}
.sm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.sm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}
.sm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.sm-stat-icon{width:50px;height:50px;border-radius:50%;background:rgba(255,255,255,.16);display:grid;place-items:center;flex:0 0 auto}
.sm-stat-icon svg{width:25px;height:25px}
.sm-stat strong{display:block;font-size:26px;line-height:1}
.sm-stat small{display:block;font-size:11px;font-weight:700;opacity:.94;margin-bottom:6px}
.sm-stat .trend{font-size:9px;font-weight:700;opacity:.94;margin-top:8px}
.sm-card{border-radius:14px;overflow:hidden}
.sm-card-head{padding:14px 16px;border-bottom:1px solid var(--border-soft,#e7ebf3);display:flex;align-items:center;justify-content:space-between;gap:10px}
.sm-card-head strong{font-size:14px}
.sm-card-body{padding:16px}
.sm-profile{display:flex;align-items:center;gap:16px}
.sm-profile-photo{width:86px;height:86px;border-radius:50%;display:grid;place-items:center;color:#fff;font-size:24px;font-weight:800;background:linear-gradient(135deg,#6d4ce7,#345fe0);overflow:hidden;flex:0 0 auto}
.sm-profile-photo img{width:100%;height:100%;object-fit:cover}
.sm-profile h2{font-size:22px;margin:0 0 5px}.sm-profile p{margin:3px 0}
.sm-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.sm-detail{padding:12px;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;background:rgba(99,102,241,.035)}
.sm-detail small{display:block;font-size:9px;font-weight:700;color:var(--text-muted,#64748b)}
.sm-detail strong{display:block;margin-top:5px;font-size:12px;word-break:break-word}
.sm-table-wrap{overflow:auto}
.sm-table{min-width:900px}
.sm-table th{font-size:10px;white-space:nowrap}.sm-table td{font-size:11px;vertical-align:middle}
.sm-empty{padding:32px 18px!important;text-align:center!important;color:var(--text-muted,#64748b)}
.sm-badge{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:9px;font-weight:800;text-transform:capitalize;white-space:nowrap}
.sm-badge.active,.sm-badge.present,.sm-badge.approved,.sm-badge.paid{color:#16834f;background:#e8f8ef}
.sm-badge.inactive,.sm-badge.pending,.sm-badge.late,.sm-badge.half_day,.sm-badge.hold{color:#9a6700;background:#fff7d6}
.sm-badge.left,.sm-badge.absent,.sm-badge.rejected,.sm-badge.cancelled{color:#dc2626;background:#fff0f1}
.sm-badge.leave,.sm-badge.on_duty,.sm-badge.holiday{color:#1d4ed8;background:#eaf2ff}
.sm-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.sm-form-grid .full{grid-column:1/-1}
#attendanceModal .modal-dialog,#leaveModal .modal-dialog,#salaryModal .modal-dialog{max-height:calc(100dvh - 32px);margin:16px auto}
#attendanceModal .modal-content,#leaveModal .modal-content,#salaryModal .modal-content{max-height:calc(100dvh - 32px);overflow:hidden}
#attendanceModal form,#leaveModal form,#salaryModal form{display:flex;flex-direction:column;max-height:calc(100dvh - 32px)}
#attendanceModal .modal-body,#leaveModal .modal-body,#salaryModal .modal-body{overflow-y:auto;min-height:0}
@media(max-width:900px){.sm-stats,.sm-detail-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:575px){.sm-stats,.sm-detail-grid,.sm-form-grid{grid-template-columns:1fr}.sm-form-grid .full{grid-column:auto}.sm-card-head,.sm-profile{align-items:flex-start;flex-direction:column}}
@media print{
    .page-actions,.no-print,.modal,.modal-backdrop{display:none!important}
    .sm-page{gap:10px}.sm-card{break-inside:avoid}.sm-table{min-width:0;width:100%}
}
</style>

<div class="sm-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Staff Profile</h1>
            <p class="page-subtitle">Complete staff information, attendance, leave and salary details.</p>
        </div>
        <div class="page-actions no-print">
            <a class="btn-ui" href="teachers.php"><i data-lucide="arrow-left"></i> Staff List</a>
            <a id="editStaffBtn" class="btn-ui" href="#"><i data-lucide="pencil"></i> Edit</a>
            <button id="printBtn" class="btn-ui btn-primary-ui" type="button"><i data-lucide="printer"></i> Print Profile</button>
        </div>
    </div>

    <div id="profileMessage" class="alert sm-message"></div>

    <section class="ui-card sm-card">
        <div class="sm-card-body">
            <div class="sm-profile">
                <div id="profilePhoto" class="sm-profile-photo">?</div>
                <div>
                    <h2 id="profileName">Loading...</h2>
                    <p><strong id="profileCode">-</strong> · <span id="profileDesignation">-</span></p>
                    <p class="text-muted"><span id="profileMobile">-</span> · <span id="profileStatus">-</span></p>
                </div>
            </div>
        </div>
    </section>

    <section class="sm-stats">
        <article class="sm-stat purple"><span class="sm-stat-icon"><i data-lucide="calendar-check"></i></span><div><small>Present Days</small><strong id="statPresent">0</strong><div class="trend">Current month</div></div></article>
        <article class="sm-stat green"><span class="sm-stat-icon"><i data-lucide="calendar-x"></i></span><div><small>Absent Days</small><strong id="statAbsent">0</strong><div class="trend">Current month</div></div></article>
        <article class="sm-stat blue"><span class="sm-stat-icon"><i data-lucide="calendar-clock"></i></span><div><small>Approved Leave</small><strong id="statLeave">0</strong><div class="trend">Current year</div></div></article>
        <article class="sm-stat orange"><span class="sm-stat-icon"><i data-lucide="badge-indian-rupee"></i></span><div><small>Basic Salary</small><strong id="statSalary">₹0</strong><div class="trend">Current staff master</div></div></article>
    </section>

    <section class="ui-card sm-card" id="informationSection">
        <div class="sm-card-head"><strong>Complete Staff Information</strong><small class="text-muted">Personal and employment details.</small></div>
        <div id="detailGrid" class="sm-card-body sm-detail-grid"><div class="sm-detail"><small>Status</small><strong>Loading...</strong></div></div>
    </section>

    <section class="ui-card sm-card" id="attendanceSection">
        <div class="sm-card-head"><strong>Attendance Summary</strong><button id="addAttendanceBtn" class="btn-ui btn-sm no-print" type="button"><i data-lucide="plus"></i> Record Attendance</button></div>
        <div class="sm-card-body sm-detail-grid">
            <div class="sm-detail"><small>Marked Days</small><strong id="attendanceMarked">0</strong></div>
            <div class="sm-detail"><small>Present</small><strong id="attendancePresent">0</strong></div>
            <div class="sm-detail"><small>Absent</small><strong id="attendanceAbsent">0</strong></div>
            <div class="sm-detail"><small>Late / Half Day</small><strong id="attendanceLate">0</strong></div>
        </div>
        <div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Remarks</th></tr></thead><tbody id="attendanceBody"><tr><td colspan="5" class="sm-empty">Loading...</td></tr></tbody></table></div>
    </section>

    <section class="ui-card sm-card" id="leaveSection">
        <div class="sm-card-head"><strong>Leave Summary</strong><button id="addLeaveBtn" class="btn-ui btn-sm no-print" type="button"><i data-lucide="plus"></i> Add Leave</button></div>
        <div class="sm-card-body sm-detail-grid">
            <div class="sm-detail"><small>Approved Days</small><strong id="leaveApprovedDays">0</strong></div>
            <div class="sm-detail"><small>Pending Requests</small><strong id="leavePending">0</strong></div>
            <div class="sm-detail"><small>Approved Requests</small><strong id="leaveApproved">0</strong></div>
            <div class="sm-detail"><small>Rejected Requests</small><strong id="leaveRejected">0</strong></div>
        </div>
        <div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead><tbody id="leaveBody"><tr><td colspan="6" class="sm-empty">Loading...</td></tr></tbody></table></div>
    </section>

    <section class="ui-card sm-card" id="salarySection">
        <div class="sm-card-head"><strong>Salary Details</strong><button id="addSalaryBtn" class="btn-ui btn-sm no-print" type="button"><i data-lucide="plus"></i> Add Salary</button></div>
        <div class="sm-card-body sm-detail-grid">
            <div class="sm-detail"><small>Basic Salary</small><strong id="salaryBasic">₹0</strong></div>
            <div class="sm-detail"><small>Latest Salary Month</small><strong id="salaryMonth">-</strong></div>
            <div class="sm-detail"><small>Latest Net Salary</small><strong id="salaryNet">₹0</strong></div>
            <div class="sm-detail"><small>Latest Payment Status</small><strong id="salaryStatus">-</strong></div>
        </div>
        <div class="sm-table-wrap"><table class="data-table sm-table"><thead><tr><th>Month</th><th>Basic</th><th>Allowances</th><th>Deductions</th><th>Net Salary</th><th>Payment Date</th><th>Status</th></tr></thead><tbody id="salaryBody"><tr><td colspan="7" class="sm-empty">Loading...</td></tr></tbody></table></div>
    </section>
</div>

<div class="modal fade" id="attendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="attendanceForm" novalidate>
        <div class="modal-header"><div><h5 class="modal-title">Record Attendance</h5><small class="text-muted">Existing attendance for the selected date will be updated.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="sm-form-grid">
            <div><label class="form-label">Attendance Date *</label><input id="attendanceDate" class="form-control" type="date" required></div>
            <div><label class="form-label">Status *</label><select id="attendanceStatus" class="form-select" required><option value="present">Present</option><option value="absent">Absent</option><option value="half_day">Half Day</option><option value="late">Late</option><option value="leave">Leave</option><option value="on_duty">On Duty</option><option value="holiday">Holiday</option></select></div>
            <div><label class="form-label">Check In</label><input id="checkIn" class="form-control" type="time"></div>
            <div><label class="form-label">Check Out</label><input id="checkOut" class="form-control" type="time"></div>
            <div class="full"><label class="form-label">Remarks</label><textarea id="attendanceRemarks" class="form-control" rows="3" maxlength="255"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Attendance</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="leaveModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="leaveForm" novalidate>
        <div class="modal-header"><div><h5 class="modal-title">Add Leave</h5><small class="text-muted">Record pending, approved or rejected leave.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="sm-form-grid">
            <div><label class="form-label">Leave Type *</label><select id="leaveType" class="form-select" required><option value="casual">Casual Leave</option><option value="sick">Sick Leave</option><option value="earned">Earned Leave</option><option value="unpaid">Unpaid Leave</option><option value="other">Other</option></select></div>
            <div><label class="form-label">Status *</label><select id="leaveStatus" class="form-select" required><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></div>
            <div><label class="form-label">From Date *</label><input id="leaveFrom" class="form-control" type="date" required></div>
            <div><label class="form-label">To Date *</label><input id="leaveTo" class="form-control" type="date" required></div>
            <div class="full"><label class="form-label">Reason</label><textarea id="leaveReason" class="form-control" rows="3" maxlength="500"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Leave</button></div>
    </form></div></div>
</div>

<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form id="salaryForm" novalidate>
        <div class="modal-header"><div><h5 class="modal-title">Add Salary Details</h5><small class="text-muted">Saving the same month again updates that salary record.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="sm-form-grid">
            <div><label class="form-label">Salary Month *</label><input id="salaryRecordMonth" class="form-control" type="month" required></div>
            <div><label class="form-label">Basic Salary *</label><input id="salaryRecordBasic" class="form-control" type="number" min="0" step="0.01" required></div>
            <div><label class="form-label">Allowances</label><input id="salaryAllowances" class="form-control" type="number" min="0" step="0.01" value="0"></div>
            <div><label class="form-label">Deductions</label><input id="salaryDeductions" class="form-control" type="number" min="0" step="0.01" value="0"></div>
            <div><label class="form-label">Net Salary</label><input id="salaryNetPreview" class="form-control" readonly></div>
            <div><label class="form-label">Payment Status *</label><select id="salaryPaymentStatus" class="form-select" required><option value="pending">Pending</option><option value="paid">Paid</option><option value="hold">Hold</option></select></div>
            <div><label class="form-label">Payment Date</label><input id="salaryPaymentDate" class="form-control" type="date"></div>
            <div><label class="form-label">Payment Method</label><select id="salaryPaymentMethod" class="form-select"><option value="">Select Method</option><option value="Cash">Cash</option><option value="UPI">UPI</option><option value="Bank">Bank Transfer</option><option value="Cheque">Cheque</option></select></div>
            <div><label class="form-label">Reference Number</label><input id="salaryReference" class="form-control" maxlength="100"></div>
            <div class="full"><label class="form-label">Remarks</label><textarea id="salaryRemarks" class="form-control" rows="3" maxlength="255"></textarea></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn-ui" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn-ui btn-primary-ui"><i data-lucide="save"></i> Save Salary</button></div>
    </form></div></div>
</div>

<script>
(function(){
'use strict';
const staffId=<?=json_encode($staffId)?>,requestedSection=<?=json_encode($section)?>,autoPrint=<?=json_encode($printProfile)?>;
const apiUrl=new URL('../api/staff.php',window.location.href).href;
let csrfToken=<?=json_encode($staffCsrf)?>,profile=null;
const $=id=>document.getElementById(id);
const esc=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
const money=value=>new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:2}).format(Number(value||0));
const badge=value=>`<span class="sm-badge ${esc(String(value||'').toLowerCase())}">${esc(String(value||'-').replaceAll('_',' '))}</span>`;
function localToday(){const now=new Date();return new Date(now.getTime()-now.getTimezoneOffset()*60000).toISOString().slice(0,10)}
function currentMonth(){return localToday().slice(0,7)}
function photoUrl(path){if(!path)return'';if(/^https?:\/\//i.test(path)||String(path).startsWith('/'))return String(path);return new URL('../'+String(path).replace(/^\.\//,''),window.location.href).href}
function message(text,success=false){const box=$('profileMessage');box.className='alert sm-message show '+(success?'alert-success':'alert-danger');box.textContent=text;clearTimeout(box._timer);box._timer=setTimeout(()=>box.className='alert sm-message',7000)}
async function request(action,data={},method='GET'){
    let response;
    if(method==='GET'){
        const url=new URL(apiUrl);url.searchParams.set('action',action);Object.entries(data).forEach(([key,value])=>{if(value!==''&&value!==null&&value!==undefined)url.searchParams.set(key,String(value))});response=await fetch(url,{headers:{Accept:'application/json'},credentials:'same-origin'});
    }else response=await fetch(apiUrl,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},credentials:'same-origin',body:JSON.stringify({action,csrf_token:csrfToken,...data})});
    const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(`Staff API returned HTTP ${response.status}. ${text.replace(/\s+/g,' ').trim().slice(0,220)||'Invalid server response.'}`)}if(!response.ok||!result.success)throw new Error(result.message||'Request failed.');if(result.data?.csrf_token)csrfToken=result.data.csrf_token;return result;
}
function initials(record){return((record.first_name||'').charAt(0)+(record.last_name||'').charAt(0)).toUpperCase()||'?'}
function detail(label,value){return`<div class="sm-detail"><small>${esc(label)}</small><strong>${esc(value||'-')}</strong></div>`}
function renderProfile(data){
    profile=data;const r=data.record||{},a=data.attendance_summary||{},l=data.leave_summary||{},latest=data.salary_summary||null;
    $('profileName').textContent=r.staff_name||'-';$('profileCode').textContent=r.staff_code||'-';$('profileDesignation').textContent=r.designation_name||'-';$('profileMobile').textContent=r.mobile||'-';$('profileStatus').innerHTML=badge(r.status);
    const src=photoUrl(r.photo_path);$('profilePhoto').innerHTML=src?`<img src="${esc(src)}" alt="${esc(r.staff_name)}" onerror="this.parentElement.textContent='${esc(initials(r))}'">`:esc(initials(r));
    $('editStaffBtn').href=`teachers.php?edit=${Number(r.id||0)}`;
    $('statPresent').textContent=Number(a.present_days||0);$('statAbsent').textContent=Number(a.absent_days||0);$('statLeave').textContent=Number(l.approved_days||0);$('statSalary').textContent=money(r.basic_salary||0);
    $('detailGrid').innerHTML=[
        detail('Staff ID',r.staff_code),detail('Staff Name',r.staff_name),detail('Gender',r.gender?String(r.gender).replaceAll('_',' '):'-'),detail('Date of Birth',r.date_of_birth_display),
        detail('Mobile Number',r.mobile),detail('Alternate Mobile',r.alternate_mobile),detail('Email',r.email),detail('Address',r.address),
        detail('Branch',r.branch_name),detail('Department',r.department_name),detail('Designation',r.designation_name),detail('Employment Type',r.employment_type?String(r.employment_type).replaceAll('_',' '):'-'),
        detail('Joining Date',r.joining_date_display),detail('Qualification',r.qualification),detail('Experience',`${Number(r.experience_years||0)} Year(s)`),detail('Basic Salary',money(r.basic_salary||0)),
        detail('Emergency Contact',r.emergency_contact_name),detail('Emergency Mobile',r.emergency_contact_mobile),detail('Status',r.status),detail('Notes',r.notes)
    ].join('');
    $('attendanceMarked').textContent=Number(a.marked_days||0);$('attendancePresent').textContent=Number(a.present_days||0);$('attendanceAbsent').textContent=Number(a.absent_days||0);$('attendanceLate').textContent=Number(a.late_days||0)+Number(a.half_days||0);
    $('attendanceBody').innerHTML=(data.attendance_records||[]).map(row=>`<tr><td>${esc(row.attendance_date_display||row.attendance_date)}</td><td>${badge(row.status)}</td><td>${esc(row.check_in_display||'-')}</td><td>${esc(row.check_out_display||'-')}</td><td>${esc(row.remarks||'-')}</td></tr>`).join('')||'<tr><td colspan="5" class="sm-empty">No attendance records found.</td></tr>';
    $('leaveApprovedDays').textContent=Number(l.approved_days||0);$('leavePending').textContent=Number(l.pending_requests||0);$('leaveApproved').textContent=Number(l.approved_requests||0);$('leaveRejected').textContent=Number(l.rejected_requests||0);
    $('leaveBody').innerHTML=(data.leave_records||[]).map(row=>`<tr><td>${esc(String(row.leave_type||'-').replaceAll('_',' '))}</td><td>${esc(row.from_date_display||row.from_date)}</td><td>${esc(row.to_date_display||row.to_date)}</td><td>${esc(row.total_days)}</td><td>${esc(row.reason||'-')}</td><td>${badge(row.status)}</td></tr>`).join('')||'<tr><td colspan="6" class="sm-empty">No leave records found.</td></tr>';
    $('salaryBasic').textContent=money(r.basic_salary||0);$('salaryMonth').textContent=latest?.salary_month_display||'-';$('salaryNet').textContent=money(latest?.net_salary||0);$('salaryStatus').innerHTML=latest?badge(latest.payment_status):'-';
    $('salaryBody').innerHTML=(data.salary_records||[]).map(row=>`<tr><td>${esc(row.salary_month_display||row.salary_month)}</td><td>${money(row.basic_salary)}</td><td>${money(row.allowances)}</td><td>${money(row.deductions)}</td><td><strong>${money(row.net_salary)}</strong></td><td>${esc(row.payment_date_display||'-')}</td><td>${badge(row.payment_status)}</td></tr>`).join('')||'<tr><td colspan="7" class="sm-empty">No salary records found.</td></tr>';
    window.lucide?.createIcons();
}
async function loadProfile(){if(staffId<=0){message('Staff record is invalid.');return}try{const result=await request('profile',{id:staffId});renderProfile(result.data);if(requestedSection){const target=$(`${requestedSection}Section`);target?.scrollIntoView({behavior:'smooth',block:'start'})}if(autoPrint)setTimeout(()=>window.print(),500)}catch(error){message(error.message)}}
function calculateNet(){const basic=Number($('salaryRecordBasic').value||0),allowances=Number($('salaryAllowances').value||0),deductions=Number($('salaryDeductions').value||0);$('salaryNetPreview').value=money(Math.max(0,basic+allowances-deductions))}
$('printBtn').onclick=()=>window.print();
$('addAttendanceBtn').onclick=()=>{$('attendanceForm').reset();$('attendanceDate').value=localToday();$('attendanceStatus').value='present';bootstrap.Modal.getOrCreateInstance($('attendanceModal')).show()};
$('addLeaveBtn').onclick=()=>{$('leaveForm').reset();$('leaveFrom').value=localToday();$('leaveTo').value=localToday();$('leaveStatus').value='pending';bootstrap.Modal.getOrCreateInstance($('leaveModal')).show()};
$('addSalaryBtn').onclick=()=>{$('salaryForm').reset();$('salaryRecordMonth').value=currentMonth();$('salaryRecordBasic').value=profile?.record?.basic_salary||0;$('salaryAllowances').value='0';$('salaryDeductions').value='0';$('salaryPaymentStatus').value='pending';calculateNet();bootstrap.Modal.getOrCreateInstance($('salaryModal')).show()};
$('attendanceForm').onsubmit=async event=>{event.preventDefault();if(!$('attendanceForm').checkValidity()){$('attendanceForm').reportValidity();return}try{const result=await request('save_attendance',{id:staffId,attendance_date:$('attendanceDate').value,status:$('attendanceStatus').value,check_in:$('checkIn').value,check_out:$('checkOut').value,remarks:$('attendanceRemarks').value.trim()},'POST');bootstrap.Modal.getInstance($('attendanceModal'))?.hide();message(result.message,true);await loadProfile()}catch(error){message(error.message)}};
$('leaveForm').onsubmit=async event=>{event.preventDefault();if(!$('leaveForm').checkValidity()){$('leaveForm').reportValidity();return}try{const result=await request('save_leave',{id:staffId,leave_type:$('leaveType').value,status:$('leaveStatus').value,from_date:$('leaveFrom').value,to_date:$('leaveTo').value,reason:$('leaveReason').value.trim()},'POST');bootstrap.Modal.getInstance($('leaveModal'))?.hide();message(result.message,true);await loadProfile()}catch(error){message(error.message)}};
$('salaryForm').onsubmit=async event=>{event.preventDefault();if(!$('salaryForm').checkValidity()){$('salaryForm').reportValidity();return}try{const result=await request('save_salary',{id:staffId,salary_month:$('salaryRecordMonth').value,basic_salary:Number($('salaryRecordBasic').value||0),allowances:Number($('salaryAllowances').value||0),deductions:Number($('salaryDeductions').value||0),payment_status:$('salaryPaymentStatus').value,payment_date:$('salaryPaymentDate').value,payment_method:$('salaryPaymentMethod').value,reference_no:$('salaryReference').value.trim(),remarks:$('salaryRemarks').value.trim()},'POST');bootstrap.Modal.getInstance($('salaryModal'))?.hide();message(result.message,true);await loadProfile()}catch(error){message(error.message)}};
['salaryRecordBasic','salaryAllowances','salaryDeductions'].forEach(id=>$(id).oninput=calculateNet);
$('salaryPaymentStatus').onchange=()=>{if($('salaryPaymentStatus').value==='paid'&&!$('salaryPaymentDate').value)$('salaryPaymentDate').value=localToday()};
loadProfile();window.lucide?.createIcons();
})();
</script>
<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
