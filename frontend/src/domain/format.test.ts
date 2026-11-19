import { describe,it,expect } from 'vitest';
import {label,money,initials} from './format';
import {canSubmitRequest,canDecide} from './policy';
import type {PurchaseRequest, User, ApprovalStep} from './types';
describe('format helpers',()=>{
  it('handles null budgets safely',()=>expect(money(null)).toBe('—'));
  it('labels server statuses',()=>expect(label('pending_approval')).toBe('Pending Approval'));
  it('renders initials',()=>expect(initials('Anna Müller')).toBe('AM'));
});
describe('client presentation guards (server policies remain authoritative)',()=>{
  const user={id:1,role:'requester',department_id:1} as User;
  const request={id:4,status:'draft',requester_id:1,department_id:1} as PurchaseRequest;
  it('limits submit action to the requester while draft',()=>{
    expect(canSubmitRequest(user,request)).toBe(true);
    expect(canSubmitRequest({...user,id:2},request)).toBe(false);
    expect(canSubmitRequest(user,{...request,status:'submitted'})).toBe(false);
  });
  it('prevents self-approval',()=>{
    const step={status:'pending',approval_role:'manager',approver_user_id:1} as ApprovalStep;
    expect(canDecide({...user,role:'manager'},request,step)).toBe(false);
  });
});
