import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { App, AuthProvider } from './app/App';
import './styles/app.css';
const queryClient = new QueryClient({defaultOptions:{queries:{retry:1,staleTime:20_000,refetchOnWindowFocus:false}}});
createRoot(document.getElementById('root')!).render(
  <React.StrictMode><QueryClientProvider client={queryClient}><BrowserRouter><AuthProvider><App /></AuthProvider></BrowserRouter></QueryClientProvider></React.StrictMode>
);
