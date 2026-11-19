import { lazy, Suspense } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { Loading } from '../components/UI';
import { useAuth } from './auth/AuthContext';
import { LoginPage } from './pages/LoginPage';
import { WorkspaceShell } from './layout/WorkspaceShell';

const EngineeringPage = lazy(() =>
  import('../features/EngineeringPage').then((mod) => ({ default: mod.EngineeringPage })),
);

function ProtectedWorkspace() {
  const { user } = useAuth();
  const location = useLocation();
  if (!user) {
    const from = `${location.pathname}${location.search}${location.hash}`;
    return <Navigate replace to="/login" state={{ from }} />;
  }
  return <WorkspaceShell />;
}

export function App() {
  const { user } = useAuth();
  return (
    <Routes>
      <Route path="/login" element={user ? <Navigate replace to="/" /> : <LoginPage />} />
      <Route
        path="/engineering"
        element={
          <Suspense fallback={<Loading />}>
            <EngineeringPage />
          </Suspense>
        }
      />
      <Route path="/*" element={<ProtectedWorkspace />} />
    </Routes>
  );
}

// Compatibility for existing feature modules while migrating to smaller imports.
export { AuthProvider, useAuth } from './auth/AuthContext';
