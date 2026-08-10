<?php
declare(strict_types=1);

$pageTitle='Terms & Semester Management';
$pageKey='terms_semester';
require dirname(__DIR__).'/includes/layout-start.php';

$csrfToken=function_exists('csrfToken')?csrfToken():'';
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="fw-bold mb-1">Terms & Semester Management</h1>
            <p class="text-muted">Plan and manage academic terms and semesters efficiently</p>
        </div>
        <div>
            <button class="btn btn-primary px-4" id="addBtn">
                <i class="fa fa-plus"></i> Add Term
            </button>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 text-white" style="background:#38b978">
                <h6>Total Terms</h6>
                <h2 id="totalTerms">0</h2>
                <small>Academic periods</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 text-white" style="background:#f43f7b">
                <h6>Active Terms</h6>
                <h2 id="activeTerms">0</h2>
                <small>Currently running</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 text-white" style="background:#ffab22">
                <h6>Semesters</h6>
                <h2 id="totalSemester">0</h2>
                <small>Configured semesters</small>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm p-3 text-white" style="background:#387de0">
                <h6>Academic Year</h6>
                <h2>2026-2027</h2>
                <small>Current year</small>
            </div>
        </div>
    </div>


    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between">
            <h5 class="mb-0">Academic Terms</h5>
            <button class="btn btn-light">
                <i class="fa fa-filter"></i> Filters
            </button>
        </div>

        <div class="card-body">

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button class="nav-link active">Terms</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link">Semesters</button>
                </li>
            </ul>

            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Term Name</th>
                        <th>Code</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody id="termBody"></tbody>

            </table>
        </div>
    </div>

</div>


<div class="modal fade" id="termModal">
<div class="modal-dialog">
<div class="modal-content">

<form id="termForm">

<div class="modal-header">
<h5 class="modal-title">Add Academic Term</h5>
<button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">

<input type="hidden" id="id">

<label>Term Name</label>
<input id="term_name" class="form-control mb-3">

<label>Term Code</label>
<input id="term_code" class="form-control mb-3">

<label>Start Date</label>
<input id="start_date" type="date" class="form-control mb-3">

<label>End Date</label>
<input id="end_date" type="date" class="form-control mb-3">

<label>Status</label>
<select id="status" class="form-control">
<option value="active">Active</option>
<option value="inactive">Inactive</option>
</select>

</div>

<div class="modal-footer">
<button class="btn btn-primary">Save Term</button>
</div>

</form>

</div>
</div>
</div>


<script>

const api="../api/terms-semester-api.php";
let csrf=<?=json_encode($csrfToken)?>;


async function request(action,data={},method="GET"){

let opt={credentials:"same-origin"};

if(method==="POST"){
opt.method="POST";
opt.headers={"Content-Type":"application/json"};
opt.body=JSON.stringify({...data,csrf_token:csrf});
}

let res=await fetch(api+"?action="+action,opt);
return await res.json();

}


async function loadTerms(){

let r=await request("list");

let rows=r.data.terms||[];

totalTerms.innerHTML=rows.length;
activeTerms.innerHTML=rows.filter(x=>x.status=="active").length;

termBody.innerHTML=rows.map(x=>`

<tr>

<td>
<b>${x.term_name}</b>
</td>

<td>${x.term_code}</td>

<td>${x.start_date}</td>

<td>${x.end_date}</td>

<td>
<span class="badge bg-success">${x.status}</span>
</td>

<td>
<button class="btn btn-sm btn-outline-primary" onclick="editTerm(${x.id})">
<i class="fa fa-edit"></i>
</button>

<button class="btn btn-sm btn-outline-danger" onclick="deleteTerm(${x.id})">
<i class="fa fa-trash"></i>
</button>
</td>

</tr>

`).join("");

}


addBtn.onclick=()=>{
bootstrap.Modal.getOrCreateInstance(termModal).show();
};


termForm.onsubmit=async e=>{

e.preventDefault();

await request("save",{
id:id.value,
term_name:term_name.value,
term_code:term_code.value,
start_date:start_date.value,
end_date:end_date.value,
status:status.value
},"POST");

bootstrap.Modal.getInstance(termModal).hide();

loadTerms();

};


async function editTerm(i){

let r=await request("get",{id:i});
let x=r.data.term;

id.value=x.id;
term_name.value=x.term_name;
term_code.value=x.term_code;
start_date.value=x.start_date;
end_date.value=x.end_date;
status.value=x.status;

bootstrap.Modal.getOrCreateInstance(termModal).show();

}


async function deleteTerm(i){

if(confirm("Delete this term?")){
await request("delete",{id:i},"POST");
loadTerms();
}

}


loadTerms();

</script>

<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>
