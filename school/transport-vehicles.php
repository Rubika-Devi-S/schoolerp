<?php
declare(strict_types=1);

$pageTitle = 'Vehicle Master';
$pageKey = 'transport_vehicles';

require dirname(__DIR__) . '/includes/layout-start.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (
    empty($_SESSION['vehicle_csrf_token']) ||
    !is_string($_SESSION['vehicle_csrf_token'])
) {
    $_SESSION['vehicle_csrf_token'] = bin2hex(random_bytes(32));
}

$vehicleCsrf = $_SESSION['vehicle_csrf_token'];
?>

<style>
*{box-sizing:border-box}

.vm-page{
    display:grid;
    gap:16px;
    width:100%;
    min-width:0;
}

.vm-page .page-heading{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    min-width:0;
}

.vm-page .page-heading>div:first-child{min-width:0}

.vm-page .page-title{
    margin:0;
    font-size:clamp(24px,2vw,30px);
    line-height:1.15;
}

.vm-page .page-subtitle{
    margin-top:5px;
    max-width:760px;
}

.vm-page .page-actions{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:9px;
}

.vm-page .page-actions .btn-ui{
    min-height:40px;
    white-space:nowrap;
}

.vm-stats{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
}

.vm-stat{
    border:0;
    border-radius:14px;
    min-height:116px;
    padding:18px 20px;
    display:flex;
    align-items:center;
    gap:14px;
    color:#fff;
    position:relative;
    overflow:hidden;
    min-width:0;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}

.vm-stat::before{
    content:"";
    position:absolute;
    inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.03),rgba(15,23,42,.05));
    pointer-events:none;
}

.vm-stat::after{
    content:"";
    position:absolute;
    width:118px;
    height:118px;
    border-radius:50%;
    right:-40px;
    top:-42px;
    background:rgba(255,255,255,.09);
}

.vm-stat.purple{background:linear-gradient(135deg,#7448e8,#4b36cf)}
.vm-stat.green{background:linear-gradient(135deg,#45c783,#1ea568)}
.vm-stat.orange{background:linear-gradient(135deg,#ffb327,#ff8a17)}
.vm-stat.blue{background:linear-gradient(135deg,#4696ef,#2469d5)}

.vm-stat-icon{
    width:50px;
    height:50px;
    border-radius:50%;
    background:rgba(255,255,255,.16);
    display:grid;
    place-items:center;
    flex:0 0 auto;
    position:relative;
    z-index:1;
}

.vm-stat-icon svg{width:25px;height:25px}

.vm-stat>div{
    position:relative;
    z-index:1;
    min-width:0;
}

.vm-stat small{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    font-weight:700;
    opacity:.96;
}

.vm-stat strong{
    display:block;
    font-size:clamp(21px,1.8vw,27px);
    line-height:1.05;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.vm-stat .trend{
    margin-top:8px;
    font-size:9px;
    font-weight:700;
    opacity:.94;
}

.vm-card{
    border-radius:14px;
    overflow:hidden;
    min-width:0;
}

.vm-card-head{
    padding:14px 16px;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
}

.vm-card-head strong{font-size:14px}

.vm-filter{
    padding:14px 16px;
    display:grid;
    grid-template-columns:minmax(280px,1.6fr) minmax(170px,.65fr) auto;
    gap:10px;
    align-items:center;
    border-bottom:1px solid var(--border-soft,#e7ebf3);
}

.vm-filter .form-control,
.vm-filter .form-select,
.vm-filter .btn-ui{
    width:100%;
    min-width:0;
    min-height:40px;
}

.vm-table-wrap{
    width:100%;
    max-width:100%;
    overflow-x:auto;
    overflow-y:visible;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
}

.vm-table{
    width:100%;
    min-width:1220px;
    border-collapse:separate;
    border-spacing:0;
}

.vm-table th{
    position:sticky;
    top:0;
    z-index:2;
    background:var(--card-bg,#fff);
    font-size:10px;
    white-space:nowrap;
}

.vm-table td{
    font-size:11px;
    vertical-align:middle;
}

.vm-table th,
.vm-table td{
    padding:11px 10px;
}

.vm-table tbody tr:hover{
    background:rgba(79,70,229,.025);
}

.vm-vehicle{
    display:flex;
    align-items:center;
    gap:9px;
    min-width:0;
}

.vm-vehicle-icon{
    width:32px;
    height:32px;
    border-radius:9px;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    color:#4f46e5;
    background:#eef2ff;
}

.vm-vehicle-icon svg{width:16px;height:16px}

.vm-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
    text-transform:capitalize;
    white-space:nowrap;
}

.vm-badge.active{color:#16834f;background:#e8f8ef}
.vm-badge.inactive{color:#dc2626;background:#fff0f1}
.vm-badge.bus{color:#4338ca;background:#eef2ff}
.vm-badge.van{color:#9a6700;background:#fff7d6}

.vm-actions{
    display:flex;
    gap:5px;
    flex-wrap:nowrap;
}

.vm-action{
    width:31px;
    height:31px;
    border:1px solid #d7def1;
    border-radius:7px;
    background:var(--card-bg,#fff);
    display:grid;
    place-items:center;
    flex:0 0 auto;
    color:#4f46e5;
}

.vm-action.edit{color:#2563eb}
.vm-action.delete{color:#dc2626}
.vm-action svg{width:14px;height:14px}

.vm-empty{
    padding:42px 18px!important;
    text-align:center!important;
    color:var(--text-muted,#64748b);
}

.vm-record-info{
    padding:12px 16px;
    border-top:1px solid var(--border-soft,#e7ebf3);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}

.vm-form-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}

.vm-form-grid>div{min-width:0}
.vm-form-grid .full{grid-column:1/-1}

.vm-view-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
}

.vm-view-item{
    padding:12px;
    border:1px solid var(--border-soft,#e3e8f1);
    border-radius:10px;
    background:rgba(99,102,241,.035);
    min-width:0;
}

.vm-view-item small{
    display:block;
    font-size:9px;
    font-weight:700;
    color:var(--text-muted,#64748b);
}

.vm-view-item strong{
    display:block;
    margin-top:5px;
    overflow-wrap:anywhere;
}

.vm-toast{
    position:fixed;
    top:18px;
    right:18px;
    z-index:2200;
    width:min(390px,calc(100vw - 36px));
    padding:13px 15px;
    border-radius:10px;
    box-shadow:0 18px 40px rgba(15,23,42,.18);
    color:#fff;
    opacity:0;
    visibility:hidden;
    transform:translateY(-8px);
    transition:.2s ease;
    font-size:12px;
    font-weight:700;
}

.vm-toast.show{
    opacity:1;
    visibility:visible;
    transform:translateY(0);
}

.vm-toast.success{background:#16834f}
.vm-toast.error{background:#dc2626}

#vehicleModal .modal-dialog,
#viewVehicleModal .modal-dialog,
#deleteVehicleModal .modal-dialog{
    max-height:calc(100dvh - 32px);
    margin:16px auto;
}

#vehicleModal .modal-dialog{
    width:min(980px,calc(100vw - 32px));
    max-width:980px;
}

#viewVehicleModal .modal-dialog{
    width:min(850px,calc(100vw - 32px));
    max-width:850px;
}

#deleteVehicleModal .modal-dialog{
    width:min(520px,calc(100vw - 32px));
    max-width:520px;
}

#vehicleModal .modal-content,
#viewVehicleModal .modal-content,
#deleteVehicleModal .modal-content{
    max-height:calc(100dvh - 32px);
    overflow:hidden;
}

#vehicleModal form{
    display:flex;
    flex-direction:column;
    max-height:calc(100dvh - 32px);
}

#vehicleModal .modal-body,
#viewVehicleModal .modal-body,
#deleteVehicleModal .modal-body{
    overflow-y:auto;
    min-height:0;
}

#vehicleModal .modal-footer{
    flex-wrap:wrap;
}

@media(min-width:1600px){
    .vm-page{gap:18px}
    .vm-stats{gap:16px}
    .vm-stat{min-height:122px;padding:20px 22px}
    .vm-table{min-width:100%}
    .vm-table th,.vm-table td{padding:12px}
}

@media(max-width:1199px){
    .vm-page .page-heading{
        flex-direction:column;
        align-items:stretch;
    }

    .vm-page .page-actions{
        justify-content:flex-start;
    }

    .vm-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .vm-filter{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .vm-filter #vehicleSearch{
        grid-column:auto;
    }
}

@media(max-width:767px){
    .vm-page{gap:12px}

    .vm-page .page-actions{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        width:100%;
    }

    .vm-page .page-actions .btn-ui{
        width:100%;
        justify-content:center;
    }

    .vm-stats{
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:10px;
    }

    .vm-stat{
        min-height:108px;
        padding:15px;
    }

    .vm-card-head{
        align-items:flex-start;
        flex-direction:column;
    }

    .vm-filter{
        grid-template-columns:1fr;
        padding:12px;
    }

    .vm-filter #vehicleSearch{
        grid-column:auto;
    }

    .vm-form-grid,
    .vm-view-grid{
        grid-template-columns:1fr;
    }

    .vm-form-grid .full{grid-column:auto}
    .vm-table{min-width:1080px}
}

@media(max-width:575px){
    .vm-page .page-title{font-size:24px}
    .vm-stats{grid-template-columns:1fr}
    .vm-stat{min-height:102px}

    #vehicleModal .modal-dialog,
    #viewVehicleModal .modal-dialog,
    #deleteVehicleModal .modal-dialog{
        width:calc(100vw - 16px);
        margin:8px auto;
        max-height:calc(100dvh - 16px);
    }

    #vehicleModal .modal-content,
    #viewVehicleModal .modal-content,
    #deleteVehicleModal .modal-content,
    #vehicleModal form{
        max-height:calc(100dvh - 16px);
    }

    #vehicleModal .modal-footer{
        display:grid;
        grid-template-columns:1fr;
    }

    #vehicleModal .modal-footer .btn-ui{
        width:100%;
        justify-content:center;
    }
}
</style>

<div id="vehicleToast" class="vm-toast" role="status" aria-live="polite"></div>

<div class="vm-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Vehicle Master</h1>
            <p class="page-subtitle">
                Manage the essential vehicle, driver and route information for the school.
            </p>
        </div>

        <div class="page-actions">
            <button id="addVehicleBtn" class="btn-ui btn-primary-ui" type="button">
                <i data-lucide="plus"></i>
                Add Vehicle
            </button>

            <button id="refreshVehicleBtn" class="btn-ui" type="button">
                <i data-lucide="refresh-cw"></i>
                Refresh
            </button>
        </div>
    </div>

    <section class="vm-stats" aria-label="Vehicle statistics">
        <article class="vm-stat purple">
            <span class="vm-stat-icon"><i data-lucide="bus-front"></i></span>
            <div>
                <small>Total Vehicles</small>
                <strong id="statTotalVehicles">0</strong>
                <div class="trend">All registered vehicles</div>
            </div>
        </article>

        <article class="vm-stat green">
            <span class="vm-stat-icon"><i data-lucide="circle-check-big"></i></span>
            <div>
                <small>Active Vehicles</small>
                <strong id="statActiveVehicles">0</strong>
                <div class="trend">Currently operational</div>
            </div>
        </article>

        <article class="vm-stat orange">
            <span class="vm-stat-icon"><i data-lucide="circle-pause"></i></span>
            <div>
                <small>Inactive Vehicles</small>
                <strong id="statInactiveVehicles">0</strong>
                <div class="trend">Not currently in service</div>
            </div>
        </article>
    </section>

    <section class="ui-card vm-card">
        <div class="vm-card-head">
            <strong>Vehicle List</strong>
            <small id="vehicleListCount" class="text-muted">Loading...</small>
        </div>

        <div class="vm-filter vm-filter-simple">
            <input
                id="vehicleSearch"
                class="form-control"
                type="search"
                placeholder="Search Vehicle Name or Vehicle Number"
                autocomplete="off"
            >

            <select id="vehicleStatusFilter" class="form-select">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>

            <button id="resetVehicleFiltersBtn" class="btn-ui" type="button">
                <i data-lucide="rotate-ccw"></i>
                Reset
            </button>
        </div>

        <div class="vm-table-wrap">
            <table class="data-table vm-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vehicle Name</th>
                        <th>Vehicle Number</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Driver</th>
                        <th>Helper / Attender</th>
                        <th>Assigned Route</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody id="vehicleTableBody">
                    <tr>
                        <td colspan="10" class="vm-empty">Loading vehicles...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="vm-record-info">
            <small id="vehicleRecordInfo" class="text-muted">0 vehicles</small>
            <small class="text-muted">Vehicle Number must be unique.</small>
        </div>
    </section>
</div>

<div class="modal fade" id="vehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="vehicleForm" novalidate>
                <div class="modal-header">
                    <div>
                        <h5 id="vehicleModalTitle" class="modal-title">Add Vehicle</h5>
                        <small id="vehicleModalSubtitle" class="text-muted">
                            Enter the school vehicle information.
                        </small>
                    </div>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>

                <div class="modal-body vm-form-grid">
                    <input id="vehicleId" type="hidden">

                    <div>
                        <label class="form-label" for="vehicleName">
                            Vehicle Name *
                        </label>
                        <input
                            id="vehicleName"
                            class="form-control"
                            maxlength="120"
                            placeholder="Example: School Bus 1"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="vehicleNumber">
                            Vehicle Number *
                        </label>
                        <input
                            id="vehicleNumber"
                            class="form-control"
                            maxlength="50"
                            placeholder="Example: VEH-001"
                            autocomplete="off"
                            required
                        >
                    </div>


                    <div>
                        <label class="form-label" for="vehicleType">
                            Vehicle Type *
                        </label>
                        <select id="vehicleType" class="form-select" required>
                            <option value="">Select type</option>
                            <option value="bus">Bus</option>
                            <option value="van">Van</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="vehicleCapacity">
                            Capacity *
                        </label>
                        <input
                            id="vehicleCapacity"
                            class="form-control"
                            type="number"
                            min="1"
                            max="200"
                            step="1"
                            placeholder="Example: 40"
                            required
                        >
                    </div>

                    <div>
                        <label class="form-label" for="driverName">
                            Driver
                            <small class="text-muted">(Optional)</small>
                        </label>
                        <input
                            id="driverName"
                            class="form-control"
                            maxlength="120"
                            placeholder="Enter driver name"
                        >
                    </div>

                    <div>
                        <label class="form-label" for="helperName">
                            Helper / Attender
                            <small class="text-muted">(Optional)</small>
                        </label>
                        <input
                            id="helperName"
                            class="form-control"
                            maxlength="120"
                            placeholder="Enter helper or attender name"
                        >
                    </div>

                    <div>
                        <label class="form-label" for="routeId">
                            Assigned Route
                            <small class="text-muted">(Optional)</small>
                        </label>
                        <select id="routeId" class="form-select">
                            <option value="">Not Assigned</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="vehicleStatus">
                            Status *
                        </label>
                        <select id="vehicleStatus" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        id="deleteVehicleFromFormBtn"
                        type="button"
                        class="btn-ui"
                        hidden
                    >
                        <i data-lucide="trash-2"></i>
                        Delete
                    </button>

                    <button
                        id="resetVehicleFormBtn"
                        type="button"
                        class="btn-ui"
                    >
                        <i data-lucide="rotate-ccw"></i>
                        Reset
                    </button>

                    <button
                        type="button"
                        class="btn-ui"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        id="saveVehicleBtn"
                        type="submit"
                        class="btn-ui btn-primary-ui"
                    >
                        <i data-lucide="save"></i>
                        <span id="saveVehicleBtnText">Add Vehicle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="viewVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Vehicle Details</h5>
                    <small id="viewVehicleSubtitle" class="text-muted"></small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <div id="viewVehicleContent" class="vm-view-grid"></div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-ui"
                    data-bs-dismiss="modal"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Delete Vehicle</h5>
                    <small class="text-muted">This action cannot be undone.</small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete this vehicle?</p>

                <div class="alert alert-warning mb-0">
                    <strong id="deleteVehicleLabel">-</strong>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn-ui"
                    data-bs-dismiss="modal"
                >
                    Cancel
                </button>

                <button
                    id="confirmDeleteVehicleBtn"
                    type="button"
                    class="btn-ui btn-primary-ui"
                >
                    <i data-lucide="trash-2"></i>
                    Delete Vehicle
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    'use strict';

    const apiUrl = new URL('../api/vehicle-management.php', window.location.href).href;
    let csrfToken = <?=json_encode($vehicleCsrf)?>;
    let vehicles = [];
    let routes = [];
    let searchTimer = null;
    let deleteVehicleId = 0;
    let toastTimer = null;

    const $ = function(id){
        return document.getElementById(id);
    };

    const escapeHtml = function(value){
        return String(value === null || value === undefined ? '' : value)
            .replace(/[&<>"']/g, function(character){
                return {
                    '&':'&amp;',
                    '<':'&lt;',
                    '>':'&gt;',
                    '"':'&quot;',
                    "'":'&#039;'
                }[character];
            });
    };


    function showToast(message, success){
        const toast = $('vehicleToast');
        toast.className = 'vm-toast show ' + (success ? 'success' : 'error');
        toast.textContent = message;

        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function(){
            toast.className = 'vm-toast';
        }, 5000);
    }

    async function request(action, data, method){
        data = data || {};
        method = method || 'GET';

        let response;

        if (method === 'GET') {
            const url = new URL(apiUrl);
            url.searchParams.set('action', action);

            Object.keys(data).forEach(function(key){
                const value = data[key];
                if (value !== '' && value !== null && value !== undefined) {
                    url.searchParams.set(key, String(value));
                }
            });

            response = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });
        } else {
            response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(
                    Object.assign(
                        {
                            action: action,
                            csrf_token: csrfToken
                        },
                        data
                    )
                )
            });
        }

        const responseText = await response.text();
        let result;

        try {
            result = JSON.parse(responseText);
        } catch (error) {
            throw new Error(
                'Vehicle API returned HTTP ' +
                response.status +
                '. ' +
                (responseText.replace(/\s+/g, ' ').trim().slice(0, 220) ||
                    'Invalid server response.')
            );
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Vehicle request failed.');
        }

        if (result.data && result.data.csrf_token) {
            csrfToken = result.data.csrf_token;
        }

        return result;
    }

    function vehicleTypeLabel(type){
        return String(type || '').toLowerCase() === 'van' ? 'Van' : 'Bus';
    }

    function statusBadge(status){
        const safeStatus = String(status || 'inactive').toLowerCase();

        return '<span class="vm-badge ' +
            escapeHtml(safeStatus) +
            '">' +
            escapeHtml(safeStatus) +
            '</span>';
    }

    function typeBadge(type){
        const safeType = String(type || 'bus').toLowerCase();

        return '<span class="vm-badge ' +
            escapeHtml(safeType) +
            '">' +
            escapeHtml(vehicleTypeLabel(safeType)) +
            '</span>';
    }

    function setRouteOptions(records, selectedId){
        routes = Array.isArray(records) ? records : [];

        const currentValue = Number(
            selectedId || $('routeId').value || 0
        );

        $('routeId').innerHTML =
            '<option value="">Not Assigned</option>' +
            routes.map(function(route){
                const routeStatus =
                    String(route.status || 'active').toLowerCase();

                const label =
                    (route.route_code ? route.route_code + ' • ' : '') +
                    route.route_name +
                    (routeStatus === 'inactive' ? ' (Inactive)' : '');

                return '<option value="' +
                    Number(route.id) +
                    '"' +
                    (routeStatus === 'inactive' ? ' disabled' : '') +
                    '>' +
                    escapeHtml(label) +
                    '</option>';
            }).join('');

        if (currentValue > 0) {
            $('routeId').value = String(currentValue);
        }
    }

    async function ensureRouteOptions(){
        if (routes.length > 0) {
            return;
        }

        const result = await request('routes');
        setRouteOptions(result.data.routes || [], 0);
    }

    function updateStatistics(stats){
        stats = stats || {};

        $('statTotalVehicles').textContent = Number(stats.total_vehicles || 0);
        $('statActiveVehicles').textContent = Number(stats.active_vehicles || 0);
        $('statInactiveVehicles').textContent = Number(stats.inactive_vehicles || 0);
    }

    function renderVehicles(records){
        vehicles = records || [];

        $('vehicleListCount').textContent =
            vehicles.length +
            ' vehicle' +
            (vehicles.length === 1 ? '' : 's');

        $('vehicleRecordInfo').textContent =
            vehicles.length +
            ' vehicle' +
            (vehicles.length === 1 ? '' : 's') +
            ' displayed';

        $('vehicleTableBody').innerHTML = vehicles.map(function(vehicle, index){
            return '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' +
                    '<div class="vm-vehicle">' +
                        '<span class="vm-vehicle-icon">' +
                            '<i data-lucide="' +
                            (String(vehicle.vehicle_type).toLowerCase() === 'van'
                                ? 'car-front'
                                : 'bus-front') +
                            '"></i>' +
                        '</span>' +
                        '<strong>' + escapeHtml(vehicle.vehicle_name) + '</strong>' +
                    '</div>' +
                '</td>' +
                '<td><strong>' + escapeHtml(vehicle.vehicle_number) + '</strong></td>' +
                '<td>' + typeBadge(vehicle.vehicle_type) + '</td>' +
                '<td>' + Number(vehicle.capacity || 0) + '</td>' +
                '<td>' + escapeHtml(vehicle.driver_name || '-') + '</td>' +
                '<td>' + escapeHtml(vehicle.helper_name || '-') + '</td>' +
                '<td>' +
                    '<strong>' + escapeHtml(vehicle.route_name || 'Not Assigned') + '</strong>' +
                    (vehicle.route_code
                        ? '<small class="d-block text-muted">' +
                          escapeHtml(vehicle.route_code) +
                          '</small>'
                        : '') +
                '</td>' +
                '<td>' + statusBadge(vehicle.status) + '</td>' +
                '<td>' +
                    '<div class="vm-actions">' +
                        '<button class="vm-action js-view-vehicle" ' +
                            'type="button" data-id="' + Number(vehicle.id) + '" title="View">' +
                            '<i data-lucide="eye"></i>' +
                        '</button>' +
                        '<button class="vm-action edit js-edit-vehicle" ' +
                            'type="button" data-id="' + Number(vehicle.id) + '" title="Edit">' +
                            '<i data-lucide="pencil"></i>' +
                        '</button>' +
                        '<button class="vm-action delete js-delete-vehicle" ' +
                            'type="button" data-id="' + Number(vehicle.id) + '" title="Delete">' +
                            '<i data-lucide="trash-2"></i>' +
                        '</button>' +
                    '</div>' +
                '</td>' +
            '</tr>';
        }).join('') || (
            '<tr>' +
                '<td colspan="10" class="vm-empty">No vehicles found.</td>' +
            '</tr>'
        );

        document.querySelectorAll('.js-view-vehicle').forEach(function(button){
            button.addEventListener('click', function(){
                viewVehicle(Number(button.dataset.id));
            });
        });

        document.querySelectorAll('.js-edit-vehicle').forEach(function(button){
            button.addEventListener('click', function(){
                openVehicleForm(Number(button.dataset.id));
            });
        });

        document.querySelectorAll('.js-delete-vehicle').forEach(function(button){
            button.addEventListener('click', function(){
                openDeleteConfirmation(Number(button.dataset.id));
            });
        });

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    async function loadVehicles(){
        try {
            const result = await request('list', {
                search: $('vehicleSearch').value.trim(),
                status: $('vehicleStatusFilter').value
            });

            setRouteOptions(result.data.routes || routes, 0);
            renderVehicles(result.data.records || []);
            updateStatistics(result.data.stats || {});
        } catch (error) {
            $('vehicleTableBody').innerHTML =
                '<tr><td colspan="10" class="vm-empty">' +
                escapeHtml(error.message) +
                '</td></tr>';

            $('vehicleListCount').textContent = 'Unable to load vehicles';
            showToast(error.message, false);
        }
    }

    function clearVehicleForm(){
        $('vehicleForm').reset();
        $('vehicleId').value = '';
        $('helperName').value = '';
        $('routeId').value = '';
        $('vehicleStatus').value = 'active';
        $('vehicleModalTitle').textContent = 'Add Vehicle';
        $('vehicleModalSubtitle').textContent =
            'Enter the school vehicle information.';
        $('saveVehicleBtnText').textContent = 'Add Vehicle';
        $('deleteVehicleFromFormBtn').hidden = true;
        $('vehicleNumber').readOnly = false;
    }

    function fillVehicleForm(vehicle){
        $('vehicleId').value = vehicle.id;
        $('vehicleName').value = vehicle.vehicle_name || '';
        $('vehicleNumber').value = vehicle.vehicle_number || '';
        $('vehicleType').value =
            String(vehicle.vehicle_type || '').toLowerCase();
        $('vehicleCapacity').value = Number(vehicle.capacity || 0);
        $('driverName').value = vehicle.driver_name || '';
        $('helperName').value = vehicle.helper_name || '';
        setRouteOptions(routes, Number(vehicle.route_id || 0));
        $('vehicleStatus').value =
            String(vehicle.status || 'active').toLowerCase();

        $('vehicleModalTitle').textContent = 'Update Vehicle';
        $('vehicleModalSubtitle').textContent =
            (vehicle.vehicle_name || '') +
            ' • ' +
            (vehicle.vehicle_number || '');
        $('saveVehicleBtnText').textContent = 'Update Vehicle';
        $('deleteVehicleFromFormBtn').hidden = false;
    }

    async function openVehicleForm(id){
        clearVehicleForm();

        try {
            await ensureRouteOptions();
        } catch (error) {
            showToast(error.message, false);
        }

        if (id > 0) {
            try {
                const result = await request('detail', {id: id});
                setRouteOptions(
                    result.data.routes || routes,
                    Number(result.data.record.route_id || 0)
                );
                fillVehicleForm(result.data.record);
            } catch (error) {
                showToast(error.message, false);
                return;
            }
        }

        bootstrap.Modal
            .getOrCreateInstance($('vehicleModal'))
            .show();

        window.setTimeout(function(){
            $('vehicleNumber').focus();
        }, 250);

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function validateVehicleForm(){
        const vehicleName = $('vehicleName').value.trim();
        const vehicleNumber =
            $('vehicleNumber').value.trim().toUpperCase();
        const vehicleType = $('vehicleType').value;
        const capacity = Number($('vehicleCapacity').value || 0);
        const driverName = $('driverName').value.trim();
        const helperName = $('helperName').value.trim();
        const routeId = Number($('routeId').value || 0);
        const status = $('vehicleStatus').value;

        if (!vehicleName) {
            throw new Error('Vehicle Name is required.');
        }

        if (!vehicleNumber) {
            throw new Error('Vehicle Number is required.');
        }

        if (!['bus', 'van'].includes(vehicleType)) {
            throw new Error('Select the Vehicle Type.');
        }

        if (!Number.isInteger(capacity) || capacity < 1 || capacity > 200) {
            throw new Error('Capacity must be between 1 and 200.');
        }

        if (driverName.length > 120) {
            throw new Error('Driver cannot exceed 120 characters.');
        }

        if (helperName.length > 120) {
            throw new Error(
                'Helper / Attender cannot exceed 120 characters.'
            );
        }

        if (!['active', 'inactive'].includes(status)) {
            throw new Error('Select a valid Status.');
        }

        return {
            id: Number($('vehicleId').value || 0),
            vehicle_name: vehicleName,
            vehicle_number: vehicleNumber,
            vehicle_type: vehicleType,
            capacity: capacity,
            driver_name: driverName,
            helper_name: helperName,
            route_id: routeId,
            status: status
        };
    }

    async function saveVehicle(event){
        event.preventDefault();

        let payload;

        try {
            payload = validateVehicleForm();
        } catch (error) {
            showToast(error.message, false);
            return;
        }

        const saveButton = $('saveVehicleBtn');
        saveButton.disabled = true;

        try {
            const result = await request('save', payload, 'POST');

            bootstrap.Modal
                .getInstance($('vehicleModal'))
                ?.hide();

            showToast(result.message, true);
            await loadVehicles();
        } catch (error) {
            showToast(error.message, false);
        } finally {
            saveButton.disabled = false;
        }
    }

    async function viewVehicle(id){
        try {
            const result = await request('detail', {id: id});
            const vehicle = result.data.record;

            $('viewVehicleSubtitle').textContent =
                vehicle.vehicle_number + ' • ' + vehicle.vehicle_name;

            const fields = [
                ['Vehicle Name', vehicle.vehicle_name],
                ['Vehicle Number', vehicle.vehicle_number],
                ['Vehicle Type', vehicleTypeLabel(vehicle.vehicle_type)],
                ['Capacity', vehicle.capacity],
                ['Driver', vehicle.driver_name || '-'],
                ['Helper / Attender', vehicle.helper_name || '-'],
                [
                    'Assigned Route',
                    (vehicle.route_code ? vehicle.route_code + ' • ' : '') +
                    (vehicle.route_name || 'Not Assigned')
                ],
                [
                    'Route Path',
                    vehicle.route_start_point && vehicle.route_end_point
                        ? vehicle.route_start_point +
                          ' → ' +
                          vehicle.route_end_point
                        : '-'
                ],
                ['Status', String(vehicle.status || '').toUpperCase()],
                [
                    'Created At',
                    vehicle.created_at_display ||
                    vehicle.created_at ||
                    '-'
                ]
            ];

            $('viewVehicleContent').innerHTML = fields.map(function(field){
                return '<div class="vm-view-item">' +
                    '<small>' + escapeHtml(field[0]) + '</small>' +
                    '<strong>' + escapeHtml(field[1]) + '</strong>' +
                '</div>';
            }).join('');

            bootstrap.Modal
                .getOrCreateInstance($('viewVehicleModal'))
                .show();
        } catch (error) {
            showToast(error.message, false);
        }
    }

    function openDeleteConfirmation(id){
        const vehicle = vehicles.find(function(item){
            return Number(item.id) === Number(id);
        });

        deleteVehicleId = Number(id);

        $('deleteVehicleLabel').textContent = vehicle
            ? vehicle.vehicle_number + ' • ' + vehicle.vehicle_name
            : 'Selected vehicle';

        bootstrap.Modal
            .getOrCreateInstance($('deleteVehicleModal'))
            .show();

        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    async function deleteVehicle(){
        if (deleteVehicleId <= 0) {
            showToast('Select a valid vehicle.', false);
            return;
        }

        const deleteButton = $('confirmDeleteVehicleBtn');
        deleteButton.disabled = true;

        try {
            const result = await request(
                'delete',
                {id: deleteVehicleId},
                'POST'
            );

            bootstrap.Modal
                .getInstance($('deleteVehicleModal'))
                ?.hide();

            bootstrap.Modal
                .getInstance($('vehicleModal'))
                ?.hide();

            deleteVehicleId = 0;
            showToast(result.message, true);
            await loadVehicles();
        } catch (error) {
            showToast(error.message, false);
        } finally {
            deleteButton.disabled = false;
        }
    }

    $('addVehicleBtn').addEventListener('click', function(){
        openVehicleForm(0);
    });

    $('refreshVehicleBtn').addEventListener('click', loadVehicles);

    $('resetVehicleFiltersBtn').addEventListener('click', function(){
        $('vehicleSearch').value = '';
        $('vehicleStatusFilter').value = 'all';
        loadVehicles();
    });

    $('vehicleStatusFilter').addEventListener('change', loadVehicles);

    $('vehicleSearch').addEventListener('input', function(){
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(loadVehicles, 300);
    });

    $('vehicleForm').addEventListener('submit', saveVehicle);

    $('resetVehicleFormBtn').addEventListener('click', function(){
        const currentId = Number($('vehicleId').value || 0);

        if (currentId > 0) {
            openVehicleForm(currentId);
        } else {
            clearVehicleForm();
        }
    });

    $('deleteVehicleFromFormBtn').addEventListener('click', function(){
        openDeleteConfirmation(Number($('vehicleId').value || 0));
    });

    $('confirmDeleteVehicleBtn').addEventListener('click', deleteVehicle);


    $('vehicleNumber').addEventListener('input', function(){
        this.value = this.value.toUpperCase();
    });

    loadVehicles();

    if (window.lucide) {
        window.lucide.createIcons();
    }
})();
</script>

<?php require dirname(__DIR__) . '/includes/layout-end.php'; ?>
