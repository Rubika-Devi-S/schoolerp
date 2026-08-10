<?php
declare(strict_types=1);

require_once __DIR__ . '/general-settings-runtime.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

$tenantId = school_settings_tenant_id();
if ($tenantId <= 0) {
    return;
}

$schoolSettings = school_settings_get($pdo, $tenantId);
$clientSettings = [
    'school_start_time' => school_settings_input_time($schoolSettings['school_start_time'] ?? '08:30:00'),
    'school_end_time' => school_settings_input_time($schoolSettings['school_end_time'] ?? '16:00:00'),
    'school_start_time_display' => school_settings_format_time($schoolSettings['school_start_time'] ?? '08:30:00', $schoolSettings),
    'school_end_time_display' => school_settings_format_time($schoolSettings['school_end_time'] ?? '16:00:00', $schoolSettings),
    'date_format' => (string)($schoolSettings['date_format'] ?? 'd-m-Y'),
    'time_format' => (string)($schoolSettings['time_format'] ?? '12'),
    'admission_number_prefix' => (string)($schoolSettings['admission_number_prefix'] ?? 'ADM'),
    'receipt_number_prefix' => (string)($schoolSettings['receipt_number_prefix'] ?? 'RCP'),
];
?>
<script>
(function(){
'use strict';
const settings = <?=json_encode($clientSettings, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
window.SCHOOL_GENERAL_SETTINGS = settings;

function pad(value){return String(value).padStart(2,'0')}
function parseDate(value){
 const text=String(value||'').trim();
 if(!text)return null;
 const iso=text.includes('T')?text:text.replace(' ','T');
 const date=new Date(iso);
 if(!Number.isNaN(date.getTime()))return date;
 const m=text.match(/^(\d{4})-(\d{2})-(\d{2})/);
 return m?new Date(Number(m[1]),Number(m[2])-1,Number(m[3])):null;
}
function formatDate(value){
 const d=parseDate(value);if(!d)return String(value||'');
 const dd=pad(d.getDate()),mm=pad(d.getMonth()+1),yyyy=d.getFullYear();
 switch(settings.date_format){
  case 'd/m/Y':return `${dd}/${mm}/${yyyy}`;
  case 'Y-m-d':return `${yyyy}-${mm}-${dd}`;
  case 'm/d/Y':return `${mm}/${dd}/${yyyy}`;
  default:return `${dd}-${mm}-${yyyy}`;
 }
}
function formatTime(value){
 const text=String(value||'').trim();if(!text)return '';
 const match=text.match(/(?:T|\s)?(\d{1,2}):(\d{2})/)||text.match(/^(\d{1,2}):(\d{2})/);
 if(!match)return text;
 let hour=Number(match[1]),minute=match[2];
 if(settings.time_format==='24')return `${pad(hour)}:${minute}`;
 const period=hour>=12?'PM':'AM';hour=hour%12||12;
 return `${hour}:${minute} ${period}`;
}
function apply(root=document){
 root.querySelectorAll?.('[data-school-default-time]').forEach(el=>{
  if(String(el.value||'').trim()!=='')return;
  const type=String(el.dataset.schoolDefaultTime||'').toLowerCase();
  if(type==='start')el.value=settings.school_start_time;
  if(type==='end')el.value=settings.school_end_time;
 });
 root.querySelectorAll?.('[data-school-time-value]').forEach(el=>{
  const value=el.dataset.schoolTimeValue||el.textContent;
  el.textContent=formatTime(value);
 });
 root.querySelectorAll?.('[data-school-date-value]').forEach(el=>{
  const value=el.dataset.schoolDateValue||el.textContent;
  el.textContent=formatDate(value);
 });
}
window.SchoolSettings={...settings,formatDate,formatTime,apply};
apply();
new MutationObserver(mutations=>mutations.forEach(m=>m.addedNodes.forEach(node=>{
 if(node.nodeType===1)apply(node);
}))).observe(document.documentElement,{childList:true,subtree:true});
})();
</script>
