import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Check, Plus, Send } from 'lucide-react';
import { useAuth } from '../../app/auth/AuthContext';
import { canSubmitRequest, canAddQuote } from '../../domain/policy';
import type { PurchaseRequest, QuoteInput } from '../../domain/types';
import { repo } from '../../services/repository';
import { Button, Field, Modal, Notice } from '../../components/UI';
function QuoteModal({ request, onClose }: { request: PurchaseRequest; onClose: () => void }) {
  const { user } = useAuth(),
    qc = useQueryClient();
  const vendors = useQuery({ queryKey: ['vendors', user.id], queryFn: () => repo.vendors(user) });
  const [form, setForm] = useState({
    vendor_id: '',
    total_amount: '',
    currency: request.currency,
    delivery_days: '',
    payment_terms: 'Net 30',
    status: 'received' as const,
    notes: '',
  });
  const mutation = useMutation({
    mutationFn: () =>
      repo.createQuote(user, request.id, {
        vendor_id: Number(form.vendor_id),
        total_amount: Number(form.total_amount),
        currency: form.currency,
        delivery_days: form.delivery_days ? Number(form.delivery_days) : null,
        payment_terms: form.payment_terms,
        status: form.status,
        notes: form.notes,
      } as QuoteInput),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['request', request.id] });
      await qc.invalidateQueries({ queryKey: ['quotes', request.id] });
      await qc.invalidateQueries({ queryKey: ['comparison', request.id] });
      await qc.invalidateQueries({ queryKey: ['requests'] });
      onClose();
    },
  });
  return (
    <Modal title="Record supplier quote" onClose={onClose}>
      <form
        className="stack-md"
        onSubmit={(e) => {
          e.preventDefault();
          mutation.mutate();
        }}
      >
        <Field label="Supplier" required>
          <select
            required
            value={form.vendor_id}
            onChange={(e) => setForm({ ...form, vendor_id: e.target.value })}
          >
            <option value="">Choose a supplier</option>
            {vendors.data?.data
              .filter((v) => v.status === 'active')
              .map((v) => (
                <option key={v.id} value={v.id}>
                  {v.name}
                </option>
              ))}
          </select>
        </Field>
        <div className="form-row">
          <Field label="Quoted total (€)" required>
            <input
              required
              type="number"
              step="0.01"
              min="0"
              value={form.total_amount}
              onChange={(e) => setForm({ ...form, total_amount: e.target.value })}
            />
          </Field>
          <Field label="Delivery (days)">
            <input
              type="number"
              min="0"
              max="3650"
              value={form.delivery_days}
              onChange={(e) => setForm({ ...form, delivery_days: e.target.value })}
            />
          </Field>
        </div>
        <Field label="Payment terms">
          <input
            value={form.payment_terms}
            onChange={(e) => setForm({ ...form, payment_terms: e.target.value })}
          />
        </Field>
        <Field label="Notes">
          <textarea
            rows={3}
            value={form.notes}
            onChange={(e) => setForm({ ...form, notes: e.target.value })}
          />
        </Field>
        <Notice>
          Demo quotes are simulated. In API mode, this form writes a real quote through Laravel.
        </Notice>
        {mutation.isError && <Notice tone="danger">{mutation.error.message}</Notice>}
        <div className="modal__actions">
          <Button variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" disabled={mutation.isPending || vendors.isPending}>
            {mutation.isPending ? 'Saving…' : 'Save quote'} <Check size={16} />
          </Button>
        </div>
      </form>
    </Modal>
  );
}
export function DetailActionBar({ request }: { request: PurchaseRequest }) {
  const { user } = useAuth(),
    qc = useQueryClient();
  const [modal, setModal] = useState(false);
  const submit = useMutation({
    mutationFn: () => repo.submit(user, request.id),
    onSuccess: async () => {
      await qc.invalidateQueries({ queryKey: ['request', request.id] });
      await qc.invalidateQueries({ queryKey: ['requests'] });
    },
  });
  return (
    <>
      <div className="detail-actions">
        {canSubmitRequest(user, request) && (
          <Button onClick={() => submit.mutate()} disabled={submit.isPending}>
            <Send size={16} /> {submit.isPending ? 'Submitting…' : 'Submit request'}
          </Button>
        )}
        {canAddQuote(user, request) && (
          <Button variant="secondary" onClick={() => setModal(true)}>
            <Plus size={16} /> Add quote
          </Button>
        )}
      </div>
      {submit.isError && <Notice tone="danger">{submit.error.message}</Notice>}
      {modal && <QuoteModal request={request} onClose={() => setModal(false)} />}
    </>
  );
}
