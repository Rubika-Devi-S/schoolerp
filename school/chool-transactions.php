<?php
declare(strict_types=1);

$pageTitle='Transaction Management';
$pageKey='transaction';
require dirname(__DIR__).'/includes/layout-start.php';

$csrfToken=function_exists('csrfToken')?csrfToken():'';
?>

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">
<div>
<h1 class="fw-bold">Transaction Management</h1>
<p class="text-muted">Manage income and expense transactions</p>
</div>

<button class="btn btn-primary" id="addBtn">
<i class="fa fa-plus"></i> Add Transaction
</button>
</div>


<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="card p-3 text-white" style="background:#38b978">
<h6>Total Transactions</h6>
<h2 id="total">0</h2>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-white" style="background:#387de0">
<h6>Total Income</h6>
<h2 id="income">0</h2>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-white" style="background:#f43f7b">
<h6>Total Expense</h6>
<h2 id="expense">0</h2>
</div>
</div>

<div class="col-md-3">
<div class="card p-3 text-white" style="background:#ffab22">
<h6>Balance</h6>
<h2 id="balance">0</h2>
</div>
</div>

</div>


<div class="card shadow-sm border-0">

<div class="card-header bg-white">
<h5>Transaction Ledger</h5>
</div>

<div class="card-body">

<table class="table align-middle">

<thead>
<tr>
<th>Date</th>
<th>Type</th>
<th>Category</th>
<th>Description</th>
<th>Payment Mode</th>
<th>Amount</th>
<th>Action</th>
</tr>
</thead>

<tbody id="transactionBody"></tbody>

</table>

</div>
</div>

</div>



<div class="modal fade" id="transactionModal">

<div class="modal-dialog">

<div class="modal-content">

<form id="transactionForm">

<div class="modal-header">
<h5>Add Transaction</h5>
</div>


<div class="modal-body">

<input type="hidden" id="id">

<input id="transaction_date" type="date" class="form-control mb-2">

<select id="transaction_type" class="form-control mb-2">
<option value="income">Income</option>
<option value="expense">Expense</option>
</select>

<input id="category" class="form-control mb-2" placeholder="Category">

<input id="reference_no" class="form-control mb-2" placeholder="Reference No">

<input id="description" class="form-control mb-2" placeholder="Description">

<select id="payment_mode" class="form-control mb-2">
<option>Cash</option>
<option>Bank</option>
<option>UPI</option>
</select>

<input id="amount" class="form-control mb-2" placeholder="Amount">

</div>


<div class="modal-footer">
<button class="btn btn-primary">Save</button>
</div>

</form>

</div>

</div>

</div>



<script>

const api="../api/transaction-api.php";
let csrf=<?=json_encode($csrfToken)?>;


async function request(action,data={},method="GET"){

let opt={credentials:"same-origin"};

if(method=="POST"){
opt.method="POST";
opt.headers={"Content-Type":"application/json"};
opt.body=JSON.stringify({...data,csrf_token:csrf});
}

return await (await fetch(api+"?action="+action,opt)).json();

}



async function load(){

let r=await request("list");
let rows=r.data||[];

let incomeTotal=0;
let expenseTotal=0;

transactionBody.innerHTML=rows.map(x=>{

if(x.transaction_type=="income")
incomeTotal+=Number(x.amount);
else
expenseTotal+=Number(x.amount);

return `
<tr>
<td>${x.transaction_date}</td>
<td>${x.transaction_type}</td>
<td>${x.category}</td>
<td>${x.description}</td>
<td>${x.payment_mode}</td>
<td>${x.amount}</td>
<td>
<button class="btn btn-sm btn-danger" onclick="del(${x.id})">
Delete
</button>
</td>
</tr>`;

}).join("");

total.innerHTML=rows.length;
income.innerHTML=incomeTotal;
expense.innerHTML=expenseTotal;
balance.innerHTML=incomeTotal-expenseTotal;

}


addBtn.onclick=()=>{
bootstrap.Modal.getOrCreateInstance(transactionModal).show();
};


transactionForm.onsubmit=async e=>{

e.preventDefault();

await request("save",{

transaction_date:transaction_date.value,
transaction_type:transaction_type.value,
category:category.value,
reference_no:reference_no.value,
description:description.value,
payment_mode:payment_mode.value,
amount:amount.value

},"POST");


bootstrap.Modal.getInstance(transactionModal).hide();

load();

}



async function del(id){

await request("delete",{id:id},"POST");
load();

}


load();

</script>


<?php require dirname(__DIR__).'/includes/layout-end.php'; ?>
