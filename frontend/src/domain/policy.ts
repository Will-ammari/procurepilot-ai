import type {ApprovalStep, PurchaseRequest, Role, User} from './types';
export function canCreateRequest(role:Role) { return ['admin','requester','procurement'].includes(role); }
export function canSubmitRequest(user:User, request:PurchaseRequest) {
  return request.status === 'draft' && (user.role === 'admin' || (user.role === 'requester' && user.id === request.requester_id));
}
export function canAddQuote(user:User, request:PurchaseRequest) {return ['admin','procurement'].includes(user.role) && ['submitted','sourcing','quotes_received'].includes(request.status);}
export function canManageSourcing(user:User, request:PurchaseRequest) {
  return ['admin','procurement'].includes(user.role) && ['sourcing','quotes_received'].includes(request.status);
}
export function canSendApproval(user:User, request:PurchaseRequest) {
  return ['admin','procurement'].includes(user.role) && request.status === 'quotes_received';
}
export function canDecide(user:User, request:PurchaseRequest, step:ApprovalStep) {
  if (step.status !== 'pending' || request.requester_id === user.id || (step.approver_user_id !== null && step.approver_user_id !== user.id)) return false;
  return (step.approval_role === 'manager' && user.role === 'manager' && request.department_id === user.department_id) ||
    (step.approval_role === 'finance' && user.role === 'finance') || (step.approval_role === 'admin' && user.role === 'admin');
}
export function canManageVendors(role:Role) {return ['admin','procurement'].includes(role);}
export function canCreateInvoice(role:Role) {return ['admin','finance'].includes(role);}
export function canMarkPaid(role:Role) {return ['admin','finance'].includes(role);}
