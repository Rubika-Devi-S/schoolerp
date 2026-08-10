document.addEventListener('DOMContentLoaded',()=>{
  if(window.lucide) lucide.createIcons();
  document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.body.classList.toggle('sidebar-open'));
  document.querySelectorAll('[data-demo-action]').forEach(btn=>btn.addEventListener('click',()=>alert(btn.dataset.demoAction+' is included as a client reference interaction.')));
});
function makeChart(id,type,labels,data,label){
 const el=document.getElementById(id); if(!el||!window.Chart)return;
 new Chart(el,{type,data:{labels,datasets:[{label,data,borderWidth:3,tension:.35,fill:type==='line'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:type==='doughnut'}},scales:type==='doughnut'?{}:{y:{beginAtZero:true,grid:{color:'#edf0f5'}},x:{grid:{display:false}}}}});
}
