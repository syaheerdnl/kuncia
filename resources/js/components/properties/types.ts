export type Option = { value: string; label: string };

export type PropertyFormData = {
    id: number;
    name: string;
    type: string;
    address: string;
    city: string;
    state: string;
    postcode: string;
    description: string | null;
    cover_url: string | null;
};

/** A tenancy a deposit or payout can be recorded against (label is "Tenant · Unit"). */
export type TenancyOption = { id: number; label: string };

/** The invoice a payment was for, shown in the timeline popup. */
export type TimelineInvoice = {
    id: number;
    invoice_no: string;
    period: string;
    status: string;
    due_date: string;
    total: string;
    items: { description: string; amount: string }[];
    payments: {
        amount: string;
        method: string;
        paid_at: string;
        reference: string | null;
    }[];
};

export type PaymentEvent = {
    type: 'payment';
    date: string;
    amount: string;
    method: string;
    reference: string | null;
    period: string;
    tenant: string;
    unit: string;
    invoice: TimelineInvoice;
};

/** A late fee, a utility bill share or an extra charge added to an invoice. */
type ChargeEvent<T extends 'late_fee' | 'utility_charge' | 'extra_charge'> = {
    type: T;
    date: string;
    amount: string;
    description: string;
    period: string;
    tenant: string;
    unit: string;
    invoice: TimelineInvoice;
};

/** Events that open the invoice popup. */
export type InvoiceEvent =
    | PaymentEvent
    | ChargeEvent<'late_fee'>
    | ChargeEvent<'utility_charge'>
    | ChargeEvent<'extra_charge'>;

/** Money that is not rent: a deposit received, or money paid out to the tenant. */
type TransactionEvent<T extends 'deposit_received' | 'paid_to_tenant'> = {
    id: number;
    type: T;
    date: string;
    amount: string;
    note: string | null;
    tenant: string;
    unit: string;
};

/** One thing that happened at a property (see PropertyTimeline.php). Dates are Y-m-d. */
export type TimelineEvent =
    | InvoiceEvent
    | TransactionEvent<'deposit_received'>
    | TransactionEvent<'paid_to_tenant'>
    | {
          type: 'tenancy_start';
          date: string;
          amount: string;
          deposit: string;
          tenant_id: number;
          tenant: string;
          unit: string;
      }
    | {
          type: 'tenancy_end';
          date: string;
          amount: string;
          deposit: string;
          deposit_status: string;
          tenant_id: number;
          tenant: string;
          unit: string;
      };

export type UnitRow = {
    id: number;
    code: string;
    type: string;
    type_label: string;
    monthly_rent: string;
    deposit: string;
    status: 'vacant' | 'occupied' | 'maintenance';
    tenant: string | null;
};
