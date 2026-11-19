/** Interactive public demonstration. All records are synthetic; this is not a hosted Laravel API. */
import type {
  Activity,
  ApprovalStep,
  Comparison,
  Department,
  Invoice,
  InvoiceInput,
  Page,
  PurchaseInput,
  PurchaseRequest,
  Quote,
  QuoteInput,
  Role,
  User,
  Vendor,
  VendorInput,
} from '../domain/types';
import {
  canDecide,
  canSubmitRequest,
  canSendApproval,
  canManageSourcing,
  canAddQuote,
  canManageVendors,
  canCreateInvoice,
  canMarkPaid,
} from '../domain/policy';
export class DemoError extends Error {
  constructor(
    public status: number,
    message: string,
  ) {
    super(message);
    this.name = 'DemoError';
  }
}
type DB = {
  requests: PurchaseRequest[];
  vendors: Vendor[];
  quotes: Quote[];
  steps: ApprovalStep[];
  comparisons: Comparison[];
  invoices: Invoice[];
  activity: Activity[];
};
const key = 'procurepilot-demo-v1';
const today = '2026-11-18T10:30:00Z';
export const departments: Department[] = [
  { id: 1, name: 'Engineering', code: 'ENG' },
  { id: 2, name: 'Operations', code: 'OPS' },
  { id: 3, name: 'Finance', code: 'FIN' },
  { id: 4, name: 'Procurement', code: 'PROC' },
];
export const users: User[] = [
  {
    id: 1,
    name: 'Anna Müller',
    email: 'admin@procurepilot.test',
    role: 'admin',
    organization_id: 1,
    department_id: 2,
  },
  {
    id: 2,
    name: 'Jonas Weber',
    email: 'requester@procurepilot.test',
    role: 'requester',
    organization_id: 1,
    department_id: 1,
  },
  {
    id: 3,
    name: 'Sophie Schneider',
    email: 'procurement@procurepilot.test',
    role: 'procurement',
    organization_id: 1,
    department_id: 4,
  },
  {
    id: 4,
    name: 'Markus Fischer',
    email: 'manager@procurepilot.test',
    role: 'manager',
    organization_id: 1,
    department_id: 1,
  },
  {
    id: 5,
    name: 'Laura Becker',
    email: 'finance@procurepilot.test',
    role: 'finance',
    organization_id: 1,
    department_id: 3,
  },
  {
    id: 6,
    name: 'Felix Wagner',
    email: 'viewer@procurepilot.test',
    role: 'viewer',
    organization_id: 1,
    department_id: 2,
  },
];
const vendorNames = [
  'Müller Office GmbH',
  'BerlinTech Supplies',
  'CloudWerk Software',
  'Schneider Bürobedarf',
  'Nordlicht Systems',
  'Hanseatic Logistics',
  'Alpine Networks',
  'GreenDesk Europe',
];
function seed(): DB {
  const vendors: Vendor[] = vendorNames.map((name, i) => ({
    id: i + 1,
    name,
    email: `sales${i + 1}@example.test`,
    country: 'DE',
    default_currency: 'EUR',
    status: i === 6 ? 'inactive' : 'active',
    website: null,
  }));
  const titles = [
    'Engineering laptop refresh',
    'Cloud infrastructure renewal',
    'Meeting room equipment',
    'Workstation monitors',
    'Security audit services',
    'Office furniture upgrade',
    'Network switch replacement',
    'Developer licenses',
    'Warehouse scanners',
    'VPN hardware upgrade',
    'Data backup solution',
    'Collaboration tools',
  ];
  const statuses: PurchaseRequest['status'][] = [
    'pending_approval',
    'quotes_received',
    'approved',
    'sourcing',
    'submitted',
    'draft',
    'rejected',
    'invoiced',
    'paid',
    'pending_approval',
    'draft',
    'approved',
  ];
  const budgets = [24800, 14600, 3200, 7650, 12300, 5900, 18200, 4800, 8900, 6300, 11900, 2400];
  const owners = [2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2, 2];
  const requests: PurchaseRequest[] = titles.map((title, i) => ({
    id: i + 101,
    title,
    description: [
      'Hardware and services sourcing for the next operating cycle.',
      'Evaluating supplier offers under a controlled approval process.',
    ][i % 2],
    status: statuses[i],
    priority: (
      [
        'high',
        'normal',
        'low',
        'high',
        'urgent',
        'normal',
        'high',
        'normal',
        'normal',
        'high',
        'normal',
        'low',
      ] as const
    )[i],
    department_id: 1,
    requester_id: owners[i],
    estimated_budget: budgets[i],
    currency: 'EUR',
    needed_by_date: `2026-12-${String(3 + i).padStart(2, '0')}`,
    approved_quote_id: null,
    created_at: `2026-11-${String(17 - i).padStart(2, '0')}T09:00:00Z`,
    updated_at: today,
    items: [
      {
        id: 1000 + i,
        name: title,
        quantity: i === 0 ? 12 : 1,
        expected_unit_price: budgets[i] / (i === 0 ? 12 : 1),
        category: 'Operations',
      },
    ],
  }));
  const quotes: Quote[] = [
    {
      id: 201,
      purchase_request_id: 101,
      vendor_id: 2,
      total_amount: 23150,
      currency: 'EUR',
      delivery_days: 7,
      payment_terms: 'Net 30',
      warranty_months: 24,
      status: 'received',
      notes: null,
      created_at: today,
    },
    {
      id: 202,
      purchase_request_id: 101,
      vendor_id: 1,
      total_amount: 24200,
      currency: 'EUR',
      delivery_days: 4,
      payment_terms: 'Net 15',
      warranty_months: 36,
      status: 'received',
      notes: null,
      created_at: today,
    },
    {
      id: 203,
      purchase_request_id: 102,
      vendor_id: 3,
      total_amount: 13700,
      currency: 'EUR',
      delivery_days: 3,
      payment_terms: 'Net 30',
      warranty_months: 12,
      status: 'received',
      notes: null,
      created_at: today,
    },
    {
      id: 204,
      purchase_request_id: 102,
      vendor_id: 5,
      total_amount: 14950,
      currency: 'EUR',
      delivery_days: 8,
      payment_terms: 'Net 30',
      warranty_months: 12,
      status: 'received',
      notes: null,
      created_at: today,
    },
    {
      id: 205,
      purchase_request_id: 103,
      vendor_id: 4,
      total_amount: 2980,
      currency: 'EUR',
      delivery_days: 5,
      payment_terms: 'Net 30',
      warranty_months: 18,
      status: 'received',
      notes: null,
      created_at: today,
    },
  ];
  const steps: ApprovalStep[] = [
    {
      id: 301,
      purchase_request_id: 101,
      sequence: 1,
      approval_role: 'manager',
      approver_user_id: 4,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    },
    {
      id: 302,
      purchase_request_id: 101,
      sequence: 2,
      approval_role: 'finance',
      approver_user_id: 5,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    },
    {
      id: 305,
      purchase_request_id: 101,
      sequence: 3,
      approval_role: 'admin',
      approver_user_id: 1,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    },
    {
      id: 303,
      purchase_request_id: 110,
      sequence: 1,
      approval_role: 'manager',
      approver_user_id: 4,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    },
    {
      id: 306,
      purchase_request_id: 110,
      sequence: 2,
      approval_role: 'finance',
      approver_user_id: 5,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    },
    {
      id: 304,
      purchase_request_id: 103,
      sequence: 1,
      approval_role: 'manager',
      approver_user_id: 4,
      status: 'approved',
      decision_comment: 'Approved within budget',
      decided_at: today,
    },
    {
      id: 307,
      purchase_request_id: 103,
      sequence: 2,
      approval_role: 'finance',
      approver_user_id: 5,
      status: 'approved',
      decision_comment: 'Finance verified',
      decided_at: today,
    },
  ];
  const comparisons: Comparison[] = [
    {
      id: 401,
      recommended_quote_id: 201,
      recommended_vendor: 'BerlinTech Supplies',
      reason: 'Best price-to-delivery tradeoff for the current quotes.',
      created_at: today,
    },
    {
      id: 402,
      recommended_quote_id: 203,
      recommended_vendor: 'CloudWerk Software',
      reason: 'Strongest value based on quoted amount and delivery timeline.',
      created_at: today,
    },
  ];
  const invoices: Invoice[] = [
    {
      id: 501,
      purchase_request_id: 108,
      vendor_id: 3,
      invoice_number: 'INV-2026-0181',
      invoice_date: '2026-11-09',
      due_date: '2026-12-09',
      subtotal: 4200,
      vat_rate: 19,
      vat_amount: 798,
      total: 4998,
      currency: 'EUR',
      status: 'received',
      notes: null,
      created_at: today,
    },
    {
      id: 502,
      purchase_request_id: 109,
      vendor_id: 2,
      invoice_number: 'INV-2026-0179',
      invoice_date: '2026-11-07',
      due_date: '2026-12-07',
      subtotal: 7479,
      vat_rate: 19,
      vat_amount: 1421.01,
      total: 8900.01,
      currency: 'EUR',
      status: 'paid',
      notes: null,
      created_at: today,
    },
    {
      id: 503,
      purchase_request_id: 103,
      vendor_id: 4,
      invoice_number: 'INV-2026-0185',
      invoice_date: '2026-11-12',
      due_date: '2026-12-12',
      subtotal: 2980,
      vat_rate: 19,
      vat_amount: 566.2,
      total: 3546.2,
      currency: 'EUR',
      status: 'approved',
      notes: null,
      created_at: today,
    },
  ];
  const activity: Activity[] = requests.slice(0, 8).map((r, i) => ({
    id: 600 + i,
    user_id: i % 2 ? 3 : 2,
    event: `purchase_request.${r.status}`,
    subject_type: 'PurchaseRequest',
    subject_id: r.id,
    user: { name: i % 2 ? 'Sophie Schneider' : 'Jonas Weber' },
    created_at: r.created_at,
  }));
  return { requests, vendors, quotes, steps, comparisons, invoices, activity };
}
function readDB(): DB {
  try {
    const value = localStorage.getItem(key);
    if (value) {
      const data: unknown = JSON.parse(value);
      if (
        data &&
        typeof data === 'object' &&
        'requests' in data &&
        Array.isArray((data as DB).requests)
      )
        return data as DB;
    }
  } catch {
    /* corrupted demo state resets */
  }
  return seed();
}
let db: DB = typeof localStorage === 'undefined' ? seed() : readDB();
function save() {
  if (typeof localStorage !== 'undefined') localStorage.setItem(key, JSON.stringify(db));
}
export function resetDemo() {
  db = seed();
  save();
}
function deny(message = 'Action not permitted for this demo role'): never {
  throw new DemoError(403, message);
}
function missing(): never {
  throw new DemoError(404, 'Record not found');
}
function requireOne<T extends { id: number }>(rows: T[], id: number): T {
  return rows.find((r) => r.id === id) ?? missing();
}
function log(user: User, event: string, subject_id: number, subject_type = 'PurchaseRequest') {
  db.activity.unshift({
    id: Date.now(),
    user_id: user.id,
    event,
    subject_type,
    subject_id,
    user: { name: user.name },
    created_at: new Date().toISOString(),
  });
}
const nextId = (rows: { id: number }[]) => Math.max(0, ...rows.map((v) => v.id)) + 1;
function page<T>(items: T[], pageNumber = 1, perPage = 100): Page<T> {
  const last = Math.max(1, Math.ceil(items.length / perPage));
  const current = Math.min(Math.max(1, pageNumber), last);
  return {
    data: items.slice((current - 1) * perPage, current * perPage),
    meta: { current_page: current, last_page: last, total: items.length, per_page: perPage },
  };
}
const annotate = (r: PurchaseRequest) => ({
  ...r,
  requester: users.find((u) => u.id === r.requester_id),
  department: departments.find((d) => d.id === r.department_id),
});
export const demo = {
  login(role: Role): User {
    return {
      ...users.find((u) => u.role === role)!,
      organization: { id: 1, name: 'Berlin Mittelstand GmbH', currency: 'EUR', country: 'DE' },
    };
  },
  async requests(
    user: User,
    filters?: { search?: string; status?: string; page?: number; per_page?: number },
  ): Promise<Page<PurchaseRequest>> {
    let rows = db.requests.filter((r) =>
      user.role === 'requester'
        ? r.requester_id === user.id
        : user.role === 'manager'
          ? r.department_id === user.department_id
          : true,
    );
    if (filters?.status) rows = rows.filter((r) => r.status === filters.status);
    if (filters?.search)
      rows = rows.filter((r) => r.title.toLowerCase().includes(filters.search!.toLowerCase()));
    return page(rows.map(annotate), filters?.page, filters?.per_page);
  },
  async request(user: User, id: number): Promise<PurchaseRequest> {
    const r = requireOne(db.requests, id);
    if (
      (user.role === 'requester' && r.requester_id !== user.id) ||
      (user.role === 'manager' && r.department_id !== user.department_id)
    )
      deny();
    return annotate(r);
  },
  async createRequest(user: User, payload: PurchaseInput): Promise<PurchaseRequest> {
    if (!['admin', 'requester', 'procurement'].includes(user.role)) deny();
    const r: PurchaseRequest = {
      id: nextId(db.requests),
      title: payload.title,
      description: payload.description ?? null,
      status: 'draft',
      priority: payload.priority,
      department_id: payload.department_id,
      requester_id: user.id,
      estimated_budget: payload.estimated_budget ?? null,
      currency: payload.currency,
      needed_by_date: payload.needed_by_date ?? null,
      approved_quote_id: null,
      items: payload.items,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    db.requests.unshift(r);
    log(user, 'purchase_request.created', r.id);
    save();
    return annotate(r);
  },
  async submit(user: User, id: number) {
    const r = requireOne(db.requests, id);
    if (!canSubmitRequest(user, r)) deny();
    r.status = 'submitted';
    log(user, 'purchase_request.submitted', id);
    save();
    return annotate(r);
  },
  async vendors(
    user: User,
    filters?: { search?: string; page?: number; per_page?: number },
  ): Promise<Page<Vendor>> {
    const visible = db.vendors.filter((v) => user.role !== 'requester' || v.status === 'active');
    const rows = filters?.search
      ? visible.filter((v) =>
          `${v.name} ${v.email ?? ''}`.toLowerCase().includes(filters.search!.toLowerCase()),
        )
      : visible;
    return page(rows, filters?.page, filters?.per_page);
  },
  async createVendor(user: User, payload: VendorInput) {
    if (!canManageVendors(user.role)) deny();
    if (db.vendors.some((v) => v.name.toLowerCase() === payload.name.toLowerCase()))
      throw new DemoError(422, 'Vendor name must be unique');
    const v: Vendor = {
      ...payload,
      id: nextId(db.vendors),
      status: payload.status ?? 'active',
      default_currency: payload.default_currency ?? 'EUR',
      country: payload.country ?? 'DE',
    };
    db.vendors.unshift(v);
    log(user, 'vendor.created', v.id, 'Vendor');
    save();
    return v;
  },
  async quotes(user: User, requestId: number): Promise<Page<Quote>> {
    await this.request(user, requestId);
    return page(
      db.quotes
        .filter((q) => q.purchase_request_id === requestId)
        .map((q) => ({ ...q, vendor: db.vendors.find((v) => v.id === q.vendor_id) })),
    );
  },
  async createQuote(user: User, requestId: number, payload: QuoteInput): Promise<Quote> {
    await this.request(user, requestId);
    const r = requireOne(db.requests, requestId);
    if (!canAddQuote(user, r)) deny();
    if (
      db.quotes.some(
        (q) => q.purchase_request_id === requestId && q.vendor_id === payload.vendor_id,
      )
    )
      throw new DemoError(422, 'Vendor already quoted this request');
    const q: Quote = {
      id: nextId(db.quotes),
      purchase_request_id: requestId,
      ...payload,
      delivery_days: payload.delivery_days ?? null,
      payment_terms: payload.payment_terms ?? null,
      warranty_months: null,
      notes: payload.notes ?? null,
      created_at: new Date().toISOString(),
    };
    db.quotes.push(q);
    r.status = 'quotes_received';
    log(user, 'quote.created', q.id, 'Quote');
    save();
    return q;
  },
  async comparison(user: User, requestId: number): Promise<Comparison | null> {
    await this.request(user, requestId);
    return (
      db.comparisons.find(
        (c) =>
          (c.id === 401 && requestId === 101) ||
          (c.id === 402 && requestId === 102) ||
          (c as Comparison & { purchase_request_id?: number }).purchase_request_id === requestId,
      ) ?? null
    );
  },
  async generateComparison(user: User, requestId: number): Promise<Comparison> {
    await this.request(user, requestId);
    const r = requireOne(db.requests, requestId);
    if (!canManageSourcing(user, r)) deny();
    const quotes = db.quotes.filter((q) => q.purchase_request_id === requestId);
    if (quotes.length < 2)
      throw new DemoError(422, 'Add at least two supplier quotes before generating a comparison.');
    const q = [...quotes].sort((a, b) => a.total_amount - b.total_amount)[0];
    const c: Comparison & { purchase_request_id: number } = {
      id: nextId(db.comparisons),
      purchase_request_id: requestId,
      recommended_quote_id: q.id,
      recommended_vendor: db.vendors.find((v) => v.id === q.vendor_id)?.name,
      reason:
        'Demo simulation: lowest quoted total. The real backend applies its own deterministic ranking logic.',
      created_at: new Date().toISOString(),
    };
    db.comparisons = db.comparisons.filter(
      (c) =>
        !(
          (c.id === 401 && requestId === 101) ||
          (c.id === 402 && requestId === 102) ||
          (c as Comparison & { purchase_request_id?: number }).purchase_request_id === requestId
        ),
    );
    db.comparisons.push(c);
    r.status = 'quotes_received';
    log(user, 'comparison.generated', requestId);
    save();
    return c;
  },
  async steps(user: User, requestId: number): Promise<ApprovalStep[]> {
    await this.request(user, requestId);
    return db.steps
      .filter((s) => s.purchase_request_id === requestId)
      .sort((a, b) => a.sequence - b.sequence)
      .map((s) => ({ ...s, approver: users.find((u) => u.id === s.approver_user_id) }));
  },
  async sendForApproval(user: User, id: number): Promise<ApprovalStep[]> {
    await this.request(user, id);
    const r = requireOne(db.requests, id);
    if (!canSendApproval(user, r)) deny();
    if (!(await this.comparison(user, id)))
      throw new DemoError(422, 'Generate a comparison before sending for approval.');
    r.status = 'pending_approval';
    const budget = Number(r.estimated_budget ?? 0);
    const sequence = (
      budget < 1000
        ? ['manager']
        : budget <= 10000
          ? ['manager', 'finance']
          : ['manager', 'finance', 'admin']
    ) as Role[];
    if (!users.some((u) => u.role === 'manager' && u.department_id === r.department_id))
      throw new DemoError(422, 'No manager is assigned to the request department.');
    const steps = sequence.map((role, i): ApprovalStep => ({
      id: nextId(db.steps) + i,
      purchase_request_id: id,
      sequence: i + 1,
      approval_role: role,
      approver_user_id: users.find((u) => u.role === role)?.id ?? null,
      status: 'pending',
      decision_comment: null,
      decided_at: null,
    }));
    db.steps.push(...steps);
    log(user, 'approval.started', id);
    save();
    return steps;
  },
  async decide(user: User, stepId: number, decision: 'approve' | 'reject', comment: string) {
    const s = requireOne(db.steps, stepId);
    await this.request(user, s.purchase_request_id);
    const r = requireOne(db.requests, s.purchase_request_id);
    if (!canDecide(user, r, s)) deny();
    if (
      s.sequence > 1 &&
      db.steps.some(
        (st) =>
          st.purchase_request_id === r.id && st.sequence < s.sequence && st.status !== 'approved',
      )
    )
      throw new DemoError(422, 'Earlier approval must be completed first.');
    if (decision === 'reject' && !comment.trim())
      throw new DemoError(422, 'Rejection requires a comment.');
    s.status = decision === 'approve' ? 'approved' : 'rejected';
    s.decided_at = new Date().toISOString();
    s.decision_comment = comment || null;
    if (decision === 'reject') r.status = 'rejected';
    else if (
      db.steps
        .filter((st) => st.purchase_request_id === r.id)
        .every((st) => st.status === 'approved')
    ) {
      r.status = 'approved';
      r.approved_quote_id =
        db.comparisons.find(
          (c) => (c as Comparison & { purchase_request_id?: number }).purchase_request_id === r.id,
        )?.recommended_quote_id ?? null;
    }
    log(user, `approval.${decision === 'approve' ? 'approved' : 'rejected'}`, r.id);
    save();
    return s;
  },
  async invoices(user: User): Promise<Page<Invoice>> {
    const items = db.invoices.filter((i) =>
      user.role === 'requester'
        ? db.requests.find((r) => r.id === i.purchase_request_id)?.requester_id === user.id
        : user.role === 'manager'
          ? db.requests.find((r) => r.id === i.purchase_request_id)?.department_id ===
            user.department_id
          : true,
    );
    return page(items.map((i) => ({ ...i, vendor: db.vendors.find((v) => v.id === i.vendor_id) })));
  },
  async createInvoice(user: User, payload: InvoiceInput): Promise<Invoice> {
    if (!canCreateInvoice(user.role)) deny();
    await this.request(user, payload.purchase_request_id);
    const request = requireOne(db.requests, payload.purchase_request_id);
    if (!['approved', 'ordered', 'invoiced'].includes(request.status))
      throw new DemoError(
        422,
        'Only approved, ordered, or invoiced requests can receive invoices.',
      );
    if (request.approved_quote_id !== null) {
      const quote = requireOne(db.quotes, request.approved_quote_id);
      if (quote.vendor_id !== payload.vendor_id)
        throw new DemoError(422, 'Invoice vendor must match the approved quote vendor.');
    }
    if (db.invoices.some((i) => i.invoice_number === payload.invoice_number))
      throw new DemoError(422, 'Invoice number must be unique.');
    const vat_rate = payload.vat_rate ?? 19,
      vat_amount = Number(((payload.subtotal * vat_rate) / 100).toFixed(2));
    const i: Invoice = {
      ...payload,
      id: nextId(db.invoices),
      due_date: payload.due_date ?? null,
      vat_rate,
      vat_amount,
      total: Number((payload.subtotal + vat_amount).toFixed(2)),
      status: 'received',
      notes: payload.notes ?? null,
      created_at: new Date().toISOString(),
    };
    db.invoices.unshift(i);
    if (['approved', 'ordered'].includes(request.status)) request.status = 'invoiced';
    log(user, 'invoice.created', i.id, 'Invoice');
    save();
    return i;
  },
  async payInvoice(user: User, id: number) {
    if (!canMarkPaid(user.role)) deny();
    const i = requireOne(db.invoices, id);
    if (i.status === 'paid' || i.status === 'cancelled')
      throw new DemoError(422, 'Paid or cancelled invoices cannot be marked as paid.');
    i.status = 'paid';
    const r = requireOne(db.requests, i.purchase_request_id);
    const active = db.invoices.filter(
      (x) => x.purchase_request_id === r.id && x.status !== 'cancelled',
    );
    r.status =
      active.some((x) => x.status === 'paid') && active.every((x) => x.status === 'paid')
        ? 'paid'
        : 'invoiced';
    log(user, 'invoice.paid', id, 'Invoice');
    save();
    return i;
  },
  async activities(user: User): Promise<Page<Activity>> {
    if (!['admin', 'procurement', 'finance'].includes(user.role)) deny();
    return page(db.activity);
  },
  async departments(): Promise<Page<Department>> {
    return page(departments);
  },
};
