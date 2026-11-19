export function money(value:number|null|undefined,currency='EUR') {
  if(value===null || value===undefined || !Number.isFinite(Number(value))) return '—';
  return new Intl.NumberFormat('en-DE',{style:'currency',currency,maximumFractionDigits:0}).format(Number(value));
}
export function date(value:string|null|undefined) {
  if(!value) return '—'; const d=new Date(value); return Number.isNaN(d.getTime())?'—':new Intl.DateTimeFormat('en-GB',{day:'2-digit',month:'short',year:'numeric'}).format(d);
}
export function label(status:string) {return status.split('_').map(w=>w.charAt(0).toUpperCase()+w.slice(1)).join(' ');}
export function initials(name:string) {return name.split(' ').filter(Boolean).slice(0,2).map(s=>s[0].toUpperCase()).join('');}
