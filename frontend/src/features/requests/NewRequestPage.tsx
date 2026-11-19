import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowLeft, ArrowRight, Plus, Trash2 } from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { useFieldArray, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useAuth } from '../../app/auth/AuthContext';
import { canCreateRequest } from '../../domain/policy';
import type { PurchaseInput } from '../../domain/types';
import { repo } from '../../services/repository';
import { Button, Field, Notice, PageHeading, Panel } from '../../components/UI';
const formSchema = z.object({
  title: z.string().trim().min(4, 'Use at least 4 characters').max(255),
  description: z.string().max(5000),
  department_id: z.number().int().min(1, 'Select a department'),
  priority: z.enum(['low', 'normal', 'high', 'urgent']),
  estimated_budget: z.number().min(0, 'Budget cannot be negative'),
  currency: z.string().length(3),
  needed_by_date: z.string(),
  items: z
    .array(
      z.object({
        name: z.string().trim().min(2, 'Describe the item'),
        quantity: z.number().int().min(1),
        expected_unit_price: z.number().min(0),
      }),
    )
    .min(1),
});
type RequestForm = z.infer<typeof formSchema>;
export function NewRequest() {
  const { user } = useAuth();
  const navigate = useNavigate(),
    qc = useQueryClient();
  const departments = useQuery({
    queryKey: ['departments', user.id],
    queryFn: () => repo.departments(),
  });
  const {
    register,
    control,
    handleSubmit,
    formState: { errors },
  } = useForm<RequestForm>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      title: '',
      description: '',
      department_id: user.department_id ?? 0,
      estimated_budget: 0,
      priority: 'normal',
      currency: 'EUR',
      needed_by_date: '',
      items: [{ name: '', quantity: 1, expected_unit_price: 0 }],
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'items' });
  const mutation = useMutation({
    mutationFn: (values: RequestForm) => {
      const payload: PurchaseInput = {
        ...values,
        description: values.description || undefined,
        needed_by_date: values.needed_by_date || null,
        items: values.items,
      };
      return repo.createRequest(user, payload);
    },
    onSuccess: async (r) => {
      await qc.invalidateQueries({ queryKey: ['requests'] });
      navigate(`/requests/${r.id}`);
    },
  });
  if (!canCreateRequest(user.role))
    return <Notice tone="danger">Your role cannot create purchase requests.</Notice>;
  return (
    <>
      <div className="back-line">
        <Link to="/requests">
          <ArrowLeft size={16} /> Back to requests
        </Link>
      </div>
      <PageHeading
        eyebrow="NEW PURCHASE REQUEST"
        title="Create a purchase request"
        description="Capture the business need and item details before sourcing begins."
      />
      <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="form-layout">
        <Panel title="Request details" subtitle="Tell the procurement team what you need">
          <div className="panel__body stack-md">
            <Field label="Request title" required error={errors.title?.message}>
              <input
                {...register('title')}
                placeholder="e.g. Engineering laptop refresh"
                maxLength={255}
              />
            </Field>
            <Field label="Business justification" error={errors.description?.message}>
              <textarea
                {...register('description')}
                rows={4}
                placeholder="Why is this purchase needed?"
              />
            </Field>
            <div className="form-row">
              <Field label="Department" required error={errors.department_id?.message}>
                <select
                  {...register('department_id', { valueAsNumber: true })}
                  disabled={departments.isPending}
                >
                  <option value={0}>Choose department</option>
                  {departments.data?.data.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.name}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Priority" required>
                <select {...register('priority')}>
                  <option value="low">Low</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </Field>
            </div>
            <div className="form-row">
              <Field label="Estimated budget (€)" error={errors.estimated_budget?.message}>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  {...register('estimated_budget', { valueAsNumber: true })}
                />
              </Field>
              <Field label="Needed by">
                <input type="date" {...register('needed_by_date')} />
              </Field>
            </div>
          </div>
        </Panel>
        <Panel title="Line items" subtitle="At least one item is required">
          <div className="panel__body stack-md">
            {fields.map((item, i) => (
              <div key={item.id} className="line-item">
                <div className="line-item__header">
                  <strong>Item {i + 1}</strong>
                  {fields.length > 1 && (
                    <button
                      type="button"
                      onClick={() => remove(i)}
                      aria-label={`Remove item ${i + 1}`}
                      className="icon-button danger"
                    >
                      <Trash2 size={17} />
                    </button>
                  )}
                </div>
                <div className="form-row form-row--wide">
                  <Field label="Item name" required error={errors.items?.[i]?.name?.message}>
                    <input
                      {...register(`items.${i}.name`)}
                      placeholder="Hardware, service, subscription…"
                    />
                  </Field>
                  <Field label="Quantity" required error={errors.items?.[i]?.quantity?.message}>
                    <input
                      type="number"
                      min="1"
                      {...register(`items.${i}.quantity`, { valueAsNumber: true })}
                    />
                  </Field>
                  <Field
                    label="Est. unit price (€)"
                    error={errors.items?.[i]?.expected_unit_price?.message}
                  >
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      {...register(`items.${i}.expected_unit_price`, { valueAsNumber: true })}
                    />
                  </Field>
                </div>
              </div>
            ))}
            <Button
              variant="secondary"
              onClick={() => append({ name: '', quantity: 1, expected_unit_price: 0 })}
            >
              <Plus size={15} /> Add line item
            </Button>
          </div>
        </Panel>
        <div className="form-bottom">
          <p>Submitted requests enter the sourcing and approval process.</p>
          {mutation.isError && <Notice tone="danger">{mutation.error.message}</Notice>}
          <Button disabled={mutation.isPending || departments.isPending} type="submit">
            {mutation.isPending ? 'Creating…' : 'Create draft request'} <ArrowRight size={17} />
          </Button>
        </div>
      </form>
    </>
  );
}
