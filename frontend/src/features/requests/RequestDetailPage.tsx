import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, Check, ChevronRight, GitCompareArrows, Send, ShieldCheck } from 'lucide-react';
import { Link, useParams } from 'react-router-dom';
import { useAuth } from '../../app/auth/AuthContext';
import { canDecide, canManageSourcing, canSendApproval } from '../../domain/policy';
import { date, money, label } from '../../domain/format';
import { repo } from '../../services/repository';
import {
  Button,
  Empty,
  ErrorState,
  Field,
  Loading,
  Modal,
  Notice,
  PageHeading,
  Panel,
  Priority as PriorityTag,
  Status,
  Table,
} from '../../components/UI';
import { DetailActionBar } from './RequestActions';
export function RequestDetail() {
  const { user } = useAuth();
  const { id } = useParams();
  const requestId = Number(id);
  const qc = useQueryClient();
  const req = useQuery({
    queryKey: ['request', requestId, user.id],
    queryFn: () => repo.request(user, requestId),
    enabled: Number.isSafeInteger(requestId) && requestId > 0,
  });
  const quotes = useQuery({
    queryKey: ['quotes', requestId, user.id],
    queryFn: () => repo.quotes(user, requestId),
    enabled: !!req.data,
  });
  const steps = useQuery({
    queryKey: ['steps', requestId, user.id],
    queryFn: () => repo.steps(user, requestId),
    enabled: !!req.data,
  });
  const comparison = useQuery({
    queryKey: ['comparison', requestId, user.id],
    queryFn: () => repo.comparison(user, requestId),
    enabled: !!req.data,
  });
  const [comment, setComment] = useState('');
  const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
  const refresh = async () => {
    await Promise.all(
      ['request', 'requests', 'quotes', 'steps', 'comparison'].map((k) =>
        qc.invalidateQueries({ queryKey: [k] }),
      ),
    );
  };
  const compare = useMutation({
    mutationFn: () => repo.generateComparison(user, requestId),
    onSuccess: refresh,
  });
  const send = useMutation({
    mutationFn: () => repo.sendForApproval(user, requestId),
    onSuccess: refresh,
  });
  const decide = useMutation({
    mutationFn: ({ stepId, kind }: { stepId: number; kind: 'approve' | 'reject' }) =>
      repo.decide(user, stepId, kind, comment),
    onSuccess: async () => {
      setDecision(null);
      setComment('');
      await refresh();
    },
  });
  if (!Number.isSafeInteger(requestId) || requestId < 1)
    return <ErrorState error={Error('Invalid purchase request ID')} />;
  if (req.isPending) return <Loading />;
  if (req.isError) return <ErrorState error={req.error} onRetry={() => req.refetch()} />;
  const r = req.data;
  const quoteItems = quotes.data?.data ?? [];
  const approvers = steps.data ?? [];
  const selectedStep = approvers.find(
    (s) =>
      canDecide(user, r, s) &&
      (s.sequence === 1 ||
        approvers.filter((x) => x.sequence < s.sequence).every((x) => x.status === 'approved')),
  );
  return (
    <>
      <div className="back-line">
        <Link to="/requests">
          <ArrowLeft size={16} /> All purchase requests
        </Link>
      </div>
      <PageHeading
        eyebrow={`PURCHASE REQUEST / REQ-${String(r.id).padStart(4, '0')}`}
        title={r.title}
        description={r.description || 'Procurement request detail and decision workspace.'}
        action={<DetailActionBar request={r} />}
      />
      <div className="detail-meta">
        <Status value={r.status} />
        <span>
          <PriorityTag value={r.priority} />
        </span>
        <span>Created {date(r.created_at)}</span>
        <span>Department: {r.department?.name || `#${r.department_id}`}</span>
      </div>
      <div className="detail-grid">
        <Panel title="Request summary" subtitle="Budget, need and ownership">
          <div className="summary-grid">
            <div>
              <small>Estimated budget</small>
              <strong>{money(r.estimated_budget, r.currency)}</strong>
            </div>
            <div>
              <small>Needed by</small>
              <strong>{date(r.needed_by_date)}</strong>
            </div>
            <div>
              <small>Requested by</small>
              <strong>{r.requester?.name || `User #${r.requester_id}`}</strong>
            </div>
            <div>
              <small>Current stage</small>
              <strong>{label(r.status)}</strong>
            </div>
          </div>
        </Panel>
        <Panel title="Approval pathway" subtitle="Tracked steps and decisions">
          <div className="mini-steps">
            <div className="mini-step mini-step--done">
              <Check size={15} /> Request created
            </div>
            <div className={`mini-step ${r.status !== 'draft' ? 'mini-step--done' : ''}`}>
              <Check size={15} /> Sourcing initiated
            </div>
            <div
              className={`mini-step ${['approved', 'paid', 'closed'].includes(r.status) ? 'mini-step--done' : ''}`}
            >
              <ShieldCheck size={15} /> Approval decision
            </div>
          </div>
        </Panel>
      </div>
      <Panel title="Line items" subtitle="The original requested goods and services">
        {r.items?.length ? (
          <Table head={['ITEM', 'QUANTITY', 'EST. UNIT PRICE', 'EST. TOTAL']}>
            {r.items.map((item, i) => (
              <tr key={item.id ?? i}>
                <td className="weight-medium">{item.name}</td>
                <td>{item.quantity}</td>
                <td>{money(item.expected_unit_price, r.currency)}</td>
                <td className="weight-medium">
                  {money((item.expected_unit_price ?? 0) * item.quantity, r.currency)}
                </td>
              </tr>
            ))}
          </Table>
        ) : (
          <Empty
            title="No line items in response"
            detail="List API resources can omit the items relation; detailed endpoints should include it."
          />
        )}
      </Panel>
      <Panel
        title="Supplier quotations"
        subtitle="Compare offers and their commercial terms"
        action={
          canManageSourcing(user, r) && quoteItems.length >= 2 ? (
            <Button
              variant="secondary"
              disabled={compare.isPending}
              onClick={() => compare.mutate()}
            >
              <GitCompareArrows size={16} />{' '}
              {compare.isPending ? 'Comparing…' : 'Generate comparison'}
            </Button>
          ) : undefined
        }
      >
        {quotes.isPending ? (
          <Loading />
        ) : quotes.isError ? (
          <ErrorState error={quotes.error} onRetry={() => quotes.refetch()} />
        ) : quoteItems.length ? (
          <Table head={['VENDOR', 'AMOUNT', 'DELIVERY', 'PAYMENT TERMS', 'STATUS']}>
            {quoteItems.map((q) => (
              <tr
                key={q.id}
                className={comparison.data?.recommended_quote_id === q.id ? 'row--highlight' : ''}
              >
                <td>
                  <strong>{q.vendor?.name || `Vendor #${q.vendor_id}`}</strong>
                  {comparison.data?.recommended_quote_id === q.id && (
                    <span className="recommend-chip">RECOMMENDED</span>
                  )}
                </td>
                <td className="weight-medium">{money(q.total_amount, q.currency)}</td>
                <td>{q.delivery_days === null ? '—' : `${q.delivery_days} days`}</td>
                <td className="cell-muted">{q.payment_terms || '—'}</td>
                <td>
                  <Status value={q.status} />
                </td>
              </tr>
            ))}
          </Table>
        ) : (
          <Empty
            title="No supplier quotes yet"
            detail="Add quotes after sourcing begins to unlock comparison and approval."
          />
        )}
        {comparison.data && (
          <div className="comparison-card">
            <div className="comparison-card__icon">
              <GitCompareArrows size={22} />
            </div>
            <div>
              <small>LATEST COMPARISON</small>
              <strong>
                {comparison.data.recommended_vendor ||
                  `Quote #${comparison.data.recommended_quote_id}`}
              </strong>
              <p>{comparison.data.reason || 'Comparison generated by the backend.'}</p>
            </div>
          </div>
        )}
        {comparison.isError && (
          <Notice tone="warning">Comparison unavailable: {comparison.error.message}</Notice>
        )}
        {compare.isError && <Notice tone="danger">{compare.error.message}</Notice>}
      </Panel>
      <Panel
        title="Approval history"
        subtitle="Sequential decisions with role-specific authorisation"
        action={
          canSendApproval(user, r) ? (
            <Button disabled={send.isPending || !comparison.data} onClick={() => send.mutate()}>
              <Send size={16} /> {send.isPending ? 'Starting…' : 'Send for approval'}
            </Button>
          ) : undefined
        }
      >
        {steps.isPending ? (
          <Loading />
        ) : steps.isError ? (
          <ErrorState error={steps.error} onRetry={() => steps.refetch()} />
        ) : approvers.length ? (
          <div className="approval-steps">
            {approvers.map((s) => (
              <div className="approval-row" key={s.id}>
                <span
                  className={`approval-number ${s.status === 'approved' ? 'approval-number--done' : ''}`}
                >
                  {s.status === 'approved' ? <Check size={16} /> : s.sequence}
                </span>
                <div className="approval-row__body">
                  <strong>{label(s.approval_role)} review</strong>
                  <small>{s.approver?.name || `Assigned role: ${label(s.approval_role)}`}</small>
                  {s.decision_comment && <p>“{s.decision_comment}”</p>}
                </div>
                <Status value={s.status} />
              </div>
            ))}
          </div>
        ) : (
          <Empty
            title="Approval hasn't started"
            detail="Generate a quote comparison, then send the request through its approval chain."
          />
        )}
        {selectedStep && (
          <div className="approval-decision">
            <strong>Your decision is requested</strong>
            <p>You are the designated {label(selectedStep.approval_role)} reviewer.</p>
            <div className="detail-actions">
              <Button
                onClick={() => {
                  setDecision('approve');
                }}
              >
                <Check size={16} /> Approve
              </Button>
              <Button
                variant="danger"
                onClick={() => {
                  setDecision('reject');
                }}
              >
                Reject request
              </Button>
            </div>
          </div>
        )}
        {send.isError && <Notice tone="danger">{send.error.message}</Notice>}
      </Panel>
      {decision && selectedStep && (
        <Modal
          title={decision === 'approve' ? 'Approve request' : 'Reject request'}
          onClose={() => setDecision(null)}
        >
          <div className="stack-md">
            <Notice tone={decision === 'reject' ? 'warning' : 'info'}>
              {decision === 'reject'
                ? 'A reason is required and will be recorded in the audit trail.'
                : 'This decision will advance the approval workflow if all earlier steps are complete.'}
            </Notice>
            <Field label="Decision comment" required={decision === 'reject'}>
              <textarea
                rows={4}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Add context for this decision…"
              />
            </Field>
            {decide.isError && <Notice tone="danger">{decide.error.message}</Notice>}
            <div className="modal__actions">
              <Button variant="secondary" onClick={() => setDecision(null)}>
                Cancel
              </Button>
              <Button
                variant={decision === 'reject' ? 'danger' : 'primary'}
                disabled={decide.isPending || (decision === 'reject' && !comment.trim())}
                onClick={() => decide.mutate({ stepId: selectedStep.id, kind: decision })}
              >
                {decide.isPending
                  ? 'Saving…'
                  : decision === 'approve'
                    ? 'Confirm approval'
                    : 'Confirm rejection'}
              </Button>
            </div>
          </div>
        </Modal>
      )}
      <div className="detail-bottom">
        <Link className="subtle-link" to="/requests">
          Back to requests <ChevronRight size={15} />
        </Link>
      </div>
    </>
  );
}
