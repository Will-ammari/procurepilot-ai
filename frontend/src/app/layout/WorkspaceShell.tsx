import { useEffect, useState } from 'react';
import type { ChangeEvent } from 'react';
import { Link, NavLink, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import {
  Activity,
  ArrowRight,
  BriefcaseBusiness,
  Building2,
  ChevronDown,
  ClipboardCheck,
  FileText,
  LayoutDashboard,
  LogOut,
  Menu,
  PanelLeftClose,
  ShieldCheck,
  Sparkles,
  X,
} from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { repo } from '../../services/repository';
import { resetDemo, users } from '../../services/demo';
import { initials } from '../../domain/format';
import type { Role } from '../../domain/types';
import { Loading } from '../../components/UI';
import { lazy, Suspense } from 'react';
const Dashboard = lazy(() =>
  import('../../features/Dashboard').then((m) => ({ default: m.Dashboard })),
);
const Requests = lazy(() =>
  import('../../features/requests/RequestsPage').then((m) => ({ default: m.Requests })),
);
const NewRequest = lazy(() =>
  import('../../features/requests/NewRequestPage').then((m) => ({ default: m.NewRequest })),
);
const RequestDetail = lazy(() =>
  import('../../features/requests/RequestDetailPage').then((m) => ({ default: m.RequestDetail })),
);
const Vendors = lazy(() => import('../../features/Vendors').then((m) => ({ default: m.Vendors })));
const Invoices = lazy(() =>
  import('../../features/Invoices').then((m) => ({ default: m.Invoices })),
);
const Approvals = lazy(() =>
  import('../../features/Approvals').then((m) => ({ default: m.Approvals })),
);
const ActivityPage = lazy(() =>
  import('../../features/ActivityPage').then((m) => ({ default: m.ActivityPage })),
);
const nav = [
  { to: '/', label: 'Overview', icon: LayoutDashboard },
  { to: '/requests', label: 'Purchase requests', icon: BriefcaseBusiness },
  { to: '/approvals', label: 'Approvals', icon: ClipboardCheck },
  { to: '/vendors', label: 'Suppliers', icon: Building2 },
  { to: '/invoices', label: 'Invoices', icon: FileText },
  { to: '/activity', label: 'Activity log', icon: Activity },
];
export function WorkspaceShell() {
  const { user, logout, login } = useAuth();
  const [mobile, setMobile] = useState(false);
  const [collapsed, setCollapsed] = useState(false);
  const [switching, setSwitching] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const queryClient = useQueryClient();
  useEffect(() => {
    if (!mobile) return;
    const close = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setMobile(false);
    };
    window.addEventListener('keydown', close);
    return () => window.removeEventListener('keydown', close);
  }, [mobile]);
  const selected =
    nav.find(
      (n) =>
        location.pathname === n.to || (n.to !== '/' && location.pathname.startsWith(n.to + '/')),
    )?.label ?? 'Overview';
  async function signOut() {
    await logout();
    navigate('/login', { replace: true });
  }
  async function changeRole(e: ChangeEvent<HTMLSelectElement>) {
    setSwitching(true);
    try {
      await login({ role: e.target.value as Role });
      navigate('/');
    } finally {
      setSwitching(false);
    }
  }
  return (
    <div className={`shell ${collapsed ? 'shell--compact' : ''}`}>
      <a className="skip-link" href="#main-content">
        Skip to main content
      </a>
      <button
        type="button"
        tabIndex={mobile ? 0 : -1}
        aria-label="Close mobile navigation"
        aria-hidden={!mobile}
        className={`mobile-shade ${mobile ? 'mobile-shade--open' : ''}`}
        onClick={() => setMobile(false)}
      />
      <aside className={`sidebar ${mobile ? 'sidebar--open' : ''}`}>
        <div className="sidebar__brand">
          <div className="brand-mark">
            <Sparkles size={21} />
          </div>
          <span className="sidebar__brandtext">
            procure<b>pilot</b>
            <sup>AI</sup>
          </span>
          <button
            aria-label="Close menu"
            className="mobile-only icon-button"
            onClick={() => setMobile(false)}
          >
            <X size={20} />
          </button>
        </div>
        <div className="sidebar__workspace">
          <span className="workspace-logo">B</span>
          <div className="sidebar__workspace-text">
            <strong>Berlin Mittelstand</strong>
            <small>Enterprise workspace</small>
          </div>
          <ChevronDown size={15} />
        </div>
        <div className="sidebar__group-label">WORKSPACE</div>
        <nav aria-label="Main navigation" className="sidebar__nav">
          {nav
            .filter(
              (item) =>
                item.to !== '/activity' || ['admin', 'procurement', 'finance'].includes(user.role),
            )
            .map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                onClick={() => setMobile(false)}
                className={({ isActive }) =>
                  `sidebar__link ${isActive ? 'sidebar__link--active' : ''}`
                }
                title={item.label}
              >
                <item.icon size={19} />
                <span>{item.label}</span>
              </NavLink>
            ))}
        </nav>
        <div className="sidebar__bottom">
          <div className="sidebar__hint">
            <span className="sidebar__hint-icon">
              <ShieldCheck size={19} />
            </span>
            <div>
              <b>{repo.mode === 'demo' ? 'DEMO ENVIRONMENT' : 'REAL API MODE'}</b>
              <p>
                {repo.mode === 'demo'
                  ? 'Synthetic data · no external requests'
                  : 'Secured by your Laravel API'}
              </p>
            </div>
          </div>
          <button
            className="sidebar__collapse"
            onClick={() => setCollapsed(!collapsed)}
            title="Toggle sidebar"
          >
            <PanelLeftClose size={17} /> <span>{collapsed ? 'Expand' : 'Collapse sidebar'}</span>
          </button>
        </div>
      </aside>
      <div className="main-column">
        <header className="topbar">
          <button
            className="mobile-only icon-button"
            aria-label="Open navigation"
            onClick={() => setMobile(true)}
          >
            <Menu size={23} />
          </button>
          <div className="breadcrumbs">
            Workspace <span>/</span> <b>{selected}</b>
          </div>
          <div className="topbar__right">
            <span className="topbar__tag">
              <span /> {repo.mode === 'demo' ? 'Demo environment' : 'Live API'}
            </span>
            <div className="topbar__divider" />
            <div className="user-chip">
              <div className="user-chip__avatar">{initials(user.name)}</div>
              <div>
                <strong>{user.name}</strong>
                <small>{user.role}</small>
              </div>
            </div>
            <button
              className="icon-button"
              title="Sign out"
              aria-label="Sign out"
              onClick={signOut}
            >
              <LogOut size={18} />
            </button>
          </div>
        </header>
        <div className="main-scroll" id="main-content" role="main" tabIndex={-1}>
          <div className="content-wrap">
            {repo.mode === 'demo' && (
              <div className="demo-strip">
                <span>
                  <Sparkles size={15} /> Public portfolio demo — simulated data, not connected to
                  production infrastructure
                </span>
                <div className="demo-tools">
                  <label>
                    Role{' '}
                    <select disabled={switching} value={user.role} onChange={changeRole}>
                      {users.map((u) => (
                        <option key={u.id} value={u.role}>
                          {u.role}
                        </option>
                      ))}
                    </select>
                  </label>
                  <button
                    onClick={() => {
                      resetDemo();
                      queryClient.clear();
                      window.location.assign('/');
                    }}
                    title="Restore original demo data"
                  >
                    Reset demo
                  </button>
                </div>
              </div>
            )}
            <Suspense fallback={<Loading label="Loading workspace section…" />}>
              <Routes>
                <Route path="/" element={<Dashboard />} />
                <Route path="/requests" element={<Requests />} />
                <Route path="/requests/new" element={<NewRequest />} />
                <Route path="/requests/:id" element={<RequestDetail />} />
                <Route path="/vendors" element={<Vendors />} />
                <Route path="/invoices" element={<Invoices />} />
                <Route path="/approvals" element={<Approvals />} />
                <Route path="/activity" element={<ActivityPage />} />
                <Route
                  path="*"
                  element={
                    <section className="notfound">
                      <h1>Page not found</h1>
                      <p>That workspace route does not exist.</p>
                      <Link to="/" className="btn btn--primary">
                        Back to dashboard <ArrowRight size={16} />
                      </Link>
                    </section>
                  }
                />
              </Routes>
            </Suspense>
            <footer className="app-footer">
              <span>© 2026 ProcurePilot AI · Portfolio demonstration</span>
              <span>
                <Link to="/engineering">Engineering case study</Link> ·{' '}
                <a
                  href="https://github.com/Will-ammari/procurepilot-ai"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  GitHub source
                </a>
              </span>
            </footer>
          </div>
        </div>
      </div>
    </div>
  );
}
