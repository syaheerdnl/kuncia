export type InvoiceRow = {
    id: number;
    invoice_no: string;
    period: string;
    due_date: string;
    total: string;
    status: string;
    tenant: string;
    unit: string;
};

export type InvoiceDetail = InvoiceRow & {
    issue_date: string;
    paid_at: string | null;
    tenant_email: string;
    tenant_id: number;
    outstanding: string;
    items: { id: number; description: string; amount: string }[];
    bills: { id: number; name: string; url: string }[];
    payments: {
        id: number;
        amount: string;
        method: string;
        reference: string | null;
        status: string;
        paid_at: string | null;
    }[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type BillScan = {
    provider: string | null;
    type: string | null;
    account_no: string | null;
    period: string | null;
    amount: number;
    usage: number | null;
    usage_unit: string | null;
    due_date: string | null;
    suggested_description: string;
};
