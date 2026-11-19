import {AlertCircle, ArrowRight, ChevronLeft, ChevronRight, LoaderCircle, Search, X} from 'lucide-react';
import type {ReactNode} from 'react';
import {useEffect,useRef} from 'react';
import {date,label,money} from '../domain/format';
export {date,money,label};
export function Status({value}:{value:string}){
 const tint=['approved','paid','active','analyzed'].includes(value)?'success':['rejected','blocked','cancelled','overdue'].includes(value)?'danger':['pending_approval','submitted','received','urgent'].includes(value)?'warning':['sourcing','quotes_received'].includes(value)?'info':'neutral';
 return <span className={`status status--${tint}`}><span className="status__dot"/>{label(value)}</span>;
}
export function Priority({value}:{value:string}){return <span className={`priority priority--${value}`}><span className="priority__dot"/>{label(value)}</span>;}
export function Panel({children,className='',title,subtitle,action}:{children:ReactNode;className?:string;title?:string;subtitle?:string;action?:ReactNode}){
 return <section className={`panel ${className}`}>{title&&<header className="panel__header"><div><h2>{title}</h2>{subtitle&&<p>{subtitle}</p>}</div>{action}</header>}{children}</section>;
}
export function PageHeading({eyebrow,title,description,action}:{eyebrow?:string;title:string;description?:string;action?:ReactNode}){
 return <header className="page-heading"><div><div className="eyebrow">{eyebrow||'WORKSPACE'}</div><h1>{title}</h1>{description&&<p>{description}</p>}</div>{action&&<div className="page-actions">{action}</div>}</header>;
}
export function Button({children,variant='primary',disabled=false,onClick,type='button',title,className=''}:{children:ReactNode;variant?:'primary'|'secondary'|'ghost'|'danger';disabled?:boolean;onClick?:()=>void;type?:'submit'|'button';title?:string;className?:string}){
 return <button type={type} className={`btn btn--${variant} ${className}`} disabled={disabled} onClick={onClick} title={title}>{children}</button>;
}
export function SearchInput({value,onChange,placeholder='Search…'}:{value:string;onChange:(s:string)=>void;placeholder?:string}){return <div className="search-input"><Search size={17}/><input aria-label={placeholder} value={value} onChange={e=>onChange(e.target.value)} placeholder={placeholder}/>{value&&<button aria-label="Clear search" onClick={()=>onChange('')}><X size={14}/></button>}</div>;}
export function Loading({label='Loading your workspace…'}:{label?:string}){return <div role="status" className="state"><LoaderCircle className="spin" size={28}/><p>{label}</p></div>;}
export function Empty({title='Nothing here yet',detail,action}:{title?:string;detail?:string;action?:ReactNode}){return <div className="state state--empty"><span className="state-icon">◎</span><h3>{title}</h3>{detail&&<p>{detail}</p>}{action}</div>;}
export function ErrorState({error,onRetry}:{error:unknown;onRetry?:()=>void}){const msg=error instanceof Error?error.message:'The data could not be loaded.';return <div className="state state--error"><AlertCircle size={26}/><h3>Something went wrong</h3><p>{msg}</p>{onRetry&&<Button variant="secondary" onClick={onRetry}>Try again <ArrowRight size={15}/></Button>}</div>;}
export function Notice({children,tone='info'}:{children:ReactNode;tone?:'info'|'success'|'warning'|'danger'}){return <div role="status" className={`notice notice--${tone}`}>{children}</div>;}
export function Modal({title,children,onClose,width='standard'}:{title:string;children:ReactNode;onClose:()=>void;width?:'standard'|'wide'}){
 const dialog=useRef<HTMLElement>(null);
 const closeRef=useRef(onClose);closeRef.current=onClose;
 useEffect(()=>{const previous=document.activeElement instanceof HTMLElement?document.activeElement:null;dialog.current?.focus();
  function onKey(event:KeyboardEvent){if(event.key==='Escape'){event.preventDefault();closeRef.current();}if(event.key==='Tab'&&dialog.current){const focusable=[...dialog.current.querySelectorAll<HTMLElement>('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), a[href]')];if(focusable.length===0){event.preventDefault();return;}const first=focusable[0],last=focusable[focusable.length-1];if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}}}
  document.addEventListener('keydown',onKey);return()=>{document.removeEventListener('keydown',onKey);previous?.focus();};},[]);
 return <div className="modal-backdrop" onMouseDown={e=>{if(e.target===e.currentTarget)onClose();}}><section ref={dialog} tabIndex={-1} className={`modal modal--${width}`} role="dialog" aria-modal="true" aria-label={title}><header className="modal__header"><h2>{title}</h2><button aria-label="Close dialog" onClick={onClose} className="icon-button"><X size={19}/></button></header><div className="modal__body">{children}</div></section></div>;}
export function Field({label,required=false,hint,children,error}:{label:string;required?:boolean;hint?:string;children:ReactNode;error?:string}){return <label className="field"><span className="field__label">{label}{required&&<span className="required"> *</span>}</span>{children}{hint&&<span className="field__hint">{hint}</span>}{error&&<span role="alert" className="field__error">{error}</span>}</label>;}
export function Table({head,children}:{head:string[];children:ReactNode}){return <div className="table-wrap"><table><thead><tr>{head.map(h=><th key={h}>{h}</th>)}</tr></thead><tbody>{children}</tbody></table></div>;}
export function PaginationNote({shown,total}:{shown:number;total:number}){return <div className="table-footer"><span>{shown} records on this page · {total} total</span>{total>shown&&<span>More records are available on other pages.</span>}</div>;}
export function MiniStat({label:caption,value,icon,change,tone='blue'}:{label:string;value:string;icon:ReactNode;change?:string;tone?:'blue'|'mint'|'amber'|'violet'}){return <div className="kpi"><div className="kpi__top"><span>{caption}</span><span className={`kpi__icon kpi__icon--${tone}`}>{icon}</span></div><strong>{value}</strong>{change&&<span className="kpi__foot">{change}</span>}</div>;}
export function SectionHeader({title,subtitle,action}:{title:string;subtitle?:string;action?:ReactNode}){return <div className="section-head"><div><h3>{title}</h3>{subtitle&&<p>{subtitle}</p>}</div>{action}</div>;}
export function Pager({page,max,onChange}:{page:number;max:number;onChange:(p:number)=>void}){if(max<2)return null;return <div className="pager"><Button variant="ghost" disabled={page===1} onClick={()=>onChange(page-1)}><ChevronLeft size={16}/> Previous</Button><span>Page {page} of {max}</span><Button variant="ghost" disabled={page>=max} onClick={()=>onChange(page+1)}>Next <ChevronRight size={16}/></Button></div>;}
