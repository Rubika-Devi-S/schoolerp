<?php
declare(strict_types=1);

/*
 * Parent Attendance
 * Location: parent/attendance.php
 * API: parent/api/attendance.php
 * Build: 2026-08-13-parent-attendance-overview-removed-v2
 *
 * Uses the same Parent Dashboard shell, sidebar, card sizes, typography,
 * spacing and color variables. Attendance is view-only for Parent accounts.
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/sidebar-manager.php';
require_once dirname(__DIR__) . '/includes/permission-chain.php';

$user = function_exists('current_user') ? current_user() : [];
$user = is_array($user) ? $user : [];

if (
    empty($user)
    && empty($_SESSION['user_id'])
    && empty($_SESSION['guardian_id'])
    && empty($_SESSION['parent_guardian_id'])
) {
    foreach ([
        dirname(__DIR__) . '/login.php' => '../login.php',
        dirname(__DIR__) . '/auth/login.php' => '../auth/login.php',
    ] as $path => $url) {
        if (is_file($path)) {
            header('Location: ' . $url);
            exit;
        }
    }
}

$tenantId = (int)(
    $user['tenant_id']
    ?? $_SESSION['tenant_id']
    ?? $_SESSION['school_id']
    ?? 0
);
$roleId = (int)(
    $user['role_id']
    ?? $_SESSION['role_id']
    ?? 0
);

$role = isset($pdo) && $pdo instanceof PDO
    ? pc_role($pdo, $tenantId, $roleId)
    : [];

if (pc_role_key((string)($role['role_key'] ?? '')) !== 'parent') {
    http_response_code(403);
    exit('Parent Attendance is available only to Parent accounts.');
}

$parentSidebarItems = isset($pdo) && $pdo instanceof PDO
    ? school_sidebar_get_items($pdo, $roleId, $tenantId)
    : [];

$attendanceEnabled = false;
foreach ($parentSidebarItems as $menu) {
    if ((string)($menu['menu_key'] ?? '') === 'parent_attendance') {
        $attendanceEnabled = true;
        break;
    }
}

if (!$attendanceEnabled) {
    http_response_code(403);
    exit('Parent Attendance is disabled for this school.');
}

$pageTitle = 'Attendance';
$pageKey = 'parent_attendance';
$sidebarFile = dirname(__DIR__) . '/school/sidebar.php';

require dirname(__DIR__) . '/includes/layout-start.php';
?>

<style>
/* Exact Parent Dashboard component language retained. */
.parent-attendance-page{display:grid;gap:16px}

.parent-attendance-page .metric-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
}

.parent-attendance-page .metric-card{
    position:relative;
    min-width:0;
    min-height:124px;
    padding:20px 22px;
    display:flex;
    align-items:center;
    gap:16px;
    overflow:hidden;
    color:#fff;
    border:0;
    border-radius:15px;
    box-shadow:0 10px 24px rgba(15,23,42,.08);
    isolation:isolate;
}

.parent-attendance-page .metric-card::before{
    content:"";
    position:absolute;
    z-index:-1;
    width:132px;
    height:132px;
    right:-42px;
    top:-55px;
    border-radius:50%;
    background:rgba(255,255,255,.085);
}

.parent-attendance-page .metric-card::after{
    content:"";
    position:absolute;
    z-index:-1;
    width:52px;
    height:52px;
    right:14px;
    bottom:-31px;
    border-radius:50%;
    background:rgba(255,255,255,.045);
}

.parent-attendance-page .metric-purple{background:linear-gradient(135deg,#7548ee 0%,#5033d5 100%)}
.parent-attendance-page .metric-blue{background:linear-gradient(135deg,#4b96ed 0%,#2e73dc 100%)}
.parent-attendance-page .metric-green{background:linear-gradient(135deg,#43c987 0%,#20aa6f 100%)}
.parent-attendance-page .metric-orange{background:linear-gradient(135deg,#ffb22a 0%,#ff8b19 100%)}

.parent-attendance-page .metric-icon{
    position:relative;
    z-index:1;
    width:54px;
    height:54px;
    flex:0 0 54px;
    display:grid;
    place-items:center;
    color:#fff;
    background:rgba(255,255,255,.17);
    border:1px solid rgba(255,255,255,.09);
    border-radius:50%;
}

.parent-attendance-page .metric-icon svg{width:27px;height:27px;stroke-width:1.9}
.parent-attendance-page .metric-card>div:last-child{position:relative;z-index:1;min-width:0}
.parent-attendance-page .metric-card small{display:block;margin:0 0 5px;color:rgba(255,255,255,.94);font-size:11px;font-weight:700;line-height:1.2}
.parent-attendance-page .metric-value{overflow:hidden;color:#fff;font-size:clamp(27px,2.05vw,34px);font-weight:800;line-height:1;letter-spacing:-.035em;text-overflow:ellipsis;white-space:nowrap}
.parent-attendance-page .metric-trend{margin-top:8px;overflow:hidden;color:rgba(255,255,255,.92);font-size:9.5px;font-weight:650;line-height:1.3;text-overflow:ellipsis;white-space:nowrap}

.parent-attendance-page .parent-child-picker{
    min-width:220px;
    height:38px;
    display:flex;
    align-items:center;
    gap:8px;
    padding:0 10px;
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:9px;
    background:var(--card-bg,#fff);
}

.parent-attendance-page .parent-child-picker svg{width:16px;height:16px;color:var(--text-muted,#64748b)}
.parent-attendance-page .parent-child-picker select{width:100%;min-width:0;border:0;outline:0;background:transparent;color:var(--text-main,#101a3b);font-size:10px;font-weight:700}
.parent-attendance-page .parent-child-picker.filter-picker{min-width:150px}

.parent-attendance-page .dashboard-card{
    overflow:hidden;
    background:var(--card-bg,#fff);
    border:1px solid var(--border-soft,#e7ebf3);
    border-radius:13px;
    box-shadow:0 5px 18px rgba(15,23,42,.04);
}

.parent-attendance-page .dashboard-card-header{
    min-height:54px;
    padding:13px 16px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.parent-attendance-page .dashboard-card-header h2{margin:0;color:var(--text-main,#101a3b);font-size:14px;font-weight:700;letter-spacing:-.15px}
.parent-attendance-page .dashboard-card-subtitle{color:var(--text-muted,#64748b);font-size:10px}

.parent-attendance-page .student-overview-card{padding:16px 18px}
.parent-attendance-page .student-overview{display:flex;align-items:center;justify-content:space-between;gap:16px}
.parent-attendance-page .student-main{min-width:0;display:flex;align-items:center;gap:13px}
.parent-attendance-page .student-avatar{width:54px;height:54px;flex:0 0 54px;display:grid;place-items:center;overflow:hidden;color:#fff;background:linear-gradient(135deg,#7548ee,#5033d5);border-radius:50%;font-size:17px;font-weight:800}
.parent-attendance-page .student-avatar img{width:100%;height:100%;object-fit:cover}
.parent-attendance-page .student-copy{min-width:0}
.parent-attendance-page .student-copy h2{margin:0;color:var(--text-main,#101a3b);font-size:15px;font-weight:700}
.parent-attendance-page .student-copy p{margin:4px 0 0;color:var(--text-muted,#64748b);font-size:10px}
.parent-attendance-page .student-pills{display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-top:8px}
.parent-attendance-page .pill{display:inline-flex;align-items:center;min-height:22px;padding:3px 7px;color:#4f46e5;background:#eef2ff;border-radius:999px;font-size:8px;font-weight:700}

.parent-attendance-page .parent-table-wrap{overflow:auto}
.parent-attendance-page .parent-table{width:100%;min-width:820px;border-collapse:collapse}
.parent-attendance-page .parent-table th,
.parent-attendance-page .parent-table td{padding:10px 13px;text-align:left;vertical-align:middle;border-bottom:1px solid var(--border-soft,#e7ebf3)}
.parent-attendance-page .parent-table th{color:var(--text-muted,#64748b);background:#f8fafc;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.035em}
.parent-attendance-page .parent-table td{color:var(--text-main,#101a3b);font-size:9.5px;font-weight:500}
.parent-attendance-page .parent-table tbody tr:last-child td{border-bottom:0}

.parent-attendance-page .badge{display:inline-flex;align-items:center;padding:4px 7px;border-radius:999px;font-size:8px;font-weight:700;text-transform:capitalize}
.parent-attendance-page .badge.good{color:#158458;background:#e9f8f1}
.parent-attendance-page .badge.warn{color:#b4770b;background:#fff6dd}
.parent-attendance-page .badge.bad{color:#c83a55;background:#fff0f3}
.parent-attendance-page .badge.neutral{color:#65728b;background:#f0f3f8}

.parent-attendance-page .empty{padding:24px 12px;color:var(--text-muted,#64748b);font-size:9px;text-align:center}
.parent-attendance-page .parent-error{display:none;padding:11px 13px;color:#a4394d;background:#fff0f3;border:1px solid #ffd7df;border-radius:10px;font-size:9px}
.parent-attendance-page .parent-error.show{display:block}

.parent-attendance-loading{position:fixed;inset:0;z-index:5000;display:grid;place-items:center;background:rgba(245,247,251,.84);backdrop-filter:blur(2px)}
.parent-attendance-loading>div{padding:13px 17px;color:var(--text-main,#101a3b);background:#fff;border:1px solid var(--border-soft,#e7ebf3);border-radius:10px;box-shadow:0 10px 28px rgba(15,23,42,.08);font-size:10px;font-weight:700}

@media(max-width:1199.98px){.parent-attendance-page .metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:991.98px){.parent-attendance-page .student-overview{align-items:flex-start;flex-direction:column}}
@media(max-width:767.98px){.parent-attendance-page .page-actions{width:100%}.parent-attendance-page .parent-child-picker{width:100%;min-width:0}.parent-attendance-page .parent-child-picker.filter-picker{min-width:0}}
@media(max-width:575.98px){.parent-attendance-page .metric-grid{grid-template-columns:1fr;gap:12px}.parent-attendance-page .metric-card{min-height:108px;padding:17px 18px}.parent-attendance-page .metric-icon{width:48px;height:48px;flex-basis:48px}.parent-attendance-page .metric-icon svg{width:24px;height:24px}.parent-attendance-page .metric-value{font-size:28px}.parent-attendance-page .student-main{align-items:flex-start}.parent-attendance-page .student-avatar{width:48px;height:48px;flex-basis:48px}}
</style>

<div id="loading" class="parent-attendance-loading">
    <div>Loading Attendance...</div>
</div>

<div class="dashboard-page parent-attendance-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Attendance</h1>
            <p class="page-subtitle">View your child’s attendance records.</p>
        </div>

        <div class="page-actions">
            <label class="parent-child-picker" for="childSelect">
                <i data-lucide="users-round"></i>
                <select id="childSelect">
                    <option>Loading child...</option>
                </select>
            </label>

            <label class="parent-child-picker filter-picker" for="monthFilter">
                <i data-lucide="calendar-days"></i>
                <select id="monthFilter">
                    <option value="0">All Months</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </label>

            <label class="parent-child-picker filter-picker" for="yearFilter">
                <i data-lucide="calendar-range"></i>
                <select id="yearFilter">
                    <option>Loading year...</option>
                </select>
            </label>
        </div>
    </div>

    <div id="errorBox" class="parent-error"></div>

    <section class="metric-grid">
        <article class="metric-card metric-purple">
            <div class="metric-icon"><i data-lucide="calendar-check-2"></i></div>
            <div>
                <small>Total Days</small>
                <div id="totalDays" class="metric-value">0</div>
                <div id="attendancePercent" class="metric-trend">0.0% attendance</div>
            </div>
        </article>

        <article class="metric-card metric-green">
            <div class="metric-icon"><i data-lucide="circle-check-big"></i></div>
            <div>
                <small>Present</small>
                <div id="presentDays" class="metric-value">0</div>
                <div class="metric-trend">Present attendance records</div>
            </div>
        </article>

        <article class="metric-card metric-orange">
            <div class="metric-icon"><i data-lucide="circle-x"></i></div>
            <div>
                <small>Absent</small>
                <div id="absentDays" class="metric-value">0</div>
                <div class="metric-trend">Absent attendance records</div>
            </div>
        </article>

        <article class="metric-card metric-blue">
            <div class="metric-icon"><i data-lucide="calendar-heart"></i></div>
            <div>
                <small>Leave</small>
                <div id="leaveDays" class="metric-value">0</div>
                <div id="leaveNote" class="metric-trend">Late: 0 day(s)</div>
            </div>
        </article>
    </section>

    <article class="dashboard-card">
        <div class="dashboard-card-header">
            <h2>Attendance Records</h2>
            <span id="periodLabel" class="dashboard-card-subtitle">Selected period</span>
        </div>

        <div class="parent-table-wrap">
            <table class="parent-table">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>Status</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Late</th>
                    <th>Remarks</th>
                </tr>
                </thead>
                <tbody id="attendanceRows">
                    <tr><td colspan="7" class="empty">Loading attendance...</td></tr>
                </tbody>
            </table>
        </div>
    </article>
</div>

<script>
(() => {
    const $ = id => document.getElementById(id);
    const apiUrl = 'api/attendance.php';

    const esc = value => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const dateText = value => {
        if (!value) return '—';
        const raw = String(value).slice(0, 10);
        const date = new Date(`${raw}T00:00:00`);
        if (Number.isNaN(date.getTime())) return raw;
        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    };

    const dayText = value => {
        if (!value) return '—';
        const raw = String(value).slice(0, 10);
        const date = new Date(`${raw}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleDateString('en-IN', {weekday: 'short'});
    };

    const timeText = value => {
        if (!value) return '—';
        const bits = String(value).split(':');
        const date = new Date();
        date.setHours(Number(bits[0] || 0), Number(bits[1] || 0), 0, 0);
        return date.toLocaleTimeString('en-IN', {
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const statusText = status => String(status || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase());

    const badgeClass = status => {
        const value = String(status || '').toLowerCase();
        if (['present', 'on_duty'].includes(value)) return 'good';
        if (['late', 'leave', 'half_day'].includes(value)) return 'warn';
        if (value === 'absent') return 'bad';
        return 'neutral';
    };

    const monthName = month => {
        if (Number(month) === 0) return 'All Months';
        const date = new Date(2026, Number(month) - 1, 1);
        return date.toLocaleDateString('en-IN', {month: 'long'});
    };

    function showError(message = '') {
        const box = $('errorBox');
        box.textContent = message;
        box.classList.toggle('show', Boolean(message));
    }

    function showLoading(show) {
        $('loading').style.display = show ? 'grid' : 'none';
    }

    async function fetchAttendance() {
        const params = new URLSearchParams();
        const studentId = Number($('childSelect').value || 0);
        const month = Number($('monthFilter').value || 0);
        const year = Number($('yearFilter').value || 0);

        if (studentId > 0) params.set('student_id', String(studentId));
        if (year > 0) params.set('year', String(year));
        params.set('month', String(month));

        const response = await fetch(`${apiUrl}?${params.toString()}`, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        });

        const text = await response.text();
        let payload;
        try {
            payload = JSON.parse(text);
        } catch (error) {
            throw new Error('Parent Attendance API returned an invalid response.');
        }

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Unable to load Parent Attendance.');
        }

        return payload.data || {};
    }

    function renderChildren(data) {
        const children = Array.isArray(data.children) ? data.children : [];
        const selected = data.selected_child || {};

        $('childSelect').innerHTML = children.map(child => `
            <option value="${esc(child.id)}"${Number(child.id) === Number(selected.id) ? ' selected' : ''}>
                ${esc(child.student_name || 'Student')} · ${esc(child.class_name || 'No Class')}
            </option>
        `).join('');

        $('childSelect').disabled = children.length <= 1;
    }

    function renderYears(data) {
        const filters = data.filters || {};
        const years = Array.isArray(filters.available_years)
            ? filters.available_years.map(Number).filter(Boolean)
            : [];
        const selectedYear = Number(filters.year || new Date().getFullYear());

        $('yearFilter').innerHTML = years.map(year => `
            <option value="${year}"${year === selectedYear ? ' selected' : ''}>${year}</option>
        `).join('');

        if (!years.length) {
            $('yearFilter').innerHTML = `<option value="${selectedYear}">${selectedYear}</option>`;
        }

        $('monthFilter').value = String(Number(filters.month ?? new Date().getMonth() + 1));
    }

    function renderStudent(data) {
        // Student overview card was intentionally removed.
        // Child selection remains available in the existing header filter.
    }

    function renderSummary(data) {
        const summary = data.summary || {};
        $('totalDays').textContent = Number(summary.total_days || 0);
        $('presentDays').textContent = Number(summary.present_days || 0);
        $('absentDays').textContent = Number(summary.absent_days || 0);
        $('leaveDays').textContent = Number(summary.leave_days || 0);
        $('attendancePercent').textContent = `${Number(summary.attendance_percentage || 0).toFixed(1)}% attendance`;
        $('leaveNote').textContent = `Late: ${Number(summary.late_days || 0)} day(s)`;
    }

    function renderRows(data) {
        const rows = Array.isArray(data.rows) ? data.rows : [];

        $('attendanceRows').innerHTML = rows.length
            ? rows.map(row => `
                <tr>
                    <td>${esc(dateText(row.attendance_date))}</td>
                    <td>${esc(dayText(row.attendance_date))}</td>
                    <td><span class="badge ${badgeClass(row.status)}">${esc(statusText(row.status))}</span></td>
                    <td>${esc(timeText(row.check_in))}</td>
                    <td>${esc(timeText(row.check_out))}</td>
                    <td>${Number(row.late_minutes || 0) > 0 ? `${esc(row.late_minutes)} min` : '—'}</td>
                    <td>${esc(row.remarks || '—')}</td>
                </tr>
            `).join('')
            : '<tr><td colspan="7" class="empty">No attendance records found for the selected period.</td></tr>';

        const filters = data.filters || {};
        $('periodLabel').textContent = `${monthName(filters.month)} ${filters.year || ''} · ${rows.length} record(s)`;
    }

    async function load(options = {}) {
        showError('');
        showLoading(true);

        try {
            const data = await fetchAttendance();

            renderChildren(data);
            if (!options.keepFilters) {
                renderYears(data);
            } else {
                const filters = data.filters || {};
                $('monthFilter').value = String(Number(filters.month || 0));
                if ([...$('yearFilter').options].some(option => Number(option.value) === Number(filters.year))) {
                    $('yearFilter').value = String(filters.year);
                } else {
                    renderYears(data);
                }
            }

            renderStudent(data);
            renderSummary(data);
            renderRows(data);
            window.lucide?.createIcons();
        } catch (error) {
            showError(error.message || 'Unable to load Parent Attendance.');
            $('attendanceRows').innerHTML = '<tr><td colspan="7" class="empty">Attendance could not be loaded.</td></tr>';
        } finally {
            showLoading(false);
        }
    }

    $('childSelect').addEventListener('change', async () => {
        /* A child can have a different set of available attendance years. */
        $('yearFilter').innerHTML = `<option value="${new Date().getFullYear()}">${new Date().getFullYear()}</option>`;
        await load({keepFilters: false});
    });

    $('monthFilter').addEventListener('change', () => load({keepFilters: true}));
    $('yearFilter').addEventListener('change', () => load({keepFilters: true}));

    const now = new Date();
    $('monthFilter').value = String(now.getMonth() + 1);
    $('yearFilter').innerHTML = `<option value="${now.getFullYear()}">${now.getFullYear()}</option>`;

    load({keepFilters: false});
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
