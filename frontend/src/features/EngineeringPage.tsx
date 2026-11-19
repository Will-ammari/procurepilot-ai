import {
  ArrowLeft,
  ExternalLink,
  GitBranch,
  ShieldCheck,
  Workflow,
  Activity,
  Layers3,
} from 'lucide-react';
import { Link } from 'react-router-dom';

const source = 'https://github.com/Will-ammari/procurepilot-ai';
const decisions = [
  {
    title: 'Transactional Outbox',
    icon: Workflow,
    detail:
      'State changes and a durable outbox record share one database transaction. A separate publisher handles retry and failure recovery without sending messages before commit.',
  },
  {
    title: 'Concurrency & data integrity',
    icon: ShieldCheck,
    detail:
      'Approval paths use explicit transaction boundaries, parent-row lock ordering and state revalidation, with MySQL concurrency tests.',
  },
  {
    title: 'Traceability & operations',
    icon: Activity,
    detail:
      'Request correlation, operational logs and metrics allow a focused diagnosis of asynchronous processing. Live SLOs and production incident ownership are not claimed.',
  },
  {
    title: 'Quality gates',
    icon: GitBranch,
    detail:
      'GitHub Actions runs Laravel checks, MySQL concurrency/outbox tests and frontend checks. The frontend demo has isolated browser tests in desktop and mobile configurations.',
  },
];
export function EngineeringPage() {
  return (
    <main className="engineering-page">
      <div className="engineering-container">
        <nav className="engineering-nav" aria-label="Case study navigation">
          <Link to="/login">
            <ArrowLeft size={16} /> Back to demo
          </Link>
          <a href={source} target="_blank" rel="noopener noreferrer">
            Browse source on GitHub <ExternalLink size={16} />
          </a>
        </nav>
        <div className="engineering-eyebrow">
          <Layers3 size={18} /> ENGINEERING CASE STUDY
        </div>
        <h1>
          ProcurePilot AI <span>— under the hood</span>
        </h1>
        <p className="engineering-lead">
          A portfolio implementation focused on procurement workflows, multi-tenant authorization,
          transactional consistency and testable asynchronous integration.
        </p>
        <div className="engineering-clarification" role="note">
          <strong>Scope matters.</strong> The public Vercel interface is a browser-based simulated
          demo. The Laravel/MySQL/Redis/FastAPI backend lives in the source repository and is tested
          through CI; it is not a publicly hosted API.
        </div>
        <h2>Architecture and decisions</h2>
        <div className="engineering-grid">
          {decisions.map(({ title, icon: Icon, detail }) => (
            <section className="engineering-card" key={title}>
              <Icon aria-hidden="true" size={23} />
              <h3>{title}</h3>
              <p>{detail}</p>
            </section>
          ))}
        </div>
        <section className="engineering-evidence">
          <h2>How to verify the work</h2>
          <p>
            Review the code, workflows and test definitions rather than treating the demo as
            evidence of live production reliability.
          </p>
          <div className="engineering-links">
            <a href={`${source}/actions`} target="_blank" rel="noopener noreferrer">
              CI runs <ExternalLink size={15} />
            </a>
            <a href={`${source}/tree/main/tests`} target="_blank" rel="noopener noreferrer">
              Backend tests <ExternalLink size={15} />
            </a>
            <a href={`${source}/tree/main/frontend/e2e`} target="_blank" rel="noopener noreferrer">
              Browser tests <ExternalLink size={15} />
            </a>
          </div>
        </section>
        <p className="engineering-foot">
          Engineering showcase · source-verifiable claims · no customer or production data
        </p>
      </div>
    </main>
  );
}
