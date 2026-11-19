import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { Activity, ArrowRight, ShieldCheck, Sparkles } from 'lucide-react';
import { useAuth } from '../auth/AuthContext';
import { repo } from '../../services/repository';
import { users } from '../../services/demo';
import type { Role } from '../../domain/types';
import { Button, Notice } from '../../components/UI';
export function LoginPage() {
  const { login } = useAuth();
  const [role, setRole] = useState<Role>('admin');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();
  const location = useLocation();
  async function submit(event: FormEvent) {
    event.preventDefault();
    setBusy(true);
    setError('');
    try {
      await login(repo.mode === 'demo' ? { role } : { email, password });
      const from = (location.state as { from?: unknown } | null)?.from;
      const safeTarget =
        typeof from === 'string' && from.startsWith('/') && !from.startsWith('//') ? from : '/';
      navigate(safeTarget, { replace: true });
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Sign in failed');
    } finally {
      setBusy(false);
    }
  }
  return (
    <main className="login-page">
      <div className="login-brand">
        <div className="brand-mark">
          <Sparkles size={23} />
        </div>
        <span>
          procure<span className="brand-light">pilot</span>
          <b>AI</b>
        </span>
      </div>
      <div className="login-grid">
        <section className="login-story">
          <div className="login-story__eyebrow">
            <span /> BUILT FOR BETTER BUYING
          </div>
          <h1>
            Every purchase.
            <br />
            <em>Under control.</em>
          </h1>
          <p>
            A modern procurement workspace to manage sourcing, approvals, supplier quotes and
            financial visibility — without the spreadsheet chaos.
          </p>
          <div className="login-trust">
            <span>
              <ShieldCheck size={18} /> Scoped permissions
            </span>
            <span>
              <Activity size={18} /> Auditable workflows
            </span>
            <span>
              <Sparkles size={18} /> Informed comparisons
            </span>
          </div>
          <div className="login-abstract">
            <span className="abstract-corner">PROCUREMENT WORKSPACE / 2026</span>
            <div className="abstract-card">
              <span>Purchase workflow</span>
              <div className="abstract-progress">
                <i />
                <i />
                <i />
              </div>
              <strong>From request to resolution.</strong>
              <small>Every decision, traceable.</small>
            </div>
          </div>
        </section>
        <section className="login-form-card">
          <div className="eyebrow">WELCOME BACK</div>
          <h2>Access your workspace</h2>
          <p>Manage decisions with clarity, from initial request to final approval.</p>
          {repo.mode === 'demo' && (
            <Notice tone="info">
              <strong>Interactive portfolio demo</strong>
              <br />
              All records and actions are simulated locally. No real backend is connected.
            </Notice>
          )}
          <form onSubmit={submit} className="stack-md">
            {repo.mode === 'demo' ? (
              <label className="field">
                <span className="field__label">Explore as a team role</span>
                <select value={role} onChange={(e) => setRole(e.target.value as Role)}>
                  {users.map((u) => (
                    <option key={u.role} value={u.role}>
                      {u.name} — {u.role}
                    </option>
                  ))}
                </select>
                <small className="field__hint">
                  Roles simulate frontend visibility; Laravel enforces real permissions in API mode.
                </small>
              </label>
            ) : (
              <>
                <label className="field">
                  <span className="field__label">Email address</span>
                  <input
                    required
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    autoComplete="username"
                    placeholder="you@company.com"
                  />
                </label>
                <label className="field">
                  <span className="field__label">Password</span>
                  <input
                    required
                    type="password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    autoComplete="current-password"
                  />
                </label>
              </>
            )}
            {error && <Notice tone="danger">{error}</Notice>}
            <Button type="submit" disabled={busy} className="btn--full">
              {busy ? 'Opening workspace…' : 'Enter workspace'} <ArrowRight size={18} />
            </Button>
          </form>
          <div className="login-foot">
            A portfolio demonstration of enterprise procurement workflows.{' '}
            <Link to="/engineering">Engineering case study</Link> ·{' '}
            <a
              href="https://github.com/Will-ammari/procurepilot-ai"
              target="_blank"
              rel="noopener noreferrer"
            >
              GitHub source
            </a>
          </div>
        </section>
      </div>
    </main>
  );
}
