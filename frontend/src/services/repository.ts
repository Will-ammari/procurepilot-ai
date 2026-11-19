/** Stable application boundary. Presentational components know nothing about Axios or demo persistence. */
import axios, { AxiosError } from 'axios';
import { demo, DemoError } from './demo';
import type {Activity,ApprovalStep,Comparison,DataMode,Department,Invoice,InvoiceInput,Page,PurchaseInput,PurchaseRequest,Quote,QuoteInput,Role,User,Vendor,VendorInput} from '../domain/types';
export const DATA_MODE:DataMode = import.meta.env.VITE_DATA_MODE === 'api'?'api':'demo';
const baseURL=import.meta.env.VITE_API_BASE_URL||'http://localhost:8000/api/v1';
const http=axios.create({baseURL,timeout:20000,headers:{Accept:'application/json','Content-Type':'application/json'}});
// Intentionally memory-only: bearer tokens are not persisted to Web Storage.
let accessToken:string|null=null;
http.interceptors.request.use(config=>{if(accessToken)config.headers.Authorization=`Bearer ${accessToken}`;return config;});
export class AppError extends Error {constructor(public status:number,message:string,public fields:Record<string,string[]>={}){super(message);this.name='AppError';}}
function errorOf(reason:unknown):AppError {
 if(reason instanceof AppError)return reason;
 if(reason instanceof DemoError)return new AppError(reason.status,reason.message);
 if(axios.isAxiosError(reason)){
  const e=reason as AxiosError<{message?:string;error?:{message?:string};errors?:Record<string,string[]>}>;
  if(!e.response)return new AppError(0,'Cannot reach the API. Check Laravel and VITE_API_BASE_URL.');
  const status=e.response.status;return new AppError(status,e.response.data?.error?.message||e.response.data?.message||({401:'Session expired. Please sign in again.',403:'You do not have permission for this action.',404:'This record was not found.',422:'Please review your input.'} as Record<number,string>)[status]||'Request failed.',e.response.data?.errors??{});
 }
 return new AppError(500,reason instanceof Error?reason.message:'Unexpected error.');
}
async function guard<T>(fn:()=>Promise<T>):Promise<T>{try{return await fn();}catch(error){throw errorOf(error);}}
async function one<T>(path:string):Promise<T>{return (await http.get<{data:T}>(path)).data.data;}
async function list<T>(path:string,params?:Record<string,string|number|undefined>):Promise<Page<T>>{const r=await http.get<Page<T>>(path,{params});return r.data;}
async function mutate<T>(verb:'post'|'patch',path:string,data?:unknown):Promise<T>{const r=await http[verb]<{data:T}>(path,data);return r.data.data;}
export const repo={
 mode:DATA_MODE,
 async login(options:{role?:Role;email?:string;password?:string}):Promise<User>{return guard(async()=>{
  if(DATA_MODE==='demo')return demo.login(options.role||'admin');
  const r=await http.post<{data:{token:string;user:User}}>('/auth/login',{email:options.email,password:options.password});
  accessToken=r.data.data.token;
  return await one<User>('/me');
 });},
 async logout(){return guard(async()=>{if(DATA_MODE==='api'&&accessToken){try{await http.post('/auth/logout');}finally{accessToken=null;}}});},
 async requests(user:User,params?:{search?:string;status?:string;page?:number;per_page?:number}):Promise<Page<PurchaseRequest>>{return guard(()=>DATA_MODE==='demo'?demo.requests(user,params):list('/purchase-requests',{...params,per_page:params?.per_page??25}));},
 async request(user:User,id:number):Promise<PurchaseRequest>{return guard(()=>DATA_MODE==='demo'?demo.request(user,id):one(`/purchase-requests/${id}`));},
 async createRequest(user:User,payload:PurchaseInput):Promise<PurchaseRequest>{return guard(()=>DATA_MODE==='demo'?demo.createRequest(user,payload):mutate('post','/purchase-requests',payload));},
 async submit(user:User,id:number):Promise<PurchaseRequest>{return guard(()=>DATA_MODE==='demo'?demo.submit(user,id):mutate('post',`/purchase-requests/${id}/submit`));},
 async vendors(user:User,params?:{search?:string;page?:number;per_page?:number}):Promise<Page<Vendor>>{return guard(()=>DATA_MODE==='demo'?demo.vendors(user,params):list('/vendors',{...params,per_page:params?.per_page??100}));},
 async createVendor(user:User,payload:VendorInput):Promise<Vendor>{return guard(()=>DATA_MODE==='demo'?demo.createVendor(user,payload):mutate('post','/vendors',payload));},
 async quotes(user:User,id:number):Promise<Page<Quote>>{return guard(()=>DATA_MODE==='demo'?demo.quotes(user,id):list(`/purchase-requests/${id}/quotes`,{per_page:100}));},
 async createQuote(user:User,id:number,payload:QuoteInput):Promise<Quote>{return guard(()=>DATA_MODE==='demo'?demo.createQuote(user,id,payload):mutate('post',`/purchase-requests/${id}/quotes`,payload));},
 async comparison(user:User,id:number):Promise<Comparison|null>{return guard(async()=>{if(DATA_MODE==='demo')return demo.comparison(user,id);try{return await one<Comparison>(`/purchase-requests/${id}/comparison`);}catch(e){if(axios.isAxiosError(e)&&(e.response?.status===404||(e.response?.status===422&&!!(e.response.data as {errors?:{comparison?:unknown}}|undefined)?.errors?.comparison)))return null;throw e;}});},
 async generateComparison(user:User,id:number):Promise<Comparison>{return guard(()=>DATA_MODE==='demo'?demo.generateComparison(user,id):mutate('post',`/purchase-requests/${id}/comparison`));},
 async steps(user:User,id:number):Promise<ApprovalStep[]>{return guard(async()=>DATA_MODE==='demo'?demo.steps(user,id):(await list<ApprovalStep>(`/purchase-requests/${id}/approval-steps`)).data);},
 async sendForApproval(user:User,id:number):Promise<ApprovalStep[]>{return guard(async()=>DATA_MODE==='demo'?demo.sendForApproval(user,id):(await mutate<ApprovalStep[]>('post',`/purchase-requests/${id}/send-for-approval`)));},
 async decide(user:User,stepId:number,decision:'approve'|'reject',comment:string):Promise<ApprovalStep>{return guard(()=>DATA_MODE==='demo'?demo.decide(user,stepId,decision,comment):mutate('post',`/approval-steps/${stepId}/${decision}`,{comment:comment||null}));},
 async invoices(user:User):Promise<Page<Invoice>>{return guard(()=>DATA_MODE==='demo'?demo.invoices(user):list('/invoices',{per_page:100}));},
 async createInvoice(user:User,payload:InvoiceInput):Promise<Invoice>{return guard(()=>DATA_MODE==='demo'?demo.createInvoice(user,payload):mutate('post','/invoices',payload));},
 async payInvoice(user:User,id:number):Promise<Invoice>{return guard(()=>DATA_MODE==='demo'?demo.payInvoice(user,id):mutate('patch',`/invoices/${id}/mark-paid`));},
 async activities(user:User):Promise<Page<Activity>>{return guard(()=>DATA_MODE==='demo'?demo.activities(user):list('/activity-logs',{per_page:100}));},
 async departments(_user:User):Promise<Page<Department>>{return guard(()=>DATA_MODE==='demo'?demo.departments():list('/departments',{per_page:100}));},
};
