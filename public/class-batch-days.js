function parseClassDays(value) {
    const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const aliases = {m:'Monday',mon:'Monday',monday:'Monday',t:'Tuesday',tu:'Tuesday',tue:'Tuesday',tues:'Tuesday',tuesday:'Tuesday',w:'Wednesday',wed:'Wednesday',wednesday:'Wednesday',th:'Thursday',thu:'Thursday',thur:'Thursday',thurs:'Thursday',thursday:'Thursday',f:'Friday',fri:'Friday',friday:'Friday',s:'Saturday',sat:'Saturday',saturday:'Saturday',su:'Sunday',sun:'Sunday',sunday:'Sunday'};
    const text = String(value || '').trim().toLowerCase();
    const compact = {mwf:['Monday','Wednesday','Friday'],tth:['Tuesday','Thursday'],tths:['Tuesday','Thursday','Saturday']};
    const selected = compact[text.replace(/[\s/,&-]+/g,'')] || text.split(/[\s,/;&+|]+/).map(part=>aliases[part]).filter(Boolean);
    return days.filter(day=>selected.includes(day));
}
function setClassBatchDays(value) {
    const selected = parseClassDays(value);
    document.querySelectorAll('input[name="schedule_days[]"]').forEach(input=>{input.checked=selected.includes(input.value);});
}
