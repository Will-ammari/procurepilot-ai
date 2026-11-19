export type Role = 'admin' | 'requester' | 'procurement' | 'manager' | 'finance' | 'viewer';
export type RequestStatus = 'draft' | 'submitted' | 'sourcing' | 'quotes_received' | 'pending_approval' | 'approved' | 'rejected' | 'ordered' | 'invoiced' | 'paid' | 'closed';
export type Priority = 'low' | 'normal' | 'high' | 'urgent';
export type Page<T> = {data:T[];meta?:{current_page:number;last_page:number;total:number;per_page:number}};
export interface User {id:number;name:string;email:string;role:Role;organization_id:number;department_id:number|null;organization?:{id:number;name:string;currency:string;country:string};department?:{id:number;name:string;code:string}|null}
export interface Department {id:number;name:string;code:string}
export interface PurchaseItem {id?:number;name:string;description?:string|null;quantity:number;expected_unit_price:number|null;category?:string|null}
export interface PurchaseRequest {id:number;title:string;description:string|null;status:RequestStatus;priority:Priority;department_id:number;requester_id:number;estimated_budget:number|null;currency:string;needed_by_date:string|null;approved_quote_id:number|null;created_at:string;updated_at:string;department?:Department;requester?:Pick<User,'id'|'name'|'email'|'role'>;items?:PurchaseItem[]}
export type PurchaseInput = {title:string;description?:string;department_id:number;estimated_budget?:number|null;priority:Priority;currency:string;needed_by_date?:string|null;items:Omit<PurchaseItem,'id'>[]};
export interface Vendor {id:number;name:string;legal_name?:string|null;email?:string|null;phone?:string|null;country?:string|null;website?:string|null;status:'active'|'inactive'|'blocked';default_currency:string;address?:string|null}
export type VendorInput = {name:string;email?:string;country?:string;default_currency?:string;status?:Vendor['status'];website?:string};
export interface Quote {id:number;purchase_request_id:number;vendor_id:number;total_amount:number;currency:string;delivery_days:number|null;payment_terms:string|null;warranty_months:number|null;status:'draft'|'received'|'analysis_pending'|'analyzed';notes:string|null;vendor?:Vendor;created_at:string}
export type QuoteInput = {vendor_id:number;total_amount:number;currency:string;delivery_days?:number|null;payment_terms?:string;status:'draft'|'received';notes?:string};
export interface Comparison {id:number;recommended_quote_id:number|null;recommended_vendor?:string|null;reason?:string|null;weights?:Record<string,number>;quotes?:unknown[];created_at:string}
export interface ApprovalStep {id:number;purchase_request_id:number;sequence:number;approval_role:Role;approver_user_id:number|null;status:'pending'|'approved'|'rejected'|'skipped';decision_comment:string|null;decided_at:string|null;approver?:User|null;decided_by?:User|null}
export interface Invoice {id:number;purchase_request_id:number;vendor_id:number;invoice_number:string;invoice_date:string;due_date:string|null;subtotal:number;vat_rate:number;vat_amount:number;total:number;currency:string;status:'received'|'approved'|'paid'|'overdue'|'cancelled';notes:string|null;vendor?:Vendor;created_at:string}
export type InvoiceInput = {purchase_request_id:number;vendor_id:number;invoice_number:string;invoice_date:string;due_date?:string|null;subtotal:number;vat_rate?:number;currency:string;notes?:string};
export interface Activity {id:number;user_id:number;event:string;subject_type:string;subject_id:number;metadata?:Record<string,unknown>;user?:Pick<User,'name'>|null;created_at:string}
export type DataMode = 'api'|'demo';
export const REQUEST_STATUSES: RequestStatus[] = ['draft','submitted','sourcing','quotes_received','pending_approval','approved','rejected','ordered','invoiced','paid','closed'];
