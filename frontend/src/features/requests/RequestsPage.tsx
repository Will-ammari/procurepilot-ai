import { useDeferredValue, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Plus, ArrowRight } from 'lucide-react';
import { Link } from 'react-router-dom';
import { useAuth } from '../../app/auth/AuthContext';
import { canCreateRequest } from '../../domain/policy';
import { date, money, label } from '../../domain/format';
import { repo } from '../../services/repository';
import {
  Empty,
  ErrorState,
  Loading,
  PageHeading,
  Panel,
  Priority as PriorityTag,
  SearchInput,
  Status,
  Table,
  PaginationNote,
  Pager,
} from '../../components/UI';
export function Requests() {
  const { user } = useAuth();
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);
  const deferred = useDeferredValue(search);
  const list = useQuery({
    queryKey: ['requests', user.id, status, deferred, page],
    queryFn: () =>
      repo.requests(user, {
        status: status || undefined,
        search: deferred || undefined,
        page,
        per_page: 15,
      }),
  });
  const rows = list.data?.data ?? [];
  return (
    <>
      <PageHeading
        eyebrow="PURCHASE OPERATIONS"
        title="Purchase requests"
        description="Create, track, and resolve every request through a consistent workflow."
        action={
          canCreateRequest(user.role) ? (
            <Link className="btn btn--primary" to="/requests/new">
              <Plus size={17} /> Create request
            </Link>
          ) : undefined
        }
      />
      <Panel
        title="All requests"
        subtitle="Browse the procurement pipeline across accessible departments"
      >
        <div className="list-toolbar">
          <SearchInput
            value={search}
            onChange={(v) => {
              setSearch(v);
              setPage(1);
            }}
            placeholder="Search request names…"
          />
          <select
            className="filter-select"
            value={status}
            aria-label="Filter status"
            onChange={(e) => {
              setStatus(e.target.value);
              setPage(1);
            }}
          >
            <option value="">All statuses</option>
            {[
              'draft',
              'submitted',
              'sourcing',
              'quotes_received',
              'pending_approval',
              'approved',
              'rejected',
              'ordered',
              'invoiced',
              'paid',
              'closed',
            ].map((s) => (
              <option key={s} value={s}>
                {label(s)}
              </option>
            ))}
          </select>
          <span className="list-count">{rows.length} records</span>
        </div>
        {list.isPending ? (
          <Loading />
        ) : list.isError ? (
          <ErrorState error={list.error} onRetry={() => list.refetch()} />
        ) : rows.length ? (
          <>
            <Table head={['REQUEST', 'DEPARTMENT', 'PRIORITY', 'BUDGET', 'STATUS', 'CREATED', '']}>
              {rows.map((r) => (
                <tr key={r.id}>
                  <td>
                    <div className="cell-title">{r.title}</div>
                    <span className="cell-muted">REQ-{String(r.id).padStart(4, '0')}</span>
                  </td>
                  <td className="cell-muted">{r.department?.name ?? `#${r.department_id}`}</td>
                  <td>
                    <PriorityTag value={r.priority} />
                  </td>
                  <td className="weight-medium">{money(r.estimated_budget, r.currency)}</td>
                  <td>
                    <Status value={r.status} />
                  </td>
                  <td className="cell-muted">{date(r.created_at)}</td>
                  <td>
                    <Link
                      className="table-arrow"
                      to={`/requests/${r.id}`}
                      aria-label={`View ${r.title}`}
                    >
                      <ArrowRight size={18} />
                    </Link>
                  </td>
                </tr>
              ))}
            </Table>
            <PaginationNote shown={rows.length} total={list.data?.meta?.total ?? rows.length} />
            <Pager
              page={list.data?.meta?.current_page ?? page}
              max={list.data?.meta?.last_page ?? 1}
              onChange={setPage}
            />
          </>
        ) : (
          <Empty
            title="No matching requests"
            detail="Adjust filters, or start a new purchase request."
            action={
              canCreateRequest(user.role) ? (
                <Link to="/requests/new" className="btn btn--secondary">
                  <Plus size={16} /> Create request
                </Link>
              ) : undefined
            }
          />
        )}
      </Panel>
    </>
  );
}
